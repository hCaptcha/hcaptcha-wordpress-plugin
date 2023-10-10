<?php
/**
 * RegisterTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\WC;

use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use HCaptcha\WC\Register;
use WP_Error;

/**
 * Test Register class.
 *
 * @group wc-register
 * @group wc
 */
class RegisterTest extends HCaptchaWPTestCase {

	/**
	 * Test constructor and init_hooks().
	 */
	public function test_constructor_and_init_hooks() {
		$subject = new Register();

		self::assertSame(
			10,
			has_action( 'woocommerce_register_form', [ $subject, 'add_captcha' ] )
		);
		self::assertSame(
			10,
			has_action( 'woocommerce_process_registration_errors', [ $subject, 'verify' ] )
		);
	}

	/**
	 * Test add_captcha().
	 */
	public function test_add_captcha() {
		hcaptcha()->init_hooks();

		$expected =
			$this->get_hcap_form() .
			wp_nonce_field( 'hcaptcha_wc_register', 'hcaptcha_wc_register_nonce', true, false );

		$subject = new Register();

		ob_start();

		$subject->add_captcha();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test verify().
	 */
	public function test_verify() {
		$validation_error = new WP_Error( 'some error' );

		$this->prepare_hcaptcha_get_verify_message( 'hcaptcha_wc_register_nonce', 'hcaptcha_wc_register' );

		$subject = new Register();
		self::assertEquals( $validation_error, $subject->verify( $validation_error ) );
	}

	/**
	 * Test verify() not verified.
	 */
	public function test_verify_not_verified() {
		$validation_error = 'some wrong error, to be replaced by WP_Error';
		$expected         = new WP_Error();

		$expected->add( 'hcaptcha_error', 'The hCaptcha is invalid.' );

		$this->prepare_hcaptcha_get_verify_message_html( 'hcaptcha_wc_register_nonce', 'hcaptcha_wc_register', false );

		$subject = new Register();
		self::assertEquals( $expected, $subject->verify( $validation_error ) );
	}
}
