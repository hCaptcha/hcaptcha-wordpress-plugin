<?php
/**
 * FormSubmitTimeTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\Helpers;

use Exception;
use HCaptcha\Helpers\FormSubmitTime;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use ReflectionException;
use WP_Error;

/**
 * Test FormSubmitTime class.
 *
 * @group helpers
 * @group helpers-fst
 */
class FormSubmitTimeTest extends HCaptchaWPTestCase {

	/**
	 * FormSubmitTime instance.
	 *
	 * @var FormSubmitTime
	 */
	private FormSubmitTime $subject;

	/**
	 * Set up the test.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->subject = new FormSubmitTime();
	}

	/**
	 * Tear down the test.
	 */
	public function tearDown(): void {
		unset( $_POST['nonce'], $_POST['postId'], $_POST['hcap_fst_token'] );

		parent::tearDown();
	}

	/**
	 * Test init_hooks().
	 */
	public function test_init_hooks(): void {
		self::assertEquals( 10, has_action( 'admin_enqueue_scripts', [ $this->subject, 'enqueue_scripts' ] ) );
		self::assertEquals( 9, has_action( 'wp_print_footer_scripts', [ $this->subject, 'enqueue_scripts' ] ) );
		self::assertEquals( 10, has_action( 'wp_ajax_nopriv_hcaptcha-fst-issue-token', [ $this->subject, 'issue_token' ] ) );
		self::assertEquals( 10, has_action( 'wp_ajax_hcaptcha-fst-issue-token', [ $this->subject, 'issue_token' ] ) );
	}

	/**
	 * Test enqueue_scripts().
	 */
	public function test_enqueue_scripts(): void {
		// 1. Test when scripts should not be printed (form not shown).
		hcaptcha()->form_shown = false;
		update_option( 'hcaptcha_settings', [ 'set_min_submit_time' => 'on' ] );
		hcaptcha()->init_hooks();

		wp_dequeue_script( 'hcaptcha-fst' );
		$this->subject->enqueue_scripts();
		self::assertFalse( wp_script_is( 'hcaptcha-fst' ) );

		// 2. Test when set_min_submit_time is off.
		hcaptcha()->form_shown = true;
		update_option( 'hcaptcha_settings', [ 'set_min_submit_time' => '' ] );
		hcaptcha()->init_hooks();

		wp_dequeue_script( 'hcaptcha-fst' );
		$this->subject->enqueue_scripts();
		self::assertFalse( wp_script_is( 'hcaptcha-fst' ) );

		// 3. Test when both are on.
		hcaptcha()->form_shown = true;
		update_option( 'hcaptcha_settings', [ 'set_min_submit_time' => 'on' ] );
		hcaptcha()->init_hooks();

		wp_dequeue_script( 'hcaptcha-fst' );
		$this->subject->enqueue_scripts();
		self::assertTrue( wp_script_is( 'hcaptcha-fst' ) );

		$localized_data = wp_scripts()->get_data( 'hcaptcha-fst', 'data' );
		self::assertStringContainsString( 'HCaptchaFSTObject', $localized_data );
		self::assertStringContainsString( 'hcaptcha-fst-issue-token', $localized_data );

		wp_dequeue_script( 'hcaptcha-fst' );
	}

