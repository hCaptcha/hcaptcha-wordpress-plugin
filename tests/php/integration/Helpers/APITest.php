<?php
/**
 * APITest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\Helpers;

use HCaptcha\Helpers\API;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;

/**
 * Test API class.
 *
 * @group helpers
 * @group helpers-api
 */
class APITest extends HCaptchaWPTestCase {
	/**
	 * Test verify_post_html().
	 */
	public function test_verify_post_html(): void {
		$nonce_field_name  = 'some nonce field';
		$nonce_action_name = 'some nonce action';

		$this->prepare_verify_post_html( $nonce_field_name, $nonce_action_name );

		self::assertNull( API::verify_post_html( $nonce_field_name, $nonce_action_name ) );
	}

	/**
	 * Test verify_post_html() not verified.
	 */
	public function test_verify_post_html_not_verified(): void {
		$nonce_field_name  = 'some nonce field';
		$nonce_action_name = 'some nonce action';

		$this->prepare_verify_post_html( $nonce_field_name, $nonce_action_name, false );

		self::assertSame(
			'<strong>hCaptcha error:</strong> The hCaptcha is invalid.',
			API::verify_post_html( $nonce_field_name, $nonce_action_name )
		);
	}

	/**
	 * Test verify_post_html() not verified with empty POST.
	 */
	public function test_verify_post_html_not_verified_empty_POST(): void {
		$nonce_field_name  = 'some nonce field';
		$nonce_action_name = 'some nonce action';

		$this->prepare_verify_post_html( $nonce_field_name, $nonce_action_name, null );

		self::assertSame(
			'<strong>hCaptcha error:</strong> Please complete the hCaptcha.',
			API::verify_post_html( $nonce_field_name, $nonce_action_name )
		);
	}

	/**
	 * Test verify_post() with no argument.
	 */
	public function test_verify_post_default_success(): void {
		$hcaptcha_response = 'some response';

		$this->prepare_verify_request( $hcaptcha_response );

		self::assertNull( API::verify_post() );
	}

	/**
	 * Test verify_post() with no argument.
	 */
	public function test_verify_post_default_empty(): void {
		$this->prepare_verify_request( '', false );

		self::assertSame( 'Please complete the hCaptcha.', API::verify_post() );
	}

	/**
	 * Test verify_post().
	 */
	public function test_verify_post(): void {
		$nonce_field_name  = 'some nonce field';
		$nonce_action_name = 'some nonce action';

		// Not logged in.
		$this->prepare_verify_post( $nonce_field_name, $nonce_action_name );

		self::assertNull( API::verify_post( $nonce_field_name, $nonce_action_name ) );

		// Logged in.
		wp_set_current_user( 1 );

		$this->prepare_verify_post( $nonce_field_name, $nonce_action_name );

		self::assertNull( API::verify_post( $nonce_field_name, $nonce_action_name ) );
	}

	/**
	 * Test verify_post() not verified.
	 */
	public function test_verify_post_not_verified(): void {
		$nonce_field_name  = 'some nonce field';
		$nonce_action_name = 'some nonce action';

		$this->prepare_verify_post( $nonce_field_name, $nonce_action_name, false );

		self::assertSame( 'The hCaptcha is invalid.', API::verify_post( $nonce_field_name, $nonce_action_name ) );
	}

	/**
	 * Test verify_post() not verified with empty POST.
	 */
	public function test_verify_post_not_verified_empty_POST(): void {
		$nonce_field_name  = 'some nonce field';
		$nonce_action_name = 'some nonce action';

		$this->prepare_verify_post( $nonce_field_name, $nonce_action_name, null );

		self::assertSame( 'Please complete the hCaptcha.', API::verify_post( $nonce_field_name, $nonce_action_name ) );
	}

	/**
	 * Test verify_post() not verified with a logged-in user.
	 */
	public function test_verify_post_not_verified_logged_in(): void {
		$nonce_field_name  = 'some nonce field';
		$nonce_action_name = 'some nonce action';

		$_POST[ $nonce_field_name ]  = 'wrong nonce';
		$_POST['h-captcha-response'] = 'some response';

		wp_set_current_user( 1 );

		self::assertSame( 'Bad hCaptcha nonce!', API::verify_post( $nonce_field_name, $nonce_action_name ) );
	}

	/**
	 * Test verify_request().
	 */
	public function test_verify_request(): void {
		$hcaptcha_response = 'some response';

		$this->prepare_verify_request( $hcaptcha_response );

		self::assertNull( API::verify_request( $hcaptcha_response ) );
	}

