<?php
/**
 * LoginTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\WP;

use HCaptcha\Abstracts\LoginBase;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use HCaptcha\WP\Login;
use ReflectionException;
use tad\FunctionMocker\FunctionMocker;
use WP_Error;
use WP_User;

/**
 * Class LoginTest.
 *
 * @group wp-login
 * @group wp
 */
class LoginTest extends HCaptchaWPTestCase {

	/**
	 * Tear down test.
	 *
	 * @noinspection PhpLanguageLevelInspection
	 * @noinspection PhpUndefinedClassInspection
	 */
	public function tearDown(): void { // phpcs:ignore PHPCompatibility.FunctionDeclarations.NewReturnTypeDeclarations.voidFound
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		unset(
			$_POST['log'],
			$_POST['pwd'],
			$_SERVER['REMOTE_ADDR'],
			$GLOBALS['wp_action']['login_init'],
			$GLOBALS['wp_action']['login_form_login'],
			$GLOBALS['wp_filters']['login_link_separator']
		);

		parent::tearDown();
	}

	/**
	 * Test constructor and init_hooks().
	 */
	public function test_constructor_and_init_hooks() {
		$subject = new Login();

		self::assertSame(
			10,
			has_action( 'login_form', [ $subject, 'add_captcha' ] )
		);
		self::assertSame(
			10,
			has_action( 'wp_authenticate_user', [ $subject, 'verify' ] )
		);
	}

	/**
	 * Test login().
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_login() {
		$ip                      = '1.1.1.1';
		$login_data[ $ip ][]     = time();
		$login_data['2.2.2.2'][] = time();
		$user_login              = 'test-user';
		$user                    = new WP_User();

		$subject = new Login();

		$this->set_protected_property( $subject, 'ip', $ip );
		$this->set_protected_property( $subject, 'login_data', $login_data );

		$subject->login( $user_login, $user );

		unset( $login_data[ $ip ] );

		self::assertSame( $login_data, $this->get_protected_property( $subject, 'login_data' ) );
		self::assertSame( $login_data, get_option( LoginBase::LOGIN_DATA ) );

		// Check that hcaptcha_login_data option is not autoloading.
		$alloptions = wp_cache_get( 'alloptions', 'options' );
		self::assertArrayNotHasKey( LoginBase::LOGIN_DATA, $alloptions );
	}

	/**
	 * Test login_failed().
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 * @noinspection UnusedFunctionResultInspection
	 */
	public function test_login_failed() {
		$ip                           = '1.1.1.1';
		$ip2                          = '2.2.2.2';
		$time                         = time();
		$login_interval               = 15;
		$login_data[ $ip ][]          = $time - $login_interval * MINUTE_IN_SECONDS;
		$login_data[ $ip ][]          = $time - 20;
		$login_data[ $ip ][]          = $time - 10;
		$login_data[ $ip2 ][]         = $time - $login_interval * MINUTE_IN_SECONDS - 5;
		$login_data[ $ip2 ][]         = $time - 25;
		$login_data[ $ip2 ][]         = $time - 15;
		$expected_login_data          = $login_data;
		$expected_login_data[ $ip ][] = $time;
		$username                     = 'test_username';
		$_SERVER['REMOTE_ADDR']       = $ip;

		array_shift( $expected_login_data[ $ip ] );
		array_shift( $expected_login_data[ $ip2 ] );

		update_option( 'hcaptcha_settings', [ 'login_interval' => $login_interval ] );
		update_option( LoginBase::LOGIN_DATA, $login_data );

		$subject = new Login();

		$this->set_protected_property( $subject, 'ip', $ip );

		FunctionMocker::replace( 'time', $time );

		$subject->login_failed( $username, null );

		self::assertSame( $expected_login_data, $this->get_protected_property( $subject, 'login_data' ) );
		self::assertSame( $expected_login_data, get_option( LoginBase::LOGIN_DATA ) );
	}

