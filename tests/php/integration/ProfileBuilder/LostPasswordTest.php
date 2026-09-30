<?php
/**
 * LostPasswordTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\ProfileBuilder;

use HCaptcha\Helpers\HCaptcha;
use HCaptcha\ProfileBuilder\LostPassword;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;

/**
 * Test the Profile Builder lost-password integration.
 *
 * @group profile-builder
 * @group profile-builder-lost-password
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
		hcaptcha()->settings()->set( 'profile_builder_status', 'lost_pass' );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );
	}

	/**
	 * Test the lost-password form contains honeypot fields.
	 *
	 * @return void
	 */
	public function test_add_captcha(): void {
		$output = ( new LostPassword() )->add_captcha( '<form><p class="form-submit">Submit</p></form>' );
		$id     = [
			'source'  => [ 'profile-builder/index.php' ],
			'form_id' => 'lost_password',
		];

		self::assertStringContainsString( 'h-captcha', $output );
		self::assertStringContainsString( HCaptcha::widget_id_value( $id ), $output );
		self::assertStringContainsString( 'name="hcap_hp_test"', $output );
		self::assertStringContainsString( 'name="hcap_hp_sig"', $output );
	}

	/**
	 * Test a filled honeypot blocks password recovery.
	 *
	 * @return void
	 */
	public function test_filled_honeypot_is_rejected(): void {
		$this->prepare_verify_post(
			'hcaptcha_profile_builder_lost_password_nonce',
			'hcaptcha_profile_builder_lost_password'
		);

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'profile-builder/index.php' ],
				'form_id' => 'lost_password',
			]
		);
		$_POST['hcap_hp_test']                 = 'bot';
		$_POST['action']                       = 'recover_password';
		$_POST['username_email']               = 'person@example.com';

		$subject = new LostPassword();

		self::assertFalse( $subject->verify( false, 'wppb-recover-password', [], [] ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput
		self::assertSame( '', $_POST['username_email'] );
		self::assertSame(
			'<p class="wppb-warning">Anti-spam check failed.</p>',
			$subject->recover_password_displayed_message1( 'message' )
		);
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput
		self::assertSame( 'person@example.com', $_POST['username_email'] );
	}
}
