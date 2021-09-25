<?php
/**
 * LoginTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\WP;

use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use HCaptcha\WP\Login;
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

		self::assertSame(
			10,
			has_action( 'login_head', [ $subject, 'login_head' ] )
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
		$user = new WP_User( 1 );

		$this->prepare_hcaptcha_get_verify_message_html( 'hcaptcha_login_nonce', 'hcaptcha_login' );

		$subject = new Login();

		self::assertEquals( $user, $subject->verify( $user, '' ) );
	}

	/**
	 * Test verify() not verified.
	 */
	public function test_verify_not_verified() {
		$user     = new WP_User( 1 );
		$expected = new WP_Error( 'invalid_hcaptcha', 'Invalid Captcha', 400 );

		$this->prepare_hcaptcha_get_verify_message_html( 'hcaptcha_login_nonce', 'hcaptcha_login', false );

		$subject = new Login();

		self::assertEquals( $expected, $subject->verify( $user, '' ) );
	}

	/**
	 * Test login_head().
	 */
	public function test_hcap_login_head() {
		$expected = '		<style>
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

		$subject = new Login();

		ob_start();

		$subject->login_head();

		self::assertSame( $expected, ob_get_clean() );
	}
}
