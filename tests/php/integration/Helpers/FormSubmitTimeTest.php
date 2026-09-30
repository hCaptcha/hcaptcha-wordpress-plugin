<?php
/**
 * FormSubmitTimeTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\Helpers;

use Exception;
use HCaptcha\Helpers\FormSubmitTime;
use HCaptcha\Helpers\FormSubmitTimeStore;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use ReflectionException;
use WP_Error;
use wpdb;

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
		FormSubmitTimeStore::delete_all();
	}

	/**
	 * Tear down the test.
	 */
	public function tearDown(): void {
		FormSubmitTimeStore::delete_all();
		unset(
			$_POST['nonce'],
			$_POST['postId'],
			$_POST['context'],
			$_POST['replaceToken'],
			$_POST['hcap_fst_token'],
			$_REQUEST['nonce'],
			$_SERVER['REMOTE_ADDR'],
			$_SERVER['HTTP_X_FORWARDED_FOR']
		);

		parent::tearDown();
	}

	/**
	 * Test init_hooks().
	 */
	public function test_init_hooks(): void {
		self::assertEquals( 9, has_action( 'admin_print_footer_scripts', [ $this->subject, 'enqueue_scripts' ] ) );
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
		ob_start();
		$this->subject->enqueue_scripts();
		$output = ob_get_clean();
		self::assertTrue( wp_script_is( 'hcaptcha-fst' ) );

		self::assertStringContainsString( 'HCaptchaFSTObject', $output );
		self::assertStringContainsString( 'hcaptcha-fst-issue-token', $output );
		self::assertStringContainsString( 'issueTokenContext', $output );
		self::assertStringContainsString( 'postId', $output );

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
			'token_id'  => str_repeat( 'a', 32 ),
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
		self::assertFalse( get_transient( 'hcap_fst_nonce_' . $signature ) );
	}

	/**
	 * Test verify_token() expired.
	 */
	public function test_verify_token_expired(): void {
		$payload = [
			'post_id'   => 123,
			'issued_at' => time() - 700,
			'ttl'       => 600,
			'token_id'  => str_repeat( 'a', 32 ),
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
		self::assertFalse( get_transient( 'hcap_fst_nonce_' . $signature ) );
	}

	/**
	 * Test verify_token() success.
	 */
	public function test_verify_token_success(): void {
		$payload = [
			'post_id'   => 123,
			'issued_at' => time() - 10,
			'ttl'       => 600,
			'token_id'  => str_repeat( 'a', 32 ),
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
	 * Test an outstanding token from a cached pre-upgrade page remains valid.
	 */
	public function test_verify_legacy_token_with_empty_post_id(): void {
		$payload = [
			'post_id'   => '',
			'issued_at' => time() - 1,
			'ttl'       => FormSubmitTime::DEFAULT_TOKEN_TTL,
			'token_id'  => str_repeat( 'l', 32 ),
		];

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$data                       = base64_encode( wp_json_encode( $payload ) );
		$signature                  = wp_hash( $data );
		$_POST['hcap_fst_token']    = $data . '-' . $signature;
		$legacy_transient           = 'hcap_fst_nonce_' . $signature;
		$legacy_transient_was_saved = set_transient( $legacy_transient, $payload, FormSubmitTime::DEFAULT_TOKEN_TTL );

		self::assertTrue( $legacy_transient_was_saved );
		self::assertTrue( $this->subject->verify_token( 0 ) );
		self::assertFalse( get_transient( $legacy_transient ) );
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
	 * Test verify_token() reports a token issuance rate limit.
	 */
	public function test_verify_token_rate_limited(): void {
		$_POST['hcap_fst_token'] = 'hcaptcha-fst-error:fst-rate-limited';

		$result = $this->subject->verify_token( 0 );

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'fst-rate-limited', $result->get_error_code() );
		self::assertSame(
			'Too many requests (429). Please refresh the page and try again.',
			$result->get_error_message()
		);
	}

	/**
	 * Test verify_token() replayed or missing transient.
	 */
	public function test_verify_token_missing_transient(): void {
		$payload = [
			'post_id'   => 123,
			'issued_at' => time() - 10,
			'ttl'       => 600,
			'token_id'  => str_repeat( 'a', 32 ),
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
			'token_id'  => str_repeat( 'a', 32 ),
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

		// Also, test an empty array payload.
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$data      = base64_encode( '[]' );
		$signature = wp_hash( $data );
		$token     = $data . '-' . $signature;

		$result = $payload_method->invoke( $this->subject, $token );

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertEquals( 'fst_bad_payload', $result->get_error_code() );

		// A previously valid deterministic payload without a unique token ID must be rejected.
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$data      = base64_encode(
			wp_json_encode(
				[
					'post_id'   => 123,
					'issued_at' => time(),
					'ttl'       => 600,
				]
			)
		);
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
	 *
	 * @noinspection ThrowRawExceptionInspection
	 * @noinspection JsonEncodingApiUsageInspection
	 */
	public function test_issue_token_success(): void {
		$this->enable_fst();

		$tokens     = [];
		$token_ids  = [];
		$signatures = [];

		for ( $i = 0; $i < 2; $i++ ) {
			$response = $this->issue_token( '123', '198.51.100.10' );

			self::assertTrue( $response['success'], wp_json_encode( $response ) );
			self::assertArrayHasKey( 'token', $response['data'] );

			$token = $response['data']['token'];

			self::assertStringContainsString( '-', $token );

			[ $data, $signature ] = explode( '-', $token, 2 );
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
			$payload = json_decode( base64_decode( $data, true ), true );
			$stored  = ( new FormSubmitTimeStore() )->has( $signature, $payload );

			self::assertTrue( $stored );
			self::assertEquals( '123', $payload['post_id'] );
			self::assertMatchesRegularExpression( '/^[A-Za-z0-9]{32}$/', $payload['token_id'] );

			$tokens[]     = $token;
			$token_ids[]  = $payload['token_id'];
			$signatures[] = $signature;
		}

		self::assertNotSame( $tokens[0], $tokens[1] );
		self::assertNotSame( $token_ids[0], $token_ids[1] );

		foreach ( $tokens as $i => $token ) {
			$_POST['hcap_fst_token'] = $token;

			self::assertTrue( $this->subject->verify_token( 0 ) );
			self::assertFalse( ( new FormSubmitTimeStore() )->has( $signatures[ $i ], $this->payload_from_token( $token ) ) );
		}
	}

	/**
	 * Test one client cannot evade its outstanding cap by varying post IDs.
	 */
	public function test_issue_token_enforces_per_client_cap_across_post_ids(): void {
		$this->enable_fst();

		for ( $i = 0; $i < FormSubmitTimeStore::MAX_PER_CLIENT; ++$i ) {
			$response = $this->issue_token( (string) ( 100 + $i ), '198.51.100.20' );

			self::assertTrue( $response['success'], wp_json_encode( $response ) );
		}

		$denied = $this->issue_token( '999', '198.51.100.20' );

		self::assertFalse( $denied['success'] );
		self::assertSame( 'fst-rate-limited', $denied['data']['code'] );
		self::assertCount( FormSubmitTimeStore::MAX_PER_CLIENT, $this->get_registry() );
	}

	/**
	 * Test untrusted forwarding headers cannot rotate the client identity.
	 */
	public function test_issue_token_uses_trusted_client_identity_resolver(): void {
		$this->enable_fst();

		for ( $i = 0; $i < FormSubmitTimeStore::MAX_PER_CLIENT; ++$i ) {
			$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.' . ( $i + 1 );
			$response                        = $this->issue_token( '1', '198.51.100.25' );

			self::assertTrue( $response['success'], wp_json_encode( $response ) );
		}

		$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.250';
		$denied                          = $this->issue_token( '1', '198.51.100.25' );

		self::assertFalse( $denied['success'] );
		self::assertSame( 'fst-rate-limited', $denied['data']['code'] );
	}

	/**
	 * Test an unresolvable trusted client identity is denied without allocation.
	 */
	public function test_issue_token_rejects_unresolvable_client_identity(): void {
		update_option(
			'hcaptcha_settings',
			[
				'set_min_submit_time'     => 'on',
				'trusted_address_headers' => [ 'HTTP_X_FORWARDED_FOR' ],
			]
		);
		hcaptcha()->init_hooks();

		$_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.1, 198.51.100.2';
		$response                        = $this->issue_token( '1', '192.0.2.1' );

		self::assertFalse( $response['success'] );
		self::assertSame( 'fst-invalid-client', $response['data']['code'] );
		self::assertSame( [], $this->get_registry() );
	}

	/**
	 * Test browser refresh replaces only an outstanding token owned by the client.
	 */
	public function test_issue_token_atomically_replaces_same_client_token(): void {
		$this->enable_fst();

		$first  = $this->issue_token( '1', '198.51.100.26' );
		$second = $this->issue_token( '1', '198.51.100.26', $first['data']['token'] );

		self::assertTrue( $first['success'], wp_json_encode( $first ) );
		self::assertTrue( $second['success'], wp_json_encode( $second ) );
		self::assertCount( 1, $this->get_registry() );

		$_POST['hcap_fst_token'] = $first['data']['token'];
		$replaced                = $this->subject->verify_token( 0 );

		self::assertInstanceOf( WP_Error::class, $replaced );
		self::assertSame( 'fst-replayed-or-expired', $replaced->get_error_code() );

		$_POST['hcap_fst_token'] = $second['data']['token'];
		self::assertTrue( $this->subject->verify_token( 0 ) );
	}

	/**
	 * Test a token from another page is not replaced.
	 */
	public function test_issue_token_does_not_replace_another_page_token(): void {
		$this->enable_fst();

		$first  = $this->issue_token( '1', '198.51.100.26' );
		$second = $this->issue_token( '2', '198.51.100.26', $first['data']['token'] );

		self::assertTrue( $first['success'], wp_json_encode( $first ) );
		self::assertTrue( $second['success'], wp_json_encode( $second ) );
		self::assertCount( 2, $this->get_registry() );

		$_POST['hcap_fst_token'] = $first['data']['token'];
		self::assertTrue( $this->subject->verify_token( 0 ) );

		$_POST['hcap_fst_token'] = $second['data']['token'];
		self::assertTrue( $this->subject->verify_token( 0 ) );
	}

	/**
	 * Test repeated page reloads replace one tab token without exhausting the client cap.
	 */
	public function test_repeated_page_reload_replacement_stays_below_client_cap(): void {
		$this->enable_fst();

		$token = '';

		for ( $i = 0; $i < FormSubmitTimeStore::MAX_PER_CLIENT + 2; ++$i ) {
			$response = $this->issue_token( '1', '198.51.100.29', $token );

			self::assertTrue( $response['success'], wp_json_encode( $response ) );
			self::assertCount( 1, $this->get_registry() );

			$token = $response['data']['token'];
		}

		$_POST['hcap_fst_token'] = $token;

		self::assertTrue( $this->subject->verify_token( 0 ) );
	}

	/**
	 * Test a client cannot replace another client's token.
	 */
	public function test_issue_token_cannot_replace_cross_client_token(): void {
		$this->enable_fst();

		$first  = $this->issue_token( '1', '198.51.100.27' );
		$second = $this->issue_token( '1', '198.51.100.28', $first['data']['token'] );

		self::assertTrue( $first['success'], wp_json_encode( $first ) );
		self::assertTrue( $second['success'], wp_json_encode( $second ) );
		self::assertCount( 2, $this->get_registry() );

		$_POST['hcap_fst_token'] = $first['data']['token'];
		self::assertTrue( $this->subject->verify_token( 0 ) );
	}

	/**
	 * Test the global cap remains authoritative with either object-cache mode.
	 *
	 * @dataProvider dp_object_cache_modes
	 *
	 * @param bool $external_object_cache Whether to enable external-cache mode.
	 */
	public function test_issue_token_enforces_global_cap( bool $external_object_cache ): void {
		$previous_cache_mode = wp_using_ext_object_cache( $external_object_cache );

		try {
			$this->enable_fst();
			$this->seed_registry( FormSubmitTimeStore::MAX_OUTSTANDING - 1 );

			$accepted = $this->issue_token( '1', '198.51.100.30' );
			$denied   = $this->issue_token( '2', '198.51.100.31' );

			self::assertTrue( $accepted['success'], wp_json_encode( $accepted ) );
			self::assertFalse( $denied['success'] );
			self::assertSame( 'fst-rate-limited', $denied['data']['code'] );
			self::assertCount( FormSubmitTimeStore::MAX_OUTSTANDING, $this->get_registry() );
		} finally {
			wp_using_ext_object_cache( $previous_cache_mode );
		}
	}

	/**
	 * Provide object-cache modes.
	 *
	 * @return array<string, array{bool}>
	 */
	public function dp_object_cache_modes(): array {
		return [
			'database cache'          => [ false ],
			'persistent object cache' => [ true ],
		];
	}

	/**
	 * Test expiry and successful consumption reclaim capacity.
	 */
	public function test_expiry_and_consumption_reclaim_capacity(): void {
		$this->enable_fst();
		$this->seed_registry( FormSubmitTimeStore::MAX_PER_CLIENT, 'same-client', time() - 1 );

		$fresh = $this->issue_token( '1', '198.51.100.40' );

		self::assertTrue( $fresh['success'], wp_json_encode( $fresh ) );
		self::assertCount( 1, $this->get_registry() );

		for ( $i = 1; $i < FormSubmitTimeStore::MAX_PER_CLIENT; ++$i ) {
			self::assertTrue( $this->issue_token( '1', '198.51.100.40' )['success'] );
		}

		self::assertFalse( $this->issue_token( '1', '198.51.100.40' )['success'] );

		$_POST['hcap_fst_token'] = $fresh['data']['token'];
		self::assertTrue( $this->subject->verify_token( 0 ) );
		self::assertTrue( $this->issue_token( '1', '198.51.100.40' )['success'] );
		self::assertCount( FormSubmitTimeStore::MAX_PER_CLIENT, $this->get_registry() );
	}

	/**
	 * Test consumption removes every expired registry record.
	 *
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_consumption_removes_all_expired_registry_records(): void {
		$this->enable_fst();

		$response = $this->issue_token( '1', '198.51.100.41' );
		$registry = $this->get_registry();
		$now      = time();

		for ( $i = 0; $i < 2; ++$i ) {
			$signature = hash( 'sha256', 'expired-' . $i );
			$payload   = [
				'post_id'   => '1',
				'issued_at' => $now - FormSubmitTime::DEFAULT_TOKEN_TTL - 1,
				'ttl'       => FormSubmitTime::DEFAULT_TOKEN_TTL,
				'token_id'  => str_pad( (string) $i, 32, 'e' ),
			];

			$registry[ $signature ] = [
				'payload'    => $payload,
				'client_id'  => 'expired-client',
				'expires_at' => $now - 1,
			];
		}

		$store        = new FormSubmitTimeStore();
		$write_method = $this->set_method_accessibility( $store, 'write_registry' );

		self::assertTrue( $write_method->invoke( $store, $registry ) );
		self::assertCount( 3, $this->get_registry() );

		$_POST['hcap_fst_token'] = $response['data']['token'];

		self::assertTrue( $this->subject->verify_token( 0 ) );
		self::assertSame( [], $this->get_registry() );
	}

	/**
	 * Test concurrent issuance is rejected while the fixed lock is owned.
	 *
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_concurrent_issue_cannot_bypass_caps(): void {
		global $wpdb;

		$this->enable_fst();

		$store          = new FormSubmitTimeStore();
		$acquire_method = $this->set_method_accessibility( $store, 'acquire_lock' );
		$release_method = $this->set_method_accessibility( $store, 'release_lock' );
		$lock           = $acquire_method->invoke( $store );
		$primary_db     = $wpdb;
		$secondary_db   = new wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
		$secondary_db->set_prefix( $primary_db->prefix );

		self::assertNotSame( '', $lock );

		try {
			// A separate connection represents a genuinely concurrent issuer.
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$wpdb   = $secondary_db;
			$denied = $this->issue_token( '1', '198.51.100.50' );

			self::assertFalse( $denied['success'] );
			self::assertSame( 'fst-storage-unavailable', $denied['data']['code'] );
			self::assertSame( [], $this->get_registry() );
		} finally {
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$wpdb = $primary_db;
			$release_method->invoke( $store, $lock );
			$secondary_db->close();
		}

		self::assertTrue( $this->issue_token( '1', '198.51.100.50' )['success'] );
	}

	/**
	 * Test disabled and tampered contexts allocate no state.
	 */
	public function test_disabled_or_invalid_context_allocates_no_state(): void {
		$this->enable_fst();
		$this->prepare_issue_request( '123', '198.51.100.60' );

		update_option( 'hcaptcha_settings', [ 'set_min_submit_time' => '' ] );
		hcaptcha()->init_hooks();

		$disabled = $this->capture_issue_response();

		self::assertFalse( $disabled['success'] );
		self::assertSame( 'fst-disabled', $disabled['data']['code'] );
		self::assertSame( [], $this->get_registry() );

		$this->enable_fst();
		$this->prepare_issue_request( '123', '198.51.100.60' );
		$_POST['postId'] = '124';

		$invalid = $this->capture_issue_response();

		self::assertFalse( $invalid['success'] );
		self::assertSame( 'fst-invalid-context', $invalid['data']['code'] );
		self::assertSame( [], $this->get_registry() );
	}

	/**
	 * Test TTL filter values are clamped to finite bounds.
	 */
	public function test_issue_token_clamps_ttl(): void {
		$this->enable_fst();

		$cases = [
			[ 0, FormSubmitTime::MIN_TOKEN_TTL ],
			[ PHP_INT_MAX, FormSubmitTime::MAX_TOKEN_TTL ],
		];

		foreach ( $cases as $i => [ $filtered_ttl, $expected_ttl ] ) {
			$ttl_filter = static fn() => $filtered_ttl;

			add_filter( 'hcap_fst_token_ttl', $ttl_filter );
			$response = $this->issue_token( (string) $i, '198.51.100.70' );
			remove_filter( 'hcap_fst_token_ttl', $ttl_filter );

			self::assertTrue( $response['success'], wp_json_encode( $response ) );
			self::assertSame( $expected_ttl, $this->payload_from_token( $response['data']['token'] )['ttl'] );
		}
	}

	/**
	 * Test a registry write failure returns no token and allocates no state.
	 *
	 * @noinspection SqlResolve
	 */
	public function test_storage_failure_allocates_no_state(): void {
		global $wpdb;

		$this->enable_fst();

		$query_filter    = static function ( string $query ): string {
			if ( false !== strpos( $query, "'" . FormSubmitTimeStore::REGISTRY_OPTION . "'" ) && false !== stripos( $query, 'INSERT INTO' ) ) {
				return 'SELECT * FROM hcaptcha_missing_fst_table';
			}

			return $query;
		};
		$suppress_errors = $wpdb->suppress_errors();

		add_filter( 'query', $query_filter, PHP_INT_MAX );

		try {
			$response = $this->issue_token( '1', '198.51.100.75' );
		} finally {
			remove_filter( 'query', $query_filter, PHP_INT_MAX );
			$wpdb->suppress_errors( $suppress_errors );
		}

		self::assertFalse( $response['success'] );
		self::assertSame( 'fst-storage-unavailable', $response['data']['code'] );
		self::assertSame( [], $this->get_registry() );
	}

	/**
	 * Test a registry read failure is not mistaken for an empty registry.
	 *
	 * @noinspection SqlResolve
	 */
	public function test_storage_read_failure_allocates_no_state(): void {
		global $wpdb;

		$this->enable_fst();

		$query_filter    = static function ( string $query ): string {
			if ( false !== strpos( $query, "'" . FormSubmitTimeStore::REGISTRY_OPTION . "'" ) && false !== stripos( $query, 'SELECT option_value' ) ) {
				return 'SELECT option_value FROM hcaptcha_missing_fst_table';
			}

			return $query;
		};
		$suppress_errors = $wpdb->suppress_errors();

		add_filter( 'query', $query_filter, PHP_INT_MAX );

		try {
			$response = $this->issue_token( '1', '198.51.100.77' );
		} finally {
			remove_filter( 'query', $query_filter, PHP_INT_MAX );
			$wpdb->suppress_errors( $suppress_errors );
		}

		self::assertFalse( $response['success'] );
		self::assertSame( 'fst-storage-unavailable', $response['data']['code'] );
		self::assertSame( [], $this->get_registry() );
	}

	/**
	 * Test a failed replacement writing leaves the prior token outstanding.
	 *
	 * @noinspection SqlResolve
	 */
	public function test_failed_replacement_preserves_prior_state(): void {
		global $wpdb;

		$this->enable_fst();

		$first           = $this->issue_token( '1', '198.51.100.76' );
		$query_filter    = static function ( string $query ): string {
			if ( false !== strpos( $query, "'" . FormSubmitTimeStore::REGISTRY_OPTION . "'" ) && false !== stripos( $query, 'INSERT INTO' ) ) {
				return 'SELECT * FROM hcaptcha_missing_fst_table';
			}

			return $query;
		};
		$suppress_errors = $wpdb->suppress_errors();

		add_filter( 'query', $query_filter, PHP_INT_MAX );

		try {
			$failed = $this->issue_token( '1', '198.51.100.76', $first['data']['token'] );
		} finally {
			remove_filter( 'query', $query_filter, PHP_INT_MAX );
			$wpdb->suppress_errors( $suppress_errors );
		}

		self::assertFalse( $failed['success'] );
		self::assertSame( 'fst-storage-unavailable', $failed['data']['code'] );
		self::assertCount( 1, $this->get_registry() );

		$_POST['hcap_fst_token'] = $first['data']['token'];
		self::assertTrue( $this->subject->verify_token( 0 ) );
	}

	/**
	 * Test non-consuming verification preserves the single-use token.
	 */
	public function test_verify_token_without_consumption(): void {
		$this->enable_fst();

		$response                   = $this->issue_token( '1', '198.51.100.80' );
		$_POST['hcap_fst_token']    = $response['data']['token'];
		$first_non_consuming_check  = $this->subject->verify_token( 0, false );
		$second_non_consuming_check = $this->subject->verify_token( 0, false );

		self::assertTrue( $first_non_consuming_check );
		self::assertTrue( $second_non_consuming_check );
		self::assertTrue( $this->subject->verify_token( 0 ) );

		$replay = $this->subject->verify_token( 0 );

		self::assertInstanceOf( WP_Error::class, $replay );
		self::assertSame( 'fst-replayed-or-expired', $replay->get_error_code() );
	}

	/**
	 * Test issue_token() invalid nonce.
	 *
	 * @noinspection ThrowRawExceptionInspection
	 * @noinspection JsonEncodingApiUsageInspection
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
		self::assertSame( [], $this->get_registry() );
	}

	/**
	 * Enable minimum-submit-time protection.
	 *
	 * @return void
	 */
	private function enable_fst(): void {
		update_option( 'hcaptcha_settings', [ 'set_min_submit_time' => 'on' ] );
		hcaptcha()->init_hooks();
	}

	/**
	 * Issue one token and decode the JSON response.
	 *
	 * @param string $post_id      Post ID.
	 * @param string $ip           Client IP.
	 * @param string $replace_token Optional token to replace.
	 *
	 * @return array Decoded response.
	 */
	private function issue_token( string $post_id, string $ip, string $replace_token = '' ): array {
		$this->prepare_issue_request( $post_id, $ip );

		if ( '' === $replace_token ) {
			unset( $_POST['replaceToken'] );
		} else {
			$_POST['replaceToken'] = $replace_token;
		}

		return $this->capture_issue_response();
	}

	/**
	 * Prepare a valid issue-token request.
	 *
	 * @param string $post_id Post ID.
	 * @param string $ip      Client IP.
	 *
	 * @return void
	 */
	private function prepare_issue_request( string $post_id, string $ip ): void {
		$action  = 'hcaptcha-fst-issue-token';
		$nonce   = wp_create_nonce( $action );
		$method  = $this->set_method_accessibility( $this->subject, 'context_from_post_id' );
		$context = $method->invoke( $this->subject, $post_id );

		$_POST['nonce']         = $nonce;
		$_REQUEST['nonce']      = $nonce;
		$_POST['postId']        = $post_id;
		$_POST['context']       = $context;
		$_SERVER['REMOTE_ADDR'] = $ip;
	}

	/**
	 * Capture an issue-token JSON response.
	 *
	 * @return array Decoded response.
	 * @noinspection ThrowRawExceptionInspection
	 * @noinspection JsonEncodingApiUsageInspection
	 */
	private function capture_issue_response(): array {
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

		$json     = ob_get_clean();
		$response = json_decode( $json, true );

		self::assertIsArray( $response, "Output was: '$json'" );

		return $response;
	}

	/**
	 * Decode a token payload.
	 *
	 * @param string $token Token.
	 *
	 * @return array Token payload.
	 * @noinspection JsonEncodingApiUsageInspection
	 */
	private function payload_from_token( string $token ): array {
		[ $data ] = explode( '-', $token, 2 );

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		return (array) json_decode( base64_decode( $data, true ), true );
	}

	/**
	 * Read the bounded registry.
	 *
	 * @return array Token registry.
	 */
	private function get_registry(): array {
		$store  = new FormSubmitTimeStore();
		$method = $this->set_method_accessibility( $store, 'read_registry' );

		return (array) $method->invoke( $store );
	}

	/**
	 * Seed bounded registry records in one database write.
	 *
	 * @param int    $count      Record count.
	 * @param string $client_id  Optional common client ID.
	 * @param int    $expires_at Optional expiration time.
	 *
	 * @return void
	 */
	private function seed_registry( int $count, string $client_id = '', int $expires_at = 0 ): void {
		$registry   = [];
		$now        = time();
		$expires_at = $expires_at ?: $now + FormSubmitTime::DEFAULT_TOKEN_TTL;

		for ( $i = 0; $i < $count; ++$i ) {
			$signature = hash( 'sha256', 'seed-' . $i );
			$payload   = [
				'post_id'   => '1',
				'issued_at' => $now,
				'ttl'       => FormSubmitTime::DEFAULT_TOKEN_TTL,
				'token_id'  => str_pad( (string) $i, 32, 'a' ),
			];

			$registry[ $signature ] = [
				'payload'    => $payload,
				'client_id'  => $client_id ?: hash( 'sha256', 'client-' . $i ),
				'expires_at' => $expires_at,
			];
		}

		$store  = new FormSubmitTimeStore();
		$method = $this->set_method_accessibility( $store, 'write_registry' );

		self::assertTrue( $method->invoke( $store, $registry ) );
	}
}
