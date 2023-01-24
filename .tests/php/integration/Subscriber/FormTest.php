<?php
/**
 * FormTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\Subscriber;

use HCaptcha\Subscriber\Form;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;

/**
 * Test Form class.
 *
 * @group subscriber
 */
class FormTest extends HCaptchaWPTestCase {

	/**
	 * Tests add_captcha().
	 */
	public function test_add_captcha() {
		hcaptcha()->init_hooks();

		$content  = '<!--some form content-->';
		$expected =
			$content .
			$this->get_hcap_form() .
			wp_nonce_field(
				'hcaptcha_subscriber_form',
				'hcaptcha_subscriber_form_nonce',
				true,
				false
			);
		$subject  = new Form();

		self::assertSame( $expected, $subject->add_captcha( $content ) );
	}

	/**
	 * Test verify().
	 */
	public function test_verify() {
		$this->prepare_hcaptcha_get_verify_message( 'hcaptcha_subscriber_form_nonce', 'hcaptcha_subscriber_form' );

		$subject = new Form();

		self::assertTrue( $subject->verify( true ) );
		self::assertFalse( $subject->verify( false ) );
	}

	/**
	 * Test verify() not verified.
	 */
	public function test_verify_not_verified() {
		$this->prepare_hcaptcha_get_verify_message( 'hcaptcha_subscriber_form_nonce', 'hcaptcha_subscriber_form', false );

		$subject = new Form();

		self::assertSame( 'The hCaptcha is invalid.', $subject->verify( true ) );
	}
}
