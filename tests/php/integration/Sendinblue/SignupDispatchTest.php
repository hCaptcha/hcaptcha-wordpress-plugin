<?php
/**
 * SignupDispatchTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */

namespace HCaptcha\Tests\Integration\Sendinblue;

use HCaptcha\AutoVerify\AutoVerify;
use HCaptcha\Helpers\API;
use HCaptcha\Helpers\FormSubmitTime;
use HCaptcha\Helpers\FormSubmitTimeStore;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Sendinblue\Sendinblue;
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use RuntimeException;
use SIB_Forms;
use SIB_Manager;
use SIB_Model_Users;
use tad\FunctionMocker\FunctionMocker;
use WP_Error;
use WP_Hook;

/**
 * Exercise Brevo's global native signup dispatch before its contact HTTP boundary.
 *
 * @group sendinblue
 * @group sendinblue-signup
 */
class SignupDispatchTest extends HCaptchaPluginWPTestCase {

	/**
	 * Native plugin entry file.
	 *
	 * @var string
	 */
	protected static $plugin = 'mailin/sendinblue.php';

	/**
	 * Replay native bootstrap and register its global signup dispatcher.
	 *
	 * @var string[]
	 */
	protected static array $plugin_load_hooks = [ 'plugins_loaded', 'init' ];

	/**
	 * Restore native lifecycle callbacks after WPTestCase resets hooks.
	 *
	 * @var bool
	 */
	protected static bool $force_plugin_load_hooks = true;

	/**
	 * Captured native contact submissions.
	 *
	 * @var array
	 */
	private array $contacts = [];

	/**
	 * Number of hCaptcha server requests.
	 *
	 * @var int
	 */
	private int $remote_calls = 0;

	/**
	 * Captured native confirmation emails.
	 *
	 * @var array
	 */
	private array $emails = [];

	/**
	 * Request and server snapshots.
	 *
	 * @var array
	 */
	private array $request = [];

	/**
	 * Preserve Main instance state around fresh lifecycle bootstrap controls.
	 *
	 * @var array
	 */
	private array $lifecycle_snapshot = [];

	/**
	 * Preserve the WordPress screen.
	 *
	 * @var mixed
	 */
	private $screen;

	/**
	 * Configure a synthetic native account and stop every external HTTP side effect.
	 */
	protected function before_load_test_plugins(): void {
		$_POST = [];
		update_option( 'sib_api_key_v3', 'integration-test-key' );
		update_option( 'sib_main_option', [ 'access_key' => 'integration-test-key' ] );
		update_option(
			'sib_home_option',
			[
				'activate_email' => 'no',
				'activate_ma'    => 'no',
			]
		);
		update_option( 'sib_use_apiv2', '1' );
		update_option( 'sib_installation_id', 'sec006-installation' );
		set_transient(
			'sib_list_' . md5( 'integration-test-key' ),
			[
				[
					'id'   => 1,
					'name' => 'Integration List',
				],
			],
			900
		);
		add_filter( 'pre_http_request', [ $this, 'capture_http' ], 11, 3 );
		add_filter( 'pre_wp_mail', [ $this, 'capture_mail' ], 10, 2 );
	}

