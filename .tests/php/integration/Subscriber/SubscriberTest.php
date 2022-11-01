<?php
/**
 * SubscriberTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\Subscriber;

use HCaptcha\Tests\Integration\HCaptchaWPTestCase;

/**
 * Test subscriber.php file.
 *
 * @group subscriber
 */
class SubscriberTest extends HCaptchaWPTestCase {

	/**
	 * Tests hcap_subscriber_form().
	 */
	public function test_hcap_subscriber_form() {
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

		self::assertSame( $expected, hcap_subscriber_form( $content ) );
	}

	/**
	 * Test hcap_subscriber_verify().
	 */
	public function test_hcap_subscriber_verify() {
		$this->prepare_hcaptcha_get_verify_message( 'hcaptcha_subscriber_form_nonce', 'hcaptcha_subscriber_form' );

		self::assertTrue( hcap_subscriber_verify() );
		self::assertTrue( hcap_subscriber_verify( true ) );
		self::assertFalse( hcap_subscriber_verify( false ) );
	}

	/**
	 * Test hcap_subscriber_verify() not verified.
	 */
	public function test_hcap_subscriber_verify_not_verified() {
		$this->prepare_hcaptcha_get_verify_message( 'hcaptcha_subscriber_form_nonce', 'hcaptcha_subscriber_form', false );

		self::assertSame( 'The hCaptcha is invalid.', hcap_subscriber_verify() );
	}
}
