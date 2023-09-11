<?php
/**
 * RequestTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\Includes;

use HCaptcha\Tests\Integration\HCaptchaWPTestCase;

/**
 * Test request file.
 *
 * @group request
 */
class RequestTest extends HCaptchaWPTestCase {

	/**
	 * Tear down the test.
	 *
	 * @noinspection PhpLanguageLevelInspection
	 * @noinspection PhpUndefinedClassInspection
	 */
	public function tearDown(): void { // phpcs:ignore PHPCompatibility.FunctionDeclarations.NewReturnTypeDeclarations.voidFound
		unset(
			$_SERVER['HTTP_CF_CONNECTING_IP'],
			$_SERVER['HTTP_CLIENT_IP'],
			$_SERVER['HTTP_X_FORWARDED_FOR'],
			$_SERVER['HTTP_X_FORWARDED'],
			$_SERVER['HTTP_X_CLUSTER_CLIENT_IP'],
			$_SERVER['HTTP_FORWARDED_FOR'],
			$_SERVER['HTTP_FORWARDED'],
			$_SERVER['REMOTE_ADDR'],
			$GLOBALS['current_user']
		);

		parent::tearDown();
	}

	/**
	 * Test hcap_get_user_ip().
	 *
	 * @param array        $headers  $_SERVER headers.
	 * @param string|false $expected User IP.
	 *
	 * @dataProvider dp_test_hcap_get_user_ip
	 */
	public function test_hcap_get_user_ip( array $headers, $expected ) {
		unset(
			$_SERVER['HTTP_CF_CONNECTING_IP'],
			$_SERVER['HTTP_CLIENT_IP'],
			$_SERVER['HTTP_X_FORWARDED_FOR'],
			$_SERVER['HTTP_X_FORWARDED'],
			$_SERVER['HTTP_X_CLUSTER_CLIENT_IP'],
			$_SERVER['HTTP_FORWARDED_FOR'],
			$_SERVER['HTTP_FORWARDED'],
			$_SERVER['REMOTE_ADDR']
		);

		foreach ( $headers as $header => $ip ) {
			$_SERVER[ $header ] = $ip;
		}

		self::assertSame( $expected, hcap_get_user_ip() );
	}

	/**
	 * Data provider for test_hcap_get_user_ip().
	 */
	public function dp_test_hcap_get_user_ip(): array {
		return [
			'HTTP_CF_CONNECTING_IP'    => [
				[ 'HTTP_CLIENT_IP' => '7.7.7.1' ],
				'7.7.7.1',
			],
			'HTTP_CLIENT_IP'           => [
				[ 'HTTP_CLIENT_IP' => '7.7.7.2' ],
				'7.7.7.2',
			],
			'HTTP_X_FORWARDED_FOR'     => [
				[ 'HTTP_X_FORWARDED_FOR' => '7.7.7.3' ],
				'7.7.7.3',
			],
			'HTTP_X_FORWARDED'         => [
				[ 'HTTP_X_FORWARDED' => '7.7.7.4' ],
				'7.7.7.4',
			],
			'HTTP_X_CLUSTER_CLIENT_IP' => [
				[ 'HTTP_X_CLUSTER_CLIENT_IP' => '7.7.7.5' ],
				'7.7.7.5',
			],
			'HTTP_FORWARDED_FOR'       => [
				[ 'HTTP_FORWARDED_FOR' => '7.7.7.6' ],
				'7.7.7.6',
			],
			'HTTP_FORWARDED'           => [
				[ 'HTTP_FORWARDED' => '7.7.7.7' ],
				'7.7.7.7',
			],
			'REMOTE_ADDR'              => [
				[ 'REMOTE_ADDR' => '7.7.7.8' ],
				'7.7.7.8',
			],
			'Order'                    => [
				[
					'HTTP_FORWARDED' => '7.7.7.9',
					'REMOTE_ADDR'    => '7.7.7.10',
				],
				'7.7.7.9',
			],
			'empty'                    => [
				[],
				false,
			],
			'zero IPv4'                => [
				[ 'REMOTE_ADDR' => '0.0.0.0' ],
				false,
			],
			'local IPv4'               => [
				[ 'REMOTE_ADDR' => '127.0.0.1' ],
				false,
			],
			'empty IPv6'               => [
				[ 'REMOTE_ADDR' => '::' ],
				false,
			],
			'address chain'            => [
				[ 'REMOTE_ADDR' => '7.7.7.11, 7.7.7.12' ],
				'7.7.7.11',
			],
		];
	}

	/**
	 * Test hcaptcha_request_verify().
	 */
	public function test_hcaptcha_request_verify() {
		$hcaptcha_response = 'some response';

		$this->prepare_hcaptcha_request_verify( $hcaptcha_response );

		self::assertNull( hcaptcha_request_verify( $hcaptcha_response ) );
	}

	/**
	 * Test hcaptcha_request_verify() with empty string as argument.
	 */
	public function test_hcaptcha_request_verify_empty() {
		self::assertSame(
			'Please complete the hCaptcha.',
			hcaptcha_request_verify( '' )
		);
	}

	/**
	 * Test hcaptcha_request_verify() with not empty string as argument.
	 */
	public function test_hcaptcha_request_verify_not_empty() {
		self::assertSame(
			'hCaptcha error: The response parameter (verification token) is invalid or malformed.',
			hcaptcha_request_verify( 'some response' )
		);
	}

	/**
	 * Test hcaptcha_request_verify() not verified.
	 */
	public function test_hcaptcha_request_verify_not_verified() {
		$hcaptcha_response = 'some response';

		$this->prepare_hcaptcha_request_verify( $hcaptcha_response, false );

		self::assertSame( 'The hCaptcha is invalid.', hcaptcha_request_verify( $hcaptcha_response ) );
	}

