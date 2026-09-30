<?php
/**
 * LoginTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\MemberPress;

use HCaptcha\Helpers\HCaptcha;
use HCaptcha\MemberPress\Login;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use WP_Error;
use WP_User;

/**
 * Test the MemberPress login integration.
 *
 * @group memberpress
 */
class LoginTest extends HCaptchaWPTestCase {

	/**
	 * Set up the test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		hcaptcha()->settings()->set( 'honeypot', 'on' );
		hcaptcha()->settings()->set( 'set_min_submit_time', 'on' );
		hcaptcha()->settings()->set( 'memberpress_status', [ 'login', 'register' ] );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );
	}

	/**
	 * Test that the rendered login form contains honeypot fields.
	 *
	 * @return void
	 */
	public function test_form_contains_honeypot(): void {
		ob_start();
		( new Login() )->add_captcha();
		$output = (string) ob_get_clean();

		self::assertStringContainsString( 'name="hcaptcha-widget-id"', $output );
		self::assertStringContainsString( 'name="hcap_hp_test"', $output );
		self::assertStringContainsString( 'name="hcap_hp_sig"', $output );
	}

	/**
	 * Test that a filled honeypot blocks login.
	 *
	 * @return void
	 */
	public function test_filled_honeypot_is_rejected(): void {
		$this->prepare_verify_post(
			'hcaptcha_memberpress_login_nonce',
			'hcaptcha_memberpress_login'
		);

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'memberpress/memberpress.php' ],
				'form_id' => 'login',
			]
		);
		$_POST['hcap_hp_test']                 = 'bot';

		// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
		apply_filters( 'mepr-validate-login', [] );

		$result = ( new Login() )->verify( new WP_User( 1 ), 'password' );

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame(
			'<strong>hCaptcha error:</strong> Anti-spam check failed.',
			$result->get_error_message()
		);
	}
}
