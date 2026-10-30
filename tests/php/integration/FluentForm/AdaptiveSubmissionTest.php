<?php
/**
 * AdaptiveSubmissionTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedNamespaceInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Integration\FluentForm;

use FluentForm\App\Models\Form as FluentForm;
use FluentForm\App\Models\FormMeta;
use FluentForm\App\Models\Submission;
use FluentForm\App\Modules\Form\FormFieldsParser;
use FluentForm\App\Modules\Form\TokenBasedSpamProtection;
use FluentForm\Framework\Foundation\App;
use FluentForm\Framework\Http\Request\Request;
use FluentForm\Framework\Validator\ValidationException;
use HCaptcha\FluentForm\Form;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Helpers\LoginAttempts;
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use RuntimeException;

/**
 * Exercise adaptive protection through native Fluent Forms submission dispatch.
 *
 * @group fluentform
 * @group fluentform-adaptive
 */
class AdaptiveSubmissionTest extends HCaptchaPluginWPTestCase {

	/**
	 * Native plugin entry file.
	 *
	 * @var string
	 */
	protected static $plugin = 'fluentform/fluentform.php';

	/**
	 * Native lifecycle callbacks required by the public AJAX endpoint.
	 *
	 * @var string[]
	 */
	protected static array $plugin_load_hooks = [ 'plugins_loaded', 'init' ];

	/**
	 * Restore native callbacks after WordPress resets test hooks.
	 *
	 * @var bool
	 */
	protected static bool $force_plugin_load_hooks = true;

	/**
	 * Preserve the native application's request container.
	 *
	 * @var mixed
	 */
	private $native_request;

	/**
	 * Native form under test.
	 *
	 * @var FluentForm
	 */
	private FluentForm $form;

	/**
	 * Adapter attached to the native submission callbacks.
	 *
	 * @var Form
	 */
	private Form $adapter;

	/**
	 * Real submitted credentials and native anti-spam fields.
	 *
	 * @var array
	 */
	private array $data = [];

	/**
	 * Address selected by the production trusted IP resolver.
	 *
	 * @var string
	 */
	private string $address = '';

	/**
	 * Counts observed after native pre-insertion checks and final insertion.
	 *
	 * @var array
	 */
	private array $observed_counts = [];

	/**
	 * Enable native token and honeypot checks before their bootstrap.
	 */
	protected function before_load_test_plugins(): void {
		update_option(
			'_fluentform_global_form_settings',
			[
				'misc' => [
					'honeypotStatus'             => 'yes',
					'tokenBasedProtectionStatus' => 'yes',
				],
			]
		);
	}

	/**
	 * Capture native request state before dispatch controls.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->native_request = App::getInstance()['request'];
		LoginAttempts::delete_all();
		wp_set_current_user( 0 );
		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter( 'fluentform/nonce_verify', '__return_true' );
		add_filter( 'pre_wp_mail', '__return_true' );
		add_filter(
			'wp_die_ajax_handler',
			static function () {
				return static function () {
					throw new RuntimeException( 'SEC008 native JSON completed.' );
				};
			}
		);
	}

	/**
	 * Restore native request state and bounded login records.
	 */
	public function tearDown(): void {
		App::getInstance()['request'] = $this->native_request;
		FormFieldsParser::resetData();
		LoginAttempts::delete_all();
		parent::tearDown();
	}

	/**
	 * Valid credentials must not remove the challenge needed by this submission.
	 *
	 * @param bool $valid_password Whether the real password is correct.
	 * @param int  $seed_count     Failed attempts preceding this request.
	 * @param bool $blocked        Whether native insertion must be prevented.
	 * @param int  $final_count    Expected count after the native request.
	 * @dataProvider dp_credentials_without_proof
	 */
	public function test_credentials_without_proof( bool $valid_password, int $seed_count, bool $blocked, int $final_count ): void {
		$this->prepare_submission( $seed_count );

		if ( ! $valid_password ) {
			$this->data['password'] = 'incorrect-synthetic-password';
		}

		unset( $this->data['h-captcha-response'] );
		$result = $this->dispatch( $this->data );

		if ( $blocked ) {
			self::assertArrayHasKey( 'h-captcha-response', $result['errors'] ?? [], wp_json_encode( $result ) );
			self::assertSame( 0, $this->submission_count() );
			self::assertSame( [], $this->observed_counts );
		} else {
			self::assertTrue( $result['success'] ?? false, wp_json_encode( $result ) );
			self::assertGreaterThan( 0, $result['data']['insert_id'] );
			self::assertSame( 1, $this->submission_count() );
			self::assertSame(
				[
					'before_insert' => $seed_count,
					'inserted'      => 0,
				],
				$this->observed_counts
			);
		}

		self::assertSame( $final_count, $this->read_count() );
	}

