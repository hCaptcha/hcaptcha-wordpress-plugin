<?php
/**
 * FormSubmitTime class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Helpers;

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
	protected static $instance;

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
		if ( ! hcaptcha()->form_shown || ! hcaptcha()->settings()->is_on( 'set_min_submit_time' ) ) {
			return;
		}

		$min = hcap_min_suffix();

		wp_enqueue_script(
			self::HANDLE,
			HCAPTCHA_URL . "/assets/js/hcaptcha-fst$min.js",
			[ 'hcaptcha' ],
			HCAPTCHA_VERSION,
			true
		);

		wp_localize_script(
			self::HANDLE,
			self::OBJECT,
			[
				'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
				'issueTokenAction' => self::ISSUE_TOKEN_ACTION,
				'issueTokenNonce'  => wp_create_nonce( self::ISSUE_TOKEN_ACTION ),
			]
		);
	}

	/**
	 * Generates and issues a token with a unique payload.
	 *
	 * This function creates a token with specific details such as form ID, issuance time,
	 * time-to-live (TTL), and unique nonce. It applies filters for TTL customization,
	 * stores the nonce in a transient for a limited period, and ensures headers
	 * prevent caching. Finally, it outputs the generated token in a JSON format and stops execution.
	 *
	 * @return void Outputs a JSON-encoded token and terminates the script execution.
	 */
	public function issue_token(): void {
		$post_id   = Request::filter_input( INPUT_POST, 'postId' );
		$issued_at = time();

		/**
		 * Filters the time-to-live (TTL) for the Form Submit Time token.
		 *
		 * @param int $ttl The time-to-live in seconds. Default is 600 seconds (10 minutes).
		 */
		$ttl = absint( apply_filters( 'hcap_fst_token_ttl', 600 ) );
		$ttl = max( $ttl, 60 ); // Minimum TTL is 60 seconds (1 minute).

		$payload   = [
			'post_id'   => $post_id,
			'issued_at' => $issued_at,
			'ttl'       => $ttl,
		];
		$token     = $this->token_from_payload( $payload );
		$signature = $this->parse_token( $token )[1];
		$transient = self::TRANSIENT_PREFIX . $signature;

		set_transient( $transient, $payload, $ttl );

		if ( function_exists( 'nocache_headers' ) ) {
			nocache_headers();
		}

		if ( ! headers_sent() ) {
			header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
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
	 * @param bool $delete_nonce    Optional. Whether to delete the nonce after successful verification.
	 *                              Default is true.
	 *
	 * @return true|WP_Error Returns true if the token is successfully verified, otherwise returns a WP_Error object.
	 */
	public function verify_token( int $min_submit_time, bool $delete_nonce = true ) {
		$token = Request::filter_input( INPUT_POST, 'hcap_fst_token' );

		$payload   = $this->payload_from_token( $token );
		$signature = $this->parse_token( $token )[1];

		if ( is_wp_error( $payload ) ) {
			return $payload;
		}

		$now       = time();
		$issued_at = (int) ( $payload['issued_at'] ?? 0 );
		$ttl       = (int) ( $payload['ttl'] ?? 0 );
		$transient = self::TRANSIENT_PREFIX . $signature;

		if ( get_transient( $transient ) !== $payload ) {
			return hcap_get_wp_error( 'fst-replayed-or-expired' );
		}

		if ( $now - $issued_at < $min_submit_time ) {
			return hcap_get_wp_error( 'fst-too-fast' );
		}

		if ( $now - $issued_at > $ttl ) {
			return hcap_get_wp_error( 'fst-expired' );
		}

		if ( $delete_nonce ) {
			delete_transient( $transient );
		}

		return true;
	}

	/**
	 * Get token form a payload.
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

		$payload = json_decode( $json, true );

		if ( ! is_array( $payload ) ) {
			return new WP_Error( 'fst_bad_payload', __( 'Invalid payload.', 'hcaptcha-for-forms-and-more' ) );
		}

		return $payload;
	}

	/**
	 * Parses a token into data and signature.
	 *
	 * @param string $token The token string to be parsed, containing a data and signature separated by a dash.
	 *
	 * @return array An array containing the parsed data and the signature.
	 */
	private function parse_token( string $token ): array {
		$token_arr = explode( '-', $token, 2 );
		$data      = $token_arr[0];
		$signature = $token_arr[1] ?? '';

		return [ $data, $signature ];
	}
}
