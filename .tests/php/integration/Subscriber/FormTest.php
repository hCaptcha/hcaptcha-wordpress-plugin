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
	public function test_add_captcha(): void {
		hcaptcha()->init_hooks();

		$content  = '<!--some form content-->';
		$args     = [
			'action' => 'hcaptcha_subscriber_form',
			'name'   => 'hcaptcha_subscriber_form_nonce',
			'id'     => [
				'source'  => [ 'subscriber/subscriber.php' ],
				'form_id' => 'form',
			],
		];
		$expected = $content . $this->get_hcap_form( $args );
		$subject  = new Form();

		self::assertSame( $expected, $subject->add_captcha( $content ) );
	}

	/**
	 * Test verify().
	 */
	public function test_verify(): void {
		$this->prepare_verify_post( 'hcaptcha_subscriber_form_nonce', 'hcaptcha_subscriber_form' );

		$subject = new Form();

		self::assertTrue( $subject->verify( true ) );
		self::assertFalse( $subject->verify( false ) );
	}

	/**
	 * Test verify() not verified.
	 */
	public function test_verify_not_verified(): void {
		$this->prepare_verify_post( 'hcaptcha_subscriber_form_nonce', 'hcaptcha_subscriber_form', false );

		$subject = new Form();

		self::assertSame( 'The hCaptcha is invalid.', $subject->verify( true ) );
	}
}