	/**
	 * Credential and threshold boundary controls.
	 *
	 * @return array
	 */
	public function dp_credentials_without_proof(): array {
		return [
			'valid credentials above threshold' => [ true, 2, true, 2 ],
			'wrong credentials above threshold' => [ false, 2, true, 3 ],
			'valid credentials below threshold' => [ true, 1, false, 0 ],
			'wrong credentials reach threshold' => [ false, 1, true, 2 ],
		];
	}

	/**
	 * An accepted challenge resets the snapshot only after actual insertion.
	 */
	public function test_valid_proof_resets_after_native_insertion(): void {
		$this->prepare_submission( 2 );
		$result = $this->dispatch( $this->data );

		self::assertTrue( $result['success'] ?? false, wp_json_encode( $result ) );
		self::assertGreaterThan( 0, $result['data']['insert_id'] );
		self::assertSame( 1, $this->submission_count() );
		self::assertSame(
			[
				'before_insert' => 2,
				'inserted'      => 0,
			],
			$this->observed_counts
		);
		self::assertSame( 0, $this->read_count() );
	}

	/**
	 * A global before-insert observer does not turn an ordinary form into a login.
	 *
	 * @param bool $valid_proof Whether the submitted challenge is valid.
	 *
	 * @dataProvider dp_ordinary_email_password_form
	 * @noinspection PhpUndefinedFieldInspection
	 */
	public function test_ordinary_email_password_form_requires_captcha( bool $valid_proof ): void {
		$this->prepare_submission( 0, false );
		$this->set_protected_property( $this->adapter, 'form_id', (int) $this->form->id );
		$render = $this->set_method_accessibility( $this->adapter, 'get_hcaptcha' );

		self::assertNotFalse( has_action( 'fluentform/before_insert_submission' ) );
		self::assertStringContainsString( '<h-captcha', $render->invoke( $this->adapter ) );

		if ( ! $valid_proof ) {
			unset( $this->data['h-captcha-response'] );
		}

		$result = $this->dispatch( $this->data );

		if ( $valid_proof ) {
			self::assertTrue( $result['success'] ?? false, wp_json_encode( $result ) );
			self::assertSame( 1, $this->submission_count() );
		} else {
			self::assertArrayHasKey( 'h-captcha-response', $result['errors'] ?? [], wp_json_encode( $result ) );
			self::assertSame( 0, $this->submission_count() );
		}

		self::assertSame( 0, $this->read_count() );
	}

	/**
	 * Ordinary form proof controls.
	 *
	 * @return array
	 */
	public function dp_ordinary_email_password_form(): array {
		return [
			'missing proof' => [ false ],
			'valid proof'   => [ true ],
		];
	}

	/**
	 * Opting in one login form cannot exempt another saved form.
	 *
	 * @noinspection PhpUndefinedFieldInspection
	 */
	public function test_login_opt_in_is_scoped_to_form_id(): void {
		$this->prepare_submission( 0 );
		$login_id = (int) $this->form->id;
		$other_id = (int) $this->create_form()->id;
		$render   = $this->set_method_accessibility( $this->adapter, 'get_hcaptcha' );

		$this->set_protected_property( $this->adapter, 'form_id', $login_id );
		self::assertSame( '', $render->invoke( $this->adapter ) );

		$this->set_protected_property( $this->adapter, 'form_id', $other_id );
		self::assertStringContainsString( '<h-captcha', $render->invoke( $this->adapter ) );
	}

