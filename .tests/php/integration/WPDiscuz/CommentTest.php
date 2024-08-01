<?php
/**
 * CommentTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\WPDiscuz;

use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use HCaptcha\WPDiscuz\Comment;
use Mockery;
use tad\FunctionMocker\FunctionMocker;

/**
 * Test Comment class.
 *
 * @group wpdiscuz
 */
class CommentTest extends HCaptchaWPTestCase {

	/**
	 * Tear down test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		unset( $_POST['action'], $_POST['h-captcha-response'], $_POST['g-recaptcha-response'] );
	}

	/**
	 * Test init_hooks().
	 *
	 * @return void
	 */
	public function test_init_hooks(): void {
		$subject = new Comment();

		self::assertTrue( has_filter( 'wpdiscuz_recaptcha_site_key' ) );
		self::assertSame( 11, has_action( 'wp_enqueue_scripts', [ $subject, 'enqueue_scripts' ] ) );

		self::assertSame( 10, has_filter( 'wpdiscuz_form_render', [ $subject, 'add_hcaptcha' ] ) );
		self::assertSame( 9, has_filter( 'preprocess_comment', [ $subject, 'verify' ] ) );
		self::assertSame( 20, has_action( 'wp_head', [ $subject, 'print_inline_styles' ] ) );

		self::assertSame( '', apply_filters( 'wpdiscuz_recaptcha_site_key', 'some site key' ) );
	}

	/**
	 * Test enqueue_scripts().
	 *
	 * @return void
	 */
	public function test_enqueue_scripts(): void {
		self::assertFalse( wp_script_is( 'wpdiscuz-google-recaptcha', 'registered' ) );
		self::assertFalse( wp_script_is( 'wpdiscuz-google-recaptcha' ) );

		wp_enqueue_script(
			'wpdiscuz-google-recaptcha',
			'https://domain.tld/api.js',
			[],
			'1.0',
			true
		);

		self::assertTrue( wp_script_is( 'wpdiscuz-google-recaptcha', 'registered' ) );
		self::assertTrue( wp_script_is( 'wpdiscuz-google-recaptcha' ) );

		$subject = new Comment();

		$subject->enqueue_scripts();

		self::assertFalse( wp_script_is( 'wpdiscuz-google-recaptcha', 'registered' ) );
		self::assertFalse( wp_script_is( 'wpdiscuz-google-recaptcha' ) );
	}

	/**
	 * Test add_captcha().
	 *
	 * @return void
	 */
	public function test_add_captcha(): void {
		$args      = [
			'id' => [
				'source'  => [ 'wpdiscuz/class.WpdiscuzCore.php' ],
				'form_id' => 0,
			],
		];
		$hcap_form = $this->get_hcap_form( $args );
		$output    = 'Some comment output<div class="wc-field-submit">Submit</div>';
		$expected  =
			'Some comment output' .
			'		<div class="wpd-field-hcaptcha wpdiscuz-item">
			<div class="wpdiscuz-hcaptcha"></div>
			' . $hcap_form . '			<div class="clearfix"></div>
		</div>
		' . '<div class="wc-field-submit">Submit</div>';

		$subject = new Comment();

		self::assertSame( $expected, $subject->add_hcaptcha( $output, 0, false ) );
	}

	/**
	 * Test verify().
	 *
	 * @return void
	 */
	public function test_verify(): void {
		$comment_data      = [ 'some comment data' ];
		$hcaptcha_response = 'some response';

		$_POST['action'] = 'wpdAddComment';

		$wp_discuz = Mockery::mock( 'WpdiscuzCore' );

		FunctionMocker::replace( 'wpDiscuz', $wp_discuz );

		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter( 'preprocess_comment', [ $wp_discuz, 'validateRecaptcha' ] );

		$this->prepare_hcaptcha_request_verify( $hcaptcha_response );

		$subject = new Comment();

		self::assertSame( $comment_data, $subject->verify( $comment_data ) );
		self::assertFalse( has_filter( 'preprocess_comment', [ $wp_discuz, 'validateRecaptcha' ] ) );
	}

	/**
	 * Test verify() when not wpd action.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function test_verify_when_not_wpd_action(): void {
		$comment_data = [ 'some comment data' ];

		$subject = new Comment();

		self::assertSame( $comment_data, $subject->verify( $comment_data ) );

		$_POST['action'] = 'some-action';

		add_filter( 'wp_doing_ajax', '__return_true' );

		$subject = new Comment();

		self::assertSame( $comment_data, $subject->verify( $comment_data ) );
	}

	/**
	 * Test verify() when not verified.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function test_verify_not_verified(): void {
		$comment_data      = [ 'some comment data' ];
		$hcaptcha_response = 'some response';
		$die_arr           = [];
		$expected          = [
			'Please complete the hCaptcha.',
			'',
			[],
		];

		$_POST['action'] = 'wpdAddComment';

		$wp_discuz = Mockery::mock( 'WpdiscuzCore' );

		FunctionMocker::replace( 'wpDiscuz', $wp_discuz );

		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter( 'preprocess_comment', [ $wp_discuz, 'validateRecaptcha' ] );

		$this->prepare_hcaptcha_request_verify( $hcaptcha_response, false );

		unset( $_POST['h-captcha-response'], $_POST['g-recaptcha-response'] );

		add_filter(
			'wp_die_ajax_handler',
			static function ( $name ) use ( &$die_arr ) {
				return static function ( $message, $title, $args ) use ( &$die_arr ) {
					$die_arr = [ $message, $title, $args ];
				};
			}
		);

		$subject = new Comment();

		$subject->verify( $comment_data );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		self::assertFalse( isset( $_POST['h-captcha-response'], $_POST['g-recaptcha-response'] ) );
		self::assertSame( $expected, $die_arr );
		self::assertFalse( has_filter( 'preprocess_comment', [ $wp_discuz, 'validateRecaptcha' ] ) );
	}

	/**
	 * Test print_inline_styles().
	 *
	 * @return void
	 */
	public function test_print_inline_styles(): void {
		$expected = '.wpd-field-hcaptcha .h-captcha{margin-left:auto}';
		$expected = "<style>\n$expected\n</style>\n";

		$subject = new Comment();

		ob_start();

		$subject->print_inline_styles();

		self::assertSame( $expected, ob_get_clean() );
	}
}
