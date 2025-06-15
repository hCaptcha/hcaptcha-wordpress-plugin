<?php
/**
 * APITest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\Helpers;

use HCaptcha\Helpers\API;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;

/**
 * Test API class.
 *
 * @group helpers
 * @group helpers-api
 */
class APITest extends HCaptchaWPTestCase {
	/**
	 * Test request_verify().
	 */
	public function test_hcaptcha_request_verify(): void {
		$hcaptcha_response = 'some response';

		$this->prepare_hcaptcha_request_verify( $hcaptcha_response );

		self::assertNull( API::request_verify( $hcaptcha_response ) );
	}

	/**
	 * Test request_verify() when protection is not enabled.
	 */
	public function test_hcaptcha_request_verify_when_protection_not_enabled(): void {
		$hcaptcha_response = 'some response';

		add_filter( 'hcap_protect_form', '__return_false' );

		self::assertNull( API::request_verify( $hcaptcha_response ) );
	}

	/**
	 * Test request_verify() with empty string as argument.
	 */
	public function test_hcaptcha_request_verify_empty(): void {
		self::assertSame(
			'Please complete the hCaptcha.',
			API::request_verify( '' )
		);
	}

	/**
	 * Test request_verify() not verified.
	 */
	public function test_hcaptcha_request_verify_not_verified(): void {
		$hcaptcha_response = 'some response';

		$this->prepare_hcaptcha_request_verify( $hcaptcha_response, false );

		self::assertSame( 'The hCaptcha is invalid.', API::request_verify( $hcaptcha_response ) );
	}

	/**
	 * Test request_verify() not verified with an empty body.
	 */
	public function test_hcaptcha_request_verify_not_verified_empty_body(): void {
		$hcaptcha_response = 'some response';

		$this->prepare_hcaptcha_request_verify( $hcaptcha_response, null );

		self::assertSame( 'The hCaptcha is invalid.', API::request_verify( $hcaptcha_response ) );
	}
}