	/**
	 * Native validation and late anti-spam failures preserve the captured record.
	 *
	 * @param string $failure The production guard that rejects the request.
	 * @param string $error   Expected native error key or message fragment.
	 *
	 * @dataProvider dp_native_failure
	 * @noinspection PhpUndefinedFieldInspection
	 */
	public function test_failed_native_submission_retains_count( string $failure, string $error ): void {
		$this->prepare_submission( 2 );

		if ( 'challenge' === $failure ) {
			add_filter(
				'pre_http_request',
				static function ( $preempt, $args, $url ) {
					return 'https://api.hcaptcha.com/siteverify' === $url
						? [
							'body' => wp_json_encode(
								[
									'success'     => false,
									'error-codes' => [ 'invalid-input-response' ],
								]
							),
						]
						: $preempt;
				},
				11,
				3
			);
		} elseif ( 'widget' === $failure ) {
			$this->data['hcaptcha-widget-id'] = HCaptcha::widget_id_value(
				[
					'source'  => [ 'fluentformpro/fluentformpro.php', 'fluentform/fluentform.php' ],
					'form_id' => (int) $this->form->id + 1,
				]
			);
		} elseif ( 'captcha_nonce' === $failure ) {
			// The hCaptcha nonce contract applies to authenticated submissions only.
			$user = get_user_by( 'email', $this->data['email'] );
			wp_set_current_user( $user->ID );
			$this->data[ '_fluentform_' . $this->form->id . '_fluentformnonce' ] = wp_create_nonce( 'fluentform-submit-form' );
			$this->data['hcaptcha_fluentform_nonce']                             = 'invalid-captcha-nonce';
		} elseif ( 'required_name' === $failure ) {
			unset( $this->data['name'] );
		} elseif ( 'native_nonce' === $failure ) {
			$this->data[ '_fluentform_' . $this->form->id . '_fluentformnonce' ] = 'invalid-native-nonce';
		} elseif ( 'native_honeypot' === $failure ) {
			$this->data[ 'item_' . $this->form->id . '__fluent_sf' ] = 'filled-honeypot';
		} elseif ( 'native_token' === $failure ) {
			$this->data[ '__fluent_protection_token_' . $this->form->id ] = 'invalid-native-token';
		} else {
			add_action(
				'fluentform/before_insert_submission',
				static function () {
					throw new ValidationException( '', 423, null, [ 'errors' => [ 'late_check' => [ 'Native late check rejected.' ] ] ] );
				},
				20,
				0
			);
		}

		$result = $this->dispatch( $this->data );

		self::assertStringContainsString( $error, wp_json_encode( $result['errors'] ?? [] ), wp_json_encode( $result ) );
		self::assertSame( 0, $this->submission_count() );
		self::assertArrayNotHasKey( 'inserted', $this->observed_counts );
		self::assertSame( 2, $this->read_count() );
	}

	/**
	 * Early validation, hCaptcha, and late native protection controls.
	 *
	 * @return array
	 */
	public function dp_native_failure(): array {
		return [
			'invalid challenge'            => [ 'challenge', 'h-captcha-response' ],
			'foreign widget identity'      => [ 'widget', 'h-captcha-response' ],
			'authenticated hCaptcha nonce' => [ 'captcha_nonce', 'h-captcha-response' ],
			'native required field'        => [ 'required_name', 'name' ],
			'native nonce'                 => [ 'native_nonce', '_fluentformnonce' ],
			'native honeypot'              => [ 'native_honeypot', 'can not submit' ],
			'native anti-spam token'       => [ 'native_token', 'Suspicious activity' ],
			'late before-insert check'     => [ 'late_check', 'late_check' ],
		];
	}

	/**
	 * Completing one valid request must retain a concurrently updated attempt record.
	 */
	public function test_concurrent_failure_survives_successful_insertion(): void {
		$this->prepare_submission( 2 );
		add_action(
			'fluentform/before_insert_submission',
			function () {
				( new LoginAttempts() )->increment( $this->address, time(), 15 * MINUTE_IN_SECONDS );
			},
			10,
			0
		);
		$result = $this->dispatch( $this->data );

		self::assertTrue( $result['success'] ?? false, wp_json_encode( $result ) );
		self::assertSame( 1, $this->submission_count() );
		self::assertSame(
			[
				'before_insert' => 3,
				'inserted'      => 3,
			],
			$this->observed_counts
		);
		self::assertSame( 3, $this->read_count() );
	}