	/**
	 * Test hcaptcha_request_verify() not verified with empty body.
	 */
	public function test_hcaptcha_request_verify_not_verified_empty_body() {
		$hcaptcha_response = 'some response';

		$this->prepare_hcaptcha_request_verify( $hcaptcha_response, null );

		self::assertSame( 'The hCaptcha is invalid.', hcaptcha_request_verify( $hcaptcha_response ) );
	}

	/**
	 * Test hcaptcha_verify_POST() with no argument.
	 */
	public function test_hcaptcha_verify_POST_default_success() {
		$hcaptcha_response = 'some response';

		$this->prepare_hcaptcha_request_verify( $hcaptcha_response );

		self::assertNull( hcaptcha_verify_post() );
	}

	/**
	 * Test hcaptcha_verify_POST() with no argument.
	 */
	public function test_hcaptcha_verify_POST_default_empty() {
		self::assertSame( 'Please complete the hCaptcha.', hcaptcha_verify_post() );
	}

	/**
	 * Test hcaptcha_verify_POST().
	 */
	public function test_hcaptcha_verify_POST() {
		$nonce_field_name  = 'some nonce field';
		$nonce_action_name = 'some nonce action';

		// Not logged in.
		$this->prepare_hcaptcha_verify_POST( $nonce_field_name, $nonce_action_name );

		self::assertNull( hcaptcha_verify_post( $nonce_field_name, $nonce_action_name ) );

		// Logged in.
		wp_set_current_user( 1 );

		$this->prepare_hcaptcha_verify_POST( $nonce_field_name, $nonce_action_name );

		self::assertNull( hcaptcha_verify_post( $nonce_field_name, $nonce_action_name ) );
	}

	/**
	 * Test hcaptcha_verify_POST() not verified.
	 */
	public function test_hcaptcha_verify_POST_not_verified() {
		$nonce_field_name  = 'some nonce field';
		$nonce_action_name = 'some nonce action';

		$this->prepare_hcaptcha_verify_POST( $nonce_field_name, $nonce_action_name, false );

		self::assertSame( 'The hCaptcha is invalid.', hcaptcha_verify_post( $nonce_field_name, $nonce_action_name ) );
	}

	/**
	 * Test hcaptcha_verify_POST() not verified with empty POST.
	 */
	public function test_hcaptcha_verify_POST_not_verified_empty_POST() {
		$nonce_field_name  = 'some nonce field';
		$nonce_action_name = 'some nonce action';

		$this->prepare_hcaptcha_verify_POST( $nonce_field_name, $nonce_action_name, null );

		self::assertSame( 'Please complete the hCaptcha.', hcaptcha_verify_post( $nonce_field_name, $nonce_action_name ) );
	}

	/**
	 * Test hcaptcha_verify_POST() not verified with logged-in user.
	 */
	public function test_hcaptcha_verify_POST_not_verified_logged_in() {
		$nonce_field_name  = 'some nonce field';
		$nonce_action_name = 'some nonce action';

		$_POST[ $nonce_field_name ]  = 'wrong nonce';
		$_POST['h-captcha-response'] = 'some response';

		wp_set_current_user( 1 );

		self::assertSame( 'Bad hCaptcha nonce!', hcaptcha_verify_post( $nonce_field_name, $nonce_action_name ) );
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

		self::assertSame( 'The hCaptcha is invalid.', hcaptcha_get_verify_output( $empty_message, $fail_message, $nonce_field_name, $nonce_action_name ) );
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

		self::assertSame( 'Please complete the hCaptcha.', hcaptcha_get_verify_output( $empty_message, $fail_message, $nonce_field_name, $nonce_action_name ) );
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

		self::assertSame( 'The hCaptcha is invalid.', hcaptcha_get_verify_message( $nonce_field_name, $nonce_action_name ) );
	}

	/**
	 * Test hcaptcha_get_verify_message() not validated with empty POST.
	 */
	public function test_hcaptcha_get_verify_message_not_validated_empty_POST() {
		$nonce_field_name  = 'some nonce field';
		$nonce_action_name = 'some nonce action';

		$this->prepare_hcaptcha_get_verify_message( $nonce_field_name, $nonce_action_name, null );

		self::assertSame( 'Please complete the hCaptcha.', hcaptcha_get_verify_message( $nonce_field_name, $nonce_action_name ) );
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

		self::assertSame(
			'<strong>hCaptcha error:</strong> The hCaptcha is invalid.',
			hcaptcha_get_verify_message_html( $nonce_field_name, $nonce_action_name )
		);
	}

	/**
	 * Test hcaptcha_get_verify_message_html() not validated with empty POST.
	 */
	public function test_hcaptcha_get_verify_message_html_not_validated_empty_POST() {
		$nonce_field_name  = 'some nonce field';
		$nonce_action_name = 'some nonce action';

		$this->prepare_hcaptcha_get_verify_message_html( $nonce_field_name, $nonce_action_name, null );

		self::assertSame( '<strong>hCaptcha error:</strong> Please complete the hCaptcha.', hcaptcha_get_verify_message_html( $nonce_field_name, $nonce_action_name ) );
	}

	/**
	 * Test hcap_hcaptcha_error_message().
	 */
	public function test_hcap_hcaptcha_error_message() {
		$hcaptcha_content = 'Some content';
		$expected         = '<p id="hcap_error" class="error hcap_error">The hCaptcha is invalid.</p>' . $hcaptcha_content;

		self::assertSame( $expected, hcap_hcaptcha_error_message( $hcaptcha_content ) );
	}
}
