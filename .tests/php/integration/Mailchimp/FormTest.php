<?php
/**
 * FormTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\Mailchimp;

use HCaptcha\Mailchimp\Form;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use Mockery;

/**
 * Test Form class.
 */
class FormTest extends HCaptchaWPTestCase {

	/**
	 * Test add_hcap_error_messages().
	 *
	 * @noinspection PhpParamsInspection
	 */
	public function test_add_hcap_error_messages() {
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
		$subject  = new Form();

		self::assertSame( $expected, $subject->add_hcap_error_messages( $messages, $form ) );
	}

	/**
	 * Test add_captcha().
	 */
	public function test_add_captcha() {
		$content  = '<input type="submit">';
		$expected =
			$this->get_hcap_form() .
			wp_nonce_field( 'hcaptcha_mailchimp', 'hcaptcha_mailchimp_nonce', true, false ) .
			'<input type="submit">';
		$subject  = new Form();

		self::assertSame( $expected, $subject->add_captcha( $content, '', '' ) );
	}

	/**
	 * Test verify().
	 */
	public function test_verify() {
		$this->prepare_hcaptcha_verify_POST( 'hcaptcha_mailchimp_nonce', 'hcaptcha_mailchimp' );

		$subject = new Form();

		self::assertTrue( $subject->verify( true, [] ) );
	}

	/**
	 * Test verify() not verified.
	 */
	public function test_verify_not_verified() {
		$this->prepare_hcaptcha_verify_POST( 'hcaptcha_mailchimp_nonce', 'hcaptcha_mailchimp', false );

		$subject = new Form();

		self::assertSame( 'fail', $subject->verify( true, [] ) );
	}
}