	/**
	 * A callback for a non-positive ID or another form cannot reset pending credentials.
	 *
	 * @param bool $foreign_form Whether a positive callback belongs to another form.
	 * @dataProvider dp_unrelated_completion
	 */
	public function test_unrelated_completion_does_not_reset_count( bool $foreign_form ): void {
		$this->prepare_submission( 2 );
		$other_form = $foreign_form ? $this->create_form() : $this->form;
		add_filter(
			'fluentform/validation_errors',
			function ( $errors, $data ) use ( $foreign_form, $other_form ) {
				// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Exercise the installed native callback contract.
				do_action( 'fluentform/submission_inserted', $foreign_form ? 12345 : 0, $data, $other_form );
				self::assertSame( 2, $this->read_count() );
				unset( $this->observed_counts['inserted'] );

				return $errors;
			},
			11,
			3
		);
		$result = $this->dispatch( $this->data );

		self::assertTrue( $result['success'] ?? false, wp_json_encode( $result ) );
		self::assertSame(
			[
				'before_insert' => 2,
				'inserted'      => 0,
			],
			$this->observed_counts
		);
		self::assertSame( 0, $this->read_count() );
	}

	/**
	 * Native final-hook ownership controls.
	 *
	 * @return array
	 */
	public function dp_unrelated_completion(): array {
		return [
			'no insertion' => [ false ],
			'another form' => [ true ],
		];
	}

	/**
	 * The embedded native field must still reach the production challenge verifier.
	 *
	 * @param bool $valid_proof Whether the serialized native form contains a challenge.
	 *
	 * @dataProvider dp_embedded_hcaptcha
	 * @noinspection PhpDynamicFieldDeclarationInspection
	 * @noinspection JsonEncodingApiUsageInspection
	 * @noinspection PhpUndefinedFieldInspection
	 * @noinspection PhpUndefinedMethodInspection
	 */
	public function test_embedded_native_hcaptcha( bool $valid_proof ): void {
		$this->prepare_submission( 2 );
		$schema                  = json_decode( $this->form->form_fields, true );
		$schema['fields'][]      = [
			'element'  => 'hcaptcha',
			'settings' => [ 'label' => 'Native challenge' ],
		];
		$this->form->form_fields = wp_json_encode( $schema );
		$this->form->save();
		FormFieldsParser::resetData();
		$native_calls     = 0;
		$production_calls = 0;
		add_filter(
			'pre_http_request',
			static function ( $preempt, $args, $url ) use ( &$native_calls, &$production_calls ) {
				if ( 'https://hcaptcha.com/siteverify' === $url ) {
					++$native_calls;
				} elseif ( 'https://api.hcaptcha.com/siteverify' === $url ) {
					++$production_calls;
				}

				return $preempt;
			},
			12,
			3
		);

		if ( ! $valid_proof ) {
			unset( $this->data['h-captcha-response'] );
		}

		$result = $this->dispatch( $this->data );

		self::assertSame( 1, $native_calls );
		self::assertSame( $valid_proof ? 1 : 0, $production_calls );

		if ( $valid_proof ) {
			self::assertTrue( $result['success'] ?? false, wp_json_encode( $result ) );
			self::assertSame( 1, $this->submission_count() );
			self::assertSame(
				[
					'before_insert' => 2,
					'inserted'      => 0,
				],
				$this->observed_counts
			);
			self::assertSame( 0, $this->read_count() );
		} else {
			self::assertArrayHasKey( 'h-captcha-response', $result['errors'] ?? [], wp_json_encode( $result ) );
			self::assertSame( 0, $this->submission_count() );
			self::assertSame( 2, $this->read_count() );
		}
	}

	/**
	 * Embedded native hCaptcha acceptance and missing-proof controls.
	 *
	 * @return array
	 */
	public function dp_embedded_hcaptcha(): array {
		return [
			'missing proof'  => [ false ],
			'accepted proof' => [ true ],
		];
	}