	/**
	 * Test protect_form().
	 *
	 * @return void
	 * @noinspection PhpConditionAlreadyCheckedInspection
	 */
	public function test_protect_form() {
		$value   = true;
		$source  = HCaptcha::get_class_source( Login::class );
		$form_id = 'login';

		$subject = new Login();

		self::assertFalse( $subject->protect_form( $value, $source, $form_id ) );

		$form_id = 'some';

		self::assertSame( $value, $subject->protect_form( $value, $source, $form_id ) );

		$form_id = 'login';
		$source  = [];

		self::assertSame( $value, $subject->protect_form( $value, $source, $form_id ) );
	}

	/**
	 * Test add_captcha().
	 */
	public function test_add_captcha() {
		// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wp_actions']['login_init']           = 1;
		$GLOBALS['wp_actions']['login_form_login']     = 1;
		$GLOBALS['wp_filters']['login_link_separator'] = 1;
		// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited

		$expected =
			$this->get_hcap_form() .
			wp_nonce_field( 'hcaptcha_login', 'hcaptcha_login_nonce', true, false );

		$subject = new Login();

		ob_start();

		$subject->add_captcha();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test add_captcha() when not WP login form.
	 */
	public function test_add_captcha_when_NOT_wp_login_form() {
		$expected = '';

		$subject = new Login();

		ob_start();

		$subject->add_captcha();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test add_captcha() when not login limit exceeded.
	 */
	public function test_add_captcha_when_NOT_login_limit_exceeded() {
		$expected = '';

		$subject = new Login();

		add_filter(
			'hcap_login_limit_exceeded',
			static function () {
				return false;
			}
		);

		// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wp_actions']['login_init']           = 1;
		$GLOBALS['wp_actions']['login_form_login']     = 1;
		$GLOBALS['wp_filters']['login_link_separator'] = 1;
		// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited

		ob_start();

		$subject->add_captcha();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test verify().
	 */
	public function test_verify() {
		$user = new WP_User( 1 );

		$this->prepare_hcaptcha_get_verify_message_html( 'hcaptcha_login_nonce', 'hcaptcha_login' );

		$_POST['log'] = 'some login';
		$_POST['pwd'] = 'some password';

		// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wp_actions']['login_init']           = 1;
		$GLOBALS['wp_actions']['login_form_login']     = 1;
		$GLOBALS['wp_filters']['login_link_separator'] = 1;
		// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited

		$subject = new Login();

		self::assertEquals( $user, $subject->verify( $user, '' ) );
	}

	/**
	 * Test verify() when nto WP login form.
	 */
	public function test_verify_when_NOT_wp_login_form() {
		$user = new WP_User( 1 );

		$subject = new Login();

		self::assertEquals( $user, $subject->verify( $user, '' ) );
	}

	/**
	 * Test verify() when login limit is not exceeded.
	 */
	public function test_verify_NOT_limit_exceeded() {
		$user = new WP_User( 1 );

		$this->prepare_hcaptcha_get_verify_message_html( 'hcaptcha_login_nonce', 'hcaptcha_login' );
		update_option( 'hcaptcha_settings', [ 'login_limit' => 5 ] );
		hcaptcha()->init_hooks();

		$_POST['log'] = 'some login';
		$_POST['pwd'] = 'some password';

		// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wp_actions']['login_init']           = 1;
		$GLOBALS['wp_actions']['login_form_login']     = 1;
		$GLOBALS['wp_filters']['login_link_separator'] = 1;
		// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited

		$subject = new Login();

		self::assertEquals( $user, $subject->verify( $user, '' ) );
	}

	/**
	 * Test verify() not verified.
	 */
	public function test_verify_not_verified() {
		$user     = new WP_User( 1 );
		$expected = new WP_Error( 'invalid_hcaptcha', '<strong>hCaptcha error:</strong> The hCaptcha is invalid.', 400 );

		$this->prepare_hcaptcha_get_verify_message_html( 'hcaptcha_login_nonce', 'hcaptcha_login', false );

		$_POST['log'] = 'some login';
		$_POST['pwd'] = 'some password';

		// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wp_actions']['login_init']           = 1;
		$GLOBALS['wp_actions']['login_form_login']     = 1;
		$GLOBALS['wp_filters']['login_link_separator'] = 1;
		// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited

		$subject = new Login();

		self::assertEquals( $expected, $subject->verify( $user, '' ) );
	}
}
