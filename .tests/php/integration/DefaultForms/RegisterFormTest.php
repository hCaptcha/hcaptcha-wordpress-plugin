<?php
/**
 * RegisterFormTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\DefaultForms;

use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use WP_Error;

/**
 * Test register form file.
 */
class RegisterFormTest extends HCaptchaWPTestCase {

	/**
	 * Test hcap_wp_register_form().
	 */
	public function test_hcap_wp_register_form() {
		$expected =
			$this->get_hcap_form() .
			wp_nonce_field( 'hcaptcha_registration', 'hcaptcha_registration_nonce', true, false );

		ob_start();

		hcap_wp_register_form();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test hcap_verify_register_captcha().
	 */
	public function test_hcap_verify_register_captcha() {
		$errors = new WP_Error( 'some error' );

		$this->prepare_hcaptcha_get_verify_message_html( 'hcaptcha_registration_nonce', 'hcaptcha_registration' );

		self::assertEquals( $errors, hcap_verify_register_captcha( $errors, '', '' ) );
	}

	/**
	 * Test hcap_verify_register_captcha() not verified.
	 */
	public function test_hcap_verify_register_captcha_not_verified() {
		$errors = new WP_Error( 'some error' );

		$errors->add( 'invalid_captcha', '<strong>Error</strong>: The Captcha is invalid.' );

		$this->prepare_hcaptcha_get_verify_message_html( 'hcaptcha_registration_nonce', 'hcaptcha_registration', false );

		self::assertEquals( $errors, hcap_verify_register_captcha( $errors, '', '' ) );
	}
}
