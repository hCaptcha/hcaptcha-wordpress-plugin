<?php
/**
 * LostPasswordTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\WC;

use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use HCaptcha\WC\LostPassword;
use tad\FunctionMocker\FunctionMocker;

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
}
