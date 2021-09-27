<?php
/**
 * LostPasswordTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\WC;

use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use HCaptcha\WC\LostPassword;

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
	public function test_constructor_and_init_hooks() {
		$subject = new LostPassword();

		self::assertSame(
			10,
			has_action( 'woocommerce_lostpassword_form', [ $subject, 'add_captcha' ] )
		);
	}

	/**
	 * Test add_captcha().
	 */
	public function test_add_captcha() {
		$expected =
			$this->get_hcap_form() .
			wp_nonce_field( 'hcaptcha_lost_password', 'hcaptcha_lost_password_nonce', true, false );

		$subject = new LostPassword();

		ob_start();

		$subject->add_captcha();

		self::assertSame( $expected, ob_get_clean() );
	}
}
