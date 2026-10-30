<?php
/**
 * CommentTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */

namespace HCaptcha\Tests\Integration\WPDiscuz;

use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use HCaptcha\WPDiscuz\Comment;
use Mockery;
use tad\FunctionMocker\FunctionMocker;
use function function_exists;

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

		hcaptcha()->settings()->set( 'honeypot', 'on' );
		hcaptcha()->settings()->set( 'set_min_submit_time', 'on' );
		hcaptcha()->settings()->set( 'wpdiscuz_status', [ 'comment_form' ] );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );

		$options                  = Mockery::mock( 'WpdiscuzOptions' );
		$options->recaptcha       = [
			'siteKey'               => 'some site key',
			'showForGuests'         => 1,
			'showForUsers'          => 1,
			'isShowOnSubscribeForm' => 1,
		];
		$this->wp_discuz          = Mockery::mock( 'WpdiscuzCore' );
		$this->wp_discuz->options = $options;

		FunctionMocker::replace(
			'function_exists',
			static function ( $function_name ) {
				return 'wpDiscuz' === $function_name || function_exists( $function_name );
			}
		);
		FunctionMocker::replace( 'wpDiscuz', $this->wp_discuz );
	}

	/**
	 * Tear down the test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		unset( $_GET['action'], $_POST['action'], $_POST['h-captcha-response'], $_POST['g-recaptcha-response'], $_REQUEST['action'] );

		parent::tearDown();
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
				return ! ( 'wpDiscuz' === $function_name ) && function_exists( $function_name );
			}
		);

		$subject = new Comment();

		self::assertFalse( has_action( 'wp_enqueue_scripts', [ $subject, 'enqueue_scripts' ] ) );
	}

	/**
	 * Test block_recaptcha().
	 *
	 * @return void
	 * @noinspection PhpUndefinedFieldInspection
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

		self::assertSame( 'some site key', $wpd_recaptcha['siteKey'] );
		self::assertSame( 0, $wpd_recaptcha['showForGuests'] );
		self::assertSame( 0, $wpd_recaptcha['showForUsers'] );
		self::assertSame( 1, $wpd_recaptcha['isShowOnSubscribeForm'] );
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
				return 'wpDiscuz' === $function_name || function_exists( $function_name );
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

		self::assertTrue( wp_script_is( 'wpdiscuz-google-recaptcha', 'registered' ) );
		self::assertTrue( wp_script_is( 'wpdiscuz-google-recaptcha' ) );
		self::assertTrue( wp_script_is( 'hcaptcha-wpdiscuz-comment' ) );
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
		$result  = $subject->add_hcaptcha( $output, 0, false );

		self::assertSame( $expected, $result );
		self::assertStringContainsString( 'name="hcap_hp_test"', $result );
		self::assertStringContainsString( 'name="hcap_hp_sig"', $result );
	}

	/**
	 * Test verify().
	 *
	 * @return void
	 */
	public function test_verify(): void {
		$comment_data      = [ 'comment_post_ID' => 5 ];
		$hcaptcha_response = 'some response';

		$_POST['action']    = 'wpdAddComment';
		$_REQUEST['action'] = 'wpdAddComment';

		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter( 'preprocess_comment', [ $this->wp_discuz, 'validateRecaptcha' ] );

		$this->prepare_verify_request( $hcaptcha_response );
		$this->prepare_comment_identity();

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

		$_POST['action']    = 'wpdAddComment';
		$_REQUEST['action'] = 'some-action';

		add_filter( 'wp_doing_ajax', '__return_true' );

		$subject = new Comment();

		self::assertSame( $comment_data, $subject->verify( $comment_data ) );
	}

	/**
	 * Test verify() rejects a missing CAPTCHA for each native AJAX action carrier.
	 *
	 * @dataProvider dp_test_verify_not_verified
	 *
	 * @param string $carrier Action carrier.
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function test_verify_not_verified( string $carrier ): void {
		$comment_data      = [ 'comment_post_ID' => 5 ];
		$hcaptcha_response = 'some response';
		$die_arr           = [];
		$expected          = [
			'Please complete the hCaptcha.',
			'',
			[],
		];

		unset( $_POST['action'], $_GET['action'] );
		$_REQUEST['action'] = 'wpdAddComment';

		if ( 'post' === $carrier ) {
			$_POST['action'] = 'wpdAddComment';
		} elseif ( 'get' === $carrier ) {
			$_GET['action'] = 'wpdAddComment';
		}

		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter( 'preprocess_comment', [ $this->wp_discuz, 'validateRecaptcha' ] );

		$this->prepare_verify_request( $hcaptcha_response, false );
		$this->prepare_comment_identity();

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
	 * Native action carriers supported by WordPress AJAX dispatch.
	 *
	 * @return array
	 */
	public function dp_test_verify_not_verified(): array {
		return [
			'POST action'    => [ 'post' ],
			'query action'   => [ 'get' ],
			'REQUEST action' => [ 'request' ],
		];
	}

	/**
	 * Test malformed AJAX actions are not treated as wpDiscuz submissions.
	 *
	 * @dataProvider dp_test_malformed_action
	 *
	 * @param mixed $action Malformed action.
	 */
	public function test_verify_with_malformed_action( $action ): void {
		$comment_data       = [ 'comment_post_ID' => 5 ];
		$_POST['action']    = 'wpdAddComment';
		$_REQUEST['action'] = $action;

		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter( 'preprocess_comment', [ $this->wp_discuz, 'validateRecaptcha' ] );

		self::assertSame( $comment_data, ( new Comment() )->verify( $comment_data ) );
		self::assertNotFalse( has_filter( 'preprocess_comment', [ $this->wp_discuz, 'validateRecaptcha' ] ) );
	}

	/**
	 * Malformed WordPress AJAX action selectors.
	 *
	 * @return array
	 */
	public function dp_test_malformed_action(): array {
		return [
			'array'  => [ [ 'wpdAddComment' ] ],
			'object' => [ (object) [ 'action' => 'wpdAddComment' ] ],
		];
	}

	/**
	 * Test a filled honeypot blocks comment processing.
	 *
	 * @return void
	 */
	public function test_filled_honeypot_is_rejected(): void {
		$comment_data      = [ 'comment_post_ID' => 5 ];
		$hcaptcha_response = 'some response';
		$die_arr           = [];

		$_POST['action']    = 'wpdAddComment';
		$_REQUEST['action'] = 'wpdAddComment';

		add_filter( 'wp_doing_ajax', '__return_true' );
		$this->prepare_verify_request( $hcaptcha_response );
		$this->prepare_comment_identity();
		$_POST['hcap_hp_test'] = 'bot';

		add_filter(
			'wp_die_ajax_handler',
			static function () use ( &$die_arr ) {
				return static function ( $message, $title, $args ) use ( &$die_arr ) {
					$die_arr = [ $message, $title, $args ];
				};
			}
		);

		( new Comment() )->verify( $comment_data );

		self::assertSame( [ 'Anti-spam check failed.', '', [] ], $die_arr );
	}

	/**
	 * Comments without a native post ID still reject another post's widget.
	 */
	public function test_missing_post_id_rejects_foreign_widget(): void {
		$die_arr            = [];
		$_POST['action']    = 'wpdAddComment';
		$_REQUEST['action'] = 'wpdAddComment';

		add_filter( 'wp_doing_ajax', '__return_true' );
		$this->prepare_verify_request( 'some response' );
		$this->prepare_comment_identity();
		add_filter(
			'wp_die_ajax_handler',
			static function () use ( &$die_arr ) {
				return static function ( $message, $title, $args ) use ( &$die_arr ) {
					$die_arr = [ $message, $title, $args ];
				};
			}
		);

		( new Comment() )->verify( [ 'comment_post_ID' => 0 ] );

		self::assertSame( [ hcap_get_error_messages()['bad-signature'], '', [] ], $die_arr );
	}

	/**
	 * Prepare the widget rendered for the comment's native post target.
	 */
	private function prepare_comment_identity(): void {
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'wpdiscuz/class.WpdiscuzCore.php' ],
				'form_id' => 5,
			]
		);
	}

	/**
	 * Test print_inline_styles().
	 *
	 * @return void
	 */
	public function test_print_inline_styles(): void {
		$expected = '.wpd-form-col-hcaptcha{min-width:302px}.wpd-field-hcaptcha .h-captcha{margin-left:auto}';
		$expected = "<style>\n$expected\n</style>\n";

		$subject = new Comment();

		ob_start();

		$subject->print_inline_styles();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Check native comment email rather than an arbitrary submitted alias.
	 *
	 * @dataProvider dp_test_native_email_policy
	 *
	 * @param string $email   Native comment author email.
	 * @param bool   $blocked Whether disposal policy rejects it.
	 */
	public function test_native_email_policy( string $email, bool $blocked ): void {
		add_filter(
			'hcap_disposable_email_domains',
			static function (): array {
				return [ 'disposable.example' => true ];
			}
		);
		$_POST['action']    = 'wpdAddComment';
		$_REQUEST['action'] = 'wpdAddComment';
		$_POST['email']     = 'safe@example.com';
		add_filter( 'wp_doing_ajax', '__return_true' );
		$this->prepare_verify_post( 'unused_nonce', 'unused_action' );
		hcaptcha()->settings()->set( 'disposable_email', [ 'on' ] );
		hcaptcha()->settings()->set( 'wpdiscuz_status', [ 'comment_form' ] );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );
		self::assertTrue( hcaptcha()->settings()->is_on( 'disposable_email' ) );
		$this->prepare_comment_identity();
		$die_message = '';
		add_filter(
			'wp_die_ajax_handler',
			static function () use ( &$die_message ) {
				return static function ( $message ) use ( &$die_message ): void {
					$die_message = $message;
				};
			}
		);
		$data = [
			'comment_post_ID'      => 5,
			'comment_author_email' => $email,
		];

		$result = ( new Comment() )->verify( $data );

		self::assertSame( $blocked ? hcap_get_error_messages()['disposable-email'] : '', $die_message );
		if ( ! $blocked ) {
			self::assertSame( $data, $result );
		}
	}

	/**
	 * Native comment email controls.
	 *
	 * @return array
	 */
	public function dp_test_native_email_policy(): array {
		return [
			'disposable author' => [ 'user@disposable.example', true ],
			'permanent author'  => [ 'safe@example.com', false ],
			'email-free data'   => [ '', false ],
		];
	}
}
