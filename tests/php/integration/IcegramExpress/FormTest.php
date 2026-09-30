<?php
/**
 * FormTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\IcegramExpress;

use HCaptcha\Helpers\HCaptcha;
use HCaptcha\IcegramExpress\Form;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;

/**
 * Test the Icegram Express form integration.
 *
 * @group icegram-express
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
		hcaptcha()->settings()->set( 'icegram_express_status', 'form' );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );
	}

	/**
	 * Test a filled honeypot blocks subscription validation.
	 *
	 * @return void
	 */
	public function test_filled_honeypot_is_rejected(): void {
		$this->prepare_verify_post( 'hcaptcha_icegram_express_nonce', 'hcaptcha_icegram_express' );

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'email-subscribers/email-subscribers.php' ],
				'form_id' => 1,
			]
		);
		$_POST['hcap_hp_test']                 = 'bot';

		$result = ( new Form() )->verify( [ 'status' => 'SUCCESS' ], [] );

		self::assertSame(
			[
				'status'  => 'ERROR',
				'message' => 'hcaptcha_error',
			],
			$result
		);
		self::assertSame(
			'Anti-spam check failed.',
			apply_filters( 'ig_es_subscription_messages', [] )['hcaptcha_error']
		);
	}

	/**
	 * Test that the integration script is only registered after a form is shown.
	 *
	 * @return void
	 */
	public function test_print_footer_scripts(): void {
		$subject = new Form();

		$subject->print_footer_scripts();
		self::assertFalse( wp_script_is( 'hcaptcha-icegram-express', 'registered' ) );

		$this->set_protected_property( $subject, 'form_shown', true );

		ob_start();
		$subject->print_footer_scripts();
		$output = (string) ob_get_clean();

		self::assertTrue( wp_script_is( 'hcaptcha-icegram-express', 'registered' ) );
		self::assertStringContainsString( 'HCaptchaIcegramExpressObject', $output );
	}
}
