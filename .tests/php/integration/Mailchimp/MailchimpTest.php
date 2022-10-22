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
			'foo' => [
				'type' => 'notice',
				'text' => 'bar',
			],
		];

		$hcap_errors = [
			'missing-input-secret'             => [
				'type' => 'error',
				'text' => 'Your secret key is missing.',
			],
			'invalid-input-secret'             => [
				'type' => 'error',
				'text' => 'Your secret key is invalid or malformed.',
			],
			'missing-input-response'           => [
				'type' => 'error',
				'text' => 'The response parameter (verification token) is missing.',
			],
			'invalid-input-response'           => [
				'type' => 'error',
				'text' => 'The response parameter (verification token) is invalid or malformed.',
			],
			'bad-request'                      => [
				'type' => 'error',
				'text' => 'The request is invalid or malformed.',
			],
			'invalid-or-already-seen-response' => [
				'type' => 'error',
				'text' => 'The response parameter has already been checked, or has another issue.',
			],
			'not-using-dummy-passcode'         => [
				'type' => 'error',
				'text' => 'You have used a testing sitekey but have not used its matching secret.',
			],
			'sitekey-secret-mismatch'          => [
				'type' => 'error',
				'text' => 'The sitekey is not registered with the provided secret.',
			],
			'empty'                            => [
				'type' => 'error',
				'text' => 'Please complete the hCaptcha.',
			],
			'fail'                             => [
				'type' => 'error',
				'text' => 'The hCaptcha is invalid.',
			],
			'bad-nonce'                        => [
				'type' => 'error',
				'text' => 'Bad hCaptcha nonce!',
			],
		];

		$expected = array_merge( $messages, $hcap_errors );

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

		self::assertSame( 'fail', hcap_mc4wp_error( true, [] ) );
	}
}
