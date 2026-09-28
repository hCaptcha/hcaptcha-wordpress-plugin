<?php
/**
 * RegisterTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\Affiliates;

use HCaptcha\Affiliates\Register;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;

/**
 * Test Register class.
 *
 * @group affiliates
 * @group affiliates-register
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
		hcaptcha()->settings()->set( 'affiliates_status', [ 'login', 'register' ] );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );
	}

	/**
	 * Test initial registration form rendering.
	 *
	 * @return void
	 */
	public function test_initial_render(): void {
		$subject = new Register();

		ob_start();
		$subject->before_section( 'registration' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<form><input type="submit" name="affiliates-registration-submit"></form>';
		$subject->after_section( 'registration' );
		$output = (string) ob_get_clean();

		self::assertStringContainsString( 'name="hcaptcha-widget-id"', $output );
		self::assertStringContainsString( 'name="hcap_hp_test"', $output );
		self::assertStringContainsString( 'name="hcap_hp_sig"', $output );
		self::assertStringNotContainsString( '<div class="error">', $output );
	}

	/**
	 * Test that a filled honeypot blocks registration.
	 *
	 * @return void
	 */
	public function test_filled_honeypot_is_rejected(): void {
		$this->prepare_verify_post( 'hcaptcha_registration_nonce', 'hcaptcha_registration' );

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ]   = HCaptcha::widget_id_value(
			[
				'source'  => [ 'affiliates/affiliates.php' ],
				'form_id' => 'register',
			]
		);
		$_POST['hcap_hp_test']                   = 'bot';
		$_POST['affiliates-registration-submit'] = 'Register';

		$subject = new Register();

		self::assertTrue( $subject->verify( false ) );

		ob_start();
		$subject->before_section( 'registration' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<form><input type="submit" name="affiliates-registration-submit"></form>';
		$subject->after_section( 'registration' );
		$output = (string) ob_get_clean();

		self::assertStringContainsString( 'Anti-spam check failed.', $output );
	}
}
