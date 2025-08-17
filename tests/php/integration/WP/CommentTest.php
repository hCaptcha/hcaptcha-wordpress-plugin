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
use tad\FunctionMocker\FunctionMocker;
use WP_Error;

/**
 * Test comment form file.
 *
 * @group wp-comment
 * @group wp
 */
class CommentTest extends HCaptchaWPTestCase {

	/**
	 * Teardown test.
	 */
	public function tearDown(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		unset( $GLOBALS['current_screen'], $_GET['rest_route'] );

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
	 * Test get_signature().
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 * @noinspection PhpConditionAlreadyCheckedInspection
	 */
	public function test_get_signature(): void {
		$subject         = new Comment();
		$form_id         = 123;
		$hcaptcha_shown  = true;
		$expected_output = 'some-signature-output';

		$this->set_protected_property( $subject, 'form_id', $form_id );
		$this->set_protected_property( $subject, 'hcaptcha_shown', $hcaptcha_shown );

		FunctionMocker::replace(
			'\\HCaptcha\\Helpers\\HCaptcha::get_signature',
			static function ( string $class_name, $fid, bool $shown ) use ( $form_id, $hcaptcha_shown, $expected_output ) {
				// Ensure CommentBase delegates correctly.
				self::assertSame( Comment::class, $class_name );
				self::assertSame( $form_id, $fid );
				self::assertSame( $hcaptcha_shown, $shown );

				return $expected_output;
			}
		);

		self::assertSame( $expected_output, $subject->get_signature() );
	}

	/**
	 * Test display_signature().
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 * @noinspection PhpConditionAlreadyCheckedInspection
	 */
	public function test_display_signature(): void {
		$subject        = new Comment();
		$form_id        = 456;
		$hcaptcha_shown = false;
		$echo_output    = 'display-signature-output';

		$this->set_protected_property( $subject, 'form_id', $form_id );
		$this->set_protected_property( $subject, 'hcaptcha_shown', $hcaptcha_shown );

		FunctionMocker::replace(
			'\\HCaptcha\\Helpers\\HCaptcha::display_signature',
			static function ( string $class_name, $fid, bool $shown ) use ( $form_id, $hcaptcha_shown, $echo_output ) {
				// Ensure CommentBase delegates correctly and echo a known string.
				self::assertSame( Comment::class, $class_name );
				self::assertSame( $form_id, $fid );
				self::assertSame( $hcaptcha_shown, $shown );

				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $echo_output;
			}
		);

		ob_start();
		$subject->display_signature();
		self::assertSame( $echo_output, ob_get_clean() );
	}

	/**
	 * Test add_captcha().
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_add_captcha(): void {
		$form_id      = 1;
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
					'sign'   => 'comment',
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
		$form_id      = 1;
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

		// Test when the hCaptcha plugin is not active.
		$this->set_protected_property( $subject, 'active', false );

		self::assertSame( $expected, $subject->add_captcha( $submit_field, [] ) );
	}

	/**
	 * Test verify().
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_verify(): void {
		$comment_data = [
			'some comment data',
			'comment_author_IP' => '7.7.7.7',
		];

		$this->prepare_verify_post_html( 'hcaptcha_comment_nonce', 'hcaptcha_comment' );

		FunctionMocker::replace( '\HCaptcha\Helpers\HCaptcha::check_signature' );

		$subject = new Comment();

		self::assertSame( $comment_data, $subject->verify( $comment_data ) );

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
		$comment_data = [ 'some comment data' ];

		set_current_screen( 'edit-post' );

		$subject = new Comment();

		self::assertSame( $comment_data, $subject->verify( $comment_data ) );
		self::assertNull( $this->get_protected_property( $subject, 'result' ) );
	}

	/**
	 * Test verify() in REST.
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_verify_in_rest(): void {
		$comment_data = [ 'some comment data' ];

		$_SERVER['REQUEST_URI'] = '/wp-json/activitypub/1.0/inbox';

		add_filter(
			'rest_url',
			static function () {
				return '/wp-json/activitypub/';
			}
		);

		$subject = new Comment();

		self::assertSame( $comment_data, $subject->verify( $comment_data ) );
		self::assertNull( $this->get_protected_property( $subject, 'result' ) );
	}

	/**
	 * Test verify() not verified.
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_verify_not_verified(): void {
		$comment_data = [
			'some comment data',
			'comment_author_IP' => '7.7.7.7',
		];
		$expected     = '<strong>hCaptcha error:</strong> The hCaptcha is invalid.';

		$this->prepare_verify_post_html( 'hcaptcha_comment_nonce', 'hcaptcha_comment', false );

		FunctionMocker::replace( '\HCaptcha\Helpers\HCaptcha::check_signature' );

		$subject = new Comment();

		self::assertSame( $comment_data, $subject->verify( $comment_data ) );
		self::assertSame( $expected, $this->get_protected_property( $subject, 'result' ) );
	}

	/**
	 * Test verify() when the signature is valid (HCaptcha::check_signature returns true).
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_verify_signature_valid_returns_early(): void {
		$comment_data = [
			'some comment data',
			'comment_author_IP' => '7.7.7.7',
		];

		// Prepare POST so that captcha fields exist; they should remain after early return.
		$this->prepare_verify_post_html( 'hcaptcha_comment_nonce', 'hcaptcha_comment' );

		FunctionMocker::replace(
			'\\HCaptcha\\Helpers\\HCaptcha::check_signature',
			static function () {
				return true;
			}
		);

		$subject = new Comment();

		self::assertSame( $comment_data, $subject->verify( $comment_data ) );

		// Since we returned early, POST captcha fields must still be present.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		self::assertTrue( isset( $_POST['h-captcha-response'] ) || isset( $_POST['g-recaptcha-response'] ) );
		self::assertTrue( $this->get_protected_property( $subject, 'result' ) );
	}

	/**
	 * Test verify() when the signature is invalid (HCaptcha::check_signature returns false).
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_verify_signature_invalid_sets_bad_signature(): void {
		$comment_data = [
			'some comment data',
			'comment_author_IP' => '7.7.7.7',
		];
		$expected     = hcap_get_error_messages()['bad-signature'];

		// Prepare POST so that captcha fields exist; they should remain after early return.
		$this->prepare_verify_post_html( 'hcaptcha_comment_nonce', 'hcaptcha_comment' );

		FunctionMocker::replace(
			'\\HCaptcha\\Helpers\\HCaptcha::check_signature',
			static function () {
				return false;
			}
		);

		$subject = new Comment();

		self::assertSame( $comment_data, $subject->verify( $comment_data ) );
		self::assertSame( $expected, $this->get_protected_property( $subject, 'result' ) );

		// Since we returned early, POST captcha fields must still be present.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		self::assertTrue( isset( $_POST['h-captcha-response'] ) || isset( $_POST['g-recaptcha-response'] ) );
	}

	/**
	 * Test pre_comment_approved().
	 *
	 * @return void
	 */
	public function test_pre_comment_approved(): void {
		$approved     = 1;
		$comment_data = [ 'some comment data' ];

		$subject = new Comment();

		self::assertSame( $approved, $subject->pre_comment_approved( $approved, $comment_data ) );
	}

	/**
	 * Test pre_comment_approved() when not verified.
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_pre_comment_approved_when_not_verified(): void {
		$approved      = 1;
		$comment_data  = [ 'some comment data' ];
		$error_message = '<strong>hCaptcha error:</strong> The hCaptcha is invalid.';
		$expected      = new WP_Error();

		$expected->add( 'invalid_hcaptcha', $error_message, 400 );

		$subject = new Comment();

		$this->set_protected_property( $subject, 'result', $error_message );

		self::assertEquals( $expected, $subject->pre_comment_approved( $approved, $comment_data ) );
	}
}
