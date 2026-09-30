<?php
/**
 * LostPasswordTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\LearnDash;

use HCaptcha\Helpers\HCaptcha;
use HCaptcha\LearnDash\LostPassword;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;

/**
 * Test LostPassword class.
 *
 * @group learn-dash
 * @group learn-dash-lost-password
 */
class LostPasswordTest extends HCaptchaWPTestCase {

	/**
	 * Set up the test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		hcaptcha()->settings()->set( 'honeypot', 'on' );
		hcaptcha()->settings()->set( 'set_min_submit_time', 'on' );
		hcaptcha()->settings()->set( 'learn_dash_status', 'lost_pass' );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );
	}

	/**
	 * Test hCaptcha output contains honeypot fields.
	 *
	 * @return void
	 */
	public function test_add_captcha(): void {
		$subject = new LostPassword();
		$output  = $subject->add_captcha(
			'<form><input type="submit" value="Reset"></form>',
			'ld_reset_password',
			[],
			[]
		);
		$id      = [
			'source'  => [ 'sfwd-lms/sfwd_lms.php' ],
			'form_id' => 'lost_password',
		];

		self::assertStringContainsString( 'h-captcha', $output );
		self::assertStringContainsString( HCaptcha::widget_id_value( $id ), $output );
		self::assertStringContainsString( 'name="hcap_hp_test"', $output );
		self::assertStringContainsString( 'name="hcap_hp_sig"', $output );
	}
}
