<?php
/**
 * LoginFormTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\DefaultForms;

use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use WP_Error;
use WP_User;

/**
 * Test login form file.
 */
class LoginFormTest extends HCaptchaWPTestCase {

	/**
	 * Test login_head().
	 */
	public function test_hcap_login_head() {
		$expected = '	<style>
		@media (max-width: 349px) {
			.h-captcha {
				display: flex;
				justify-content: center;
			}
		}
		@media (min-width: 350px) {
			#login {
				width: 350px;
			}
		}
	</style>
	';
		ob_start();

		hcaptcha_login_head();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test hcap_wp_login_form().
	 */
	public function test_hcap_wp_login_form() {
		$expected =
			$this->get_hcap_form() .
			wp_nonce_field( 'hcaptcha_login', 'hcaptcha_login_nonce', true, false );

		ob_start();

		hcap_wp_login_form();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test hcap_verify_login_captcha().
	 */
	public function test_hcap_verify_login_captcha() {
		$user = new WP_User( 1 );

		$this->prepare_hcaptcha_get_verify_message_html( 'hcaptcha_login_nonce', 'hcaptcha_login' );

		self::assertEquals( $user, hcap_verify_login_captcha( $user, '' ) );
	}

	/**
	 * Test hcap_verify_login_captcha() not verified.
	 */
	public function test_hcap_verify_login_captcha_not_verified() {
		$user     = new WP_User( 1 );
		$expected = new WP_Error( 'Invalid Captcha', '<strong>Error</strong>: The Captcha is invalid.' );

		$this->prepare_hcaptcha_get_verify_message_html( 'hcaptcha_login_nonce', 'hcaptcha_login', false );

		self::assertEquals( $expected, hcap_verify_login_captcha( $user, '' ) );
	}
}