	/**
	 * Test verify() with expected widget id.
	 */
	public function test_verify_with_expected_widget_id(): void {
		$nonce_field_name  = 'some nonce field';
		$nonce_action_name = 'some nonce action';
		$expected_id       = [
			'source'  => [ 'test/source' ],
			'form_id' => 'test-form',
		];

		$this->prepare_verify_post( $nonce_field_name, $nonce_action_name );

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value( $expected_id );

		self::assertNull(
			API::verify(
				[
					'nonce_name'   => $nonce_field_name,
					'nonce_action' => $nonce_action_name,
					'expected_id'  => $expected_id,
				]
			)
		);
	}

	/**
	 * Test verify() with unexpected widget id.
	 */
	public function test_verify_with_unexpected_widget_id(): void {
		$nonce_field_name  = 'some nonce field';
		$nonce_action_name = 'some nonce action';
		$expected_id       = [
			'source'  => [ 'test/source' ],
			'form_id' => 'test-form',
		];
		$actual_id         = [
			'source'  => [ 'test/source' ],
			'form_id' => 'other-form',
		];

		$this->prepare_verify_post( $nonce_field_name, $nonce_action_name );

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value( $actual_id );

		self::assertSame(
			'Bad hCaptcha signature!',
			API::verify(
				[
					'nonce_name'   => $nonce_field_name,
					'nonce_action' => $nonce_action_name,
					'expected_id'  => $expected_id,
				]
			)
		);
	}

	/**
	 * Test verify() with missing expected widget id.
	 */
	public function test_verify_with_missing_expected_widget_id(): void {
		$nonce_field_name  = 'some nonce field';
		$nonce_action_name = 'some nonce action';
		$expected_id       = [
			'source'  => [],
			'form_id' => 0,
		];

		$this->prepare_verify_post( $nonce_field_name, $nonce_action_name );

		self::assertSame(
			'Bad hCaptcha signature!',
			API::verify(
				[
					'nonce_name'   => $nonce_field_name,
					'nonce_action' => $nonce_action_name,
					'expected_id'  => $expected_id,
				]
			)
		);
	}

	/**
	 * Test verify() cleans post data after unexpected widget id.
	 */
	public function test_verify_cleans_post_data_after_unexpected_widget_id(): void {
		$nonce_field_name  = 'some nonce field';
		$nonce_action_name = 'some nonce action';
		$expected_id       = [
			'source'  => [ 'test/source' ],
			'form_id' => 'test-form',
		];
		$actual_id         = [
			'source'  => [ 'test/source' ],
			'form_id' => 'other-form',
		];
		$post_data         = [
			$nonce_field_name            => wp_create_nonce( $nonce_action_name ),
			'h-captcha-response'         => 'some response',
			HCaptcha::HCAPTCHA_WIDGET_ID => HCaptcha::widget_id_value( $actual_id ),
		];

		self::assertSame(
			'Bad hCaptcha signature!',
			API::verify(
				[
					'nonce_name'   => $nonce_field_name,
					'nonce_action' => $nonce_action_name,
					'post_data'    => $post_data,
					'expected_id'  => $expected_id,
				]
			)
		);

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		self::assertArrayNotHasKey( HCaptcha::HCAPTCHA_WIDGET_ID, $_POST );
	}

	/**
	 * Test verify_request() when protection is not enabled.
	 */
	public function test_verify_request_when_protection_not_enabled(): void {
		$hcaptcha_response = 'some response';

		add_filter( 'hcap_protect_form', '__return_false' );

		self::assertNull( API::verify_request( $hcaptcha_response ) );
	}

	/**
	 * Test verify_request() with empty string as argument.
	 */
	public function test_verify_request_empty(): void {
		$this->prepare_verify_request( '', false );

		self::assertSame(
			'Please complete the hCaptcha.',
			API::verify_request( '' )
		);
	}

	/**
	 * Test verify_request() not verified.
	 */
	public function test_verify_request_not_verified(): void {
		$hcaptcha_response = 'some response';

		$this->prepare_verify_request( $hcaptcha_response, false );

		self::assertSame( 'The hCaptcha is invalid.', API::verify_request( $hcaptcha_response ) );
	}

	/**
	 * Test verify_request() not verified with an empty body.
	 */
	public function test_verify_request_not_verified_empty_body(): void {
		$hcaptcha_response = 'some response';

		$this->prepare_verify_request( $hcaptcha_response, null );

		self::assertSame( 'The hCaptcha is invalid.', API::verify_request( $hcaptcha_response ) );
	}
}
