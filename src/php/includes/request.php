<?php
/**
 * Request file.
 *
 * @package hcaptcha-wp
 */

use HCaptcha\Helpers\HCaptcha;

/**
 * Determines the user's actual IP address and attempts to partially
 * anonymize an IP address by converting it to a network ID.
 *
 * Based on the code of the \WP_Community_Events::get_unsafe_client_ip.
 * Returns a string with the IP address or false for local IPs.
 *
 * @return string|false
 */
function hcap_get_user_ip() {
	$ip = false;

	// In order of preference, with the best ones for this purpose first.
	$address_headers = [
		'HTTP_TRUE_CLIENT_IP',
		'HTTP_CF_CONNECTING_IP',
		'HTTP_X_REAL_IP',
		'HTTP_CLIENT_IP',
		'HTTP_X_FORWARDED_FOR',
		'HTTP_X_FORWARDED',
		'HTTP_X_CLUSTER_CLIENT_IP',
		'HTTP_FORWARDED_FOR',
		'HTTP_FORWARDED',
		'REMOTE_ADDR',
	];

	foreach ( $address_headers as $header ) {
		if ( ! array_key_exists( $header, $_SERVER ) ) {
			continue;
		}

		/*
		 * HTTP_X_FORWARDED_FOR can contain a chain of comma-separated addresses.
		 * The first one is the original client.
		 * It can't be trusted for authenticity, but we don't need to for this purpose.
		 */
		$address_chain = explode(
			',',
			filter_var( wp_unslash( $_SERVER[ $header ] ), FILTER_SANITIZE_FULL_SPECIAL_CHARS ),
			2
		);
		$ip            = trim( $address_chain[0] );

		break;
	}

	// Filter out local addresses.
	return filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
}

/**
 * Get error messages provided by API and the plugin.
 *
 * @return array
 */
function hcap_get_error_messages(): array {
	/**
	 * Filters hCaptcha error messages.
	 *
	 * @param array $error_messages Error messages.
	 */
	return apply_filters(
		'hcap_error_messages',
		[
			// API messages.
			'missing-input-secret'     => __( 'Your secret key is missing.', 'hcaptcha-for-forms-and-more' ),
			'invalid-input-secret'     => __( 'Your secret key is invalid or malformed.', 'hcaptcha-for-forms-and-more' ),
			'missing-input-response'   => __( 'The response parameter (verification token) is missing.', 'hcaptcha-for-forms-and-more' ),
			'invalid-input-response'   => __( 'The response parameter (verification token) is invalid or malformed.', 'hcaptcha-for-forms-and-more' ),
			'expired-input-response'   => __( 'The response parameter (verification token) is expired. (120s default)', 'hcaptcha-for-forms-and-more' ),
			'already-seen-response'    => __( 'The response parameter (verification token) was already verified once.', 'hcaptcha-for-forms-and-more' ),
			'bad-request'              => __( 'The request is invalid or malformed.', 'hcaptcha-for-forms-and-more' ),
			'missing-remoteip'         => __( 'The remoteip parameter is missing.', 'hcaptcha-for-forms-and-more' ),
			'invalid-remoteip'         => __( 'The remoteip parameter is not a valid IP address or blinded value.', 'hcaptcha-for-forms-and-more' ),
			'not-using-dummy-passcode' => __( 'You have used a testing sitekey but have not used its matching secret.', 'hcaptcha-for-forms-and-more' ),
			'sitekey-secret-mismatch'  => __( 'The sitekey is not registered with the provided secret.', 'hcaptcha-for-forms-and-more' ),
			// Plugin messages.
			'empty'                    => __( 'Please complete the hCaptcha.', 'hcaptcha-for-forms-and-more' ),
			'fail'                     => __( 'The hCaptcha is invalid.', 'hcaptcha-for-forms-and-more' ),
			'bad-nonce'                => __( 'Bad hCaptcha nonce!', 'hcaptcha-for-forms-and-more' ),
			'bad-signature'            => __( 'Bad hCaptcha signature!', 'hcaptcha-for-forms-and-more' ),
		]
	);
}

/**
 * Get hCaptcha error message.
 *
 * @param string|string[] $error_codes Error codes.
 *
 * @return string
 */
