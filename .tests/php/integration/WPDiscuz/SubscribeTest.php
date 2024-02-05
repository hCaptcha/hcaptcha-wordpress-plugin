<?php
/**
 * SubscribeTest class file.
 *
 * @package HCaptcha\Tests
 */

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
	 * Tear down test.
	 *
	 * @return void
	 * @noinspection PhpLanguageLevelInspection
	 * @noinspection PhpUndefinedClassInspection
	 */
	public function tearDown(): void { // phpcs:ignore PHPCompatibility.FunctionDeclarations.NewReturnTypeDeclarations.voidFound
		unset( $_POST['h-captcha-response'], $_POST['g-recaptcha-response'] );
	}

	/**
	 * Test init_hooks().
	 *
	 * @return void
	 */
	public function test_init_hooks() {
		$subject = new Subscribe();

		self::assertTrue( has_filter( 'wpdiscuz_recaptcha_site_key' ) );
		self::assertSame( 11, has_action( 'wp_enqueue_scripts', [ $subject, 'enqueue_scripts' ] ) );

		self::assertSame( 10, has_action( 'wpdiscuz_after_subscription_form', [ $subject, 'add_hcaptcha' ] ) );
		self::assertSame( 9, has_action( 'wp_ajax_wpdAddSubscription', [ $subject, 'verify' ] ) );
		self::assertSame( 9, has_action( 'wp_ajax_nopriv_wpdAddSubscription', [ $subject, 'verify' ] ) );
		self::assertSame( 20, has_action( 'wp_head', [ $subject, 'print_inline_styles' ] ) );

		self::assertSame( '', apply_filters( 'wpdiscuz_recaptcha_site_key', 'some site key' ) );
	}

	/**
	 * Test enqueue_scripts().
	 *
	 * @return void
	 */
	public function test_enqueue_scripts() {
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
	public function test_add_hcaptcha() {
		$expected = '		<div
			class="h-captcha"
			data-sitekey=""
			data-theme=""
			data-size=""
			data-auto="false">
		</div>
		';

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
	public function test_verify() {
		$hcaptcha_response = 'some response';

		$this->prepare_hcaptcha_request_verify( $hcaptcha_response );

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
	public function test_verify_NOT_verified() {
		$hcaptcha_response = 'some response';
		$die_arr           = [];
		$expected          = [
			'',
			'',
			[ 'response' => null ],
		];

		$this->prepare_hcaptcha_request_verify( $hcaptcha_response, false );

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
	public function test_print_inline_styles() {
		$expected = '#wpdiscuz-subscribe-form .h-captcha{margin-top:5px;margin-left:auto}';
		$expected = "<style>\n$expected\n</style>\n";

		$subject = new Subscribe();

		ob_start();

		$subject->print_inline_styles();

		self::assertSame( $expected, ob_get_clean() );
	}
}
