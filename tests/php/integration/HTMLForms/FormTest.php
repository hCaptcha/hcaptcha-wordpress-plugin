<?php
/**
 * FormTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Integration\HTMLForms;

use HCaptcha\Helpers\HCaptcha;
use HCaptcha\HTMLForms\Form;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use Mockery;

/**
 * Test the HTML Forms integration.
 *
 * @group html-forms
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
		hcaptcha()->settings()->set( 'html_forms_status', 'form' );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );
	}

	/**
	 * Test that the rendered form contains honeypot fields.
	 *
	 * @return void
	 */
	public function test_form_contains_honeypot(): void {
		$form     = Mockery::mock( 'HTML_Forms\Form' );
		$form->ID = 42;

		$output = ( new Form() )->add_captcha(
			'<form><p><input type="submit" value="Send"></p></form>',
			$form
		);

		self::assertStringContainsString( 'name="hcap_hp_test"', $output );
		self::assertStringContainsString( 'name="hcap_hp_sig"', $output );
		self::assertStringContainsString( 'name="hcaptcha-widget-id"', $output );
	}

	/**
	 * Test that a filled honeypot blocks form processing.
	 *
	 * @return void
	 */
	public function test_filled_honeypot_is_rejected(): void {
		$this->prepare_verify_post( 'html_forms_form_nonce', 'html_forms_form' );

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'html-forms/html-forms.php' ],
				'form_id' => 42,
			]
		);
		$_POST['hcap_hp_test']                 = 'bot';

		$form     = Mockery::mock( 'HTML_Forms\Form' );
		$form->ID = 42;
		$subject  = new Form();

		self::assertSame( 'hcaptcha_error', $subject->verify( '', $form, [] ) );
		self::assertSame( 'Anti-spam check failed.', $subject->get_message( 'hcaptcha_error' ) );
	}

	/**
	 * Test enqueue_scripts().
	 *
	 * @return void
	 */
	public function test_enqueue_scripts(): void {
		$subject = new Form();

		$subject->enqueue_scripts();

		self::assertTrue( wp_script_is( 'hcaptcha-html-forms' ) );
		self::assertSame(
			[ 'hcaptcha' ],
			wp_scripts()->registered['hcaptcha-html-forms']->deps
		);
	}
}
