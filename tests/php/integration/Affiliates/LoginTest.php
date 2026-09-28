<?php
/**
 * LoginTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\Affiliates;

use HCaptcha\Affiliates\Login;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;

/**
 * Test the Affiliates login integration.
 *
 * @group affiliates
 * @group affiliates-login
 */
class LoginTest extends HCaptchaWPTestCase {

	/**
	 * Test that the rendered login form contains honeypot fields.
	 *
	 * @return void
	 */
	public function test_form_contains_honeypot(): void {
		hcaptcha()->settings()->set( 'honeypot', 'on' );
		hcaptcha()->settings()->set( 'set_min_submit_time', 'on' );
		hcaptcha()->settings()->set( 'affiliates_status', [ 'login', 'register' ] );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );

		$subject = new Login();

		do_action( 'affiliates_dashboard_before_section', 'login' );

		$output = $subject->add_affiliates_captcha( '', [] );

		self::assertStringContainsString( 'name="hcaptcha-widget-id"', $output );
		self::assertStringContainsString( 'name="hcap_hp_test"', $output );
		self::assertStringContainsString( 'name="hcap_hp_sig"', $output );
	}
}
