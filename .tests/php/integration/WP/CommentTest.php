<?php
/**
 * CommentFormTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpLanguageLevelInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Integration\DefaultForms;

use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use HCaptcha\WP\Comment;
use WP_Error;

/**
 * Test comment form file.
 *
 * @group wp-comment
 * @group wp
 */
class CommentTest extends HCaptchaWPTestCase {

	/**
	 * Tear down test.
	 */
	public function tearDown(): void {
		unset( $GLOBALS['current_screen'] );

		parent::tearDown();
	}

	/**
	 * Test constructor and init_hooks().
	 */
	public function test_constructor_and_init_hooks() {
		$subject = new Comment();

		self::assertSame(
			10,
			has_filter( 'comment_form_submit_button', [ $subject, 'add_captcha' ] )
		);
		self::assertSame(
			10,
			has_filter( 'pre_comment_approved', [ $subject, 'verify' ] )
		);
	}

	/**
	 * Test add_captcha().
	 */
	public function test_add_captcha() {
		$submit_button = '<input name="submit" type="submit" id="submit" class="submit" value="Post Comment">';

		$expected =
			$this->get_hcap_form() .
			wp_nonce_field( 'hcaptcha_comment_form', 'hcaptcha_comment_form_nonce', true, false ) .
			$submit_button;

		$subject = new Comment();

		self::assertSame( $expected, $subject->add_captcha( $submit_button, [] ) );
	}

	/**
	 * Test verify().
	 */
	public function test_verify() {
		$approved    = 1;
		$commentdata = [ 'some comment data' ];

		$this->prepare_hcaptcha_get_verify_message_html( 'hcaptcha_comment_form_nonce', 'hcaptcha_comment_form' );

		$subject = new Comment();

		self::assertSame( $approved, $subject->verify( $approved, $commentdata ) );
	}

	/**
	 * Test verify() not verified in admin.
	 */
	public function test_verify_not_verified_in_admin() {
		$approved    = 1;
		$commentdata = [ 'some comment data' ];

		set_current_screen( 'edit-post' );

		$subject = new Comment();

		self::assertSame( $approved, $subject->verify( $approved, $commentdata ) );
	}

	/**
	 * Test verify() not verified, not in admin.
	 */
	public function test_verify_not_verified_not_admin() {
		$approved    = 1;
		$commentdata = [ 'some comment data' ];
		$expected    = new WP_Error( 'invalid_hcaptcha', 'Invalid Captcha', 400 );

		$this->prepare_hcaptcha_get_verify_message_html( 'hcaptcha_comment_form_nonce', 'hcaptcha_comment_form', false );

		$subject = new Comment();

		self::assertEquals( $expected, $subject->verify( $approved, $commentdata ) );
	}
}
