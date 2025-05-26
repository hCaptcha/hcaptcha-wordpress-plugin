<?php
/**
 * API class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Helpers;

/**
 * Class Request.
 */
class API {
	/**
	 * Result of the hCaptcha widget verification.
	 *
	 * @var string|null
	 */
	private static $result;

	/**
	 * Error codes of the hCaptcha widget verification.
	 *
	 * @var array
	 */
	private static $error_codes;

	/**
	 * Verify hCaptcha response.
	 *
	 * @param string|null $hcaptcha_response hCaptcha response.
	 *
	 * @return null|string Null on success, error message on failure.
	 * @noinspection PhpMissingParamTypeInspection
	 */
	public static function request_verify( $hcaptcha_response ): ?string {
		// Do not make remote request more than once.
		if ( hcaptcha()->has_result ) {
			return self::filtered_result( self::$result, self::$error_codes );
		}

		hcaptcha()->has_result = true;

		/**
		 * Filters the user IP to check whether it is denylisted.
		 * For denylisted IPs, any form submission fails.
		 *
		 * @param bool   $denylisted IP is denylisted.
		 * @param string $ip         IP string.
		 */
		$denylisted = apply_filters( 'hcap_blacklist_ip', false, hcap_get_user_ip( false ) );

		// The request is denylisted.
		if ( $denylisted ) {
			$result      = hcap_get_error_messages()['fail'];
			$error_codes = [ 'fail' ];

			return self::filtered_result( $result, $error_codes );
		}

		// Protection is not enabled.
		if ( ! HCaptcha::is_protection_enabled() ) {
			return self::filtered_result( null, [] );
		}

		$hcaptcha_response_sanitized = htmlspecialchars(
			filter_var( $hcaptcha_response, FILTER_SANITIZE_FULL_SPECIAL_CHARS ),
			ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401
		);

		// The hCaptcha response field is empty.
		if ( '' === $hcaptcha_response_sanitized ) {
			$result      = hcap_get_error_messages()['empty'];
			$error_codes = [ 'empty' ];

			return self::filtered_result( $result, $error_codes );
		}

		$params = [
			'secret'   => hcaptcha()->settings()->get_secret_key(),
			'response' => $hcaptcha_response_sanitized,
		];

		$ip = hcap_get_user_ip();

		if ( $ip ) {
			$params['remoteip'] = $ip;
		}

		return self::process_request( $params );
	}

	/**
	 * Process request.
	 *
	 * @param array $params Request parameters.
	 *
	 * @return string|null
	 */
	public static function process_request( array $params ): ?string {
		// Process API request.
		$raw_response = wp_remote_post(
			hcaptcha()->get_verify_url(),
			[ 'body' => $params ]
		);

		if ( is_wp_error( $raw_response ) ) {
			$result      = implode( "\n", $raw_response->get_error_messages() );
			$error_codes = $raw_response->get_error_codes();

			return self::filtered_result( $result, $error_codes );
		}

		$raw_body = wp_remote_retrieve_body( $raw_response );

		$fail_message = hcap_get_error_messages()['fail'];

		if ( empty( $raw_body ) ) {
			// Verification request failed.
			$result      = $fail_message;
			$error_codes = [ 'fail' ];

			return self::filtered_result( $result, $error_codes );
		}

		$body = json_decode( $raw_body, true );

		if ( ! isset( $body['success'] ) || true !== (bool) $body['success'] ) {
			// Verification request is not verified.
			$error_codes        = $body['error-codes'] ?? [];
			$hcap_error_message = hcap_get_error_message( $error_codes );
			$result             = $hcap_error_message ?: $fail_message;
			$error_codes        = $hcap_error_message ? $error_codes : [ 'fail' ];

			return self::filtered_result( $result, $error_codes );
		}

		// Success.
		return self::filtered_result( null, [] );
	}

	/**
	 * Get filtered result.
	 *
	 * @param string|null $result      Result.
	 * @param array       $error_codes Error codes.
	 *
	 * @return string|null
	 */
	private static function filtered_result( ?string $result, array $error_codes ): ?string {
		/**
		 * Filters the result of request verification.
		 *
		 * @param string|null $result      The result of verification. The null means success.
		 * @param string[]    $error_codes Error code(s). Empty array on success.
		 */
		$result = apply_filters( 'hcap_verify_request', $result, $error_codes );

		$result = null === $result ? null : (string) $result;

		self::$result      = $result;
		self::$error_codes = $error_codes;

		return $result;
	}
}
