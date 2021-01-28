<?php
/**
 * LostPasswordFormTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\Common;

use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use WP_Error;

/**
 * Test lost-password-form file.
 */
class LostPasswordFormTest extends HCaptchaWPTestCase {

	/**
	 * Test hcaptcha_lost_password_display().
	 */
	public function test_hcaptcha_lost_password_display() {
		$expected =
			$this->get_hcap_form() .
			wp_nonce_field( 'hcaptcha_lost_password', 'hcaptcha_lost_password_nonce', true, false );

		ob_start();

		hcaptcha_lost_password_display();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test hcaptcha_lost_password_verify().
	 */
	public function test_hcaptcha_lost_password_verify() {
		$error = new WP_Error( 'error', 'some error' );

		$this->prepare_hcaptcha_get_verify_message_html( 'hcaptcha_lost_password_nonce', 'hcaptcha_lost_password' );

		self::assertSame( $error, hcaptcha_lost_password_verify( $error ) );
	}

	/**
	 * Test hcaptcha_lost_password_verify() not verified.
	 */
	public function test_hcaptcha_lost_password_verify_not_verified() {
		$error    = new WP_Error( 'error', 'some error' );
		$expected = new WP_Error( 'error', 'some error' );
		$expected->add( 'invalid_captcha', '<strong>Error</strong>: The Captcha is invalid.' );

		$this->prepare_hcaptcha_get_verify_message_html( 'hcaptcha_lost_password_nonce', 'hcaptcha_lost_password', false );

		self::assertEquals( $expected, hcaptcha_lost_password_verify( $error ) );
	}
}
