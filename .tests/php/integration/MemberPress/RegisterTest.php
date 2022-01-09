<?php
/**
 * RegisterTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\MemberPress;

use HCaptcha\MemberPress\Register;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;

/**
 * Test Register class.
 *
 * @group memberpress
 */
class RegisterTest extends HCaptchaWPTestCase {

	/**
	 * Test constructor and init hooks.
	 */
	public function test_constructor_and_init_hooks() {
		$subject = new Register();

		self::assertSame(
			10,
			has_action( 'mepr-checkout-before-submit', [ $subject, 'add_captcha' ] )
		);
		self::assertSame(
			10,
			has_action( 'mepr-validate-signup', [ $subject, 'verify' ] )
		);
	}

	/**
	 * Test add_captcha().
	 */
	public function test_add_captcha() {
		$subject = new Register();

		$expected = $this->get_hcap_form(
			'hcaptcha_memberpress_register',
			'hcaptcha_memberpress_register_nonce'
		);

		ob_start();
		$subject->add_captcha();

		self::assertSame( $expected, ob_get_clean() );
	}
}
