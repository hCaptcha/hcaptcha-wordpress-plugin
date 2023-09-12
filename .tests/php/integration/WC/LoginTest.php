<?php
/**
 * LoginTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\WC;

use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use HCaptcha\WC\Login;
use ReflectionException;
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
	 * @throws ReflectionException ReflectionException.
	 */
	public function tearDown(): void { // phpcs:ignore PHPCompatibility.FunctionDeclarations.NewReturnTypeDeclarations.voidFound
		$this->set_protected_property( hcaptcha(), 'loaded_classes', [] );

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
		$expected->add( 'hcaptcha_error', 'The hCaptcha is invalid.' );

		$this->prepare_hcaptcha_get_verify_message( 'hcaptcha_login_nonce', 'hcaptcha_login', false );

		$subject = new Login();

		self::assertEquals( $expected, apply_filters( 'woocommerce_process_login_errors', $validation_error ) );
	}
}
