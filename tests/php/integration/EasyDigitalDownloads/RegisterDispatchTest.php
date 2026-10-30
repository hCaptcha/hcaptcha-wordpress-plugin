<?php
/**
 * RegisterDispatchTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedNamespaceInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Integration\EasyDigitalDownloads;

use EDD\Actions\Router;
use EDD\Users\RegistrationGuard;
use EDD\Utils\Messages;
use HCaptcha\EasyDigitalDownloads\Register;
use HCaptcha\Helpers\FormSubmitTime;
use HCaptcha\Helpers\FormSubmitTimeStore;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;

/**
 * Exercise the actual EDD registration router and native registration checks.
 *
 * @group easy-digital-downloads
 * @group easy-digital-downloads-register
 */
class RegisterDispatchTest extends HCaptchaPluginWPTestCase {

	/**
	 * Native plugin entry file.
	 *
	 * @var string
	 */
	protected static $plugin = 'easy-digital-downloads/easy-digital-downloads.php';

	/**
	 * Replay EDD's deferred bootstrap; registration routing does not need cart init callbacks.
	 *
	 * @var string[]
	 */
	protected static array $plugin_load_hooks = [ 'plugins_loaded' ];

	/**
	 * Replay bootstrap when WPTestCase resets action counters.
	 *
	 * @var bool
	 */
	protected static bool $force_plugin_load_hooks = true;

	/**
	 * Request snapshots before the test.
	 *
	 * @var array
	 */
	private array $request = [];

	/**
	 * Server snapshot before the test.
	 *
	 * @var array
	 */
	private array $server = [];

	/**
	 * Prepare native registration guards and deterministic CAPTCHA settings.
	 */
	public function setUp(): void {
		parent::setUp();

		if ( ! class_exists( Router::class ) || ! class_exists( RegistrationGuard::class ) || ! class_exists( Messages::class ) ) {
			self::markTestSkipped( 'The modern EDD registration router is unavailable in this EDD version.' );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$this->request = [ $_GET, $_REQUEST ];
		$this->server  = $_SERVER;
		$_GET          = [];
		$_POST         = [];
		$_REQUEST      = [];
		wp_set_current_user( 0 );
		Messages::clear();
		Router::reset();
		hcaptcha()->settings()->set( 'easy_digital_downloads_status', [ 'register' ] );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );
	}

