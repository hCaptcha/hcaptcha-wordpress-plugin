<?php
/**
 * RequestTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\Common;

use HCaptcha\Tests\Integration\HCaptchaWPTestCase;

/**
 * Test lost-password-form file.
 */
class RequestTest extends HCaptchaWPTestCase {

	public function tearDown(): void {
		unset( $_REQUEST['h-captcha-response'] );

		parent::tearDown();
	}

	/**
	 * Test hcaptcha_request_verify().
	 */
	public function test_hcaptcha_request_verify() {
		$hcaptcha_response = 'some response';

		$this->prepare_hcaptcha_request_verify( $hcaptcha_response );

		self::assertSame( 'success', hcaptcha_request_verify( $hcaptcha_response ) );
	}

	/**
	 * Test hcaptcha_request_verify() with no argument.
	 */
	public function test_hcaptcha_request_verify_default_success() {
		$hcaptcha_response = 'some response';

		$_REQUEST['h-captcha-response'] = $hcaptcha_response;

		$this->prepare_hcaptcha_request_verify( $hcaptcha_response );

		self::assertSame( 'success', hcaptcha_request_verify() );
	}

	/**
	 * Test hcaptcha_request_verify() with no argument.
	 */
	public function test_hcaptcha_request_verify_default_fail() {
		self::assertSame( 'fail', hcaptcha_request_verify() );
	}

	/**
	 * Test hcaptcha_request_verify() not verified.
	 */
	public function test_hcaptcha_request_verify_not_verified() {
		$hcaptcha_response = 'some response';

		$this->prepare_hcaptcha_request_verify( $hcaptcha_response, false );

		self::assertSame( 'fail', hcaptcha_request_verify( $hcaptcha_response ) );
	}

	/**
	 * Test hcaptcha_request_verify() not verified with empty body.
	 */
	public function test_hcaptcha_request_verify_not_verified_empty_body() {
		$hcaptcha_response = 'some response';

		$this->prepare_hcaptcha_request_verify( $hcaptcha_response, null );

		self::assertSame( 'fail', hcaptcha_request_verify( $hcaptcha_response ) );
	}

	/**
	 * Test hcaptcha_verify_POST().
	 */
	public function test_hcaptcha_verify_POST() {
		$nonce_field_name  = 'some nonce field';
		$nonce_action_name = 'some nonce action';

		$this->prepare_hcaptcha_verify_POST( $nonce_field_name, $nonce_action_name );

		self::assertSame( 'success', hcaptcha_verify_POST( $nonce_field_name, $nonce_action_name ) );
	}

	/**
	 * Test hcaptcha_verify_POST() not verified.
	 */
	public function test_hcaptcha_verify_POST_not_verified() {
		$nonce_field_name  = 'some nonce field';
		$nonce_action_name = 'some nonce action';

		$this->prepare_hcaptcha_verify_POST( $nonce_field_name, $nonce_action_name, false );

		self::assertSame( 'fail', hcaptcha_verify_POST( $nonce_field_name, $nonce_action_name ) );
	}

	/**
	 * Test hcaptcha_verify_POST() not verified with empty POST.
	 */
	public function test_hcaptcha_verify_POST_not_verified_empty_POST() {
		$nonce_field_name  = 'some nonce field';
		$nonce_action_name = 'some nonce action';

		$this->prepare_hcaptcha_verify_POST( $nonce_field_name, $nonce_action_name, null );

		self::assertSame( 'empty', hcaptcha_verify_POST( $nonce_field_name, $nonce_action_name ) );
	}

	/**
	 * Test hcaptcha_get_verify_output().
	 */
	public function test_hcaptcha_get_verify_output() {
		$empty_message     = 'some empty message';
		$fail_message      = 'some fail message';
		$nonce_field_name  = 'some nonce field';
		$nonce_action_name = 'some nonce action';

		$this->prepare_hcaptcha_verify_POST( $nonce_field_name, $nonce_action_name );

		self::assertNull( hcaptcha_get_verify_output( $empty_message, $fail_message, $nonce_field_name, $nonce_action_name ) );
	}