function hcap_get_error_message( $error_codes ): string {
	$error_codes = (array) $error_codes;
	$errors      = hcap_get_error_messages();
	$message_arr = [];

	foreach ( $error_codes as $error_code ) {
		if ( array_key_exists( $error_code, $errors ) ) {
			$message_arr[] = $errors[ $error_code ];
		}
	}

	if ( ! $message_arr ) {
		return '';
	}

	$header = _n( 'hCaptcha error:', 'hCaptcha errors:', count( $message_arr ), 'hcaptcha-for-forms-and-more' );

	return $header . ' ' . implode( '; ', $message_arr );
}

/**
 * Check site configuration.
 *
 * @return array
 */
function hcap_check_site_config(): array {
	$settings = hcaptcha()->settings();
	$params   = [
		'host'    => (string) wp_parse_url( home_url(), PHP_URL_HOST ),
		'sitekey' => $settings->get_site_key(),
		'sc'      => 1,
		'swa'     => 1,
		'spst'    => 0,
	];
	$url      = add_query_arg( $params, hcaptcha()->get_check_site_config_url() );

	$raw_response = wp_remote_post( $url );

	if ( is_wp_error( $raw_response ) ) {
		return [
			'error' => implode( "\n", $raw_response->get_error_messages() ),
		];
	}

	$raw_body = wp_remote_retrieve_body( $raw_response );

	if ( empty( $raw_body ) ) {
		return [
			'error' => __( 'Cannot communicate with hCaptcha server.', 'hcaptcha-for-forms-and-more' ),
		];
	}

	$body = (array) json_decode( $raw_body, true );

	if ( ! $body ) {
		return [
			'error' => __( 'Cannot decode hCaptcha server response.', 'hcaptcha-for-forms-and-more' ),
		];
	}

	if ( empty( $body['pass'] ) ) {
		$error = (string) ( $body['error'] ?? '' );

		return [
			'error' => $error,
		];
	}

	return $body;
}

if ( ! function_exists( 'hcaptcha_request_verify' ) ) {
	/**
	 * Verify hCaptcha response.
	 *
	 * @param string|null $hcaptcha_response hCaptcha response.
	 *
	 * @return null|string Null on success, error message on failure.
	 * @noinspection PhpMissingParamTypeInspection
	 */
	function hcaptcha_request_verify( $hcaptcha_response ): ?string {
		static $result;
		static $error_codes;

		// Do not make remote request more than once.
		if ( hcaptcha()->has_result ) {
			/**
			 * Filters the result of request verification.
			 *
			 * @param string|null $result      The result of verification. The null means success.
			 * @param string[]    $error_codes Error code(s). Empty array on success.
			 */
			return apply_filters( 'hcap_verify_request', $result, $error_codes );
		}

		hcaptcha()->has_result = true;

		$errors        = hcap_get_error_messages();
		$empty_message = $errors['empty'];
		$fail_message  = $errors['fail'];

		// Protection is not enabled.
		if ( ! HCaptcha::is_protection_enabled() ) {
			$result      = null;
			$error_codes = [];

			/** This filter is documented above. */
			return apply_filters( 'hcap_verify_request', $result, $error_codes );
		}

		$hcaptcha_response_sanitized = htmlspecialchars(
			filter_var( $hcaptcha_response, FILTER_SANITIZE_FULL_SPECIAL_CHARS ),
			ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401
		);

		// The hCaptcha response field is empty.
		if ( '' === $hcaptcha_response_sanitized ) {
			$result      = $empty_message;
			$error_codes = [ 'empty' ];

			/** This filter is documented above. */
			return apply_filters( 'hcap_verify_request', $result, $error_codes );
		}

		$params = [
			'secret'   => hcaptcha()->settings()->get_secret_key(),
			'response' => $hcaptcha_response_sanitized,
		];

		$ip = hcap_get_user_ip();

		if ( $ip ) {
			$params['remoteip'] = $ip;
		}

		// Verify hCaptcha on the API server.
		$raw_response = wp_remote_post(
			hcaptcha()->get_verify_url(),
			[ 'body' => $params ]
		);

		if ( is_wp_error( $raw_response ) ) {
			$result      = implode( "\n", $raw_response->get_error_messages() );
			$error_codes = $raw_response->get_error_codes();

			/** This filter is documented above. */
			return apply_filters( 'hcap_verify_request', $result, $error_codes );
		}

		$raw_body = wp_remote_retrieve_body( $raw_response );

		// Verification request failed.
		if ( empty( $raw_body ) ) {
			$result      = $fail_message;
			$error_codes = [ 'fail' ];

			/** This filter is documented above. */
			return apply_filters( 'hcap_verify_request', $result, $error_codes );
		}

		$body = json_decode( $raw_body, true );

		// Verification request is not verified.
		if ( ! isset( $body['success'] ) || true !== (bool) $body['success'] ) {
			$error_codes        = $body['error-codes'] ?? [];
			$hcap_error_message = hcap_get_error_message( $error_codes );
			$result             = $hcap_error_message ?: $fail_message;
			$error_codes        = $hcap_error_message ? $error_codes : [ 'fail' ];

			/** This filter is documented above. */
			return apply_filters( 'hcap_verify_request', $result, $error_codes );
		}

		// Success.
		$result      = null;
		$error_codes = [];

		/** This filter is documented above. */
		return apply_filters( 'hcap_verify_request', $result, $error_codes );
	}
}

