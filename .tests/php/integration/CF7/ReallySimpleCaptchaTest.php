<?php
/**
 * ReallySimpleCaptchaTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpLanguageLevelInspection */
/** @noinspection PhpUndefinedClassInspection */
/** @noinspection PhpUndefinedFunctionInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Integration\CF7;

use HCaptcha\CF7\ReallySimpleCaptcha;
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use Mockery;
use WPCF7_ContactForm;
use WPCF7_Submission;
use function PHPUnit\Framework\assertSame;

/**
 * Test ReallySimpleCaptcha class.
 *
 * @requires PHP >= 7.4
 *
 * @group    cf7
 * @group    cf7-really-simple-captcha
 */
class ReallySimpleCaptchaTest extends HCaptchaPluginWPTestCase {

	/**
	 * Test init_hooks().
	 *
	 * @return void
	 */
	public function test_init_hooks(): void {
		update_option( 'hcaptcha_settings', [ 'cf7_status' => [ 'replace_rsc' ] ] );
		hcaptcha()->init_hooks();

		$subject = new ReallySimpleCaptcha();

		self::assertSame( 0, has_action( 'wpcf7_init', [ $subject, 'remove_wpcf7_add_form_tag_captcha_action' ] ) );
		self::assertSame( 10, has_filter( 'do_shortcode_tag', [ $subject, 'wpcf7_shortcode' ] ) );
		self::assertSame( 10, has_filter( 'hcap_cf7_has_field', [ $subject, 'has_field' ] ) );
	}

	/**
	 * Test remove_wpcf7_add_form_tag_captcha_action().
	 *
	 * @return void
	 */
	public function test_remove_wpcf7_add_form_tag_captcha_action(): void {
		add_action( 'wpcf7_init', 'wpcf7_add_form_tag_captcha' );

		$subject = new ReallySimpleCaptcha();

		self::assertSame( 10, has_action( 'wpcf7_init', 'wpcf7_add_form_tag_captcha' ) );

		$subject->remove_wpcf7_add_form_tag_captcha_action();

		self::assertFalse( has_action( 'wpcf7_init', 'wpcf7_add_form_tag_captcha' ) );
	}

	/**
	 * Test wpcf7_shortcode().
	 *
	 * @return void
	 */
	public function test_wpcf7_shortcode(): void {
		$tag      = 'some tag';
		$output   = 'some output [captchac some-attrs] [captchar some-attrs] more output';
		$expected = 'some output [cf7-hcaptcha some-attrs]  more output';

		$subject = new ReallySimpleCaptcha();

		// Wrong tag.
		self::assertSame( $output, $subject->wpcf7_shortcode( $output, $tag, [], [] ) );

		$tag = 'contact-form-7';

		// CF7 tag.
		self::assertSame( $expected, $subject->wpcf7_shortcode( $output, $tag, [], [] ) );
	}

	/**
	 * Test has_field().
	 *
	 * @return void
	 */
	public function test_has_field(): void {
		$form_html = 'some html [captchac some-attrs] [captchar some-attrs] more html';

		$contact_form = Mockery::mock( WPCF7_ContactForm::class );
		$submission   = Mockery::mock( WPCF7_Submission::class );

		$contact_form->shouldReceive( 'form_html' )->andReturn( $form_html );
		$submission->shouldReceive( 'get_contact_form' )->andReturn( $contact_form );

		$subject = new ReallySimpleCaptcha();

		// Wrong type.
		self::assertFalse( $subject->has_field( false, $submission, 'some-type' ) );

		// The hcaptcha type.
		self::assertTrue( $subject->has_field( false, $submission, 'hcaptcha' ) );
	}
}