	/**
	 * Test verify_token() too fast.
	 */
	public function test_verify_token_too_fast(): void {
		$payload = [
			'post_id'   => 123,
			'issued_at' => time(),
			'ttl'       => 600,
		];

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$data      = base64_encode( wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
		$signature = wp_hash( $data );
		$token     = $data . '-' . $signature;

		$_POST['hcap_fst_token'] = $token;
		set_transient( 'hcap_fst_nonce_' . $signature, $payload, 600 );

		$result = $this->subject->verify_token( 5 ); // 5 seconds min submit time.

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertEquals( 'fst-too-fast', $result->get_error_code() );
	}

	/**
	 * Test verify_token() expired.
	 */
	public function test_verify_token_expired(): void {
		$payload = [
			'post_id'   => 123,
			'issued_at' => time() - 700,
			'ttl'       => 600,
		];

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$data      = base64_encode( wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
		$signature = wp_hash( $data );
		$token     = $data . '-' . $signature;

		$_POST['hcap_fst_token'] = $token;

		// Even if transient exists, it should fail if issued_at + ttl < now.
		set_transient( 'hcap_fst_nonce_' . $signature, $payload, 1000 );

		$result = $this->subject->verify_token( 0 );

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertEquals( 'fst-expired', $result->get_error_code() );
	}

	/**
	 * Test verify_token() success.
	 */
	public function test_verify_token_success(): void {
		$payload = [
			'post_id'   => 123,
			'issued_at' => time() - 10,
			'ttl'       => 600,
		];

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$data      = base64_encode( wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
		$signature = wp_hash( $data );
		$token     = $data . '-' . $signature;

		$_POST['hcap_fst_token'] = $token;
		set_transient( 'hcap_fst_nonce_' . $signature, $payload, 600 );

		$result = $this->subject->verify_token( 5 );

		self::assertTrue( $result );
		// Transient should be deleted by default.
		self::assertFalse( get_transient( 'hcap_fst_nonce_' . $signature ) );
	}

	/**
	 * Test verify_token() with an invalid signature.
	 */
	public function test_verify_token_invalid_signature(): void {
		$_POST['hcap_fst_token'] = 'invalid-token';

		$result = $this->subject->verify_token( 0 );
		self::assertInstanceOf( WP_Error::class, $result );
		self::assertEquals( 'fst_bad_sig', $result->get_error_code() );
	}

	/**
	 * Test verify_token() replayed or missing transient.
	 */
	public function test_verify_token_missing_transient(): void {
		$payload = [
			'post_id'   => 123,
			'issued_at' => time() - 10,
			'ttl'       => 600,
		];

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$data      = base64_encode( wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
		$signature = wp_hash( $data );
		$token     = $data . '-' . $signature;

		$_POST['hcap_fst_token'] = $token;
		// Do not set transient.

		$result = $this->subject->verify_token( 0 );

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertEquals( 'fst-replayed-or-expired', $result->get_error_code() );
	}

	/**
	 * Test token_from_payload and payload_from_token private methods.
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_token_payload_cycle(): void {
		$token_method   = $this->set_method_accessibility( $this->subject, 'token_from_payload' );
		$payload_method = $this->set_method_accessibility( $this->subject, 'payload_from_token' );

		$payload = [
			'post_id'   => 456,
			'issued_at' => 123456789,
			'ttl'       => 300,
		];

		$token = $token_method->invoke( $this->subject, $payload );
		self::assertStringContainsString( '-', $token );

		$decoded_payload = $payload_method->invoke( $this->subject, $token );
		self::assertEquals( $payload, $decoded_payload );
	}

	/**
	 * Test payload_from_token with an invalid signature.
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_payload_from_token_invalid_sig(): void {
		$payload_method = $this->set_method_accessibility( $this->subject, 'payload_from_token' );

		$token  = 'some-data-invalid-sig';
		$result = $payload_method->invoke( $this->subject, $token );

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertEquals( 'fst_bad_sig', $result->get_error_code() );
	}

	/**
	 * Test payload_from_token with bad base64 data.
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_payload_from_token_bad_b64(): void {
		$payload_method = $this->set_method_accessibility( $this->subject, 'payload_from_token' );

		// Invalid base64 character: '#'.
		$data      = 'invalid#base64';
		$signature = wp_hash( $data );
		$token     = $data . '-' . $signature;

		$result = $payload_method->invoke( $this->subject, $token );

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertEquals( 'fst_bad_b64', $result->get_error_code() );
	}

	/**
	 * Test payload_from_token with a bad payload (invalid JSON).
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_payload_from_token_bad_payload(): void {
		$payload_method = $this->set_method_accessibility( $this->subject, 'payload_from_token' );

		// Valid base64, but invalid JSON content.
		// base64_encode('{invalid-json}') = 'e2ludmFsaWQtanNvbn0='.
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$data      = base64_encode( '{invalid-json}' );
		$signature = wp_hash( $data );
		$token     = $data . '-' . $signature;

		$result = $payload_method->invoke( $this->subject, $token );

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertEquals( 'fst_bad_payload', $result->get_error_code() );

		// Also test empty array payload.
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$data      = base64_encode( '[]' );
		$signature = wp_hash( $data );
		$token     = $data . '-' . $signature;

		$result = $payload_method->invoke( $this->subject, $token );

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertEquals( 'fst_bad_payload', $result->get_error_code() );
	}

	/**
	 * Test parse_token private method.
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_parse_token(): void {
		$method = $this->set_method_accessibility( $this->subject, 'parse_token' );

		$result = $method->invoke( $this->subject, 'data-sig' );
		self::assertEquals( [ 'data', 'sig' ], $result );

		$result = $method->invoke( $this->subject, 'data-sig-more' );
		self::assertEquals( [ 'data', 'sig-more' ], $result );

		$result = $method->invoke( $this->subject, 'no_sig' );
		self::assertEquals( [ 'no_sig', '' ], $result );
	}

	/**
	 * Test issue_token() success.
	 */
	public function test_issue_token_success(): void {
		$action = 'hcaptcha-fst-issue-token';
		$nonce  = wp_create_nonce( $action );

		$_POST['nonce']    = $nonce;
		$_REQUEST['nonce'] = $nonce; // The check_ajax_referer uses $_REQUEST.
		$_POST['postId']   = '123';

		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter(
			'wp_die_ajax_handler',
			static function () {
				return static function () {
					throw new Exception( 'wp_die' );
				};
			}
		);

		ob_start();

		try {
			$this->subject->issue_token();
		} catch ( Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// Catch die statement.
		}

		$json = ob_get_clean();

		$response = json_decode( $json, true );

		self::assertIsArray( $response, "Output was: '$json'" );
		self::assertTrue( $response['success'], "Output was: '$json'" );
		self::assertArrayHasKey( 'token', $response['data'] );

		$token = $response['data']['token'];

		self::assertStringContainsString( '-', $token );

		$signature     = explode( '-', $token )[1];
		$transient_key = 'hcap_fst_nonce_' . $signature;
		$transient_val = get_transient( $transient_key );

		self::assertNotFalse( $transient_val );
		self::assertEquals( '123', $transient_val['post_id'] );

		delete_transient( $transient_key );
	}

	/**
	 * Test issue_token() invalid nonce.
	 */
	public function test_issue_token_invalid_nonce(): void {
		$_POST['nonce']    = 'invalid-nonce';
		$_REQUEST['nonce'] = 'invalid-nonce';

		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter(
			'wp_die_ajax_handler',
			static function () {
				return static function () {
					throw new Exception( 'wp_die' );
				};
			}
		);

		ob_start();

		try {
			$this->subject->issue_token();
		} catch ( Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// Catch die statement.
		}

		$json = ob_get_clean();

		$response = json_decode( $json, true );

		self::assertIsArray( $response, "Output was: '$json'" );
		self::assertFalse( $response['success'], "Output was: '$json'" );
	}
}
