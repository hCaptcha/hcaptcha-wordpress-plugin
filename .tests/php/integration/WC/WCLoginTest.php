<?php
/**
 * WCLoginTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\WC;

use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use WP_Error;

/**
 * Test wc-login.php file.
 */
class WCLoginTest extends HCaptchaWPTestCase {

	/**
	 * Test hcap_display_wc_login().
	 */
	public function test_hcap_display_wc_login() {
		$expected =
			$this->get_hcap_form() .
			wp_nonce_field( 'hcaptcha_login', 'hcaptcha_login', true, false );

		ob_start();

		hcap_display_wc_login();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test hcap_verify_wc_login_captcha().
	 */
	public function test_hcap_verify_wc_login_captcha() {
		$validation_error = new WP_Error();

		$this->prepare_hcaptcha_get_verify_message( 'hcaptcha_login_nonce', 'hcaptcha_login' );

		self::assertEquals( $validation_error, hcap_verify_wc_login_captcha( $validation_error ) );
	}

	/**
	 * Test hcap_verify_login_captcha() not verified.
	 */
	public function test_hcap_verify_login_captcha_not_verified() {
		$validation_error = new WP_Error();
		$expected         = new WP_Error();
		$expected->add( 'hcaptcha_error', 'The Captcha is invalid.' );

		$this->prepare_hcaptcha_get_verify_message( 'hcaptcha_login_nonce', 'hcaptcha_login', false );

		self::assertEquals( $expected, hcap_verify_wc_login_captcha( $validation_error ) );
	}
}
