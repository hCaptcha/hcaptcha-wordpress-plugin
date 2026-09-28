<?php
/**
 * ReturnRequestTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\WCGermanized;

use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use HCaptcha\WCGermanized\ReturnRequest;
use tad\FunctionMocker\FunctionMocker;

/**
 * Test ReturnRequest class.
 *
 * @group woocommerce-germanized
 * @group woocommerce-germanized-return-request
 */
class ReturnRequestTest extends HCaptchaWPTestCase {

	/**
	 * Set up the test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		hcaptcha()->settings()->set( 'honeypot', 'on' );
		hcaptcha()->settings()->set( 'set_min_submit_time', 'on' );
		hcaptcha()->settings()->set( 'woocommerce_germanized_status', 'return_request' );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );
	}

	/**
	 * Test the return-request form contains honeypot fields.
	 *
	 * @return void
	 */
	public function test_add_hcaptcha(): void {
		$subject = new ReturnRequest();

		ob_start();
		$subject->before_submit_button();

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Test fixture.
		echo '<button type="submit">Submit</button>';

		$subject->after_submit_button();
		$output = (string) ob_get_clean();
		$id     = [
			'source'  => [ 'woocommerce-germanized/woocommerce-germanized.php' ],
			'form_id' => 'return_request',
		];

		self::assertStringContainsString( 'h-captcha', $output );
		self::assertStringContainsString( HCaptcha::widget_id_value( $id ), $output );
		self::assertStringContainsString( 'name="hcap_hp_test"', $output );
		self::assertStringContainsString( 'name="hcap_hp_sig"', $output );
	}

	/**
	 * Test a filled honeypot blocks return-request processing.
	 *
	 * @return void
	 */
	public function test_filled_honeypot_is_rejected(): void {
		$this->prepare_verify_request( 'some response' );

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'woocommerce-germanized/woocommerce-germanized.php' ],
				'form_id' => 'return_request',
			]
		);
		$_POST['hcap_hp_test']                 = 'bot';
		$_POST['return_request']               = '1';

		$notice = [];

		FunctionMocker::replace(
			'wc_add_notice',
			static function ( $message, $type ) use ( &$notice ) {
				$notice = [ $message, $type ];
			}
		);

		( new ReturnRequest() )->verify();

		self::assertSame( [ 'Anti-spam check failed.', 'error' ], $notice );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		self::assertArrayNotHasKey( 'return_request', $_POST );
	}
}
