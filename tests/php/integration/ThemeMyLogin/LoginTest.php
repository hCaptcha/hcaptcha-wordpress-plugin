<?php
/**
 * LoginTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\ThemeMyLogin;

use HCaptcha\Abstracts\LoginBase;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Helpers\LoginAttempts;
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use HCaptcha\ThemeMyLogin\Login;
use HCaptcha\ThemeMyLogin\Register;
use HCaptcha\WP\Login as WPLogin;
use ReflectionProperty;
use WP_Error;

/**
 * Test `Theme My Login` login forms.
 *
 * @group theme-my-login
 */
class LoginTest extends HCaptchaPluginWPTestCase {
	/**
	 * Do not render a challenge outside the Theme My Login form action.
	 */
	public function test_add_captcha_outside_form_action(): void {
		$subject = new Login();

		ob_start();
		$subject->add_captcha();

		self::assertSame( '', ob_get_clean() );
	}

	/**
	 * Plugin relative path.
	 *
	 * @var string
	 */
	protected static $plugin = 'theme-my-login/theme-my-login.php';

	/**
	 * Clear shared login attempts after each test.
	 */
	public function tearDown(): void {
		LoginAttempts::delete_all();

		parent::tearDown();
	}

	/**
	 * Test the real shortcode renders valid login contexts despite TML's array hook argument.
	 *
	 * @param bool $tml_enabled Theme My Login protection setting.
	 * @param bool $wp_enabled  Native WordPress login protection setting.
	 * @param int  $login_limit Failed login threshold.
	 *
	 * @dataProvider dp_test_login_shortcode
	 *
	 * @return void
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function test_login_shortcode( bool $tml_enabled, bool $wp_enabled, int $login_limit ): void {
		wp_set_current_user( 0 );
		update_option(
			'hcaptcha_settings',
			[
				'login_limit'           => $login_limit,
				'login_interval'        => 15,
				'theme_my_login_status' => $tml_enabled ? [ 'login' ] : [],
				'wp_status'             => $wp_enabled ? [ 'login' ] : [],
			]
		);
		hcaptcha()->settings()->init();
		remove_all_actions( 'login_form' );

		if ( $tml_enabled ) {
			new Login();
		}

		if ( $wp_enabled ) {
			new WPLogin();
		}

		tml_register_default_actions();
		tml_register_login_form();

		$form          = do_shortcode( '[theme-my-login action="login"]' );
		$captcha_shown = $tml_enabled && 0 === $login_limit;

		self::assertStringContainsString( 'name="log"', $form );
		self::assertSame( (int) $captcha_shown, substr_count( $form, '<h-captcha' ) );

		if ( $tml_enabled ) {
			self::assertStringContainsString( HCaptcha::get_signature( Login::class, 'login', $captcha_shown ), $form );
		}

		if ( $wp_enabled ) {
			self::assertStringContainsString( HCaptcha::get_signature( WPLogin::class, 'login', false ), $form );
		}
	}

	/**
	 * Protection combinations exercising TML and shared WordPress login hooks.
	 *
	 * @return array
	 */
	public function dp_test_login_shortcode(): array {
		return [
			'TML protection only'              => [ true, false, 0 ],
			'TML and WordPress protection'     => [ true, true, 0 ],
			'WordPress protection only'        => [ false, true, 0 ],
			'below the failed login threshold' => [ true, true, 1 ],
		];
	}