	/**
	 * Prepare real native form storage and the production integration callbacks.
	 */
	public function setUp(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$this->request = [ $_GET, $_REQUEST, $_SERVER ];
		$this->screen  = $GLOBALS['current_screen'] ?? null;
		parent::setUp();
		$_GET     = [];
		$_POST    = [];
		$_REQUEST = [];
		set_current_screen( 'front' );
		wp_set_current_user( 0 );
		AutoVerify::delete_all();
		$this->create_form( 1 );
		update_option( 'sib_old_form_id', 1 );
		hcaptcha()->settings()->set( 'sendinblue_status', [ 'form' ] );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );
		( new AutoVerify() )->init();
		new Sendinblue();
	}

	/**
	 * Restore request context and automatic form registrations.
	 */
	public function tearDown(): void {
		AutoVerify::delete_all();
		[ $_GET, $_REQUEST, $_SERVER ] = $this->request;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restore the screen snapshot after native request controls.
		$GLOBALS['current_screen'] = $this->screen;
		parent::tearDown();

		if ( $this->lifecycle_snapshot ) {
			$main = hcaptcha();

			// Retain the default settings reset by the base, then restore non-settings instance state.
			foreach ( [ 'loaded_classes', 'auto_verify', 'supported_forms', 'active' ] as $property ) {
				$this->set_protected_property( $main, $property, $this->lifecycle_snapshot[ $property ] );
			}

			$main->modules = $this->lifecycle_snapshot['modules'];
		}
	}

	/**
	 * Native accepted actions require proof even when URL discovery cannot find a form.
	 *
	 * @dataProvider dp_test_unregistered_native_routes
	 *
	 * @param string $shape  Native request shape.
	 * @param string $action Raw native action.
	 * @param bool   $proof  Whether valid hCaptcha proof is present.
	 */
	public function test_unregistered_native_routes( string $shape, string $action, bool $proof ): void {
		$payload                    = $this->prepare_payload( $proof );
		$payload['sib_form_action'] = $action;
		$_SERVER['REQUEST_METHOD']  = 'POST';
		$_SERVER['REQUEST_URI']     = '/sec006-unregistered/';

		if ( 'admin' === $shape ) {
			set_current_screen( 'dashboard' );
		} elseif ( 'REST shape' === $shape ) {
			$_SERVER['REQUEST_URI'] = '/sec006-unregistered/?rest_route=/unrelated';
			$_GET['rest_route']     = '/unrelated';
		}

		$result = $this->dispatch( $payload );

		self::assertCount( $proof ? 1 : 0, $this->contacts );
		self::assertSame( $proof ? 'success' : 'failure', $result['status'] );

		if ( ! $proof ) {
			self::assertSame( hcap_get_error_messages()['bad-signature'], $result['msg']['errorMsg'] );
		}
	}

	/**
	 * Native sanitize_text_field accepts aliases before URL routing runs.
	 *
	 * @return array
	 */
	public function dp_test_unregistered_native_routes(): array {
		return [
			'ordinary URL missing proof' => [ 'frontend', 'subscribe_form_submit', false ],
			'whitespace native alias'    => [ 'frontend', ' subscribe_form_submit ', false ],
			'HTML native alias'          => [ 'frontend', '<b>subscribe_form_submit</b>', false ],
			'admin URL missing proof'    => [ 'admin', 'subscribe_form_submit', false ],
			'REST shape missing proof'   => [ 'REST shape', 'subscribe_form_submit', false ],
			'genuine proof on any URL'   => [ 'frontend', 'subscribe_form_submit', true ],
		];
	}

	/**
	 * Native signup remains protected when ordinary hCaptcha module loading is skipped.
	 *
	 * @dataProvider dp_test_skipped_lifecycle_signup
	 *
	 * @param string $protocol Lifecycle early-return condition.
	 * @param bool   $proof    Include genuine challenge proof.
	 */
	public function test_skipped_lifecycle_signup( string $protocol, bool $proof ): void {
		$payload = $this->prepare_payload( $proof );
		$this->bootstrap_skipped_lifecycle( $protocol, $payload );
		$result = $this->dispatch( $payload );

		self::assertSame( $proof ? 'success' : 'failure', $result['status'] );
		self::assertCount( $proof ? 1 : 0, $this->contacts );
		self::assertSame( $proof ? 1 : 0, $this->remote_calls );
		self::assertSame( 1, $this->signup_guard_count() );
		self::assertNull( $this->get_protected_property( hcaptcha(), 'auto_verify' ) );
	}

	/**
	 * Fresh production bootstrap under native XML-RPC and cron conditions.
	 *
	 * @return array
	 */
	public function dp_test_skipped_lifecycle_signup(): array {
		return [
			'XML-RPC missing proof' => [ 'XML-RPC', false ],
			'XML-RPC valid proof'   => [ 'XML-RPC', true ],
			'cron missing proof'    => [ 'cron', false ],
			'cron valid proof'      => [ 'cron', true ],
		];
	}

	/**
	 * Narrow lifecycle loading must retain integration activation and native action boundaries.
	 *
	 * @dataProvider dp_test_skipped_lifecycle_exemptions
	 *
	 * @param string $variant Expected native integration exemption.
	 */
	public function test_skipped_lifecycle_exemptions( string $variant ): void {
		if ( 'off when logged in' === $variant ) {
			wp_set_current_user( $this->factory()->user->create( [ 'role' => 'subscriber' ] ) );
		}

		$payload  = $this->prepare_payload( false );
		$settings = [];

		if ( 'disabled integration' === $variant ) {
			$settings['sendinblue_status'] = [];
		} elseif ( 'off when logged in' === $variant ) {
			$settings['off_when_logged_in'] = [ 'on' ];
		} elseif ( 'allowlisted IP' === $variant ) {
			$settings['whitelisted_ips'] = '7.7.7.7';
		} elseif ( 'activation filter' === $variant ) {
			add_filter( 'hcap_activate', '__return_false' );
		} elseif ( 'unrelated action' === $variant ) {
			$payload['sib_form_action'] = 'unrelated';
		}

		$this->bootstrap_skipped_lifecycle( 'XML-RPC', $payload, $settings );
		$result = $this->dispatch( $payload );

		self::assertSame( 0, $this->signup_guard_count() );
		self::assertSame( 0, $this->remote_calls );
		self::assertCount( 'unrelated action' === $variant ? 0 : 1, $this->contacts );
		self::assertSame( 'unrelated action' === $variant ? null : 'success', $result['status'] ?? null );
		self::assertNull( $this->get_protected_property( hcaptcha(), 'auto_verify' ) );
	}

	/**
	 * Existing activation controls and an unrelated action must not install a global signup guard.
	 *
	 * @return array
	 */
	public function dp_test_skipped_lifecycle_exemptions(): array {
		return [
			'disabled integration' => [ 'disabled integration' ],
			'logged-in exemption'  => [ 'off when logged in' ],
			'allowlisted IP'       => [ 'allowlisted IP' ],
			'activation filter'    => [ 'activation filter' ],
			'unrelated action'     => [ 'unrelated action' ],
		];
	}

	/**
	 * Unrelated native actions remain outside the signup verifier.
	 */
	public function test_unrelated_action_does_not_submit(): void {
		$payload                    = $this->prepare_payload( false );
		$payload['sib_form_action'] = 'SUBSCRIBE_FORM_SUBMIT';
		$result                     = $this->dispatch( $payload );

		self::assertNull( $result );
		self::assertSame( [], $this->contacts );
		self::assertSame( 0, $this->remote_calls );
	}

	/**
	 * Native form aliases must select the stored row rather than a posted alias identity.
	 *
	 * @dataProvider dp_test_native_form_selectors
	 *
	 * @param string|null $selector Native selector, or null for the default.
	 * @param int         $id       Canonical stored form ID.
	 */
	public function test_native_form_selectors( ?string $selector, int $id ): void {
		$this->create_form( $id );
		$payload                                 = $this->prepare_payload();
		$payload[ HCaptcha::HCAPTCHA_WIDGET_ID ] = $this->widget_id( $id );
		update_option( 'sib_old_form_id', $id );

		if ( null === $selector ) {
			unset( $payload['sib_form_id'] );
		} else {
			$payload['sib_form_id'] = $selector;
		}

		$result = $this->dispatch( $payload );

		self::assertSame( 'success', $result['status'] );
		self::assertCount( 1, $this->contacts );
		self::assertSame( 1, $this->remote_calls );
	}

	/**
	 * Native database selector equivalence controls.
	 *
	 * @return array
	 */
	public function dp_test_native_form_selectors(): array {
		return [
			'absent means one' => [ null, 1 ],
			'legacy option'    => [ 'oldForm', 2 ],
			'leading zero'     => [ '01', 1 ],
			'numeric suffix'   => [ '1suffix', 1 ],
		];
	}

	/**
	 * A legitimately unprotected form cannot lend its signed exemption to another form.
	 *
	 * @dataProvider dp_test_signed_exemptions
	 *
	 * @param int  $submitted_id Signed client widget ID.
	 * @param bool $accepted     Whether the native form owns that exemption.
	 */
	public function test_signed_exemptions( int $submitted_id, bool $accepted ): void {
		$this->create_form( 2 );
		$payload                                 = $this->prepare_payload( false );
		$payload[ HCaptcha::HCAPTCHA_WIDGET_ID ] = $this->widget_id( $submitted_id );
		add_filter(
			'hcap_protect_form',
			static function ( bool $protect, array $source, $id ): bool {
				return [ 'mailin/sendinblue.php' ] === $source && 2 === $id ? false : $protect;
			},
			10,
			3
		);

		if ( $accepted ) {
			$payload['sib_form_id'] = '2';
		}

		$result = $this->dispatch( $payload );

		self::assertSame( $accepted ? 'success' : 'failure', $result['status'] );
		self::assertCount( $accepted ? 1 : 0, $this->contacts );
		self::assertSame( 0, $this->remote_calls );
	}

	/**
	 * Signed exemption ownership controls.
	 *
	 * @return array
	 */
	public function dp_test_signed_exemptions(): array {
		return [
			'own exemption'     => [ 2, true ],
			'foreign exemption' => [ 2, false ],
		];
	}

	/**
	 * Invalid native CSRF, form, and email remain native rejections without contact creation.
	 *
	 * @dataProvider dp_test_native_rejections
	 *
	 * @param string      $field  Native field replaced.
	 * @param string      $value  Invalid value, or markup around a real nonce.
	 * @param string|null $status Native JSON status, or null when native validation returns.
	 */
	public function test_native_rejections( string $field, string $value, ?string $status ): void {
		$payload = $this->prepare_payload( false );

		if ( 'wrapped nonce' === $value ) {
			$value = '<b>' . $payload['sib_security'] . '</b>';
		}

		$payload[ $field ] = $value;
		$result            = $this->dispatch( $payload );

		self::assertSame( $status, $result['status'] ?? null );
		self::assertSame( [], $this->contacts );
		self::assertSame( 0, $this->remote_calls );
	}

	/**
	 * Native rejection controls before the subscriber sink.
	 *
	 * @return array
	 */
	public function dp_test_native_rejections(): array {
		return [
			'invalid native nonce' => [ 'sib_security', 'invalid', 'sib_security' ],
			'nonce markup'         => [ 'sib_security', 'wrapped nonce', 'sib_security' ],
			'missing native form'  => [ 'sib_form_id', '987654', 'failure' ],
			'invalid native email' => [ 'email', 'invalid-email', null ],
		];
	}

	/**
	 * Authenticated requests still bind proof to the integration's fixed nonce.
	 */
	public function test_authenticated_nonce_is_required(): void {
		wp_set_current_user( $this->factory()->user->create( [ 'role' => 'subscriber' ] ) );
		$payload                              = $this->prepare_payload();
		$payload['hcaptcha_sendinblue_nonce'] = 'invalid';
		$result                               = $this->dispatch( $payload );

		self::assertSame( 'failure', $result['status'] );
		self::assertSame( hcap_get_error_messages()['bad-nonce'], $result['msg']['errorMsg'] );
		self::assertSame( [], $this->contacts );
		self::assertSame( 0, $this->remote_calls );
	}

	/**
	 * Email policy follows native sanitized email, even after a reusable challenge succeeds.
	 *
	 * @dataProvider dp_test_native_email_policy
	 *
	 * @param bool $disposable Native address is disposable.
	 * @param bool $cached     Prime the exact same remote challenge first.
	 */
	public function test_native_email_policy( bool $disposable, bool $cached ): void {
		$payload = $this->prepare_payload();
		hcaptcha()->settings()->set( 'disposable_email', [ 'on' ] );
		add_filter(
			'hcap_disposable_email_domains',
			static function (): array {
				return [ 'disposable.example' => true ];
			}
		);

		if ( $cached ) {
			$_POST = wp_slash( $payload );
			self::assertNull(
				API::verify(
					[
						'nonce_name'      => 'hcaptcha_sendinblue_nonce',
						'nonce_action'    => 'hcaptcha_sendinblue',
						'expected_id'     => [
							'source'  => [ static::$plugin ],
							'form_id' => 1,
						],
						'email_addresses' => [ 'allowed@example.com' ],
					]
				)
			);
		}

		$payload['email']         = $disposable ? ' native@disposable.example ' : ' native@example.com ';
		$payload['contact_email'] = $disposable ? 'decoy@example.com' : 'decoy@disposable.example';
		$result                   = $this->dispatch( $payload );

		self::assertSame( $disposable ? 'failure' : 'success', $result['status'] );
		self::assertCount( $disposable ? 0 : 1, $this->contacts );
		self::assertSame( $cached || ! $disposable ? 1 : 0, $this->remote_calls );

		if ( $disposable ) {
			self::assertSame( hcap_get_error_messages()['disposable-email'], $result['msg']['errorMsg'] );
		} else {
			self::assertSame( 'native@example.com', $this->contacts[0]['email'] );
		}
	}

	/**
	 * Current native email controls with and without cached remote verification.
	 *
	 * @return array
	 */
	public function dp_test_native_email_policy(): array {
		return [
			'fresh disposable'  => [ true, false ],
			'fresh permanent'   => [ false, false ],
			'cached disposable' => [ true, true ],
			'cached permanent'  => [ false, true ],
		];
	}

	/**
	 * The early guard must leave WordPress slashes for the native handler to remove once.
	 */
	public function test_native_handler_retains_unslashing_ownership(): void {
		$payload         = $this->prepare_payload();
		$payload['NAME'] = "O'Reilly \\ Lab";
		$result          = $this->dispatch( $payload );

		self::assertSame( 'success', $result['status'] );
		self::assertSame( $payload['NAME'], $this->contacts[0]['attributes']['NAME'] );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput -- Assert the untouched value after native unslashing.
		self::assertSame( $payload['NAME'], $_POST['NAME'] );
	}

	/**
	 * Another registered form on the current URL cannot override the native signup identity.
	 */
	public function test_another_registered_route_defers_to_native_signup(): void {
		$payload                   = $this->prepare_payload();
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['REQUEST_URI']    = '/sec006-other-protected/';
		$markup                    = '<form method="post"><input name="unrelated">' . HCaptcha::form(
			[
				'auto' => true,
				'id'   => [
					'source'  => [ AutoVerify::class ],
					'form_id' => 55,
				],
			]
		) . '</form>';
		do_action( 'hcap_auto_verify_register', $markup );
		$result = $this->dispatch( $payload );

		self::assertSame( 'success', $result['status'] );
		self::assertCount( 1, $this->contacts );
		self::assertSame( 1, $this->remote_calls );
	}

	/**
	 * Automatic verification and the native guard share one genuine single-use timing proof.
	 */
	public function test_registered_signup_reuses_genuine_timing_proof(): void {
		$payload                         = $this->prepare_payload();
		$settings                        = (array) get_option( 'hcaptcha_settings', [] );
		$settings['set_min_submit_time'] = 'on';
		$settings['min_submit_time']     = '0';
		$settings['sendinblue_status']   = [ 'form' ];
		$settings['honeypot']            = [ 'on' ];
		update_option( 'hcaptcha_settings', $settings );
		hcaptcha()->init_hooks();
		remove_all_filters( 'hcap_fst_token' );
		FormSubmitTimeStore::delete_all();

		try {
			$payload['hcap_fst_token'] = $this->issue_timing_token();
			self::assertCount( 1, (array) get_option( FormSubmitTimeStore::REGISTRY_OPTION, [] ) );
			remove_filter( 'hcap_verify_fst_token', '__return_true' );
			$verification_checks = [];
			add_filter(
				'hcap_verify_request',
				static function ( $result ) use ( &$verification_checks ) {
					$verification_checks[] = [
						'priority' => $GLOBALS['wp_filter']['init']->current_priority(),
						'result'   => $result,
					];

					return $result;
				},
				9
			);
			$timing_checks = [];
			add_filter(
				'hcap_verify_fst_token',
				static function ( $check ) use ( &$timing_checks ) {
					$timing_checks[] = $check;

					return $check;
				},
				PHP_INT_MAX
			);
			$_SERVER['REQUEST_METHOD'] = 'POST';
			$_SERVER['REQUEST_URI']    = '/sec006-newsletter/';
			do_shortcode( '[sibwp_form id="1"]' );
			self::assertTrue( hcaptcha()->settings()->is_on( 'honeypot' ) );
			self::assertTrue( hcaptcha()->settings()->is_on( 'set_min_submit_time' ) );
			self::assertSame( 0, (int) hcaptcha()->settings()->get( 'min_submit_time' ) );
			$result = $this->dispatch( $payload );

			self::assertSame( 'success', $result['status'] );
			self::assertCount( 1, $this->contacts );
			self::assertSame( 1, $this->remote_calls );
			self::assertSame( [ -PHP_INT_MAX, 9 ], array_values( array_unique( array_column( $verification_checks, 'priority' ) ) ) );
			self::assertSame( array_fill( 0, count( $verification_checks ), null ), array_column( $verification_checks, 'result' ) );
			self::assertSame( [ true ], $timing_checks );
			self::assertSame( [], (array) get_option( FormSubmitTimeStore::REGISTRY_OPTION, [] ) );
		} finally {
			FormSubmitTimeStore::delete_all();
		}
	}

	/**
	 * A valid hCaptcha challenge cannot bypass Brevo's independently configured CAPTCHA.
	 */
	public function test_native_captcha_remains_required(): void {
		$this->configure_form(
			1,
			[
				'gCaptcha'          => 3,
				'selectCaptchaType' => 1,
			]
		);
		$payload = $this->prepare_payload();
		$result  = $this->dispatch( $payload );

		self::assertSame( 'gcaptchaEmpty', $result['status'] );
		self::assertSame( [], $this->contacts );
		self::assertSame( 1, $this->remote_calls );
	}

	/**
	 * Double opt-in still records confirmation state and sends only its native confirmation.
	 *
	 * @dataProvider dp_test_double_optin
	 *
	 * @param bool $proof Whether valid hCaptcha proof is present.
	 */
	public function test_double_optin_preserves_native_confirmation( bool $proof ): void {
		$this->configure_form( 1, [ 'isDopt' => 1 ] );
		$payload = $this->prepare_payload( $proof );
		$result  = $this->dispatch( $payload );

		self::assertSame( $proof ? 'success' : 'failure', $result['status'] );
		self::assertSame( [], $this->contacts );
		self::assertCount( $proof ? 1 : 0, $this->emails );
		self::assertSame( $proof, is_array( SIB_Model_Users::get_data_by_email( $payload['email'], 1 ) ) );
	}

	/**
	 * Native confirmation success and missing-proof rejection.
	 *
	 * @return array
	 */
	public function dp_test_double_optin(): array {
		return [
			'missing proof' => [ false ],
			'valid proof'   => [ true ],
		];
	}

	/**
	 * Capture only the final native email transport.
	 *
	 * @param mixed $preempt Previous mail transport result.
	 * @param array $mail    Native confirmation email arguments.
	 *
	 * @return bool
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function capture_mail( $preempt, array $mail ): bool {
		$this->emails[] = $mail;

		return true;
	}

	/**
	 * Mock only the outbound HTTP boundary reached by native Brevo code.
	 *
	 * @param mixed  $preempt Previously prepared HTTP response.
	 * @param array  $args    Actual native HTTP arguments.
	 * @param string $url     Actual native destination.
	 *
	 * @return mixed
	 * @noinspection JsonEncodingApiUsageInspection
	 */
	public function capture_http( $preempt, array $args, string $url ) {
		if ( 'https://api.hcaptcha.com/siteverify' === $url ) {
			++$this->remote_calls;

			return $preempt;
		}

		if ( 0 !== strpos( $url, 'https://api.brevo.com/v3/' ) ) {
			return new WP_Error( 'sec006_external_http', 'External requests are disabled in this native fixture.' );
		}

		$path = wp_parse_url( $url, PHP_URL_PATH );
		$code = 200;
		$body = [];

		if ( '/v3/account' === $path ) {
			$body = [
				'email' => 'account@example.com',
				'relay' => [ 'enabled' => false ],
			];
		} elseif ( '/v3/contacts/lists' === $path ) {
			$body = [
				'lists' => [
					[
						'id'   => 1,
						'name' => 'Integration List',
					],
				],
				'count' => 1,
			];
		} elseif ( '/v3/contacts/attributes' === $path ) {
			$body = [ 'attributes' => [] ];
		} elseif ( '/v3/contacts' === $path && 'POST' === $args['method'] ) {
			$this->contacts[] = json_decode( $args['body'], true );
			$code             = 201;
			$body             = [ 'id' => 6006 ];
		} elseif ( 0 === strpos( $path, '/v3/contacts/' ) ) {
			$code = 404;
			$body = [ 'code' => 'document_not_found' ];
		}

		return [
			'response' => [ 'code' => $code ],
			'body'     => wp_json_encode( $body ),
		];
	}

	/**
	 * Prepare the same native fields and proof emitted by a protected signup form.
	 *
	 * @param bool $proof Include a valid signed hCaptcha proof.
	 *
	 * @return array
	 */
	private function prepare_payload( bool $proof = true ): array {
		$this->prepare_verify_post( 'hcaptcha_sendinblue_nonce', 'hcaptcha_sendinblue' );
		hcaptcha()->settings()->set( 'sendinblue_status', [ 'form' ] );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = $this->widget_id( 1 );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Transfer test proof into the native form payload.
		$captcha = $proof ? $_POST : [];

		return array_merge(
			$captcha,
			[
				'sib_form_action' => 'subscribe_form_submit',
				'sib_form_id'     => '1',
				'sib_security'    => wp_create_nonce( 'sib_front_ajax_nonce' ),
				'email'           => 'sec006@example.com',
				'FIRSTNAME'       => 'SEC006',
				'LASTNAME'        => 'Native',
				'NAME'            => "O'Reilly",
			]
		);
	}

	/**
	 * Sign a concrete native form identity.
	 *
	 * @param int $id Native stored form ID.
	 *
	 * @return string
	 */
	private function widget_id( int $id ): string {
		return HCaptcha::widget_id_value(
			[
				'source'  => [ static::$plugin ],
				'form_id' => $id,
			]
		);
	}

	/**
	 * Populate the native form table without replacing any native lookup behavior.
	 *
	 * @param int $id Native form ID.
	 */
	private function create_form( int $id ): void {
		global $wpdb;

		$form               = SIB_Forms::getDefaultForm();
		$form['id']         = $id;
		$form['title']      = 'SEC006 synthetic signup';
		$form['listID']     = maybe_serialize( [ 1 ] );
		$form['attributes'] = 'email,NAME';
		$form['date']       = gmdate( 'Y-m-d' );
		$form['isDopt']     = 0;
		$form['isOpt']      = 0;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Populate the native integration fixture.
		$wpdb->replace( $wpdb->prefix . SIB_Forms::TABLE_NAME, $form );
		self::assertSame( $id, (int) SIB_Forms::getForm( $id )['id'] );
	}

	/**
	 * Change native form configuration without replacing its lookup or submit handler.
	 *
	 * @param int   $id     Native form ID.
	 * @param array $fields Native configured fields.
	 *
	 * @noinspection PhpSameParameterValueInspection
	 */
	private function configure_form( int $id, array $fields ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Configure native fixture data.
		$wpdb->update( $wpdb->prefix . SIB_Forms::TABLE_NAME, $fields, [ 'id' => $id ] );
	}

	/**
	 * Exercise production bootstrap without borrowing ordinary-request integration objects.
	 *
	 * @param string $protocol Lifecycle early-return condition.
	 * @param array  $payload  Actual native browser fields.
	 * @param array  $settings Native integration activation overrides.
	 */
	private function bootstrap_skipped_lifecycle( string $protocol, array $payload, array $settings = [] ): void {
		global $wp_filter;

		$main                     = hcaptcha();
		$this->lifecycle_snapshot = [ 'modules' => $main->modules ];

		foreach ( [ 'loaded_classes', 'auto_verify', 'supported_forms', 'active' ] as $property ) {
			$this->lifecycle_snapshot[ $property ] = $this->get_protected_property( $main, $property );
		}

		update_option( 'hcaptcha_settings', array_merge( (array) get_option( 'hcaptcha_settings', [] ), [ 'sendinblue_status' => [ 'form' ] ], $settings ) );

		foreach ( $wp_filter as $hook_name => $hook ) {
			foreach ( $hook->callbacks as $priority => $callbacks ) {
				foreach ( $callbacks as $callback ) {
					$callable = $callback['function'];

					if ( ! is_array( $callable ) || ! is_object( $callable[0] ) ) {
						continue;
					}

					if (
						$callable[0] instanceof Sendinblue || $callable[0] instanceof AutoVerify ||
						( $callable[0] === $main && in_array( $callable[1], [ 'load_modules', 'allowlist_ip', 'denylist_ip', 'allow_honeypot_and_fst' ], true ) )
					) {
						remove_filter( $hook_name, $callable, $priority );
					}
				}
			}
		}

		$loaded = $this->get_protected_property( $main, 'loaded_classes' );
		unset( $loaded[ Sendinblue::class ], $loaded[ FormSubmitTime::class ] );
		$this->set_protected_property( $main, 'loaded_classes', $loaded );
		$this->set_protected_property( $main, 'auto_verify', null );
		$this->set_protected_property( $main, 'supported_forms', null );
		$main->modules = [];
		$_POST         = wp_slash( $payload );

		if ( 'XML-RPC' === $protocol ) {
			FunctionMocker::replace( 'HCaptcha\Helpers\Request::is_xml_rpc', true );
		} else {
			add_filter( 'wp_doing_cron', '__return_true' );
		}

		$main->init_hooks();
	}

	/**
	 * Count actual installed signup guards, including duplicate bootstrap mistakes.
	 *
	 * @return int
	 */
	private function signup_guard_count(): int {
		global $wp_filter;

		$count = 0;

		foreach ( $wp_filter['init']->callbacks as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$callable = $callback['function'];

				if ( is_array( $callable ) && $callable[0] instanceof Sendinblue && 'verify_signup' === $callable[1] ) {
					++$count;
				}
			}
		}

		return $count;
	}

	/**
	 * Issue a genuine timing token through the production JSON endpoint.
	 *
	 * @return string
	 * @noinspection JsonEncodingApiUsageInspection
	 */
	private function issue_timing_token(): string {
		$fst = hcaptcha()->get( FormSubmitTime::class );
		self::assertInstanceOf( FormSubmitTime::class, $fst );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$post = $_POST;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$request = $_REQUEST;
		$_POST   = [
			'nonce'   => wp_create_nonce( 'hcaptcha-fst-issue-token' ),
			'postId'  => '0',
			'context' => $this->set_method_accessibility( $fst, 'context_from_post_id' )->invoke( $fst, '0' ),
		];
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- check_ajax_referer consumes the actual request fields.
		$_REQUEST    = $_POST;
		$die_handler = static function (): callable {
			return static function (): void {};
		};
		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter( 'wp_die_ajax_handler', $die_handler );

		try {
			ob_start();
			$fst->issue_token();
			$response = json_decode( (string) ob_get_clean(), true );
		} finally {
			remove_filter( 'wp_doing_ajax', '__return_true' );
			remove_filter( 'wp_die_ajax_handler', $die_handler );
			$_POST    = $post;
			$_REQUEST = $request;
		}

		self::assertTrue( $response['success'] );

		return $response['data']['token'];
	}

	/**
	 * Run the real verification and native signup callbacks in their registered order.
	 *
	 * @param array $payload Unsanitized browser fields before WordPress magic quotes.
	 *
	 * @return array|null
	 *
	 * @throws RuntimeException Unexpected runtime error from a native callback.
	 * @noinspection PhpConditionAlreadyCheckedInspection
	 * @noinspection JsonEncodingApiUsageInspection
	 */
	private function dispatch( array $payload ): ?array {
		global $wp_filter, $wp_current_filter;

		$_POST       = wp_slash( $payload );
		$init        = $wp_filter['init'];
		$hook_stack  = $wp_current_filter;
		$init_subset = new WP_Hook();

		foreach ( $init->callbacks as $priority => $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$callable = $callback['function'];

				if ( ! is_array( $callable ) || ! is_object( $callable[0] ) ) {
					continue;
				}

				if ( $callable[0] instanceof AutoVerify || $callable[0] instanceof Sendinblue || $callable[0] instanceof SIB_Manager ) {
					$init_subset->add_filter( 'init', $callable, $priority, $callback['accepted_args'] );
				}
			}
		}

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Run only relevant real callbacks with their native priorities.
		$wp_filter['init'] = $init_subset;
		$die_handler       = static function (): callable {
			return static function (): void {
				throw new RuntimeException( 'SEC006 native JSON completed.' );
			};
		};
		add_filter( 'wp_die_handler', $die_handler );
		add_filter( 'wp_die_ajax_handler', $die_handler );
		$json_context = static function ( bool $ajax ) use ( $init_subset ): bool {
			// Preserve the actual frontend decision made by the earliest automatic verifier.
			return $ajax || doing_filter( 'hcap_verify_request' ) ||
				( doing_action( 'init' ) && 9 <= $init_subset->current_priority() );
		};
		add_filter( 'wp_doing_ajax', $json_context );
		ob_start();

		try {
			do_action( 'init' );
		} catch ( RuntimeException $exception ) {
			if ( 'SEC006 native JSON completed.' !== $exception->getMessage() ) {
				throw $exception;
			}
		} finally {
			$json = (string) ob_get_clean();
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restore the complete native hook after the scoped dispatch.
			$wp_filter['init'] = $init;
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- wp_die interrupts do_action before WordPress pops its stack.
			$wp_current_filter = $hook_stack;
			remove_filter( 'wp_die_handler', $die_handler );
			remove_filter( 'wp_die_ajax_handler', $die_handler );
			remove_filter( 'wp_doing_ajax', $json_context );
		}

		return json_decode( $json, true );
	}
}
