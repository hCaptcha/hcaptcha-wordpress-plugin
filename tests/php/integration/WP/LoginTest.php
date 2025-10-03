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
use function PHPUnit\Framework\assertSame;

/**
 * Class LoginTest.
 *
 * @group wp-login
 * @group wp
 */
class LoginTest extends HCaptchaWPTestCase {

	/**
	 * Teardown test.
	 */
	public function tearDown(): void {
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
	public function test_constructor_and_init_hooks(): void {
		$subject = new Login();

		self::assertSame( 10, has_action( 'hcap_signature', [ $subject, 'display_signature' ] ) );
		self::assertSame( PHP_INT_MAX, has_action( 'login_form', [ $subject, 'display_signature' ] ) );
		self::assertSame( PHP_INT_MAX, has_filter( 'login_form_middle', [ $subject, 'add_signature' ] ) );
		self::assertSame( PHP_INT_MAX, has_filter( 'wp_authenticate_user', [ $subject, 'check_signature' ] ) );
		self::assertSame( 100, has_filter( 'authenticate', [ $subject, 'hide_login_error' ] ) );

		self::assertSame( 10, has_action( 'wp_login', [ $subject, 'login' ] ) );
		self::assertSame( 10, has_action( 'wp_login_failed', [ $subject, 'login_failed' ] ) );

		self::assertSame( 0, has_action( 'hcap_delay_api', [ $subject, 'delay_api' ] ) );

		self::assertSame( 10, has_action( 'login_form', [ $subject, 'add_captcha' ] ) );
	}

	/**
	 * Test display_signature().
	 *
	 * @return void
	 */
	public function test_display_signature(): void {
		$subject = new Login();

		$expected = $this->get_signature( get_class( $subject ) );

		ob_start();
		$subject->display_signature();
		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test add_signature().
	 *
	 * @return void
	 */
	public function test_add_signature(): void {
		$content = 'some content';

		$subject = new Login();

		$expected = $content . $this->get_signature( get_class( $subject ) );

		self::assertSame( $expected, $subject->add_signature( $content, [] ) );
	}

	/**
	 * Test check_signature().
	 *
	 * @return void
	 */
	public function test_check_signature(): void {
		$user     = wp_get_current_user();
		$password = 'some password';

		FunctionMocker::replace( '\HCaptcha\Helpers\HCaptcha::check_signature' );

		$this->prepare_verify_post_html( 'hcaptcha_login_nonce', 'hcaptcha_login' );

		$subject = new Login();

		// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wp_actions']['login_init']           = 1;
		$GLOBALS['wp_actions']['login_form_login']     = 1;
		$GLOBALS['wp_filters']['login_link_separator'] = 1;
		// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited

		self::assertSame( $user, $subject->check_signature( $user, $password ) );
	}

	/**
	 * Test check_signature() when NOT wp login form.
	 *
	 * @return void
	 */
	public function test_check_signature_when_NOT_wp_login_form(): void {
		$user     = wp_get_current_user();
		$password = 'some password';

		$subject = new Login();

		self::assertSame( $user, $subject->check_signature( $user, $password ) );
	}

	/**
	 * Test check_signature() when good signature.
	 *
	 * @return void
	 */
	public function test_check_signature_when_good_signature(): void {
		$user     = wp_get_current_user();
		$password = 'some password';

		FunctionMocker::replace( '\HCaptcha\Helpers\HCaptcha::check_signature', true );

		$subject = new Login();

		// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wp_actions']['login_init']           = 1;
		$GLOBALS['wp_actions']['login_form_login']     = 1;
		$GLOBALS['wp_filters']['login_link_separator'] = 1;
		// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited

		self::assertSame( $user, $subject->check_signature( $user, $password ) );
	}

	/**
	 * Test check_signature() when bad signature.
	 *
	 * @return void
	 */
	public function test_check_signature_when_bad_signature(): void {
		$user     = wp_get_current_user();
		$password = 'some password';

		$subject = new Login();

		// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wp_actions']['login_init']           = 1;
		$GLOBALS['wp_actions']['login_form_login']     = 1;
		$GLOBALS['wp_filters']['login_link_separator'] = 1;
		// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited

		$expected = new WP_Error( 'bad-signature', 'Bad hCaptcha signature!', 400 );

		self::assertSame( wp_json_encode( $expected ), wp_json_encode( $subject->check_signature( $user, $password ) ) );
	}

	/**
	 * Test hide_login_error().
	 *
	 * @return void
	 */
	public function test_hide_login_error(): void {
		$user     = new WP_User();
		$username = 'some username';
		$password = 'some password';
		$subject  = new Login();

		// Not a login error.
		self::assertSame( $user, $subject->hide_login_error( $user, $username, $password ) );

		$user = new WP_Error();

		// Some login error.
		self::assertSame( $user, $subject->hide_login_error( $user, $username, $password ) );

		update_option( 'hcaptcha_settings', [ 'hide_login_errors' => false ] );
		hcaptcha()->init_hooks();

		// The setting 'hide_login_errors' is off.
		self::assertSame( $user, $subject->hide_login_error( $user, $username, $password ) );

		update_option( 'hcaptcha_settings', [ 'hide_login_errors' => true ] );
		hcaptcha()->init_hooks();

		$user = new WP_Error( 'empty_username' );

		// Ignore empty_username error code.
		self::assertSame( $user, $subject->hide_login_error( $user, $username, $password ) );

		$user = new WP_Error( 'empty_password' );

		// Ignore empty_password error code.
		self::assertSame( $user, $subject->hide_login_error( $user, $username, $password ) );

		$user     = new WP_Error( 'some_error', 'Some error message.', 400 );
		$expected = new WP_Error( 'login_error', 'Login failed.' );

		// Remove non-hCaptcha messages.
		self::assertEquals( $expected, $subject->hide_login_error( $user, $username, $password ) );

		$user = new WP_Error( 'some_error', 'Some error message.', 400 );

		$user->add( 'fail', 'The hCaptcha is invalid.' );

		$expected = new WP_Error( 'fail', 'The hCaptcha is invalid.' );

		// Remove non-hCaptcha messages.
		self::assertEquals( $expected, $subject->hide_login_error( $user, $username, $password ) );
	}

	/**
	 * Test login().
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_login(): void {
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
	public function test_login_failed(): void {
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

		$subject->login_failed( $username );

		self::assertSame( $expected_login_data, $this->get_protected_property( $subject, 'login_data' ) );
		self::assertSame( $expected_login_data, get_option( LoginBase::LOGIN_DATA ) );
	}

	/**
	 * Test add_captcha().
	 */
	public function test_add_captcha(): void {
		// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wp_actions']['login_init']           = 1;
		$GLOBALS['wp_actions']['login_form_login']     = 1;
		$GLOBALS['wp_filters']['login_link_separator'] = 1;
		// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited

		$args     = [
			'action' => 'hcaptcha_login',
			'name'   => 'hcaptcha_login_nonce',
			'id'     => [
				'source'  => [ 'WordPress' ],
				'form_id' => 'login',
			],
		];
		$expected = $this->get_hcap_form( $args );

		$subject = new Login();

		ob_start();

		$subject->add_captcha();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test add_captcha() when not WP login form.
	 */
	public function test_add_captcha_when_NOT_wp_login_form(): void {
		$expected = '';

		$subject = new Login();

		ob_start();

		$subject->add_captcha();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test add_captcha() when not login limit exceeded.
	 */
	public function test_add_captcha_when_NOT_login_limit_exceeded(): void {
		$expected = '';

		$subject = new Login();

		add_filter( 'hcap_login_limit_exceeded', '__return_false' );

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
	public function test_verify(): void {
		$user = new WP_User( 1 );

		$this->prepare_verify_post_html( 'hcaptcha_login_nonce', 'hcaptcha_login' );

		$_POST['log'] = 'some login';
		$_POST['pwd'] = 'some password';

		// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wp_actions']['login_init']           = 1;
		$GLOBALS['wp_actions']['login_form_login']     = 1;
		$GLOBALS['wp_filters']['login_link_separator'] = 1;
		// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited

		$subject = new Login();

		self::assertEquals( $user, $subject->login_base_verify( $user, '' ) );
	}

	/**
	 * Test verify() when login limit is not exceeded.
	 */
	public function test_verify_NOT_limit_exceeded(): void {
		$user = new WP_User( 1 );

		$this->prepare_verify_post_html( 'hcaptcha_login_nonce', 'hcaptcha_login' );
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

		self::assertEquals( $user, $subject->login_base_verify( $user, '' ) );
	}

	/**
	 * Test verify() not verified.
	 */
	public function test_verify_not_verified(): void {
		$user     = new WP_User( 1 );
		$expected = new WP_Error( 'fail', 'The hCaptcha is invalid.', 400 );

		$this->prepare_verify_post_html( 'hcaptcha_login_nonce', 'hcaptcha_login', false );

		$_POST['log'] = 'some login';
		$_POST['pwd'] = 'some password';

		// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wp_actions']['login_init']           = 1;
		$GLOBALS['wp_actions']['login_form_login']     = 1;
		$GLOBALS['wp_filters']['login_link_separator'] = 1;
		// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited

		$subject = new Login();

		self::assertEquals( $expected, $subject->login_base_verify( $user, '' ) );
	}

	/**
	 * Get signature.
	 *
	 * @param string $class_name Class name.
	 *
	 * @return string
	 */
	private function get_signature( string $class_name ): string {
		$const = HCaptcha::HCAPTCHA_SIGNATURE;

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$name = $const . '-' . base64_encode( $class_name );

		return '		<input
				type="hidden"
				class="' . $const . '"
				name="' . $name . '"
				value="' . $this->get_encoded_signature( [ 'WordPress' ], 'login', false ) . '">
		';
	}
}