	/**
	 * Build real schema, credentials, anti-spam inputs, and a pre-authentication snapshot.
	 *
	 * @param int  $seed_count Failed login records preceding this request.
	 * @param bool $login_form Whether this synthetic form is an opted-in login.
	 *
	 * @noinspection DynamicInvocationViaScopeResolutionInspection
	 * @noinspection PhpUndefinedFieldInspection
	 */
	private function prepare_submission( int $seed_count, bool $login_form = true ): void {
		update_option(
			'hcaptcha_settings',
			[
				'fluent_status'  => [ 'form' ],
				'login_limit'    => 2,
				'login_interval' => 15,
			]
		);
		$this->prepare_verify_request( 'sec008-challenge' );
		$this->remove_integration_callbacks();
		$this->address = hcap_get_user_ip();
		$attempts      = new LoginAttempts();

		for ( $i = 0; $i < $seed_count; ++$i ) {
			$attempts->increment( $this->address, time(), 15 * MINUTE_IN_SECONDS );
		}

		$this->adapter = new Form();
		$user_id       = self::factory()->user->create(
			[
				'user_login' => 'sec008-native-reader',
				'user_email' => 'sec008-native-reader@example.com',
				'user_pass'  => 'sec008-synthetic-password',
			]
		);
		$this->form    = $this->create_form();

		if ( $login_form ) {
			add_filter(
				'hcap_fluentform_is_login_form',
				function ( $is_login_form, int $form_id ): bool {
					return (int) $this->form->id === $form_id;
				},
				10,
				2
			);
		}

		$this->data = [
			'email'                     => get_userdata( $user_id )->user_email,
			'password'                  => 'sec008-synthetic-password',
			'name'                      => 'Native reader',
			'_wp_http_referer'          => home_url( '/sec008-native-form/' ),
			'h-captcha-response'        => 'sec008-challenge',
			'hcaptcha-widget-id'        => HCaptcha::widget_id_value(
				[
					'source'  => [ 'fluentformpro/fluentformpro.php', 'fluentform/fluentform.php' ],
					'form_id' => (int) $this->form->id,
				]
			),
			'hcaptcha_fluentform_nonce' => wp_create_nonce( 'hcaptcha_fluentform' ),
			'hcap_hp_test'              => '',
			'hcap_hp_sig'               => wp_create_nonce( 'hcap_hp_test' ),
			'hcap_fst_token'            => 'test_token',
		];
		$this->data[ '_fluentform_' . $this->form->id . '_fluentformnonce' ] = wp_create_nonce( 'fluentform-submit-form' );
		$this->data[ 'item_' . $this->form->id . '__fluent_sf' ]             = '';
		$this->data += $this->issue_native_token();
		add_action(
			'fluentform/before_insert_submission',
			function () {
				$this->observed_counts['before_insert'] = $this->read_count();
			},
			11,
			0
		);
		add_action(
			'fluentform/submission_inserted',
			function () {
				$this->observed_counts['inserted'] = $this->read_count();
			},
			PHP_INT_MAX,
			0
		);
		self::assertSame( $seed_count, $this->read_count() );
		self::assertNotFalse( has_action( 'wp_ajax_nopriv_fluentform_submit' ) );
	}

	/**
	 * Remove prior production instances so the seeded snapshot owns this request.
	 */
	private function remove_integration_callbacks(): void {
		global $wp_filter;

		foreach ( $wp_filter as $hook => $registered ) {
			foreach ( $registered->callbacks as $priority => $callbacks ) {
				foreach ( $callbacks as $callback ) {
					$function = $callback['function'];

					if ( is_array( $function ) && $function[0] instanceof Form ) {
						remove_filter( $hook, $function, $priority );
					}
				}
			}
		}
	}