	/**
	 * A native failed login must reload the form when it first enables CAPTCHA.
	 *
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function test_adaptive_login_reloads_form_before_enabling_ajax(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended
		$post       = $_POST;
		$request    = $_REQUEST;
		$server     = $_SERVER;
		$query_vars = $GLOBALS['wp']->query_vars;
		// phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended
		$failed_event = new ReflectionProperty( LoginBase::class, 'last_wp_login_failed_action' );
		$failed_event->setAccessible( true );
		$previous_event = $failed_event->getValue();

		try {
			// WPTestCase resets action counters; reset the corresponding request-local deduplication state.
			$failed_event->setValue( 0 );
			wp_set_current_user( 0 );
			LoginAttempts::delete_all();
			$_POST                     = [];
			$_REQUEST                  = [];
			$_SERVER['REMOTE_ADDR']    = '203.0.113.17';
			$_SERVER['REQUEST_METHOD'] = 'POST';
			unset( $_SERVER['HTTP_CLIENT_IP'] );
			update_option(
				'hcaptcha_settings',
				[
					'login_limit'           => 1,
					'login_interval'        => 15,
					'theme_my_login_status' => [ 'login', 'register' ],
				]
			);
			hcaptcha()->settings()->init();
			update_option( 'users_can_register', true );
			remove_all_actions( 'login_form' );
			add_filter( 'tml_use_ajax', '__return_true' );
			new Login();
			new Register();
			tml_register_default_actions();
			tml_register_login_form();
			tml_register_registration_form();

			$form = do_shortcode( '[theme-my-login action="login"]' );

			self::assertStringContainsString( 'data-ajax="0"', $form );
			self::assertStringNotContainsString( '<h-captcha', $form );

			$user_id = $this->factory()->user->create( [ 'user_pass' => 'sec001-test-password' ] );
			$user    = get_user_by( 'id', $user_id );
			$result  = null;
			add_filter(
				'authenticate',
				static function ( $authenticated ) use ( &$result ) {
					$result = $authenticated;

					return $authenticated;
				},
				PHP_INT_MAX
			);
			$_POST['log']                        = $user->user_login;
			$_POST['pwd']                        = 'incorrect-password';
			$_REQUEST['ajax']                    = '0';
			$GLOBALS['wp']->query_vars['action'] = 'login';
			do_action( 'tml_action_login' );

			self::assertInstanceOf( WP_Error::class, $result );
			self::assertSame( 'incorrect_password', $result->get_error_code() );
			self::assertSame( 1, ( new LoginAttempts() )->read( hcap_get_user_ip(), time() ) );

			// Recreate the native form as the next page load does, using the updated server counter.
			$_POST                     = [];
			$_REQUEST                  = [];
			$_SERVER['REQUEST_METHOD'] = 'GET';
			tml_register_login_form();
			tml_register_registration_form();
			$form         = do_shortcode( '[theme-my-login action="login"]' );
			$registration = do_shortcode( '[theme-my-login action="register"]' );

			self::assertStringContainsString( 'data-ajax="1"', $form );
			self::assertSame( 1, substr_count( $form, '<h-captcha' ) );
			self::assertStringContainsString( 'data-ajax="1"', $registration );
			self::assertSame( 1, substr_count( $registration, '<h-captcha' ) );
		} finally {
			$failed_event->setValue( $previous_event );
			$failed_event->setAccessible( false );
			$_POST                     = $post;
			$_REQUEST                  = $request;
			$_SERVER                   = $server;
			$GLOBALS['wp']->query_vars = $query_vars;
		}
	}

	/**
	 * An immediately protected login keeps native AJAX submission available.
	 *
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function test_always_protected_login_keeps_native_ajax(): void {
		wp_set_current_user( 0 );
		update_option(
			'hcaptcha_settings',
			[
				'login_limit'           => 0,
				'login_interval'        => 15,
				'theme_my_login_status' => [ 'login' ],
			]
		);
		hcaptcha()->settings()->init();
		remove_all_actions( 'login_form' );
		add_filter( 'tml_use_ajax', '__return_true' );
		new Login();
		tml_register_default_actions();
		tml_register_login_form();

		$form = do_shortcode( '[theme-my-login action="login"]' );

		self::assertStringContainsString( 'data-ajax="1"', $form );
		self::assertSame( 1, substr_count( $form, '<h-captcha' ) );
	}
}
