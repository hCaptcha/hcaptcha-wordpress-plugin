<?php
/**
 * APITest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\Helpers;

use HCaptcha\Helpers\API;
use HCaptcha\Helpers\FormSubmitTime;
use HCaptcha\Helpers\FormSubmitTimeStore;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Settings\General;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use ReflectionException;
use WP_Error;

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
	 * Test verify_post() checks an empty hCaptcha response before FST.
	 */
	public function test_verify_post_empty_checks_hcaptcha_before_fst(): void {
		$settings                        = (array) get_option( 'hcaptcha_settings', [] );
		$settings['set_min_submit_time'] = 'on';
		$settings['min_submit_time']     = '0';

		update_option( 'hcaptcha_settings', $settings );

		$this->prepare_verify_request( '', false );

		remove_filter( 'hcap_verify_fst_token', '__return_true' );
		add_filter(
			'hcap_verify_fst_token',
			static function () {
				return new WP_Error( 'fst-replayed-or-expired', 'Token replayed or expired.' );
			},
			9
		);

		self::assertSame( 'Please complete the hCaptcha.', API::verify_post() );
	}

	/**
	 * Test verify_post() consumes an FST token when the hCaptcha response is empty.
	 *
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_verify_post_empty_consumes_fst_token(): void {
		$now       = time();
		$payload   = [
			'post_id'   => '0',
			'issued_at' => $now,
			'ttl'       => FormSubmitTime::DEFAULT_TOKEN_TTL,
			'token_id'  => wp_generate_password( 32, false ),
		];
		$fst       = new FormSubmitTime();
		$method    = $this->set_method_accessibility( $fst, 'token_from_payload' );
		$token     = (string) $method->invoke( $fst, $payload );
		$signature = explode( '-', $token, 2 )[1];
		$store     = new FormSubmitTimeStore();
		$settings  = (array) get_option( 'hcaptcha_settings', [] );

		FormSubmitTimeStore::delete_all();

		$settings['set_min_submit_time'] = 'on';
		$settings['min_submit_time']     = '0';

		update_option( 'hcaptcha_settings', $settings );
		hcaptcha()->init_hooks();

		self::assertTrue(
			$store->reserve(
				$signature,
				$payload,
				'test-client',
				$now + FormSubmitTime::DEFAULT_TOKEN_TTL,
				$now
			)
		);

		$this->prepare_verify_request( '', false );
		$_POST['hcap_fst_token'] = $token;

		try {
			self::assertSame( 'Please complete the hCaptcha.', API::verify_post() );
			self::assertFalse( $store->has( $signature, $payload ) );
		} finally {
			FormSubmitTimeStore::delete_all();
		}
	}

	/**
	 * Test FST token consumption happens before an early verification failure.
	 *
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_verify_request_consumes_fst_token_before_denylist_check(): void {
		$now       = time();
		$payload   = [
			'post_id'   => '0',
			'issued_at' => $now,
			'ttl'       => FormSubmitTime::DEFAULT_TOKEN_TTL,
			'token_id'  => wp_generate_password( 32, false ),
		];
		$fst       = new FormSubmitTime();
		$method    = $this->set_method_accessibility( $fst, 'token_from_payload' );
		$token     = (string) $method->invoke( $fst, $payload );
		$signature = explode( '-', $token, 2 )[1];
		$store     = new FormSubmitTimeStore();
		$settings  = (array) get_option( 'hcaptcha_settings', [] );

		FormSubmitTimeStore::delete_all();

		$settings['set_min_submit_time'] = 'on';
		$settings['min_submit_time']     = '0';

		update_option( 'hcaptcha_settings', $settings );
		hcaptcha()->init_hooks();

		self::assertTrue(
			$store->reserve(
				$signature,
				$payload,
				'test-client',
				$now + FormSubmitTime::DEFAULT_TOKEN_TTL,
				$now
			)
		);

		$this->prepare_verify_request( 'some response', false );
		$_POST['hcap_fst_token'] = $token;

		add_filter( 'hcap_blacklist_ip', '__return_true' );

		try {
			self::assertSame( 'The hCaptcha is invalid.', API::verify_request( 'some response' ) );
			self::assertFalse( $store->has( $signature, $payload ) );
		} finally {
			remove_filter( 'hcap_blacklist_ip', '__return_true' );
			FormSubmitTimeStore::delete_all();
		}
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
	 * Test whether verify_request() sends a local IP in the configured mode.
	 *
	 * @param string $mode Mode.
	 *
	 * @dataProvider dp_test_verify_request_sends_local_ip
	 */
	public function test_verify_request_sends_local_ip( string $mode ): void {
		$hcaptcha_response = 'some response';
		$local_ip          = '127.0.0.1';
		$settings          = (array) get_option( 'hcaptcha_settings', [] );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$previous_ip     = $_SERVER['REMOTE_ADDR'] ?? null;
		$request_checked = false;

		$settings['mode']       = $mode;
		$settings['site_key']   = 'some site key';
		$settings['secret_key'] = 'some secret key';

		update_option( 'hcaptcha_settings', $settings );
		hcaptcha()->init_hooks();

		$_SERVER['REMOTE_ADDR'] = $local_ip;

		$filter = static function ( $preempt, $parsed_args, $url ) use ( $local_ip, &$request_checked ) {
			if ( hcaptcha()->get_verify_url() !== $url ) {
				return $preempt;
			}

			$request_checked = true;

			self::assertSame( $local_ip, $parsed_args['body']['remoteip'] ?? null );

			return [
				'body' => wp_json_encode(
					[
						'success' => true,
					]
				),
			];
		};

		add_filter( 'pre_http_request', $filter, 10, 3 );

		try {
			$_POST['h-captcha-response'] = $hcaptcha_response;
			$_POST['hcap_hp_test']       = '';
			$_POST['hcap_hp_sig']        = wp_create_nonce( 'hcap_hp_test' );

			self::assertNull( API::verify_request( $hcaptcha_response ) );
			self::assertTrue( $request_checked );
		} finally {
			remove_filter( 'pre_http_request', $filter );

			if ( null === $previous_ip ) {
				unset( $_SERVER['REMOTE_ADDR'] );
			} else {
				$_SERVER['REMOTE_ADDR'] = $previous_ip;
			}
		}
	}

	/**
	 * Data provider for test_verify_request_sends_local_ip().
	 *
	 * @return array
	 */
	public function dp_test_verify_request_sends_local_ip(): array {
		return [
			'live mode'                     => [ General::MODE_LIVE ],
			'publisher test mode'           => [ General::MODE_TEST_PUBLISHER ],
			'enterprise safe end user mode' => [ General::MODE_TEST_ENTERPRISE_SAFE_END_USER ],
			'enterprise bot detected mode'  => [ General::MODE_TEST_ENTERPRISE_BOT_DETECTED ],
		];
	}

	/**
	 * Test that verify_request() exposes and caches the normalized siteverify response.
	 */
	public function test_verify_request_exposes_and_caches_siteverify_response(): void {
		$hcaptcha_response   = 'some response';
		$siteverify_response = [
			'success'      => true,
			'challenge_ts' => '2026-09-19T10:00:00Z',
			'hostname'     => 'test.test',
			'credit'       => true,
			'error-codes'  => [],
			'score'        => 1.0,
			'score_reason' => [ 'test-reason' ],
		];
		$expected_response   = [
			'success'      => true,
			'challenge_ts' => '2026-09-19T10:00:00Z',
			'hostname'     => 'test.test',
			'credit'       => true,
			'error-codes'  => [],
		];
		$captured_responses  = [];

		add_filter(
			'hcap_verify_request',
			static function ( $result, $deprecated, $error_info ) use ( &$captured_responses ) {
				$captured_responses[] = $error_info->siteverify;

				return $result;
			},
			PHP_INT_MAX,
			3
		);

		$this->prepare_verify_request( $hcaptcha_response, $siteverify_response );

		self::assertNull( API::verify_request( $hcaptcha_response ) );
		self::assertNull( API::verify_request( $hcaptcha_response ) );
		self::assertSame( [ $expected_response, $expected_response ], $captured_responses );
		self::assertSame( $expected_response, API::get_siteverify_response() );
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
	 * Test verify() with a honeypot override in the expected widget id.
	 */
	public function test_verify_with_honeypot_override_in_expected_widget_id(): void {
		$nonce_field_name  = 'some nonce field';
		$nonce_action_name = 'some nonce action';
		$expected_id       = [
			'source'  => [ 'test/source' ],
			'form_id' => 'test-form',
		];
		$actual_id         = array_merge( $expected_id, [ 'honeypot' => false ] );

		$this->prepare_verify_post( $nonce_field_name, $nonce_action_name );

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value( $actual_id );

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

		$filtered_expected_id = null;

		add_filter(
			'hcap_verify_request',
			static function ( $result, $deprecated, $error_info ) use ( &$filtered_expected_id ) {
				$filtered_expected_id = $error_info->expected_id;

				return $result;
			},
			10,
			3
		);

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
		self::assertSame( $expected_id, $filtered_expected_id );
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
	 * Test verify_request() with missing keys.
	 */
	public function test_verify_request_with_missing_keys(): void {
		$request_count = 0;
		$empty_key     = static function () {
			return '';
		};

		add_filter( 'hcap_site_key', $empty_key );
		add_filter( 'hcap_secret_key', $empty_key );
		add_filter(
			'pre_http_request',
			static function ( $preempt ) use ( &$request_count ) {
				++$request_count;

				return $preempt;
			}
		);

		self::assertSame(
			'Site Key and Secret Key are required.',
			API::verify_request( 'some response' )
		);
		self::assertSame( 0, $request_count );
	}

	/**
	 * Test verify_request() with an empty string as an argument.
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
	/**
	 * Test verify_post_data().
	 */
	public function test_verify_post_data(): void {
		$hcaptcha_response = 'some response';

		$this->prepare_verify_request( $hcaptcha_response );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$post_data = $_POST;
		$_POST     = [];

		self::assertNull( API::verify_post_data( HCAPTCHA_NONCE, HCAPTCHA_ACTION, $post_data ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		self::assertArrayNotHasKey( 'h-captcha-response', $_POST );
	}

	/**
	 * Test verify() without nonce and expected widget id.
	 */
	public function test_verify_without_nonce_and_expected_widget_id(): void {
		$hcaptcha_response = 'some response';

		$this->prepare_verify_request( $hcaptcha_response );

		self::assertNull(
			API::verify(
				[
					'nonce_name'         => null,
					'nonce_action'       => null,
					'h-captcha-response' => $hcaptcha_response,
				]
			)
		);
	}

	/**
	 * Test that form entry and transport data are absent from the siteverify body.
	 *
	 * @return void
	 */
	public function test_verify_does_not_send_form_data_to_siteverify(): void {
		$hcaptcha_response = 'some response';
		$this->prepare_verify_request( $hcaptcha_response );

		self::assertNull(
			API::verify(
				[
					'h-captcha-response' => $hcaptcha_response,
					'data'               => [ 'password' => 'private-password' ],
					'post_data'          => [ 'payment_method' => 'private-payment-data' ],
				]
			)
		);
	}

	/**
	 * Test verify_request() for a denylisted IP.
	 */
	public function test_verify_request_denylisted_ip(): void {
		add_filter( 'hcap_blacklist_ip', '__return_true' );

		self::assertSame( 'The hCaptcha is invalid.', API::verify_request( 'some response' ) );
	}

	/**
	 * Test verify_request() with a failed honeypot.
	 */
	public function test_verify_request_honeypot_failure(): void {
		$hcaptcha_response = 'some response';

		$this->prepare_verify_request( $hcaptcha_response );

		$_POST['hcap_hp_test'] = 'bot value';

		self::assertSame( 'Anti-spam check failed.', API::verify_request( $hcaptcha_response ) );

		hcaptcha()->has_result = false;
		$_POST['hcap_hp_test'] = [ 'nested' ];

		self::assertSame( 'Anti-spam check failed.', API::verify_request( $hcaptcha_response ) );
	}

	/**
	 * Test verify_request() with honeypot disabled by the signed widget id.
	 */
	public function test_verify_request_honeypot_disabled_by_widget_id(): void {
		$hcaptcha_response = 'some response';

		$this->prepare_verify_request( $hcaptcha_response );

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'   => [],
				'form_id'  => 0,
				'honeypot' => false,
			]
		);
		$_POST['hcap_hp_test']                 = 'bot value';

		self::assertNull( API::verify_request( $hcaptcha_response ) );
	}

	/**
	 * Test verify_request() with honeypot enabled by the signed widget id.
	 */
	public function test_verify_request_honeypot_enabled_by_widget_id(): void {
		$hcaptcha_response = 'some response';

		$this->prepare_verify_request( $hcaptcha_response );

		$settings             = (array) get_option( 'hcaptcha_settings', [] );
		$settings['honeypot'] = [ '' ];

		update_option( 'hcaptcha_settings', $settings );
		hcaptcha()->init_hooks();

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'   => [],
				'form_id'  => 0,
				'honeypot' => true,
			]
		);
		$_POST['hcap_hp_test']                 = 'bot value';

		self::assertSame( 'Anti-spam check failed.', API::verify_request( $hcaptcha_response ) );
	}

	/**
	 * Test verify_request() ignores a honeypot override with an invalid signature.
	 */
	public function test_verify_request_ignores_unsigned_honeypot_override(): void {
		$hcaptcha_response = 'some response';

		$this->prepare_verify_request( $hcaptcha_response );

		$widget_id = HCaptcha::widget_id_value(
			[
				'source'   => [],
				'form_id'  => 0,
				'honeypot' => false,
			]
		);

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = $widget_id . 'tampered';
		$_POST['hcap_hp_test']                 = 'bot value';

		self::assertSame( 'Anti-spam check failed.', API::verify_request( $hcaptcha_response ) );
	}

	/**
	 * Test verify_request() without a Form Submit Time object.
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_verify_request_without_fst_object(): void {
		$hcaptcha_response = 'some response';
		$settings          = (array) get_option( 'hcaptcha_settings', [] );

		$settings['set_min_submit_time'] = [ 'on' ];

		update_option( 'hcaptcha_settings', $settings );
		$this->prepare_verify_request( $hcaptcha_response );

		remove_filter( 'hcap_verify_fst_token', '__return_true' );

		$loaded_classes = $this->get_protected_property( hcaptcha(), 'loaded_classes' );
		$no_fst_classes = $loaded_classes;

		unset( $no_fst_classes[ FormSubmitTime::class ] );

		$this->set_protected_property( hcaptcha(), 'loaded_classes', $no_fst_classes );

		try {
			self::assertSame( 'FST object does not exist.', API::verify_request( $hcaptcha_response ) );
		} finally {
			$this->set_protected_property( hcaptcha(), 'loaded_classes', $loaded_classes );
		}
	}

	/**
	 * Test verify_request() with a failed Form Submit Time token.
	 */
	public function test_verify_request_fst_token_failure(): void {
		$hcaptcha_response = 'some response';
		$settings          = (array) get_option( 'hcaptcha_settings', [] );

		$settings['set_min_submit_time'] = [ 'on' ];

		update_option( 'hcaptcha_settings', $settings );
		$this->prepare_verify_request( $hcaptcha_response );

		remove_filter( 'hcap_verify_fst_token', '__return_true' );
		add_filter(
			'hcap_verify_fst_token',
			static function () {
				return new WP_Error( 'fst-replayed-or-expired', 'Token replayed or expired.' );
			}
		);

		self::assertSame( 'Token replayed or expired.', API::verify_request( $hcaptcha_response ) );
	}

	/**
	 * Test verify_request() with a disposable email.
	 */
	public function test_verify_request_disposable_email_failure(): void {
		$hcaptcha_response = 'some response';
		$settings          = (array) get_option( 'hcaptcha_settings', [] );

		$settings['disposable_email'] = [ 'on' ];

		update_option( 'hcaptcha_settings', $settings );
		$this->prepare_verify_request( $hcaptcha_response );

		add_filter( 'hcap_is_disposable_email', '__return_true' );

		self::assertSame(
			'Please use a permanent email address.',
			API::verify_request(
				$hcaptcha_response,
				[
					'data' => [ 'email' => 'test@example.com' ],
				]
			)
		);
	}

	/**
	 * Test verify_request() with a remote request error.
	 */
	public function test_verify_request_remote_error(): void {
		$hcaptcha_response = 'some response';
		$hcaptcha_settings = (array) get_option( 'hcaptcha_settings', [] );
		$filter            = static function () {
			return new WP_Error( 'remote-error', 'Remote failed.' );
		};

		$_POST['h-captcha-response'] = $hcaptcha_response;
		$_POST['hcap_hp_test']       = '';
		$_POST['hcap_hp_sig']        = wp_create_nonce( 'hcap_hp_test' );

		$hcaptcha_settings['secret_key']              = 'test secret';
		$hcaptcha_settings['trusted_address_headers'] = [ 'HTTP_CLIENT_IP' ];

		update_option( 'hcaptcha_settings', $hcaptcha_settings );
		hcaptcha()->init_hooks();

		$_SERVER['HTTP_CLIENT_IP'] = '7.7.7.7';

		add_filter( 'pre_http_request', $filter );

		try {
			self::assertSame( 'Remote failed.', API::verify_request( $hcaptcha_response ) );
		} finally {
			remove_filter( 'pre_http_request', $filter );
		}
	}
}
