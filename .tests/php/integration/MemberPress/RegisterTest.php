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
	public function test_constructor_and_init_hooks(): void {
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
	public function test_add_captcha(): void {
		$subject = new Register();

		$expected = $this->get_hcap_form(
			[
				'action' => 'hcaptcha_memberpress_register',
				'name'   => 'hcaptcha_memberpress_register_nonce',
				'id'     => [
					'source'  => [ 'memberpress/memberpress.php' ],
					'form_id' => 'register',
				],
			]
		);

		ob_start();
		$subject->add_captcha();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test verify().
	 *
	 * @return void
	 */
	public function test_verify(): void {
		$subject = new Register();

		$errors = [ 'some errors' ];

		$this->prepare_verify_post(
			'hcaptcha_memberpress_register_nonce',
			'hcaptcha_memberpress_register'
		);

		self::assertSame( $errors, $subject->verify( $errors ) );
	}

	/**
	 * Test verify().
	 *
	 * @return void
	 */
	public function test_verify_no_success(): void {
		$subject = new Register();

		$errors        = [ 'some errors' ];
		$error_message = array_merge( $errors, [ 'The hCaptcha is invalid.' ] );

		$this->prepare_verify_post(
			'hcaptcha_memberpress_register_nonce',
			'hcaptcha_memberpress_register',
			false
		);

		self::assertSame( $error_message, $subject->verify( $errors ) );
	}
}