if ( ! function_exists( 'hcaptcha_verify_post' ) ) {
	/**
	 * Verify POST.
	 *
	 * @param string $nonce_field_name  Nonce field name.
	 * @param string $nonce_action_name Nonce action name.
	 *
	 * @return null|string Null on success, error message on failure.
	 */
	function hcaptcha_verify_post( string $nonce_field_name = HCAPTCHA_NONCE, string $nonce_action_name = HCAPTCHA_ACTION ): ?string {

		$hcaptcha_response = isset( $_POST['h-captcha-response'] ) ?
			filter_var( wp_unslash( $_POST['h-captcha-response'] ), FILTER_SANITIZE_FULL_SPECIAL_CHARS ) :
			'';

		$hcaptcha_nonce = isset( $_POST[ $nonce_field_name ] ) ?
			filter_var( wp_unslash( $_POST[ $nonce_field_name ] ), FILTER_SANITIZE_FULL_SPECIAL_CHARS ) :
			'';

		// Verify nonce for logged-in users only.
		if (
			is_user_logged_in() &&
			! wp_verify_nonce( $hcaptcha_nonce, $nonce_action_name ) &&
			HCaptcha::is_protection_enabled()
		) {
			$errors      = hcap_get_error_messages();
			$result      = $errors['bad-nonce'];
			$error_codes = [ 'bad-nonce' ];

			/** This filter is documented above. */
			return apply_filters( 'hcap_verify_request', $result, $error_codes );
		}

		return hcaptcha_request_verify( $hcaptcha_response );
	}
}

if ( ! function_exists( 'hcaptcha_get_verify_message' ) ) {
	/**
	 * Get 'verify' message.
	 *
	 * @param string $nonce_field_name  Nonce field name.
	 * @param string $nonce_action_name Nonce action name.
	 *
	 * @return null|string Null on success, error message on failure.
	 */
	function hcaptcha_get_verify_message( string $nonce_field_name, string $nonce_action_name ): ?string {
		return hcaptcha_verify_post( $nonce_field_name, $nonce_action_name );
	}
}

if ( ! function_exists( 'hcaptcha_get_verify_message_html' ) ) {
	/**
	 * Get verify message HTML.
	 *
	 * @param string $nonce_field_name  Nonce field name.
	 * @param string $nonce_action_name Nonce action name.
	 *
	 * @return null|string Null on success, error message on failure.
	 */
	function hcaptcha_get_verify_message_html( string $nonce_field_name, string $nonce_action_name ): ?string {
		$message = hcaptcha_verify_post( $nonce_field_name, $nonce_action_name );

		if ( null === $message ) {
			return null;
		}

		$header = _n( 'hCaptcha error:', 'hCaptcha errors:', substr_count( $message, ';' ) + 1, 'hcaptcha-for-forms-and-more' );

		if ( false === strpos( $message, $header ) ) {
			$message = $header . ' ' . $message;
		}

		return str_replace( $header, '<strong>' . $header . '</strong>', $message );
	}
}
