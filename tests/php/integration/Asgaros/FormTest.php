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
	 * Ignore other shortcodes and print the forum editor spacing rules.
	 */
	public function test_other_shortcode_and_inline_styles(): void {
		$subject = new Form();

		self::assertSame( 'other', $subject->add_captcha( 'other', 'other', [], [] ) );

		ob_start();
		$subject->print_inline_styles();

		self::assertStringContainsString( '#af-wrapper div.editor-row.editor-row-hcaptcha', ob_get_clean() );
	}

	/**
	 * Allow a forum post after successful verification.
	 */
	public function test_valid_forum_proof_is_accepted(): void {
		global $asgarosforum;

		$this->prepare_verify_post( 'hcaptcha_asgaros_new_topic_nonce', 'hcaptcha_asgaros_new_topic' );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'asgaros-forum/asgaros-forum.php' ],
				'form_id' => 7,
			]
		);
		$asgarosforum                          = Mockery::mock();
		$asgarosforum->current_forum           = 7;
		$asgarosforum->shouldNotReceive( 'add_notice' );

		self::assertTrue( ( new Form() )->verify( true ) );
	}

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
		global $asgarosforum;

		$asgarosforum                = Mockery::mock();
		$asgarosforum->current_forum = 7;

		$html   = '<form><div class="editor-row editor-row-submit"></div></form>';
		$output = ( new Form() )->add_captcha( $html, 'forum', [ 'id' => 7 ], [] );

		self::assertStringContainsString( 'name="hcap_hp_test"', $output );
		self::assertStringContainsString( 'name="hcap_hp_sig"', $output );
		self::assertStringContainsString( 'name="hcaptcha-widget-id"', $output );
		self::assertStringContainsString(
			HCaptcha::widget_id_value(
				[
					'source'  => [ 'asgaros-forum/asgaros-forum.php' ],
					'form_id' => 7,
				]
			),
			$output
		);
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

		$asgarosforum                = Mockery::mock();
		$asgarosforum->current_forum = 7;
		$asgarosforum->shouldReceive( 'add_notice' )->once()->with( 'Anti-spam check failed.' );

		self::assertFalse( ( new Form() )->verify( true ) );
	}

	/**
	 * A widget rendered for another forum is rejected.
	 */
	public function test_foreign_forum_widget_is_rejected(): void {
		global $asgarosforum;

		$this->prepare_verify_post( 'hcaptcha_asgaros_new_topic_nonce', 'hcaptcha_asgaros_new_topic' );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'asgaros-forum/asgaros-forum.php' ],
				'form_id' => 8,
			]
		);

		$asgarosforum                = Mockery::mock();
		$asgarosforum->current_forum = 7;
		$asgarosforum->shouldReceive( 'add_notice' )->once()->with( hcap_get_error_messages()['bad-signature'] );

		self::assertFalse( ( new Form() )->verify( true ) );
	}
}
