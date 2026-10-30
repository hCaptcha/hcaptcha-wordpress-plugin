<?php
/**
 * ActionVerificationTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\ThemeMyLogin;

use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Helpers\LoginAttempts;
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use HCaptcha\ThemeMyLogin\Login;
use HCaptcha\ThemeMyLogin\Register;
use WP_Error;
use WP_User;

/**
 * Exercise both native Theme My Login authentication routes.
 *
 * @group theme-my-login
 */
class ActionVerificationTest extends HCaptchaPluginWPTestCase {

	/**
	 * Plugin relative path.
	 *
	 * @var string
	 */
	protected static $plugin = 'theme-my-login/theme-my-login.php';

	/**
	 * Request values before the test.
	 *
	 * @var array
	 */
	private array $request = [];

	/**
	 * Prepare the real native handlers and isolate their transport side effects.
	 *
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function setUp(): void {
		parent::setUp();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$this->request             = $_REQUEST;
		$_REQUEST                  = [];
		$_POST                     = [];
		$_SERVER['REQUEST_METHOD'] = 'POST';
		wp_set_current_user( 0 );
		LoginAttempts::delete_all();
		update_option( 'users_can_register', true );
		update_option(
			'hcaptcha_settings',
			[
				'login_limit'           => 0,
				'login_interval'        => 15,
				'theme_my_login_status' => [ 'login', 'register' ],
			]
		);
		hcaptcha()->settings()->init();
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );

		tml_register_default_actions();
		tml_register_login_form();
		tml_register_registration_form();
	}

	/**
	 * Restore the request and bounded failed-login state.
	 */
	public function tearDown(): void {
		$_REQUEST = $this->request;
		unset( $_SERVER['REQUEST_METHOD'] );
		LoginAttempts::delete_all();

		parent::tearDown();
	}

