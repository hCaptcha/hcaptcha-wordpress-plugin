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

namespace HCaptcha\Tests\Integration\WP;

use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use HCaptcha\WP\Comment;
use Mockery;
use ReflectionException;
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
	public function tearDown(): void { // phpcs:ignore PHPCompatibility.FunctionDeclarations.NewReturnTypeDeclarations.voidFound
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		unset( $GLOBALS['current_screen'] );

		parent::tearDown();
	}

	/**
	 * Test constructor and init_hooks().
	 *
	 * @param bool $active Active flag.
	 *
	 * @dataProvider dp_test_constructor_and_init_hooks
	 */
	public function test_constructor_and_init_hooks( bool $active ) {
		if ( $active ) {
			update_option( 'hcaptcha_settings', [ 'wp_status' => 'comment' ] );
		}

		hcaptcha()->init_hooks();

		$subject = new Comment();

		if ( $active ) {
			self::assertSame(
				10,
				has_filter( 'comment_form_submit_field', [ $subject, 'add_captcha' ] )
			);
		}

		self::assertSame(
			20,
			has_filter( 'pre_comment_approved', [ $subject, 'verify' ] )
		);
	}

	/**
	 * Data provider for test_constructor_and_init_hooks().
	 *
	 * @return array
	 */
	public function dp_test_constructor_and_init_hooks(): array {
		return [
			[ true ],
			[ false ],
		];
	}

	/**
	 * Test add_captcha().
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_add_captcha() {
		$submit_field =
			'<p class="form-submit"><input name="submit" type="submit" id="submit" class="submit et_pb_button" value="Submit Comment" />' .
			"<input type='hidden' name='comment_post_ID' value='1' id='comment_post_ID' />" .
			"<input type='hidden' name='comment_parent' id='comment_parent' value='0' />" .
			'</p>';

		$expected =
			$this->get_hcap_form( 'hcaptcha_comment', 'hcaptcha_comment_nonce' ) .
			$submit_field;

		$subject = Mockery::mock( Comment::class )->makePartial();
		$this->set_protected_property( $subject, 'active', true );

		self::assertSame( $expected, $subject->add_captcha( $submit_field, [] ) );
	}

	/**
	 * Test verify().
	 */
	public function test_verify() {
		$approved    = 1;
		$commentdata = [ 'some comment data' ];

		$this->prepare_hcaptcha_get_verify_message_html( 'hcaptcha_comment_nonce', 'hcaptcha_comment' );

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
	 * Test verify() do not need to verify, not in admin.
	 */
	public function test_verify_do_not_need_to_verify_not_admin() {
		$approved    = 1;
		$commentdata = [ 'some comment data' ];
		$expected    = new WP_Error( 'invalid_hcaptcha', '<strong>hCaptcha error:</strong> Please complete the hCaptcha.', 400 );

		$subject = new Comment();

		self::assertEquals( $expected, $subject->verify( $approved, $commentdata ) );
	}

	/**
	 * Test verify() not verified, not in admin.
	 */
	public function test_verify_not_verified_not_admin() {
		$approved    = 1;
		$commentdata = [ 'some comment data' ];
		$expected    = new WP_Error( 'invalid_hcaptcha', '<strong>hCaptcha error:</strong> The hCaptcha is invalid.', 400 );

		$this->prepare_hcaptcha_get_verify_message_html( 'hcaptcha_comment_nonce', 'hcaptcha_comment', false );

		$subject = new Comment();

		self::assertEquals( $expected, $subject->verify( $approved, $commentdata ) );
	}
}
