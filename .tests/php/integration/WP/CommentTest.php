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
use HCaptcha\Helpers\Origin;
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
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		unset( $GLOBALS['current_screen'], $_POST[ Origin::NAME ] );

		delete_transient( Origin::TRANSIENT );

		parent::tearDown();
	}

	/**
	 * Test constructor and init_hooks().
	 *
	 * @param bool $active Active flag.
	 * @dataProvider dp_test_constructor_and_init_hooks
	 */
	public function test_constructor_and_init_hooks( $active ) {
		if ( $active ) {
			update_option( 'hcaptcha_settings', [ 'wp_status' => 'comment' ] );
		}

		hcaptcha()->init_hooks();

		$subject = new Comment();

		self::assertSame(
			PHP_INT_MAX,
			has_filter( 'comment_form_submit_button', [ $subject, 'add_origin' ] )
		);

		if ( $active ) {
			self::assertSame(
				10,
				has_filter( 'comment_form_submit_button', [ $subject, 'add_captcha' ] )
			);
		}

		self::assertSame(
			10,
			has_filter( 'pre_comment_approved', [ $subject, 'verify' ] )
		);
	}

	/**
	 * Data provider for test_constructor_and_init_hooks().
	 *
	 * @return array
	 */
	public function dp_test_constructor_and_init_hooks() {
		return [
			[ true ],
			[ false ],
		];
	}

	/**
	 * Test add_captcha().
	 */
	public function test_add_captcha() {
		$submit_button = '<input name="submit" type="submit" id="submit" class="submit" value="Post Comment">';

		$expected =
			$this->get_hcap_form() .
			wp_nonce_field( 'hcaptcha_comment', 'hcaptcha_comment_nonce', true, false ) .
			$submit_button;

		$subject = new Comment();

		self::assertSame( $expected, $subject->add_captcha( $submit_button, [] ) );
	}

	/**
	 * Test verify().
	 */
	public function test_verify() {
		$approved              = 1;
		$commentdata           = [ 'some comment data' ];
		$time                  = time();
		$new_id                = wp_hash( $time );
		$transient_data        = [
			$new_id => [
				'time'   => $time,
				'action' => 'hcaptcha_comment',
				'nonce'  => 'hcaptcha_comment_nonce',
			],
		];
		$_POST[ Origin::NAME ] = $new_id;

		$this->prepare_hcaptcha_get_verify_message_html( 'hcaptcha_comment_nonce', 'hcaptcha_comment' );

		set_transient( Origin::TRANSIENT, $transient_data );

		$subject = new Comment();

		self::assertSame( $approved, $subject->verify( $approved, $commentdata ) );
		self::assertSame( [], get_transient( Origin::TRANSIENT ) );
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
	 * Test verify() wrong origin, not in admin.
	 */
	public function test_verify_wrong_origin_not_admin() {
		$approved              = 1;
		$commentdata           = [ 'some comment data' ];
		$time                  = time();
		$new_id                = wp_hash( $time );
		$transient_data        = [
			$new_id => [
				'time'   => $time,
				'action' => 'hcaptcha_comment',
				'nonce'  => 'hcaptcha_comment_nonce',
			],
		];
		$_POST[ Origin::NAME ] = 'some id';
		$expected              = new WP_Error( 'invalid_hcaptcha', 'Invalid Captcha', 400 );

		set_transient( Origin::TRANSIENT, $transient_data );

		$subject = new Comment();

		self::assertEquals( $expected, $subject->verify( $approved, $commentdata ) );
	}

	/**
	 * Test verify() do not need to verify, not in admin.
	 */
	public function test_verify_do_not_need_to_verify_not_admin() {
		$approved              = 1;
		$commentdata           = [ 'some comment data' ];
		$time                  = time();
		$new_id                = wp_hash( $time );
		$transient_data        = [
			$new_id => [
				'time'   => $time,
				'action' => '',
				'nonce'  => '',
			],
		];
		$_POST[ Origin::NAME ] = $new_id;

		set_transient( Origin::TRANSIENT, $transient_data );

		$subject = new Comment();

		self::assertSame( $approved, $subject->verify( $approved, $commentdata ) );
		self::assertSame( [], get_transient( Origin::TRANSIENT ) );
	}

	/**
	 * Test verify() not verified, not in admin.
	 */
	public function test_verify_not_verified_not_admin() {
		$approved              = 1;
		$commentdata           = [ 'some comment data' ];
		$time                  = time();
		$new_id                = wp_hash( $time );
		$transient_data        = [
			$new_id => [
				'time'   => $time,
				'action' => 'hcaptcha_comment',
				'nonce'  => 'hcaptcha_comment_nonce',
			],
		];
		$_POST[ Origin::NAME ] = $new_id;
		$expected              = new WP_Error( 'invalid_hcaptcha', 'Invalid Captcha', 400 );

		$this->prepare_hcaptcha_get_verify_message_html( 'hcaptcha_comment_nonce', 'hcaptcha_comment', false );

		set_transient( Origin::TRANSIENT, $transient_data );

		$subject = new Comment();

		self::assertEquals( $expected, $subject->verify( $approved, $commentdata ) );
	}
}
