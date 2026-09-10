<?php
/**
 * CommentTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\Divi;

use HCaptcha\Divi\Comment;
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;

/**
 * Class CommentTest.
 *
 * @group divi
 */
class CommentTest extends HCaptchaPluginWPTestCase {

	/**
	 * Theme stylesheet slug.
	 *
	 * @var string
	 */
	protected static string $theme = 'Divi';

	/**
	 * Live Divi shortcode modules used by the test.
	 *
	 * @var array<string, string>
	 */
	protected static array $theme_shortcode_classes = [
		'et_pb_comments' => 'ET_Builder_Module_Comments',
	];

	/**
	 * Expected incorrect usage notices caused by the late theme load.
	 *
	 * @var string[]
	 */
	protected static array $theme_expected_incorrect_usage = [ "add_theme_support( 'title-tag' )" ];

	/**
	 * Test constructor and init_hooks().
	 *
	 * @return void
	 */
	public function test_constructor_and_init_hooks(): void {
		$subject = new Comment();

		self::assertTrue( function_exists( 'et_setup_builder' ) );
		self::assertNotFalse( has_action( 'init', 'et_setup_builder' ) );
		self::assertTrue( class_exists( 'ET_Builder_Module_Comments' ) );
		self::assertTrue( shortcode_exists( 'et_pb_comments' ) );
		self::assertSame( 10, has_filter( Comment::TAG . '_shortcode_output', [ $subject, 'add_captcha' ] ) );
	}

	/**
	 * Test the live Divi Comments module.
	 *
	 * @return void
	 */
	public function test_live_comments_module(): void {
		$post_id = $this->factory()->post->create(
			[
				'post_status'    => 'publish',
				'comment_status' => 'open',
			]
		);

		$this->go_to( get_permalink( $post_id ) );
		update_option( 'hcaptcha_settings', [ 'divi_status' => [ 'comment' ] ] );
		hcaptcha()->init_hooks();

		new Comment();

		self::assertTrue( shortcode_exists( 'et_pb_comments' ) );

		$output = do_shortcode( '[et_pb_comments][/et_pb_comments]' );

		self::assertStringContainsString( 'et_pb_comments_module', $output );
		self::assertStringContainsString( 'id="commentform"', $output );
		self::assertStringContainsString( '<h-captcha', $output );
		self::assertStringContainsString( 'name="hcaptcha_comment_nonce"', $output );
		self::assertStringContainsString( "name='comment_post_ID' value='$post_id'", $output );
	}

	/**
	 * Test add_captcha() when the output is not a string.
	 *
	 * @return void
	 */
	public function test_add_captcha_when_output_is_not_a_string(): void {
		$output = [ 'some string' ];

		$subject = new Comment();

		self::assertSame( $output, $subject->add_captcha( $output, Comment::TAG ) );
	}

	/**
	 * Test add_captcha() when the output already has hCaptcha.
	 *
	 * @return void
	 */
	public function test_add_captcha_when_output_has_hcaptcha(): void {
		$output = 'some output with h-captcha attr';

		$subject = new Comment();

		self::assertSame( $output, $subject->add_captcha( $output, Comment::TAG ) );
	}

	/**
	 * Test add_captcha() in the live Divi frontend builder state.
	 *
	 * @return void
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function test_add_captcha_in_frontend_builder(): void {
		$output = 'some string';

		add_filter( 'et_fb_is_enabled', '__return_true' );

		$subject = new Comment();

		self::assertTrue( et_core_is_fb_enabled() );
		self::assertSame( $output, $subject->add_captcha( $output, Comment::TAG ) );
	}
}
