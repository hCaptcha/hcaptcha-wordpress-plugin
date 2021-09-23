<?php
/**
 * WCRegisterTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\WC;

use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use WP_Error;

/**
 * Test wc-register.php file.
 *
 * @group wc
 */
class WCRegisterTest extends HCaptchaWPTestCase {

	/**
	 * Test hcap_display_wc_register().
	 */
	public function test_hcap_display_wc_register() {
		$expected =
			$this->get_hcap_form() .
			wp_nonce_field( 'hcaptcha_wc_register', 'hcaptcha_wc_register_nonce', true, false );

		ob_start();

		hcap_display_wc_register();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test hcap_verify_wc_register_captcha().
	 */
	public function test_hcap_verify_wc_register_captcha() {
		$validation_error = new WP_Error( 'some error' );

		$this->prepare_hcaptcha_get_verify_message( 'hcaptcha_wc_register_nonce', 'hcaptcha_wc_register' );

		self::assertEquals( $validation_error, hcap_verify_wc_register_captcha( $validation_error ) );
	}

	/**
	 * Test hcap_verify_wc_register_captcha() not verified.
	 */
	public function test_hcap_verify_wc_register_captcha_not_verified() {
		$validation_error = new WP_Error( 'some error' );

		$validation_error->add( 'hcaptcha_error', 'The Captcha is invalid.' );

		$this->prepare_hcaptcha_get_verify_message_html( 'hcaptcha_wc_register_nonce', 'hcaptcha_wc_register', false );

		self::assertEquals( $validation_error, hcap_verify_wc_register_captcha( $validation_error ) );
	}
}
