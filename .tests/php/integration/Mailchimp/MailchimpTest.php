<?php
/**
 * MailchimpTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\Mailchimp;

use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use Mockery;

/**
 * Test mailchimp form file.
 */
class MailchimpTest extends HCaptchaWPTestCase {

	/**
	 * Test hcap_add_mc4wp_error_message().
	 */
	public function test_hcap_add_mc4wp_error_message() {
		$form = Mockery::mock( 'MC4WP_Form' );

		$messages = [
			'foo' => 'bar',
		];

		$expected = [
			'invalid_hcaptcha' =>
				[
					'type' => 'error',
					'text' => 'The Captcha is invalid.',
				],
		];

		$expected = array_merge( $messages, $expected );

		self::assertSame( $expected, hcap_add_mc4wp_error_message( $messages, $form ) );
	}

	/**
	 * Test hcap_mailchimp_wp_form().
	 */
	public function test_hcap_mailchimp_wp_form() {
		$content  = '<input type="submit">';
		$expected =
			$this->get_hcap_form() .
			wp_nonce_field( 'hcaptcha_mailchimp', 'hcaptcha_mailchimp_nonce', true, false ) .
			'<input type="submit">';

			self::assertSame( $expected, hcap_mailchimp_wp_form( $content ) );
	}

	/**
	 * Test hcap_mc4wp_error().
	 */
	public function test_hcap_mc4wp_error() {
		$this->prepare_hcaptcha_verify_POST( 'hcaptcha_mailchimp_nonce', 'hcaptcha_mailchimp' );

		self::assertTrue( hcap_mc4wp_error( true, [] ) );
	}

	/**
	 * Test hcap_mc4wp_error() not verified.
	 */
	public function test_hcap_mc4wp_error_not_verified() {
		$this->prepare_hcaptcha_verify_POST( 'hcaptcha_mailchimp_nonce', 'hcaptcha_mailchimp', false );

		self::assertSame( 'The hCaptcha is invalid.', hcap_mc4wp_error( true, [] ) );
	}
}
