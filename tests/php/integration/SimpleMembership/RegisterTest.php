<?php
/**
 * RegisterTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\SimpleMembership;

use HCaptcha\Helpers\HCaptcha;
use HCaptcha\SimpleMembership\Register;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;

/**
 * Test the Simple Membership registration integration.
 *
 * @group simple-membership
 * @group simple-membership-register
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
		hcaptcha()->settings()->set( 'simple_membership_status', 'register' );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );
	}

	/**
	 * Test the registration form contains honeypot fields.
	 *
	 * @return void
	 */
	public function test_add_hcaptcha(): void {
		$output = ( new Register() )->add_hcaptcha(
			'<form><div class="swpm-form-row swpm-submit-section swpm-registration-submit-section"></div></form>',
			'swpm_registration_form',
			[],
			[]
		);
		$id     = [
			'source'  => [ 'simple-membership/simple-wp-membership.php' ],
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
			'hcaptcha_simple_membership_register_nonce',
			'hcaptcha_simple_membership_register'
		);

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'simple-membership/simple-wp-membership.php' ],
				'form_id' => 'register',
			]
		);
		$_POST['hcap_hp_test']                 = 'bot';

		self::assertSame( 'Anti-spam check failed.', ( new Register() )->verify() );
	}
}
