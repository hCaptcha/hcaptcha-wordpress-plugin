<?php
/**
 * SubscribeTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */

namespace HCaptcha\Tests\Integration\WPDiscuz;

use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use HCaptcha\WPDiscuz\Subscribe;
use Mockery;
use tad\FunctionMocker\FunctionMocker;

/**
 * Test Subscribe class.
 *
 * @group wpdiscuz
 */
class SubscribeTest extends HCaptchaWPTestCase {

	/**
	 * The wpDiscuz core class mock.
	 *
	 * @var Mockery\MockInterface|WpdiscuzCore
	 * @noinspection PhpPrivateFieldCanBeLocalVariableInspection
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
	 * Tear down test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		unset( $_POST['h-captcha-response'], $_POST['g-recaptcha-response'] );

		parent::tearDown();
	}

	/**
	 * Test init_hooks().
	 *
	 * @return void
	 */
	public function test_init_hooks(): void {
		$subject = new Subscribe();

		self::assertSame( 11, has_action( 'wp_enqueue_scripts', [ $subject, 'enqueue_scripts' ] ) );

		self::assertSame( 10, has_action( 'wpdiscuz_after_subscription_form', [ $subject, 'add_hcaptcha' ] ) );
		self::assertSame( 9, has_action( 'wp_ajax_wpdAddSubscription', [ $subject, 'verify' ] ) );
		self::assertSame( 9, has_action( 'wp_ajax_nopriv_wpdAddSubscription', [ $subject, 'verify' ] ) );
		self::assertSame( 20, has_action( 'wp_head', [ $subject, 'print_inline_styles' ] ) );
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

		$subject = new Subscribe();

		$subject->enqueue_scripts();

		self::assertFalse( wp_script_is( 'wpdiscuz-google-recaptcha', 'registered' ) );
		self::assertFalse( wp_script_is( 'wpdiscuz-google-recaptcha' ) );
	}

	/**
	 * Test add_hcaptcha().
	 *
	 * @return void
	 */
	public function test_add_hcaptcha(): void {
		$args     = [
			'id' => [
				'source'  => [ 'wpdiscuz/class.WpdiscuzCore.php' ],
				'form_id' => 0,
			],
		];
		$expected = $this->get_hcap_form( $args );

		ob_start();

		$subject = new Subscribe();

		$subject->add_hcaptcha();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test verify().
	 *
	 * @return void
	 */
	public function test_verify(): void {
		$hcaptcha_response = 'some response';

		$this->prepare_verify_request( $hcaptcha_response );

		$subject = new Subscribe();

		$subject->verify();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		self::assertFalse( isset( $_POST['h-captcha-response'], $_POST['g-recaptcha-response'] ) );
	}

	/**
	 * Test verify() when not verified.
	 *
	 * @return void
	 */
	public function test_verify_NOT_verified(): void {
		$hcaptcha_response = 'some response';
		$die_arr           = [];
		$expected          = [
			'',
			'',
			[ 'response' => null ],
		];

		$this->prepare_verify_request( $hcaptcha_response, false );

		unset( $_POST['h-captcha-response'], $_POST['g-recaptcha-response'] );

		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter(
			'wp_die_ajax_handler',
			static function () use ( &$die_arr ) {
				return static function ( $message, $title, $args ) use ( &$die_arr ) {
					$die_arr = [ $message, $title, $args ];
				};
			}
		);

		$subject = new Subscribe();

		ob_start();

		$subject->verify();

		self::assertSame( '{"success":false,"data":"Please complete the hCaptcha."}', ob_get_clean() );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		self::assertFalse( isset( $_POST['h-captcha-response'], $_POST['g-recaptcha-response'] ) );
		self::assertSame( $expected, $die_arr );
	}

	/**
	 * Test print_inline_styles().
	 *
	 * @return void
	 */
	public function test_print_inline_styles(): void {
		$expected = '#wpdiscuz-subscribe-form .h-captcha{margin-top:5px;margin-left:auto}';
		$expected = "<style>\n$expected\n</style>\n";

		$subject = new Subscribe();

		ob_start();

		$subject->print_inline_styles();

		self::assertSame( $expected, ob_get_clean() );
	}
}
