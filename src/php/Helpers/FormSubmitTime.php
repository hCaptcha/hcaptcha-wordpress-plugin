<?php
/**
 * FormSubmitTime class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Helpers;

use HCaptcha\DelayedScript\DelayedScript;
use HCaptcha\Main;
use WP_Error;

/**
 * Class FormSubmitTime.
 */
class FormSubmitTime {
	/**
	 * Instance.
	 *
	 * @var FormSubmitTime|null
	 */
	protected static ?FormSubmitTime $instance = null;

	/**
	 * Script handle.
	 */
	private const HANDLE = 'hcaptcha-fst';

	/**
	 * Script localization object.
	 */
	private const OBJECT = 'HCaptchaFSTObject';

	/**
	 * Issue token action.
	 */
	private const ISSUE_TOKEN_ACTION = 'hcaptcha-fst-issue-token';

	/**
	 * Transient prefix.
	 */
	private const TRANSIENT_PREFIX = 'hcap_fst_nonce_';

	/**
	 * Default token lifetime in seconds.
	 */
	public const DEFAULT_TOKEN_TTL = 600;

	/**
	 * Minimum token lifetime in seconds.
	 */
	public const MIN_TOKEN_TTL = 60;

	/**
	 * Maximum token lifetime in seconds.
	 */
	public const MAX_TOKEN_TTL = 3600;

	/**
	 * Token ID length.
	 */
	private const TOKEN_ID_LENGTH = 32;

	/**
	 * Maximum accepted replacement token length.
	 */
	private const MAX_TOKEN_LENGTH = 512;