	/**
	 * Test hcaptcha_get_verify_output() not validated.
	 */
	public function test_hcaptcha_get_verify_output_not_validated() {
		$empty_message     = 'some empty message';
		$fail_message      = 'some fail message';
		$nonce_field_name  = 'some nonce field';
		$nonce_action_name = 'some nonce action';

		$this->prepare_hcaptcha_verify_POST( $nonce_field_name, $nonce_action_name, false );

		self::assertSame( $fail_message, hcaptcha_get_verify_output( $empty_message, $fail_message, $nonce_field_name, $nonce_action_name ) );
	}

	/**
	 * Test hcaptcha_get_verify_output() not validated with empty_POST.
	 */
	public function test_hcaptcha_get_verify_output_not_validated_empty_POST() {
		$empty_message     = 'some empty message';
		$fail_message      = 'some fail message';
		$nonce_field_name  = 'some nonce field';
		$nonce_action_name = 'some nonce action';

		$this->prepare_hcaptcha_verify_POST( $nonce_field_name, $nonce_action_name, null );

		self::assertSame( $empty_message, hcaptcha_get_verify_output( $empty_message, $fail_message, $nonce_field_name, $nonce_action_name ) );
	}

	/**
	 * Test hcaptcha_get_verify_message().
	 */
	public function test_hcaptcha_get_verify_message() {
		$nonce_field_name  = 'some nonce field';
		$nonce_action_name = 'some nonce action';

		$this->prepare_hcaptcha_get_verify_message( $nonce_field_name, $nonce_action_name );

		self::assertNull( hcaptcha_get_verify_message( $nonce_field_name, $nonce_action_name ) );
	}

	/**
	 * Test hcaptcha_get_verify_message() not validated.
	 */
	public function test_hcaptcha_get_verify_message_not_validated() {
		$nonce_field_name  = 'some nonce field';
		$nonce_action_name = 'some nonce action';

		$this->prepare_hcaptcha_get_verify_message( $nonce_field_name, $nonce_action_name, false );

		self::assertSame( 'The Captcha is invalid.', hcaptcha_get_verify_message( $nonce_field_name, $nonce_action_name ) );
	}

	/**
	 * Test hcaptcha_get_verify_message() not validated with empty POST.
	 */
	public function test_hcaptcha_get_verify_message_not_validated_empty_POST() {
		$nonce_field_name  = 'some nonce field';
		$nonce_action_name = 'some nonce action';

		$this->prepare_hcaptcha_get_verify_message( $nonce_field_name, $nonce_action_name, null );

		self::assertSame( 'Please complete the captcha.', hcaptcha_get_verify_message( $nonce_field_name, $nonce_action_name ) );
	}

	/**
	 * Test hcaptcha_get_verify_message_html().
	 */
	public function test_hcaptcha_get_verify_message_html() {
		$nonce_field_name  = 'some nonce field';
		$nonce_action_name = 'some nonce action';

		$this->prepare_hcaptcha_get_verify_message_html( $nonce_field_name, $nonce_action_name );

		self::assertNull( hcaptcha_get_verify_message_html( $nonce_field_name, $nonce_action_name ) );
	}

	/**
	 * Test hcaptcha_get_verify_message_html() not validated.
	 */
	public function test_hcaptcha_get_verify_message_html_not_validated() {
		$nonce_field_name  = 'some nonce field';
		$nonce_action_name = 'some nonce action';

		$this->prepare_hcaptcha_get_verify_message_html( $nonce_field_name, $nonce_action_name, false );

		self::assertSame( '<strong>Error</strong>: The Captcha is invalid.', hcaptcha_get_verify_message_html( $nonce_field_name, $nonce_action_name ) );
	}

	/**
	 * Test hcaptcha_get_verify_message_html() not validated with empty POST.
	 */
	public function test_hcaptcha_get_verify_message_html_not_validated_empty_POST() {
		$nonce_field_name  = 'some nonce field';
		$nonce_action_name = 'some nonce action';

		$this->prepare_hcaptcha_get_verify_message_html( $nonce_field_name, $nonce_action_name, null );

		self::assertSame( '<strong>Error</strong>: Please complete the captcha.', hcaptcha_get_verify_message_html( $nonce_field_name, $nonce_action_name ) );
	}
}
