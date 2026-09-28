<?php
/**
 * FormTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\SupportCandy;

use HCaptcha\Helpers\API;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\SupportCandy\Form;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;

/**
 * Test the Support Candy integration.
 *
 * @group support-candy
 */
class FormTest extends HCaptchaWPTestCase {

	/**
	 * Set up the test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		hcaptcha()->settings()->set( 'honeypot', 'on' );
		hcaptcha()->settings()->set( 'set_min_submit_time', 'on' );
		hcaptcha()->settings()->set( 'supportcandy_status', 'form' );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );
	}

	/**
	 * Test that the rendered form contains honeypot fields.
	 *
	 * @return void
	 */
	public function test_form_contains_honeypot(): void {
		ob_start();
		( new Form() )->add_captcha();
		$output = (string) ob_get_clean();

		self::assertStringContainsString( 'name="hcap_hp_test"', $output );
		self::assertStringContainsString( 'name="hcap_hp_sig"', $output );
		self::assertStringContainsString( 'name="hcaptcha-widget-id"', $output );
	}

	/**
	 * Test that a filled honeypot blocks ticket creation.
	 *
	 * @return void
	 */
	public function test_filled_honeypot_is_rejected(): void {
		$this->prepare_verify_post(
			'hcaptcha_support_candy_new_topic_nonce',
			'hcaptcha_support_candy_new_topic'
		);

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'supportcandy/supportcandy.php' ],
				'form_id' => 'form',
			]
		);
		$_POST['hcap_hp_test']                 = 'bot';

		self::assertSame(
			'Anti-spam check failed.',
			API::verify_post(
				'hcaptcha_support_candy_new_topic_nonce',
				'hcaptcha_support_candy_new_topic'
			)
		);
	}

	/**
	 * Test that scripts are printed for Support Candy ticket shortcodes.
	 *
	 * @return void
	 */
	public function test_print_hcaptcha_scripts_for_ticket_shortcodes(): void {
		$subject = new Form();

		self::assertFalse( $subject->print_hcaptcha_scripts( false ) );
		self::assertSame( 'output', $subject->support_candy_shortcode_tag( 'output', 'other', [], [] ) );
		self::assertFalse( $subject->print_hcaptcha_scripts( false ) );

		$subject->support_candy_shortcode_tag( 'output', 'supportcandy', [], [] );

		self::assertTrue( $subject->print_hcaptcha_scripts( false ) );

		$subject = new Form();

		$subject->support_candy_shortcode_tag( 'output', 'wpsc_create_ticket', [], [] );

		self::assertTrue( $subject->print_hcaptcha_scripts( false ) );
	}
}
