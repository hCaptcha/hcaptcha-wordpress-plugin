<?php
/**
 * RegisterTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\LoginSignupPopup;

use HCaptcha\Helpers\HCaptcha;
use HCaptcha\LoginSignupPopup\Register;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use WP_Error;

/**
 * Test the Login/Signup Popup registration integration.
 *
 * @group login-signup-popup
 * @group login-signup-popup-register
 */
class RegisterTest extends HCaptchaWPTestCase {

	/**
	 * Set up the test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		hcaptcha()->settings()->set( 'honeypot', 'on' );
		hcaptcha()->settings()->set( 'set_min_submit_time', 'on' );
		hcaptcha()->settings()->set( 'login_signup_popup_status', 'register' );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );
	}

	/**
	 * Test the registration form contains honeypot fields.
	 *
	 * @return void
	 */
	public function test_add_captcha(): void {
		$subject = new Register();

		ob_start();
		$subject->form_start( 'register', [] );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Test fixture.
		echo '<form><button type="submit">Register</button></form>';

		$subject->add_login_signup_popup_hcaptcha( 'register', [] );
		$output = (string) ob_get_clean();
		$id     = [
			'source'  => [ 'easy-login-woocommerce/xoo-el-main.php' ],
			'form_id' => 'register',
		];

		self::assertStringContainsString( 'h-captcha', $output );
		self::assertStringContainsString( HCaptcha::widget_id_value( $id ), $output );
		self::assertStringContainsString( 'name="hcap_hp_test"', $output );
		self::assertStringContainsString( 'name="hcap_hp_sig"', $output );
	}

	/**
	 * Test a filled honeypot blocks registration.
	 *
	 * @return void
	 */
	public function test_filled_honeypot_is_rejected(): void {
		$this->prepare_verify_post(
			'hcaptcha_login_signup_popup_register_nonce',
			'hcaptcha_login_signup_popup_register'
		);

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'easy-login-woocommerce/xoo-el-main.php' ],
				'form_id' => 'register',
			]
		);
		$_POST['hcap_hp_test']                 = 'bot';

		$result = ( new Register() )->verify( new WP_Error(), 'user', 'password', 'person@example.com' );

		self::assertSame( 'Anti-spam check failed.', $result->get_error_message( 'spam' ) );
	}
}