	/**
	 * Marker submitted when token issuance is rate limited.
	 */
	private const RATE_LIMITED_TOKEN = 'hcaptcha-fst-error:fst-rate-limited';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		add_action( 'admin_print_footer_scripts', [ $this, 'enqueue_scripts' ], 9 );
		add_action( 'wp_print_footer_scripts', [ $this, 'enqueue_scripts' ], 9 );
		add_action( 'wp_ajax_nopriv_' . self::ISSUE_TOKEN_ACTION, [ $this, 'issue_token' ] );
		add_action( 'wp_ajax_' . self::ISSUE_TOKEN_ACTION, [ $this, 'issue_token' ] );
	}

	/**
	 * Enqueue scripts.
	 *
	 * @return void
	 */
	public function enqueue_scripts(): void {
		/**
		 * Filters whether to print hCaptcha scripts.
		 *
		 * @param bool $status Current print status.
		 */
		$status = (bool) apply_filters( 'hcap_print_hcaptcha_scripts', hcaptcha()->form_shown );

		$settings = hcaptcha()->settings();

		if ( ! $status || ! $settings || ! $settings->is_on( 'set_min_submit_time' ) ) {
			return;
		}

		$min = hcap_min_suffix();

		wp_register_script(
			self::HANDLE,
			HCAPTCHA_URL . "/assets/js/hcaptcha-fst$min.js",
			[ Main::HANDLE ],
			HCAPTCHA_VERSION,
			true
		);

		DelayedScript::enqueue( self::HANDLE );
		$post_id = (string) absint( get_queried_object_id() );

		wp_print_inline_script_tag(
			'var ' . self::OBJECT . ' = ' . wp_json_encode(
				[
					'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
					'issueTokenAction'  => self::ISSUE_TOKEN_ACTION,
					'issueTokenNonce'   => wp_create_nonce( self::ISSUE_TOKEN_ACTION ),
					'issueTokenContext' => $this->context_from_post_id( $post_id ),
					'postId'            => $post_id,
				]
			) . ';'
		);
	}

	/**
	 * Generates and issues a token with a unique payload.
	 *
	 * TTL is clamped to 60-3,600 seconds. Issuance is limited to 10 outstanding
	 * tokens per trusted client identity and 1,000 per site. The bounded registry
	 * is database-authoritative and uses a fixed lock, including when a persistent
	 * object cache is active. Overflow returns HTTP 429; storage contention or
	 * failure returns HTTP 503 without issuing a token.
	 *
	 * @return void Outputs a JSON-encoded token and terminates the script execution.
	 * @noinspection PhpUnreachableStatementInspection
	 */
	public function issue_token(): void {
		if ( ! check_ajax_referer( self::ISSUE_TOKEN_ACTION, 'nonce', false ) ) {
			wp_send_json_error();

			return; // For testing purposes.
		}

		$settings = hcaptcha()->settings();

		if ( ! $settings || ! $settings->is_on( 'set_min_submit_time' ) ) {
			$this->send_issue_error( 'fst-disabled', 403 );

			return;
		}

		$post_id = $this->validate_issue_context();

		if ( is_wp_error( $post_id ) ) {
			$this->send_issue_error( $post_id->get_error_code(), 400 );

			return;
		}

		$issued_at = time();

		/**
		 * Filters the time-to-live (TTL) for the Form Submit Time token.
		 *
		 * Values are clamped to 60-3,600 seconds.
		 *
		 * @param int $ttl The time-to-live in seconds. Default is 600 seconds (10 minutes).
		 */
		$ttl = absint( apply_filters( 'hcap_fst_token_ttl', self::DEFAULT_TOKEN_TTL ) );
		$ttl = min( max( $ttl, self::MIN_TOKEN_TTL ), self::MAX_TOKEN_TTL );

		$client_ip = hcap_get_user_ip( false );

		if ( false === $client_ip ) {
			$this->send_issue_error( 'fst-invalid-client', 400 );

			return;
		}

		$payload   = [
			'post_id'   => $post_id,
			'issued_at' => $issued_at,
			'ttl'       => $ttl,
			'token_id'  => wp_generate_password( self::TOKEN_ID_LENGTH, false ),
		];
		$token     = $this->token_from_payload( $payload );
		$signature = $this->parse_token( $token )[1];
		$client_id = hash_hmac( 'sha256', $client_ip, wp_salt( 'nonce' ) );
		$replace   = $this->replacement_from_request();
		$stored    = ( new FormSubmitTimeStore() )->reserve(
			$signature,
			$payload,
			$client_id,
			$issued_at + $ttl,
			$issued_at,
			$replace['signature'],
			$replace['payload']
		);

		if ( is_wp_error( $stored ) ) {
			$status = 'fst-rate-limited' === $stored->get_error_code() ? 429 : 503;

			$this->send_issue_error( $stored->get_error_code(), $status );

			return;
		}

		if ( function_exists( 'nocache_headers' ) ) {
			nocache_headers();
		}

		if ( ! headers_sent() ) {
			// @codeCoverageIgnoreStart
			header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
			// @codeCoverageIgnoreEnd
		}

		/**
		 * Filters the generated token for the Form Submit Time.
		 *
		 * @param string $token The generated token.
		 */
		$token = (string) apply_filters( 'hcap_fst_token', $token );

		wp_send_json_success( [ 'token' => $token ] );
	}

	/**
	 * Verifies the token from POST request against timing and integrity constraints.
	 *
	 * This method validates a submitted token's payload for required fields, checks if the token has expired,
	 * detects replay attempts, and ensures the form has not been submitted too quickly. Optionally, it may
	 * delete the nonce after verification. Returns an error if the verification fails or true on success.
	 *
	 * @param int  $min_submit_time The minimum time, in seconds, that must elapse since the token was issued.
	 * @param bool $delete_nonce    Optional. Whether to consume the token after it is found, including when
	 *                              the timing check fails. Default is true.
	 *
	 * @return true|WP_Error Returns true if the token is successfully verified, otherwise returns a WP_Error object.
	 */
	public function verify_token( int $min_submit_time, bool $delete_nonce = true ) {
		$token = Request::filter_input( INPUT_POST, 'hcap_fst_token' );

		if ( self::RATE_LIMITED_TOKEN === $token ) {
			return hcap_get_wp_error( 'fst-rate-limited' );
		}

		$payload   = $this->payload_from_token( $token );
		$signature = $this->parse_token( $token )[1];

		if ( is_wp_error( $payload ) ) {
			return $payload;
		}

		$storage = $this->get_token_storage( $signature, $payload );

		if ( is_wp_error( $storage ) ) {
			return $storage;
		}

		$timing = $this->check_token_timing( $payload, $min_submit_time );

		if ( ! $delete_nonce ) {
			return $timing;
		}

		$consumed = $this->consume_token( $signature, $payload, $storage );

		if ( is_wp_error( $consumed ) ) {
			return $consumed;
		}

		return $timing;
	}

	/**
	 * Get token from a payload.
	 * Signs the given payload and appends a signature.
	 *
	 * @param array $payload The data array to be signed.
	 *
	 * @return string The signed data in the format 'encoded_payload-signature'.
	 */
	private function token_from_payload( array $payload ): string {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$data      = base64_encode( wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
		$signature = wp_hash( $data );

		return $data . '-' . $signature;
	}

	/**
	 * Get payload from a signed token.
	 * Verifies token for integrity and authenticity.
	 *
	 * @param string $token The token to verify, consisting of a base64-encoded payload and a signature.
	 *
	 * @return array|WP_Error Returns the decoded payload as an array and the signature as a string
	 *                        if the token is valid, or a WP_Error object if verification fails
	 *                        (e.g., malformed token, signature mismatch, decode error, or invalid payload).
	 */
	private function payload_from_token( string $token ) {
		[ $data, $signature ] = $this->parse_token( $token );

		if ( ! hash_equals( wp_hash( $data ), $signature ) ) {
			return new WP_Error( 'fst_bad_sig', __( 'Signature mismatch.', 'hcaptcha-for-forms-and-more' ) );
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$json = base64_decode( $data, true );

		if ( false === $json ) {
			return new WP_Error( 'fst_bad_b64', __( 'Decode error.', 'hcaptcha-for-forms-and-more' ) );
		}

		$payload   = Utils::json_decode_arr( $json );
		$token_id  = $payload['token_id'] ?? null;
		$issued_at = $payload['issued_at'] ?? null;
		$ttl       = $payload['ttl'] ?? null;
		$post_id   = $payload['post_id'] ?? null;

		if (
			! is_int( $issued_at ) ||
			$issued_at <= 0 ||
			! is_int( $ttl ) ||
			$ttl < self::MIN_TOKEN_TTL ||
			$ttl > self::MAX_TOKEN_TTL ||
			( ! is_int( $post_id ) && ! is_string( $post_id ) ) ||
			! is_string( $token_id ) ||
			self::TOKEN_ID_LENGTH !== strlen( $token_id ) ||
			( '' !== (string) $post_id && ! ctype_digit( (string) $post_id ) )
		) {
			return new WP_Error( 'fst_bad_payload', __( 'Invalid payload.', 'hcaptcha-for-forms-and-more' ) );
		}

		return $payload;
	}

	/**
	 * Parses a token into data and signature.
	 *
	 * @param string $token The token string to be parsed, containing data and signature separated by a dash.
	 *
	 * @return array An array containing the parsed data and the signature.
	 */
	private function parse_token( string $token ): array {
		$token_arr = explode( '-', $token, 2 );
		$data      = $token_arr[0];
		$signature = $token_arr[1] ?? '';

		return [ $data, $signature ];
	}

	/**
	 * Create the signed page/form context published with the AJAX nonce.
	 *
	 * @param string $post_id Normalized queried object ID.
	 *
	 * @return string Signed context.
	 */
	private function context_from_post_id( string $post_id ): string {
		return $post_id . '|' . wp_hash( self::ISSUE_TOKEN_ACTION . '|' . $post_id );
	}

	/**
	 * Validate the requested page/form context.
	 *
	 * @return string|WP_Error Normalized post ID or an error.
	 */
	private function validate_issue_context() {
		$post_id = Request::filter_input( INPUT_POST, 'postId' );
		$context = Request::filter_input( INPUT_POST, 'context' );

		if ( ! is_string( $post_id ) || ! is_string( $context ) || ! ctype_digit( $post_id ) ) {
			return new WP_Error(
				'fst-invalid-context',
				__( 'Invalid form timing context.', 'hcaptcha-for-forms-and-more' )
			);
		}

		$post_id  = (string) absint( $post_id );
		$expected = $this->context_from_post_id( $post_id );

		if ( ! hash_equals( $expected, $context ) ) {
			return new WP_Error(
				'fst-invalid-context',
				__( 'Invalid form timing context.', 'hcaptcha-for-forms-and-more' )
			);
		}

		return $post_id;
	}

	/**
	 * Read an optional, bounded replacement token from the request.
	 *
	 * Invalid, expired, consumed, and cross-client tokens are ignored by the store,
	 * and issuance remains subject to the normal quotas.
	 *
	 * @return array{signature: string, payload: array} Replacement details.
	 */
	private function replacement_from_request(): array {
		$token = Request::filter_input( INPUT_POST, 'replaceToken' );

		if ( ! is_string( $token ) || '' === $token || self::MAX_TOKEN_LENGTH < strlen( $token ) ) {
			return [
				'signature' => '',
				'payload'   => [],
			];
		}

		$payload = $this->payload_from_token( $token );

		if ( is_wp_error( $payload ) ) {
			return [
				'signature' => '',
				'payload'   => [],
			];
		}

		return [
			'signature' => $this->parse_token( $token )[1],
			'payload'   => $payload,
		];
	}

	/**
	 * Send a controlled issuance error.
	 *
	 * @param string $code   Error code.
	 * @param int    $status HTTP status.
	 *
	 * @return void
	 */
	private function send_issue_error( string $code, int $status ): void {
		wp_send_json_error( [ 'code' => $code ], $status );
	}

	/**
	 * Locate a token state in the bounded registry or the legacy transient store.
	 *
	 * @param string $signature Token signature.
	 * @param array  $payload   Token payload.
	 *
	 * @return array|WP_Error Storage details or an error.
	 */
	private function get_token_storage( string $signature, array $payload ) {
		$store  = new FormSubmitTimeStore();
		$stored = $store->has( $signature, $payload );

		if ( is_wp_error( $stored ) ) {
			return $stored;
		}

		$transient     = self::TRANSIENT_PREFIX . $signature;
		$legacy_stored = false === $stored && get_transient( $transient ) === $payload;

		if ( ! $stored && ! $legacy_stored ) {
			return hcap_get_wp_error( 'fst-replayed-or-expired' );
		}

		return [
			'store'     => $store,
			'stored'    => $stored,
			'transient' => $transient,
		];
	}

	/**
	 * Check token timing constraints.
	 *
	 * @param array $payload         Token payload.
	 * @param int   $min_submit_time Minimum submit time.
	 *
	 * @return true|WP_Error True on success, error otherwise.
	 */
	private function check_token_timing( array $payload, int $min_submit_time ) {
		$now       = time();
		$issued_at = (int) $payload['issued_at'];
		$ttl       = (int) $payload['ttl'];

		if ( $issued_at > $now ) {
			return new WP_Error( 'fst_bad_payload', __( 'Invalid payload.', 'hcaptcha-for-forms-and-more' ) );
		}

		if ( $now - $issued_at < $min_submit_time ) {
			return hcap_get_wp_error( 'fst-too-fast' );
		}

		if ( $now - $issued_at > $ttl ) {
			return hcap_get_wp_error( 'fst-expired' );
		}

		return true;
	}

	/**
	 * Consume token state after a verification attempt.
	 *
	 * @param string $signature Token signature.
	 * @param array  $payload   Token payload.
	 * @param array  $storage   Storage details.
	 *
	 * @return true|WP_Error True on success, error otherwise.
	 */
	private function consume_token( string $signature, array $payload, array $storage ) {
		if ( $storage['stored'] ) {
			return $storage['store']->consume( $signature, $payload );
		}

		return $storage['store']->consume_legacy( $storage['transient'], $payload );
	}
}
