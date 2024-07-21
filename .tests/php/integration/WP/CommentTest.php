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
	public function tearDown(): void {
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
	public function test_constructor_and_init_hooks( bool $active ): void {
		if ( $active ) {
			update_option( 'hcaptcha_settings', [ 'wp_status' => 'comment' ] );
		}

		hcaptcha()->init_hooks();

		$subject = new Comment();

		self::assertSame( 10, has_filter( 'comment_form_submit_field', [ $subject, 'add_captcha' ] ) );
		self::assertSame( - PHP_INT_MAX, has_filter( 'preprocess_comment', [ $subject, 'verify' ] ) );
		self::assertSame( 20, has_filter( 'pre_comment_approved', [ $subject, 'pre_comment_approved' ] ) );
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
	public function test_add_captcha(): void {
		$form_id      = '1';
		$submit_field =
			'<p class="form-submit"><input name="submit" type="submit" id="submit" class="submit et_pb_button" value="Submit Comment" />' .
			"<input type='hidden' name='comment_post_ID' value='$form_id' id='comment_post_ID' />" .
			"<input type='hidden' name='comment_parent' id='comment_parent' value='0' />" .
			'</p>';

		$expected =
			$this->get_hcap_form(
				[
					'action' => 'hcaptcha_comment',
					'name'   => 'hcaptcha_comment_nonce',
					'id'     => [
						'source'  => [ 'WordPress' ],
						'form_id' => $form_id,
					],
				]
			) .
			$submit_field;

		$subject = Mockery::mock( Comment::class )->makePartial();
		$this->set_protected_property( $subject, 'active', true );

		// Test when hCaptcha plugin is active.
		self::assertSame( $expected, $subject->add_captcha( $submit_field, [] ) );
	}

	/**
	 * Test add_captcha() when not active.
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_add_captcha_when_NOT_active(): void {
		$form_id      = '1';
		$submit_field =
			'<p class="form-submit"><input name="submit" type="submit" id="submit" class="submit et_pb_button" value="Submit Comment" />' .
			"<input type='hidden' name='comment_post_ID' value='$form_id' id='comment_post_ID' />" .
			"<input type='hidden' name='comment_parent' id='comment_parent' value='0' />" .
			'</p>';
		$hcap_widget  = $this->get_hcap_widget(
			[
				'source'  => [ 'WordPress' ],
				'form_id' => $form_id,
			]
		);
		$expected     = $hcap_widget . '
		' . $submit_field;

		$subject = Mockery::mock( Comment::class )->makePartial();

		// Test when hCaptcha plugin is not active.
		$this->set_protected_property( $subject, 'active', false );

		self::assertSame( $expected, $subject->add_captcha( $submit_field, [] ) );
	}

	/**
	 * Test verify().
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_verify(): void {
		$commentdata = [ 'some comment data' ];

		$this->prepare_hcaptcha_get_verify_message_html( 'hcaptcha_comment_nonce', 'hcaptcha_comment' );

		$subject = new Comment();

		self::assertSame( $commentdata, $subject->verify( $commentdata ) );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		self::assertFalse( isset( $_POST['h-captcha-response'], $_POST['g-recaptcha-response'] ) );
		self::assertNull( $this->get_protected_property( $subject, 'result' ) );
	}

	/**
	 * Test verify() in admin.
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_verify_in_admin(): void {
		$commentdata = [ 'some comment data' ];

		set_current_screen( 'edit-post' );

		$subject = new Comment();

		self::assertSame( $commentdata, $subject->verify( $commentdata ) );
		self::assertNull( $this->get_protected_property( $subject, 'result' ) );
	}

	/**
	 * Test verify() not verified.
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_verify_not_verified(): void {
		$commentdata = [ 'some comment data' ];
		$expected    = '<strong>hCaptcha error:</strong> The hCaptcha is invalid.';

		$this->prepare_hcaptcha_get_verify_message_html( 'hcaptcha_comment_nonce', 'hcaptcha_comment', false );

		$subject = new Comment();

		self::assertSame( $commentdata, $subject->verify( $commentdata ) );
		self::assertSame( $expected, $this->get_protected_property( $subject, 'result' ) );
	}

	/**
	 * Test pre_comment_approved().
	 *
	 * @return void
	 */
	public function test_pre_comment_approved(): void {
		$approved    = 1;
		$commentdata = [ 'some comment data' ];

		$subject = new Comment();

		self::assertSame( $approved, $subject->pre_comment_approved( $approved, $commentdata ) );
	}

	/**
	 * Test pre_comment_approved() when not verified.
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_pre_comment_approved_when_not_verified(): void {
		$approved      = 1;
		$commentdata   = [ 'some comment data' ];
		$error_message = '<strong>hCaptcha error:</strong> The hCaptcha is invalid.';
		$expected      = new WP_Error();

		$expected->add( 'invalid_hcaptcha', $error_message, 400 );

		$subject = new Comment();

		$this->set_protected_property( $subject, 'result', $error_message );

		self::assertEquals( $expected, $subject->pre_comment_approved( $approved, $commentdata ) );
	}

	/**
	 * Test verify().
	 */
	public function est_verify(): void {
		$approved    = 1;
		$commentdata = [ 'some comment data' ];

		$this->prepare_hcaptcha_get_verify_message_html( 'hcaptcha_comment_nonce', 'hcaptcha_comment' );

		$subject = new Comment();

		self::assertSame( $approved, $subject->pre_comment_approved( $approved, $commentdata ) );
	}

	/**
	 * Test verify() not verified in admin.
	 */
	public function est_verify_not_verified_in_admin(): void {
		$approved    = 1;
		$commentdata = [ 'some comment data' ];

		set_current_screen( 'edit-post' );

		$subject = new Comment();

		self::assertSame( $approved, $subject->pre_comment_approved( $approved, $commentdata ) );
	}

	/**
	 * Test verify() do not need to verify, not in admin.
	 */
	public function est_verify_do_not_need_to_verify_not_admin(): void {
		$approved    = 1;
		$commentdata = [ 'some comment data' ];
		$expected    = new WP_Error( 'invalid_hcaptcha', '<strong>hCaptcha error:</strong> Please complete the hCaptcha.', 400 );

		$subject = new Comment();

		self::assertEquals( $expected, $subject->pre_comment_approved( $approved, $commentdata ) );
	}

	/**
	 * Test verify() not verified, not in admin.
	 */
	public function est_verify_not_verified_not_admin(): void {
		$approved    = 1;
		$commentdata = [ 'some comment data' ];
		$expected    = new WP_Error( 'invalid_hcaptcha', '<strong>hCaptcha error:</strong> The hCaptcha is invalid.', 400 );

		$this->prepare_hcaptcha_get_verify_message_html( 'hcaptcha_comment_nonce', 'hcaptcha_comment', false );

		$subject = new Comment();

		self::assertEquals( $expected, $subject->pre_comment_approved( $approved, $commentdata ) );
	}
}