	/**
	 * Restore native messages, rate state, and request values.
	 *
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function tearDown(): void {
		if ( class_exists( Router::class ) && class_exists( RegistrationGuard::class ) && class_exists( Messages::class ) ) {
			RegistrationGuard::limiter()->reset( edd_get_ip() );
			Messages::clear();
			[ $_GET, $_REQUEST ] = $this->request;
			$_SERVER             = $this->server;
		}

		parent::tearDown();
	}

	/**
	 * Every native spelling and transport must enforce the same registration proof.
	 *
	 * @dataProvider dp_test_native_registration_routes
	 *
	 * @param string $transport Native request transport.
	 * @param string $action    Raw action before native sanitize_key normalization.
	 * @param bool   $proof     Valid CAPTCHA proof present.
	 */
	public function test_native_registration_routes( string $transport, string $action, bool $proof ): void {
		$payload = $this->prepare_payload( $action, $proof );
		new Register();

		if ( 'GET' === $transport ) {
			$_GET  = $payload;
			$_POST = [];
		} else {
			$_POST = $payload;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Snapshot request state around the native dispatch.
		$before = $_POST;
		$result = $this->dispatch( $payload );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verify the adapter restores the untouched outer request.
		self::assertSame( $before, $_POST );
		self::assertSame( $proof ? 1 : 0, $result['sink_attempts'] );
		self::assertSame( $proof ? [] : [ 'bad-signature' => hcap_get_error_messages()['bad-signature'] ], $result['filtered_errors'] );
		self::assertFalse( username_exists( 'sec002-registration' ) );
	}

	/**
	 * Native Router accepts both request arrays and normalized action spellings.
	 *
	 * @return array
	 */
	public function dp_test_native_registration_routes(): array {
		return [
			'canonical POST missing proof'   => [ 'POST', 'user_register', false ],
			'uppercase POST missing proof'   => [ 'POST', 'USER_REGISTER', false ],
			'punctuation POST missing proof' => [ 'POST', 'user!_register', false ],
			'GET missing proof'              => [ 'GET', 'user_register', false ],
			'canonical POST valid proof'     => [ 'POST', 'user_register', true ],
			'GET valid proof'                => [ 'GET', 'user_register', true ],
		];
	}

	/**
	 * Proof and email must come from the current native payload, without merging transports.
	 *
	 * @dataProvider dp_test_conflicting_payloads
	 *
	 * @param string $transport      Native request transport.
	 * @param string $variant        Conflicting proof or address scenario.
	 * @param string $expected_error Expected hCaptcha error code, or empty on success.
	 */
	public function test_conflicting_payloads( string $transport, string $variant, string $expected_error ): void {
		$payload = $this->prepare_payload( 'POST' === $transport ? 'USER_REGISTER' : 'user_register', 'missing proof' !== $variant );
		$decoy   = $this->prepare_payload( 'user_register' );
		$this->enable_email_policy();

		if ( 'foreign widget' === $variant ) {
			$payload[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
				[
					'source'  => [ 'easy-digital-downloads/easy-digital-downloads.php' ],
					'form_id' => 'checkout',
				]
			);
		} elseif ( 'native disposable' === $variant ) {
			$payload['edd_user_email'] = 'native@disposable.example';
		} elseif ( 'decoy disposable' === $variant ) {
			$decoy['edd_user_email']                = 'decoy@disposable.example';
			$decoy[ HCaptcha::HCAPTCHA_WIDGET_ID ] .= 'tampered';
		}

		$_POST = 'GET' === $transport ? $decoy : $payload;
		$_GET  = 'GET' === $transport ? $payload : $decoy;
		new Register();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Preserve the complete outer transport snapshot.
		$before = $_POST;
		$result = $this->dispatch( $payload );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verify full restoration, including decoy proof and fields.
		self::assertSame( $before, $_POST );
		self::assertSame( '' === $expected_error ? 1 : 0, $result['sink_attempts'] );
		self::assertSame( '' === $expected_error ? [] : [ $expected_error => hcap_get_error_messages()[ $expected_error ] ], $result['filtered_errors'] );
	}

	/**
	 * Conflicting transport controls.
	 *
	 * @return array
	 */
	public function dp_test_conflicting_payloads(): array {
		return [
			'GET cannot borrow POST proof'     => [ 'GET', 'missing proof', 'bad-signature' ],
			'GET rejects own foreign widget'   => [ 'GET', 'foreign widget', 'bad-signature' ],
			'GET checks own disposable email'  => [ 'GET', 'native disposable', 'disposable-email' ],
			'GET ignores bad POST decoys'      => [ 'GET', 'decoy disposable', '' ],
			'POST cannot borrow GET proof'     => [ 'POST', 'missing proof', 'bad-signature' ],
			'POST checks own disposable email' => [ 'POST', 'native disposable', 'disposable-email' ],
		];
	}

	/**
	 * The integration must retain native origin and inexpensive form checks.
	 *
	 * @dataProvider dp_test_native_guards
	 *
	 * @param string $field          Native field modified.
	 * @param string $value          Invalid native value.
	 * @param string $expected_error Expected native code, or empty for a non-submission.
	 */
	public function test_native_guards( string $field, string $value, string $expected_error ): void {
		$payload           = $this->prepare_payload( 'user_register' );
		$payload[ $field ] = $value;
		$_POST             = $payload;
		new Register();
		$result = $this->dispatch( $payload );

		self::assertSame( 0, $result['sink_attempts'] );

		if ( '' === $expected_error ) {
			self::assertSame( [], $result['stored_errors'] );
		} else {
			self::assertArrayHasKey( $expected_error, $result['stored_errors'] );
		}

		if ( in_array( $field, [ 'edd_register_submit', 'edd_register_token' ], true ) ) {
			self::assertSame( 0, $result['remote_calls'] );
		}
	}

	/**
	 * Native checks stay authoritative before account insertion.
	 *
	 * @return array
	 */
	public function dp_test_native_guards(): array {
		return [
			'not a submission'   => [ 'edd_register_submit', '', '' ],
			'invalid HMAC token' => [ 'edd_register_token', 'invalid-token', 'registration_token' ],
			'filled honeypot'    => [ 'edd_honeypot', 'bot', 'invalid_form_data' ],
			'password mismatch'  => [ 'edd_user_pass2', 'different-password', 'password_mismatch' ],
			'invalid email'      => [ 'edd_user_email', 'invalid-email', 'email_invalid' ],
		];
	}

	/**
	 * Valid CAPTCHA must not bypass native registration volume limits.
	 */
	public function test_native_rate_limit_remains_authoritative(): void {
		$payload = $this->prepare_payload( 'user_register' );

		for ( $i = 0; $i < RegistrationGuard::MAX_ATTEMPTS; ++$i ) {
			self::assertTrue( RegistrationGuard::check_rate_limit() );
		}

		$_POST = $payload;
		new Register();
		$result = $this->dispatch( $payload );

		self::assertSame( 0, $result['sink_attempts'] );
		self::assertArrayHasKey( 'rate_limit_exceeded', $result['stored_errors'] );
		self::assertSame( 1, $result['remote_calls'] );
	}

	/**
	 * Only the current native registration hook owns the error collection.
	 *
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function test_unrelated_and_completed_actions_do_not_claim_errors(): void {
		$payload = $this->prepare_payload( 'user_register', false );
		$_POST   = $payload;
		new Register();
		$native = [ 'native_error' => 'Native error remains unchanged.' ];
		edd_set_error( 'native_error', $native['native_error'] );
		$observed = null;
		add_action(
			'edd_sec002_unrelated',
			static function () use ( &$observed ): void {
				$observed = edd_get_errors();
			}
		);
		Router::frontend( [ 'edd_action' => 'sec002_unrelated' ] );

		self::assertSame( $native, $observed );
		// No submit means the native registration handler returns without error collection.
		unset( $payload['edd_register_submit'] );
		Router::frontend( $payload );
		self::assertSame( $native, edd_get_errors() );
	}

	/**
	 * A nested native dispatch must restore its outer payload and single-use proof.
	 *
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function test_nested_different_proofs_restore_outer_registration(): void {
		$outer                       = $this->prepare_payload( 'user_register' );
		$inner                       = $this->prepare_payload( 'user_register' );
		$inner['edd_user_login']     = 'sec002-inner-registration';
		$inner['edd_user_email']     = 'sec002-inner@example.com';
		$outer['h-captcha-response'] = 'outer-response';
		$inner['h-captcha-response'] = 'inner-response';
		$this->prepare_verify_request( 'outer-response' );
		add_filter(
			'pre_http_request',
			static function ( $preempt, array $args, string $url ) {
				if ( 'https://api.hcaptcha.com/siteverify' !== $url ) {
					return $preempt;
				}

				self::assertContains( $args['body']['response'], [ 'outer-response', 'inner-response' ] );

				return [ 'body' => wp_json_encode( [ 'success' => true ] ) ];
			},
			11,
			3
		);
		$settings                                  = (array) get_option( 'hcaptcha_settings', [] );
		$settings['set_min_submit_time']           = 'on';
		$settings['min_submit_time']               = '0';
		$settings['easy_digital_downloads_status'] = [ 'register' ];
		update_option( 'hcaptcha_settings', $settings );
		hcaptcha()->init_hooks();
		self::assertSame( 0, (int) hcaptcha()->settings()->get( 'min_submit_time' ) );
		// The standard API helper replaces issued tokens; this case needs their real signatures.
		remove_all_filters( 'hcap_fst_token' );
		$_SERVER['REMOTE_ADDR'] = '203.0.113.22';
		FormSubmitTimeStore::delete_all();

		try {
			$outer['hcap_fst_token'] = $this->issue_timing_token();
			$inner['hcap_fst_token'] = $this->issue_timing_token();
			remove_filter( 'hcap_verify_fst_token', '__return_true' );
			$_POST = $outer;
			new Register();
			$nested = false;
			add_action(
				'edd_process_register_form',
				static function () use ( $inner, &$nested ): void {
					if ( $nested ) {
						return;
					}

					$nested = true;
					// Extensions can collect errors before triggering another native registration.
					self::assertSame( [], (array) edd_get_errors() );
					Router::frontend( $inner );
				}
			);
			$result = $this->dispatch( $outer );

			self::assertSame( [], $result['filtered_errors'] );
			self::assertSame( [], $result['stored_errors'] );
			self::assertSame( 2, $result['sink_attempts'] );
			self::assertSame( 2, $result['remote_calls'] );
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verify both scoped verifications restore the outer request.
			self::assertSame( $outer, $_POST );
		} finally {
			FormSubmitTimeStore::delete_all();
		}
	}

	/**
	 * Enable deterministic native disposable-email controls.
	 */
	private function enable_email_policy(): void {
		hcaptcha()->settings()->set( 'disposable_email', [ 'on' ] );
		add_filter(
			'hcap_disposable_email_domains',
			static function (): array {
				return [ 'disposable.example' => true ];
			}
		);
	}

	/**
	 * Call the real timing-token issuance endpoint without terminating the runner.
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
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Native check_ajax_referer reads REQUEST.
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
	 * Issue the native HMAC token and a complete form payload.
	 *
	 * @param string $action Raw EDD action.
	 * @param bool   $proof  Whether to include valid CAPTCHA proof.
	 *
	 * @return array
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	private function prepare_payload( string $action, bool $proof = true ): array {
		$this->prepare_verify_post( 'hcaptcha_easy_digital_downloads_register_nonce', 'hcaptcha_easy_digital_downloads_register' );
		hcaptcha()->settings()->set( 'easy_digital_downloads_status', [ 'register' ] );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'easy-digital-downloads/easy-digital-downloads.php' ],
				'form_id' => 'register',
			]
		);
		RegistrationGuard::limiter()->reset( edd_get_ip() );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Transfer prepared test proof into the native payload.
		$captcha = $proof ? $_POST : [];

		ob_start();
		RegistrationGuard::token_fields();
		$markup = (string) ob_get_clean();
		preg_match_all( '/name="([^"]+)" value="([^"]+)"/', $markup, $fields, PREG_SET_ORDER );
		$tokens = [];

		foreach ( $fields as $field ) {
			$tokens[ $field[1] ] = html_entity_decode( $field[2], ENT_QUOTES );
		}

		$payload = array_merge(
			$captcha,
			$tokens,
			[
				'edd_action'          => $action,
				'edd_register_submit' => 'Register',
				'edd_user_login'      => 'sec002-registration',
				'edd_user_email'      => 'sec002@example.com',
				'edd_user_pass'       => 'sec002-test-password',
				'edd_user_pass2'      => 'sec002-test-password',
				'edd_honeypot'        => '',
			]
		);
		self::assertTrue( RegistrationGuard::has_valid_token( $payload ) );

		return $payload;
	}

	/**
	 * Run native routing and prevent only the final insertion side effect.
	 *
	 * @param array $payload The unmerged request passed by the native router.
	 *
	 * @return array
	 */
	private function dispatch( array $payload ): array {
		$filtered_errors = [];
		$sink_attempts   = 0;
		$remote_calls    = 0;
		add_filter(
			'pre_http_request',
			static function ( $preempt, array $args, string $url ) use ( &$remote_calls ) {
				if ( 'https://api.hcaptcha.com/siteverify' === $url ) {
					++$remote_calls;
				}

				return $preempt;
			},
			5,
			3
		);
		add_filter(
			'edd_errors',
			static function ( $errors ) use ( &$filtered_errors ) {
				$filtered_errors = (array) $errors;

				return $errors;
			},
			PHP_INT_MAX
		);
		add_filter(
			'edd_insert_user_args',
			static function ( array $args ) use ( &$sink_attempts ): array {
				++$sink_attempts;
				// An empty native login fails wp_insert_user before it creates an account.
				$args['user_login'] = '';

				return $args;
			}
		);
		Router::frontend( $payload );

		return [
			'filtered_errors' => $filtered_errors,
			'stored_errors'   => Messages::get_by_type( 'error' ),
			'sink_attempts'   => $sink_attempts,
			'remote_calls'    => $remote_calls,
		];
	}
}
