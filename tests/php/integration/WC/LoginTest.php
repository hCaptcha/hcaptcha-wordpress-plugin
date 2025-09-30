<?php
/**
 * LoginTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\WC;

use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use HCaptcha\WC\Login;
use tad\FunctionMocker\FunctionMocker;
use WP_Error;

/**
 * Test Login class.
 *
 * @group wc-login
 * @group wc
 */
class LoginTest extends HCaptchaWPTestCase {

	/**
	 * Test constructor and init_hooks().
	 */
	public function test_constructor_and_init_hooks(): void {
		$subject = new Login();

		self::assertSame( 10, has_action( 'woocommerce_login_form', [ $subject, 'add_captcha' ] ) );
		self::assertSame( 10, has_action( 'woocommerce_process_login_errors', [ $subject, 'verify' ] ) );
		self::assertSame( 20, has_action( 'wp_head', [ $subject, 'print_inline_styles' ] ) );
	}

	/**
	 * Test add_captcha().
	 */
	public function test_add_captcha(): void {
		$args     = [
			'action' => 'hcaptcha_login',
			'name'   => 'hcaptcha_login_nonce',
			'id'     => [
				'source'  => [ 'woocommerce/woocommerce.php' ],
				'form_id' => 'login',
			],
		];
		$expected = $this->get_hcap_form( $args );

		$subject = new Login();

		ob_start();

		$subject->add_captcha();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test verify().
	 */
	public function test_verify(): void {
		$validation_error = new WP_Error();

		$this->prepare_verify_post( 'hcaptcha_login_nonce', 'hcaptcha_login' );

		$subject = new Login();

		add_filter( 'woocommerce_process_login_errors', [ $subject, 'verify' ] );

		self::assertEquals(
			$validation_error,
			apply_filters( 'woocommerce_process_login_errors', $validation_error )
		);
	}

	/**
	 * Test verify() when not in the WC filter.
	 */
	public function test_verify_NOT_wc_filter(): void {
		$validation_error = new WP_Error();

		$subject = new Login();

		self::assertEquals( $validation_error, $subject->verify( $validation_error ) );
	}

	/**
	 * Test verify() when not login limit exceeded.
	 */
	public function test_verify_NOT_login_limit_exceeded(): void {
		$validation_error = new WP_Error();

		$subject = new Login();

		add_filter( 'hcap_login_limit_exceeded', '__return_false' );

		add_filter( 'woocommerce_process_login_errors', [ $subject, 'verify' ] );

		self::assertEquals(
			$validation_error,
			apply_filters( 'woocommerce_process_login_errors', $validation_error )
		);
	}

	/**
	 * Test verify() not verified.
	 */
	public function test_verify_not_verified(): void {
		$validation_error = 'some wrong error, to be replaced by WP_Error';
		$expected         = new WP_Error();
		$expected->add( 'hcaptcha_error', 'The hCaptcha is invalid.' );

		$this->prepare_verify_post( 'hcaptcha_login_nonce', 'hcaptcha_login', false );

		$subject = new Login();

		add_filter( 'woocommerce_process_login_errors', [ $subject, 'verify' ] );

		self::assertEquals(
			$expected,
			apply_filters( 'woocommerce_process_login_errors', $validation_error )
		);
	}

	/**
	 * Test print_inline_styles().
	 *
	 * @return void
	 * @noinspection CssUnusedSymbol
	 */
	public function test_print_inline_styles(): void {
		FunctionMocker::replace(
			'defined',
			static function ( $constant_name ) {
				return 'SCRIPT_DEBUG' === $constant_name;
			}
		);

		FunctionMocker::replace(
			'constant',
			static function ( $name ) {
				return 'SCRIPT_DEBUG' === $name;
			}
		);

		$expected = <<<'CSS'
	.woocommerce-form-login .h-captcha {
		margin-top: 2rem;
	}
CSS;
		$expected = "<style>\n$expected\n</style>\n";

		$subject = new Login();

		ob_start();

		$subject->print_inline_styles();

		self::assertSame( $expected, ob_get_clean() );
	}
}
