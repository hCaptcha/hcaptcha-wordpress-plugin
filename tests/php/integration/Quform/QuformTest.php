<?php
/**
 * QuformTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Integration\Quform;

use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Quform\Quform;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use Mockery;
use Quform_Form;

/**
 * Test the Quform integration.
 *
 * @group quform
 */
class QuformTest extends HCaptchaWPTestCase {

	/**
	 * Set up the test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		hcaptcha()->settings()->set( 'honeypot', 'on' );
		hcaptcha()->settings()->set( 'set_min_submit_time', 'on' );
		hcaptcha()->settings()->set( 'quform_status', 'form' );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );
	}

	/**
	 * Test that the rendered form contains honeypot fields.
	 *
	 * @return void
	 */
	public function test_add_hcaptcha_contains_honeypot(): void {
		$output = '<form><div class="quform-element quform-element-submit"></div></form>';
		$output = ( new Quform() )->add_hcaptcha( $output, 'quform', [ 'id' => 1 ], [] );

		self::assertStringContainsString( 'name="hcap_hp_test"', $output );
		self::assertStringContainsString( 'name="hcap_hp_sig"', $output );
		self::assertStringContainsString( 'name="hcaptcha-widget-id"', $output );
	}

	/**
	 * Test that a filled honeypot blocks Quform processing.
	 *
	 * @return void
	 */
	public function test_filled_honeypot_is_rejected(): void {
		$this->prepare_verify_post( 'hcaptcha_quform_nonce', 'hcaptcha_quform' );

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'quform/quform.php' ],
				'form_id' => 1,
			]
		);
		$_POST['hcap_hp_test']                 = 'bot';

		$form = Mockery::mock( Quform_Form::class );
		$form->shouldReceive( 'getId' )->once()->andReturn( 1 );
		$form->shouldReceive( 'getValues' )->once()->andReturn( [] );
		$form->shouldReceive( 'getCurrentPage' )->once()->andReturn( null );

		$result = ( new Quform() )->verify( [], $form );

		self::assertSame( 'error', $result['type'] );
		self::assertSame( 'Anti-spam check failed.', $result['errors']['9999_9999'] );
	}
}
