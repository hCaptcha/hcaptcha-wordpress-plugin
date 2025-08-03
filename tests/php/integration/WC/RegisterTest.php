<?php
/**
 * RegisterTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\WC;

use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use HCaptcha\WC\Register;
use tad\FunctionMocker\FunctionMocker;
use WP_Error;

/**
 * Test Register class.
 *
 * @group wc-register
 * @group wc
 */
class RegisterTest extends HCaptchaWPTestCase {

	/**
	 * Test constructor and init_hooks().
	 */
	public function test_constructor_and_init_hooks(): void {
		$subject = new Register();

		self::assertSame(
			10,
			has_action( 'woocommerce_register_form', [ $subject, 'add_captcha' ] )
		);
		self::assertSame(
			10,
			has_action( 'woocommerce_process_registration_errors', [ $subject, 'verify' ] )
		);
	}

	/**
	 * Test add_captcha().
	 */
	public function test_add_captcha(): void {
		hcaptcha()->init_hooks();

		$args     = [
			'action' => 'hcaptcha_wc_register',
			'name'   => 'hcaptcha_wc_register_nonce',
			'id'     => [
				'source'  => [ 'woocommerce/woocommerce.php' ],
				'form_id' => 'register',
			],
		];
		$expected = $this->get_hcap_form( $args );

		$subject = new Register();

		ob_start();

		$subject->add_captcha();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test verify().
	 */
	public function test_verify(): void {
		$validation_error = new WP_Error( 'some error' );

		$this->prepare_verify_post( 'hcaptcha_wc_register_nonce', 'hcaptcha_wc_register' );

		$subject = new Register();
		self::assertEquals( $validation_error, $subject->verify( $validation_error ) );
	}

	/**
	 * Test verify() not verified.
	 */
	public function test_verify_not_verified(): void {
		$validation_error = 'some wrong error, to be replaced by WP_Error';
		$expected         = new WP_Error();

		$expected->add( 'hcaptcha_error', 'The hCaptcha is invalid.' );

		$this->prepare_verify_post_html( 'hcaptcha_wc_register_nonce', 'hcaptcha_wc_register', false );

		$subject = new Register();
		self::assertEquals( $expected, $subject->verify( $validation_error ) );
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
	.woocommerce-form-register .h-captcha {
		margin-top: 2rem;
	}
CSS;
		$expected = "<style>\n$expected\n</style>\n";

		$subject = new Register();

		ob_start();

		$subject->print_inline_styles();

		self::assertSame( $expected, ob_get_clean() );
	}
}