	/**
	 * Real wp_signon authentication must require the same proof on either route.
	 *
	 * @dataProvider dp_test_native_login_verification
	 *
	 * @param bool   $ajax          Native AJAX route.
	 * @param string $proof         Submitted proof variant.
	 * @param string $expected_code Expected hCaptcha error code, or an empty success code.
	 */
	public function test_native_login_verification( bool $ajax, string $proof, string $expected_code ): void {
		$user_id = $this->factory()->user->create( [ 'user_pass' => 'sec001-test-password' ] );
		$user    = get_user_by( 'id', $user_id );

		if ( 'bad nonce' === $proof ) {
			wp_set_current_user( $user_id );
		}

		$this->prepare_proof( 'login', $proof );
		new Login();

		$_POST['log'] = $user->user_login;
		$_POST['pwd'] = 'sec001-test-password';
		$result       = $this->run_login_handler( $ajax );

		if ( '' === $expected_code ) {
			self::assertInstanceOf( WP_User::class, $result );
			self::assertSame( $user_id, $result->ID );

			return;
		}

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'invalid_hcaptcha', $result->get_error_code() );
		self::assertSame( 400, $result->get_error_data() );
		self::assertSame( $this->html_error( $expected_code ), $result->get_error_message() );
	}

	/**
	 * Successful proof and independent signature, nonce, and remote failures.
	 *
	 * @return array
	 */
	public function dp_test_native_login_verification(): array {
		$cases = [];

		foreach ( [
			'normal' => false,
			'AJAX'   => true,
		] as $route => $ajax ) {
			foreach (
				[
					'valid'            => '',
					'missing widget'   => 'bad-signature',
					'foreign widget'   => 'bad-signature',
					'tampered widget'  => 'bad-signature',
					'missing response' => 'empty',
					'invalid response' => 'fail',
					'bad nonce'        => 'bad-nonce',
				] as $proof => $expected_code
			) {
				$cases[ $route . ': ' . $proof ] = [ $ajax, $proof, $expected_code ];
			}
		}

		return $cases;
	}

	/**
	 * The native password check remains authoritative after valid CAPTCHA proof.
	 */
	public function test_ajax_login_preserves_native_password_error(): void {
		$user_id = $this->factory()->user->create( [ 'user_pass' => 'sec001-test-password' ] );
		$user    = get_user_by( 'id', $user_id );

		$this->prepare_proof( 'login', 'valid' );
		new Login();
		$_POST['log'] = $user->user_login;
		$_POST['pwd'] = 'incorrect-password';

		$result = $this->run_login_handler( true );

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'incorrect_password', $result->get_error_code() );
	}

	/**
	 * Adaptive protection uses current server attempts on the AJAX route.
	 *
	 * @dataProvider dp_test_ajax_login_threshold
	 *
	 * @param int  $failures        Stored failures.
	 * @param bool $trusted_override Trusted server filter disables protection.
	 * @param bool $blocked         Whether CAPTCHA is required.
	 */
	public function test_ajax_login_threshold( int $failures, bool $trusted_override, bool $blocked ): void {
		$user_id = $this->factory()->user->create( [ 'user_pass' => 'sec001-test-password' ] );
		$user    = get_user_by( 'id', $user_id );

		$this->prepare_proof( 'login', 'missing response' );
		hcaptcha()->settings()->set( 'login_limit', 2 );

		for ( $i = 0; $i < $failures; ++$i ) {
			( new LoginAttempts() )->increment( '7.7.7.7', time(), 15 * MINUTE_IN_SECONDS );
		}

		if ( $trusted_override ) {
			add_filter( 'hcap_login_limit_exceeded', '__return_false' );
		}

		new Login();
		$_POST['log'] = $user->user_login;
		$_POST['pwd'] = 'sec001-test-password';
		$result       = $this->run_login_handler( true );

		if ( $blocked ) {
			self::assertInstanceOf( WP_Error::class, $result );
			self::assertSame( 'invalid_hcaptcha', $result->get_error_code() );

			return;
		}

		self::assertInstanceOf( WP_User::class, $result );
		self::assertSame( $user_id, $result->ID );
	}

	/**
	 * Server threshold controls.
	 *
	 * @return array
	 */
	public function dp_test_ajax_login_threshold(): array {
		return [
			'below threshold'  => [ 1, false, false ],
			'at threshold'     => [ 2, false, true ],
			'trusted override' => [ 2, true, false ],
		];
	}

	/**
	 * Real registration validation must reject missing proof on either route.
	 *
	 * @dataProvider dp_test_native_registration_verification
	 *
	 * @param bool   $ajax          Native AJAX route.
	 * @param string $proof         Submitted proof variant.
	 * @param string $expected_code Expected hCaptcha error code, or an empty success code.
	 */
	public function test_native_registration_verification( bool $ajax, string $proof, string $expected_code ): void {
		$this->prepare_proof( 'register', $proof );
		new Register();
		$result = $this->run_registration_handler( $ajax, 'safe@example.com' );

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( '' === $expected_code ? [] : [ 'invalid_captcha' ], $result->get_error_codes() );
		self::assertSame( '' === $expected_code ? '' : $this->html_error( $expected_code ), $result->get_error_message() );
		self::assertFalse( username_exists( 'sec001-register-user' ) );
	}

	/**
	 * Registration proof controls through the native handler.
	 *
	 * @return array
	 */
	public function dp_test_native_registration_verification(): array {
		return [
			'normal valid'          => [ false, 'valid', '' ],
			'normal missing widget' => [ false, 'missing widget', 'bad-signature' ],
			'normal missing token'  => [ false, 'missing response', 'empty' ],
			'AJAX valid'            => [ true, 'valid', '' ],
			'AJAX missing widget'   => [ true, 'missing widget', 'bad-signature' ],
			'AJAX foreign widget'   => [ true, 'foreign widget', 'bad-signature' ],
			'AJAX invalid token'    => [ true, 'invalid response', 'fail' ],
		];
	}

	/**
	 * CAPTCHA adds its error to the native registration error object.
	 */
	public function test_ajax_registration_preserves_native_errors(): void {
		$this->prepare_proof( 'register', 'missing response' );
		new Register();

		$native_errors = null;
		add_filter(
			'registration_errors',
			static function ( WP_Error $errors ) use ( &$native_errors ): WP_Error {
				$native_errors = $errors;

				return $errors;
			},
			9
		);

		$result = $this->run_registration_handler( true, 'invalid-email' );

		self::assertSame( $native_errors, $result );
		self::assertContains( 'invalid_email', $result->get_error_codes() );
		self::assertContains( 'invalid_captcha', $result->get_error_codes() );
	}

	/**
	 * AJAX registration retains the hook-derived disposable-email policy.
	 */
	public function test_ajax_registration_checks_native_email(): void {
		$this->prepare_proof( 'register', 'valid' );
		hcaptcha()->settings()->set( 'disposable_email', [ 'on' ] );
		add_filter(
			'hcap_disposable_email_domains',
			static function (): array {
				return [ 'disposable.example' => true ];
			}
		);
		$_POST['email'] = 'safe@example.com';
		new Register();

		$result = $this->run_registration_handler( true, 'native@disposable.example' );

		self::assertSame( [ 'invalid_captcha' ], $result->get_error_codes() );
		self::assertSame( $this->html_error( 'disposable-email' ), $result->get_error_message() );
	}

	/**
	 * Completed action counters cannot claim unrelated authentication flows.
	 */
	public function test_completed_actions_do_not_claim_other_flows(): void {
		$login    = new Login();
		$register = new Register();
		$user     = new WP_User();
		$errors   = new WP_Error( 'native-error', 'Native registration error.' );

		foreach ( [ 'tml_action_login', 'tml_action_ajax_login', 'tml_action_register', 'tml_action_ajax_register' ] as $hook ) {
			remove_all_actions( $hook );
			do_action( $hook );
		}

		self::assertSame( $user, $login->verify( $user, 'password' ) );
		self::assertSame( $errors, $register->verify( $errors, 'guest', 'safe@example.com' ) );
		add_action(
			'tml_action_sec001_unrelated',
			static function () use ( $login, $register, $user, $errors ): void {
				self::assertSame( $user, $login->verify( $user, 'password' ) );
				self::assertSame( $errors, $register->verify( $errors, 'guest', 'safe@example.com' ) );
			}
		);
		do_action( 'tml_action_sec001_unrelated' );
	}

	/**
	 * Prepare signed form metadata and the production API's mocked remote response.
	 *
	 * @param string $form_id Form identity.
	 * @param string $proof   Proof variant.
	 */
	private function prepare_proof( string $form_id, string $proof ): void {
		$nonce  = 'login' === $form_id ? 'hcaptcha_login_nonce' : 'hcaptcha_theme_my_login_register_nonce';
		$action = 'login' === $form_id ? 'hcaptcha_login' : 'hcaptcha_theme_my_login_register';
		$this->prepare_verify_post( $nonce, $action, 'invalid response' !== $proof );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'theme-my-login/theme-my-login.php' ],
				'form_id' => 'foreign widget' === $proof ? 'other-form' : $form_id,
			]
		);

		if ( 'missing widget' === $proof ) {
			unset( $_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] );
		} elseif ( 'tampered widget' === $proof ) {
			$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] .= 'tampered';
		} elseif ( 'missing response' === $proof ) {
			unset( $_POST['h-captcha-response'] );
		} elseif ( 'bad nonce' === $proof ) {
			$_POST[ $nonce ] = 'invalid-nonce';
		}
	}

	/**
	 * Execute the registered native handler, stopping before cookies and redirects.
	 *
	 * @param bool $ajax Native AJAX route.
	 *
	 * @return WP_User|WP_Error|null
	 */
	private function run_login_handler( bool $ajax ) {
		$result = null;
		add_filter(
			'authenticate',
			static function ( $user ) use ( &$result ): WP_Error {
				$result = $user;

				return new WP_Error( 'sec001-stop', 'Stop before login cookies.' );
			},
			PHP_INT_MAX
		);
		$_REQUEST['ajax']                    = $ajax ? '1' : '0';
		$GLOBALS['wp']->query_vars['action'] = 'login';
		$this->run_native_action( $ajax, 'login' );

		return $result;
	}

	/**
	 * Execute native registration validation, stopping before account creation.
	 *
	 * @param bool   $ajax  Native AJAX route.
	 * @param string $email Native submitted address.
	 *
	 * @return WP_Error|null
	 */
	private function run_registration_handler( bool $ajax, string $email ): ?WP_Error {
		$result = null;
		add_filter(
			'registration_errors',
			static function ( WP_Error $errors ) use ( &$result ): WP_Error {
				$result = $errors;

				return new WP_Error( 'sec001-stop', 'Stop before account creation.' );
			},
			PHP_INT_MAX
		);
		$_POST['user_login']                 = 'sec001-register-user';
		$_POST['user_email']                 = $email;
		$_REQUEST['ajax']                    = $ajax ? '1' : '0';
		$GLOBALS['wp']->query_vars['action'] = 'register';
		$this->run_native_action( $ajax, 'register' );

		return $result;
	}

	/**
	 * Let the native JSON response finish without terminating the test process.
	 *
	 * @param bool   $ajax    Native AJAX route.
	 * @param string $form_id Native form identity.
	 */
	private function run_native_action( bool $ajax, string $form_id ): void {
		if ( $ajax ) {
			add_filter( 'wp_doing_ajax', '__return_true' );
			add_filter(
				'wp_die_ajax_handler',
				static function (): callable {
					return static function (): void {};
				}
			);
		}

		ob_start();
		do_action( $ajax ? 'tml_action_ajax_' . $form_id : 'tml_action_' . $form_id );
		ob_end_clean();
	}

	/**
	 * Get the adapter's existing HTML error presentation.
	 *
	 * @param string $code Error code.
	 *
	 * @return string
	 */
	private function html_error( string $code ): string {
		return '<strong>hCaptcha error:</strong> ' . hcap_get_error_messages()[ $code ];
	}
}
