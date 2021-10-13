<?php
/**
 * LoginTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\WC;

use HCaptcha\Main;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use HCaptcha\WC\Login;
use WP_Error;

/**
 * Test Login class.
 *
 * @group wc-login
 * @group wc
 */
class LoginTest extends HCaptchaWPTestCase {

	/**
	 * Tear down the test.
	 *
	 * @noinspection PhpLanguageLevelInspection
	 * @noinspection PhpUndefinedClassInspection
	 */
	public function tearDown(): void {
		global $hcaptcha_wordpress_plugin;

		$hcaptcha_wordpress_plugin->loaded_classes = [];

		parent::tearDown();
	}

	/**
	 * Test constructor and init_hooks().
	 */
	public function test_constructor_and_init_hooks() {
		$subject = new Login();

		self::assertSame(
			10,
			has_action( 'woocommerce_login_form', [ $subject, 'add_captcha' ] )
		);
		self::assertSame(
			10,
			has_action( 'woocommerce_process_login_errors', [ $subject, 'verify' ] )
		);
		self::assertSame(
			10,
			has_filter(
				'woocommerce_login_credentials',
				[ $subject, 'remove_filter_wp_authenticate_user' ]
			)
		);
	}

	/**
	 * Test add_captcha().
	 */
	public function test_add_captcha() {
		$expected =
			$this->get_hcap_form() .
			wp_nonce_field( 'hcaptcha_login', 'hcaptcha_login_nonce', true, false );

		$subject = new Login();

		ob_start();

		$subject->add_captcha();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test verify().
	 */
	public function test_verify() {
		$validation_error = new WP_Error();

		$this->prepare_hcaptcha_get_verify_message( 'hcaptcha_login_nonce', 'hcaptcha_login' );

		$subject = new Login();

		self::assertEquals( $validation_error, $subject->verify( $validation_error ) );
	}

	/**
	 * Test verify() not verified.
	 */
	public function test_verify_not_verified() {
		$validation_error = new WP_Error();
		$expected         = new WP_Error();
		$expected->add( 'hcaptcha_error', 'The Captcha is invalid.' );

		$this->prepare_hcaptcha_get_verify_message( 'hcaptcha_login_nonce', 'hcaptcha_login', false );

		$subject = new Login();

		self::assertEquals( $expected, $subject->verify( $validation_error ) );
	}

	/**
	 * Test remove_filter_wp_authenticate_user().
	 *
	 * Must be after test_load_modules().
	 */
	public function test_remove_filter_wp_authenticate_user() {
		global $hcaptcha_wordpress_plugin;

		$wp_login_class = \HCaptcha\WP\Login::class;
		$wp_login       = new $wp_login_class();

		$hcaptcha_wordpress_plugin->loaded_classes[ $wp_login_class ] = $wp_login;

		new Login();

		add_filter( 'wp_authenticate_user', [ $wp_login, 'verify' ], 10, 2 );

		self::assertSame(
			10,
			has_filter( 'wp_authenticate_user', [ $wp_login, 'verify' ] )
		);

		$credentials = [
			'user_login'    => 'KAGG',
			'user_password' => 'Design',
			'remember'      => false,
		];
		self::assertSame( $credentials, apply_filters( 'woocommerce_login_credentials', $credentials ) );

		self::assertFalse(
			has_filter( 'wp_authenticate_user', [ $wp_login, 'verify' ] )
		);
	}
}
