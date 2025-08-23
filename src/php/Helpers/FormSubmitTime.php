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
		if ( ! hcaptcha()->form_shown ) {
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

		$abs_path  = wp_normalize_path( ABSPATH );
		$ajax_path = wp_normalize_path( // Avoid symlinks.
			WP_PLUGIN_DIR . '/' . basename( HCAPTCHA_PATH ) . '/src/php/includes/ajax.php'
		);

		wp_localize_script(
			self::HANDLE,
			self::OBJECT,
			[
				'ajaxUrl'          => plugin_dir_url( HCAPTCHA_FILE ) . 'src/php/includes/ajax.php',
				'absPath'          => $abs_path,
				'ajaxPath'         => $ajax_path,
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
		$ttl   = absint( apply_filters( 'hcap_fst_token_ttl', 600 ) );
		$nonce = wp_generate_uuid4();
		$value = 1;

		$payload = [
			'value'     => $value,
			'post_id'   => $post_id,
			'issued_at' => $issued_at,
			'ttl'       => $ttl,
			'nonce'     => $nonce,
		];

		set_transient( self::TRANSIENT_PREFIX . $nonce, $value, $ttl );

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
		$token = (string) apply_filters( 'hcap_fst_token', $this->sign( $payload ) );

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

		$payload = $this->verify_sig( $token );

		if ( is_wp_error( $payload ) ) {
			return $payload;
		}

		$now       = time();
		$issued_at = (int) ( $payload['issued_at'] ?? 0 );
		$ttl       = (int) ( $payload['ttl'] ?? 0 );
		$nonce_key = self::TRANSIENT_PREFIX . $payload['nonce'] ?? '';

		if ( get_transient( $nonce_key ) === false ) {
			return new WP_Error( 'fst_replay_or_expired', __( 'Token replayed or expired.', 'hcaptcha-for-forms-and-more' ) );
		}

		if ( $now - $issued_at < $min_submit_time ) {
			return new WP_Error( 'fst_too_fast', __( 'Form submitted too quickly.', 'hcaptcha-for-forms-and-more' ) );
		}

		if ( $now - $issued_at > $ttl ) {
			return new WP_Error( 'fst_expired', __( 'Token expired.', 'hcaptcha-for-forms-and-more' ) );
		}

		if ( $delete_nonce ) {
			delete_transient( $nonce_key );
		}

		return true;
	}

	/**
	 * Signs the given payload by encoding it and appending a signature.
	 *
	 * @param array $payload The data array to be signed.
	 *
	 * @return string The signed data in the format 'encoded_payload-signature'.
	 */
	private function sign( array $payload ): string {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$data = base64_encode( wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
		$sig  = wp_hash( $data );

		return $data . '-' . $sig;
	}

	/**
	 * Verifies a signed token for integrity and authenticity.
	 *
	 * @param string $token The token to verify, consisting of a base64-encoded payload and a signature.
	 *
	 * @return array|WP_Error Returns the decoded payload as an associative array if the token is valid,
	 *                        or a WP_Error object if verification fails
	 *                        (e.g., malformed token, signature mismatch, decode error, or invalid payload).
	 */
	private function verify_sig( string $token ) {
		[ $data, $sig ] = explode( '-', $token . '-', 2 );

		$sig  = rtrim( $sig, '-' );
		$calc = wp_hash( $data );

		if ( ! hash_equals( $calc, $sig ) ) {
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
}
