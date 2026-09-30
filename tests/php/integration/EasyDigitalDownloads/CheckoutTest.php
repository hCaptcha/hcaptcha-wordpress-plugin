<?php
/**
 * CheckoutTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\EasyDigitalDownloads;

use HCaptcha\EasyDigitalDownloads\Checkout;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;

/**
 * Test the Easy Digital Downloads checkout integration.
 *
 * @group easy-digital-downloads
 * @group easy-digital-downloads-checkout
 */
class CheckoutTest extends HCaptchaWPTestCase {

	/**
	 * Set up the test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		hcaptcha()->settings()->set( 'honeypot', 'on' );
		hcaptcha()->settings()->set( 'set_min_submit_time', 'on' );
		hcaptcha()->settings()->set( 'easy_digital_downloads_status', 'checkout' );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );
	}

	/**
	 * Test the checkout form contains honeypot fields.
	 *
	 * @return void
	 */
	public function test_add_captcha(): void {
		ob_start();
		( new Checkout() )->add_captcha();
		$output = (string) ob_get_clean();
		$id     = [
			'source'  => [ 'easy-digital-downloads/easy-digital-downloads.php' ],
			'form_id' => 'checkout',
		];

		self::assertStringContainsString( 'h-captcha', $output );
		self::assertStringContainsString( HCaptcha::widget_id_value( $id ), $output );
		self::assertStringContainsString( 'name="hcap_hp_test"', $output );
		self::assertStringContainsString( 'name="hcap_hp_sig"', $output );
		self::assertTrue( wp_script_is( 'hcaptcha-easy-digital-downloads' ) );
	}

	/**
	 * Test a filled honeypot blocks checkout.
	 *
	 * @return void
	 */
	public function test_filled_honeypot_is_rejected(): void {
		$this->prepare_verify_post(
			'hcaptcha_easy_digital_downloads_register_nonce',
			'hcaptcha_easy_digital_downloads_register'
		);

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'easy-digital-downloads/easy-digital-downloads.php' ],
				'form_id' => 'checkout',
			]
		);
		$_POST['hcap_hp_test']                 = 'bot';
		$_POST['action']                       = 'edd_process_checkout';

		self::assertSame(
			[ 'spam' => 'Anti-spam check failed.' ],
			( new Checkout() )->verify( [] )
		);
	}
}
