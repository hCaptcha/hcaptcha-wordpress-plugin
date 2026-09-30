<?php
/**
 * FormTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\Asgaros;

use HCaptcha\Asgaros\Form;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use Mockery;

/**
 * Test the Asgaros Forum integration.
 *
 * @group asgaros
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
		hcaptcha()->settings()->set( 'asgaros_status', 'form' );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );
	}

	/**
	 * Tear down the test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		unset( $GLOBALS['asgarosforum'] );

		parent::tearDown();
	}

	/**
	 * Test that the rendered form contains honeypot fields.
	 *
	 * @return void
	 */
	public function test_form_contains_honeypot(): void {
		$html   = '<form><div class="editor-row editor-row-submit"></div></form>';
		$output = ( new Form() )->add_captcha( $html, 'forum', [ 'id' => 7 ], [] );

		self::assertStringContainsString( 'name="hcap_hp_test"', $output );
		self::assertStringContainsString( 'name="hcap_hp_sig"', $output );
		self::assertStringContainsString( 'name="hcaptcha-widget-id"', $output );
	}

	/**
	 * Test that a filled honeypot blocks forum posting.
	 *
	 * @return void
	 */
	public function test_filled_honeypot_is_rejected(): void {
		global $asgarosforum;

		$this->prepare_verify_post( 'hcaptcha_asgaros_new_topic_nonce', 'hcaptcha_asgaros_new_topic' );

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'asgaros-forum/asgaros-forum.php' ],
				'form_id' => 7,
			]
		);
		$_POST['hcap_hp_test']                 = 'bot';

		$asgarosforum = Mockery::mock();
		$asgarosforum->shouldReceive( 'add_notice' )->once()->with( 'Anti-spam check failed.' );

		self::assertFalse( ( new Form() )->verify( true ) );
	}
}
