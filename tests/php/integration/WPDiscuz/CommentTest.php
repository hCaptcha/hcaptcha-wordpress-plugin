<?php
/**
 * CommentTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */

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
	 * The wpDiscuz core class mock.
	 *
	 * @var Mockery\MockInterface|WpdiscuzCore
	 */
	private $wp_discuz;

	/**
	 * Setup test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		$options                  = Mockery::mock( 'WpdiscuzOptions' );
		$options->recaptcha       = [
			'siteKey'       => 'some site key',
			'showForGuests' => 1,
			'showForUsers'  => 1,
		];
		$this->wp_discuz          = Mockery::mock( 'WpdiscuzCore' );
		$this->wp_discuz->options = $options;

		FunctionMocker::replace(
			'function_exists',
			static function ( $function_name ) {
				return 'wpDiscuz' === $function_name;
			}
		);
		FunctionMocker::replace( 'wpDiscuz', $this->wp_discuz );
	}

	/**
	 * Teardown test.
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

		self::assertSame( 11, has_action( 'wp_enqueue_scripts', [ $subject, 'enqueue_scripts' ] ) );

		self::assertSame( 10, has_filter( 'wpdiscuz_form_render', [ $subject, 'add_hcaptcha' ] ) );
		self::assertSame( 9, has_filter( 'preprocess_comment', [ $subject, 'verify' ] ) );
		self::assertSame( 20, has_action( 'wp_head', [ $subject, 'print_inline_styles' ] ) );
	}

	/**
	 * Test init_hooks() without wpDiscuz.
	 *
	 * @return void
	 */
	public function test_init_hooks_without_wpdiscuz(): void {
		FunctionMocker::replace(
			'function_exists',
			static function ( $function_name ) {
				return 'wpDiscuz' !== $function_name;
			}
		);

		$subject = new Comment();

		self::assertFalse( has_action( 'wp_enqueue_scripts', [ $subject, 'enqueue_scripts' ] ) );
	}

	/**
	 * Test block_recaptcha().
	 *
	 * @return void
	 */
	public function test_block_recaptcha(): void {
		// Ensure initial values come from setUp().
		self::assertSame( 'some site key', $this->wp_discuz->options->recaptcha['siteKey'] );
		self::assertSame( 1, $this->wp_discuz->options->recaptcha['showForGuests'] );
		self::assertSame( 1, $this->wp_discuz->options->recaptcha['showForUsers'] );

		$subject = new Comment();

		// Call method under test.
		$subject->block_recaptcha();

		$wpd_recaptcha = (array) $this->wp_discuz->options->recaptcha;

		self::assertSame( '', $wpd_recaptcha['siteKey'] );
		self::assertSame( 0, $wpd_recaptcha['showForGuests'] );
		self::assertSame( 0, $wpd_recaptcha['showForUsers'] );
	}

	/**
	 * Test block_recaptcha() when wpDiscuz options are not set (null).
	 * This covers the early return branch in Base::block_recaptcha.
	 */
	public function test_block_recaptcha_without_options(): void {
		$core = Mockery::mock( 'WpdiscuzCore' );

		$core->options = null; // Simulate missing options.

		// Ensure the plugin thinks wpDiscuz exists and returns our core with null options.
		FunctionMocker::replace(
			'function_exists',
			static function ( $function_name ) {
				return 'wpDiscuz' === $function_name;
			}
		);
		FunctionMocker::replace( 'wpDiscuz', $core );

		$subject = new Comment();

		// Check before call.
		self::assertNull( $core->options );

		// Call method; it should return early and not throw/notices.
		$subject->block_recaptcha();

		// Still null, nothing modified.
		self::assertNull( $core->options );
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
		$output    = 'Some comment output<div class="wpd-form-col-right"> <div class="wc-field-submit">Submit</div>';
		$expected  =
			'Some comment output<div class="wpd-form-col-right wpd-form-col-hcaptcha"> ' .
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

		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter( 'preprocess_comment', [ $this->wp_discuz, 'validateRecaptcha' ] );

		$this->prepare_verify_request( $hcaptcha_response );

		$subject = new Comment();

		self::assertSame( $comment_data, $subject->verify( $comment_data ) );
		self::assertFalse( has_filter( 'preprocess_comment', [ $this->wp_discuz, 'validateRecaptcha' ] ) );
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

		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter( 'preprocess_comment', [ $this->wp_discuz, 'validateRecaptcha' ] );

		$this->prepare_verify_request( $hcaptcha_response, false );

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
		self::assertFalse( has_filter( 'preprocess_comment', [ $this->wp_discuz, 'validateRecaptcha' ] ) );
	}

	/**
	 * Test print_inline_styles().
	 *
	 * @return void
	 */
	public function test_print_inline_styles(): void {
		$expected = '.wpd-form-col-hcaptcha{min-width:303px}.wpd-field-hcaptcha .h-captcha{margin-left:auto}';
		$expected = "<style>\n$expected\n</style>\n";

		$subject = new Comment();

		ob_start();

		$subject->print_inline_styles();

		self::assertSame( $expected, ob_get_clean() );
	}
}
