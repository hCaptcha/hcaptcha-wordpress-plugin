<?php
/**
 * RegisterTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\ProfileBuilder;

use HCaptcha\Helpers\HCaptcha;
use HCaptcha\ProfileBuilder\Register;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;

/**
 * Test the Profile Builder registration integration.
 *
 * @group profile-builder
 * @group profile-builder-register
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
		hcaptcha()->settings()->set( 'profile_builder_status', 'register' );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );
	}

	/**
	 * Test the registration form contains honeypot fields.
	 *
	 * @return void
	 */
	public function test_add_captcha(): void {
		$output = ( new Register() )->add_captcha( '<form><p class="form-submit">Submit</p></form>' );
		$id     = [
			'source'  => [ 'profile-builder/index.php' ],
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
		$this->prepare_verify_post( 'hcaptcha_profile_builder_register_nonce', 'hcaptcha_profile_builder_register' );

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'profile-builder/index.php' ],
				'form_id' => 'register',
			]
		);
		$_POST['hcap_hp_test']                 = 'bot';

		$result = ( new Register() )->verify( [], [], [], 'register' );

		self::assertSame( [ 'Anti-spam check failed.' ], $result );
	}
}
