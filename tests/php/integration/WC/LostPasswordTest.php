<?php
/**
 * LostPasswordTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\WC;

use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use HCaptcha\WC\LostPassword;
use tad\FunctionMocker\FunctionMocker;
use WP_Error;

/**
 * LostPasswordTest class.
 *
 * @group wc-lost-password
 * @group wc
 */
class LostPasswordTest extends HCaptchaWPTestCase {

	/**
	 * Test constructor and init_hooks().
	 */
	public function test_constructor_and_init_hooks(): void {
		$subject = new LostPassword();

		self::assertSame(
			10,
			has_action( 'woocommerce_lostpassword_form', [ $subject, 'add_captcha' ] )
		);
	}

	/**
	 * Test add_captcha().
	 */
	public function test_add_captcha(): void {
		$args     = [
			'action' => 'hcaptcha_wc_lost_password',
			'name'   => 'hcaptcha_wc_lost_password_nonce',
			'id'     => [
				'source'  => [ 'woocommerce/woocommerce.php' ],
				'form_id' => 'lost_password',
			],
		];
		$expected = $this->get_hcap_form( $args );

		$subject = new LostPassword();

		ob_start();

		$subject->add_captcha();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test verify().
	 */
	public function test_verify(): void {
		$validation_error           = new WP_Error( 'some error' );
		$expected                   = clone $validation_error;
		$_POST['wc_reset_password'] = 'true';

		$this->prepare_verify_post( 'hcaptcha_wc_lost_password_nonce', 'hcaptcha_wc_lost_password' );
		$this->prepare_widget_id();

		$subject = new LostPassword();
		$subject->verify( $validation_error );

		self::assertEquals( $expected, $validation_error );
	}

	/**
	 * Test verify() not verified.
	 */
	public function test_verify_not_verified(): void {
		$validation_error           = new WP_Error( 'some error' );
		$expected                   = clone $validation_error;
		$_POST['wc_reset_password'] = 'true';

		$expected->add( 'fail', 'The hCaptcha is invalid.' );

		$this->prepare_verify_post( 'hcaptcha_wc_lost_password_nonce', 'hcaptcha_wc_lost_password', false );
		$this->prepare_widget_id();

		$subject = new LostPassword();
		$subject->verify( $validation_error );

		self::assertEquals( $expected, $validation_error );
	}

	/**
	 * Test verify() when widget id is bad.
	 */
	public function test_verify_bad_widget_id(): void {
		$validation_error           = new WP_Error( 'some error' );
		$expected                   = clone $validation_error;
		$_POST['wc_reset_password'] = 'true';

		$expected->add( 'bad-signature', 'Bad hCaptcha signature!' );

		$this->prepare_verify_post( 'hcaptcha_wc_lost_password_nonce', 'hcaptcha_wc_lost_password' );
		$this->prepare_widget_id(
			[
				'source'  => [ 'WordPress' ],
				'form_id' => 'lost_password',
			]
		);

		$subject = new LostPassword();
		$subject->verify( $validation_error );

		self::assertEquals( $expected, $validation_error );
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
	.woocommerce-ResetPassword .h-captcha {
		margin-top: 0.5rem;
	}
CSS;
		$expected = "<style>\n$expected\n</style>\n";

		$subject = new LostPassword();

		ob_start();

		$subject->print_inline_styles();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Prepare widget id.
	 *
	 * @param array $id The hCaptcha widget id.
	 */
	private function prepare_widget_id( array $id = [] ): void {
		$id = $id ?: [
			'source'  => [ 'woocommerce/woocommerce.php' ],
			'form_id' => 'lost_password',
		];

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value( $id );
	}
}