	/**
	 * Issue the enabled native anti-spam token through its nonce-guarded endpoint.
	 *
	 * @return array
	 * @noinspection PhpUndefinedFieldInspection
	 * @noinspection JsonEncodingApiUsageInspection
	 * @noinspection PhpArrayWriteIsNotUsedInspection
	 */
	private function issue_native_token(): array {
		$_POST = [
			'nonce'   => wp_create_nonce( 'fluentform_generate_token_nonce' ),
			'form_id' => $this->form->id,
		];
		ob_start();

		try {
			( new TokenBasedSpamProtection( App::getInstance() ) )->ajaxGenerateToken();
		} catch ( RuntimeException $exception ) {
			self::assertSame( 'SEC008 native JSON completed.', $exception->getMessage() );
		}

		$response = json_decode( ob_get_clean(), true );
		self::assertTrue( $response['success'] ?? false, wp_json_encode( $response ) );

		return [ '__fluent_protection_token_' . $this->form->id => $response['data']['token'] ];
	}

	/**
	 * Run the actual public native AJAX action, including parser and insert lifecycle.
	 *
	 * @param array $data Submitted native form fields.
	 *
	 * @return array
	 * @noinspection JsonEncodingApiUsageInspection
	 * @noinspection PhpConditionAlreadyCheckedInspection
	 * @noinspection PhpUndefinedFieldInspection
	 */
	private function dispatch( array $data ): array {
		global $wp_current_filter;

		$_POST = [
			'action'  => 'fluentform_submit',
			'form_id' => (int) $this->form->id,
			'data'    => http_build_query( $data ),
		];
		$app   = App::getInstance();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Native dispatch validates the real submitted nonce.
		$app['request']  = new Request( $app, [], $_POST );
		$current_filters = $wp_current_filter;
		FormFieldsParser::resetData();
		ob_start();

		try {
			do_action( is_user_logged_in() ? 'wp_ajax_fluentform_submit' : 'wp_ajax_nopriv_fluentform_submit' );
		} catch ( RuntimeException $exception ) {
			self::assertSame( 'SEC008 native JSON completed.', $exception->getMessage() );
		} finally {
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Native JSON stops its action before WordPress pops the stack.
			$wp_current_filter = $current_filters;
		}

		return (array) json_decode( ob_get_clean(), true );
	}

	/**
	 * Create a native saved login-shaped schema with real required-field validation.
	 *
	 * @return FluentForm
	 * @noinspection PhpIncompatibleReturnTypeInspection
	 * @noinspection PhpUndefinedMethodInspection
	 */
	private function create_form(): FluentForm {
		$fields = [];

		foreach ( [
			'email'    => 'email',
			'password' => 'password',
			'name'     => 'text',
		] as $name => $type ) {
			$fields[] = [
				'element'        => 'input_' . $type,
				'attributes'     => [
					'type'        => $type,
					'name'        => $name,
					'value'       => '',
					'id'          => '',
					'class'       => '',
					'placeholder' => $name,
				],
				'settings'       => [
					'container_class'    => '',
					'label'              => $name,
					'label_placement'    => '',
					'help_message'       => '',
					'validation_rules'   => [
						'required' => [
							'value'   => true,
							'message' => 'This field is required',
						],
					],
					'conditional_logics' => [],
				],
				'editor_options' => [
					'title'    => $name,
					'template' => 'inputText',
				],
			];
		}

		$form = FluentForm::create(
			FluentForm::prepare(
				[
					'title'       => 'SEC008 native credential form',
					'status'      => 'published',
					'form_fields' => wp_json_encode(
						[
							'fields'       => $fields,
							'submitButton' => [
								'element'    => 'button',
								'attributes' => [
									'type'  => 'submit',
									'class' => '',
								],
								'settings'   => [
									'button_ui' => [
										'type' => 'default',
										'text' => 'Submit',
									],
								],
							],
						]
					),
					'type'        => 'form',
				]
			)
		);
		FormMeta::persist( $form->id, 'formSettings', FluentForm::getFormsDefaultSettings() );

		return FluentForm::find( $form->id );
	}

	/**
	 * Count actual native rows for this saved form.
	 *
	 * @return int
	 * @noinspection PhpUndefinedFieldInspection
	 */
	private function submission_count(): int {
		return Submission::where( 'form_id', $this->form->id )->count();
	}

	/**
	 * Read the production store without mocking its threshold or reset behavior.
	 *
	 * @return int
	 */
	private function read_count(): int {
		return ( new LoginAttempts() )->read( $this->address, time() );
	}
}
