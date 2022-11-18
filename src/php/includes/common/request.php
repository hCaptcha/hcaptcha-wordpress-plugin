<?php
/**
 * Request file.
 *
 * @package hcaptcha-wp
 */

/**
 * Determines the user's actual IP address and attempts to partially
 * anonymize an IP address by converting it to a network ID.
 *
 * Based on the code of the \WP_Community_Events::get_unsafe_client_ip.
 * Returns a string with the IP address or false for local IPs.
 *
 * @return false|string
 */
function hcap_get_user_ip() {
	$client_ip = false;

	// In order of preference, with the best ones for this purpose first.
	$address_headers = [
		'HTTP_CF_CONNECTING_IP',
		'HTTP_CLIENT_IP',
		'HTTP_X_FORWARDED_FOR',
		'HTTP_X_FORWARDED',
		'HTTP_X_CLUSTER_CLIENT_IP',
		'HTTP_FORWARDED_FOR',
		'HTTP_FORWARDED',
		'REMOTE_ADDR',
	];

	foreach ( $address_headers as $header ) {
		if ( array_key_exists( $header, $_SERVER ) ) {
			$address_chain = explode(
				',',
				filter_var( wp_unslash( $_SERVER[ $header ] ), FILTER_SANITIZE_FULL_SPECIAL_CHARS )
			);
			$client_ip     = trim( $address_chain[0] );

			break;
		}
	}

	// Filter out local addresses.
	return filter_var(
		$client_ip,
		FILTER_VALIDATE_IP,
		FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
	);
}

/**
 * Get error messages provided by API and the plugin.
 *
 * @return array
 */
function hcap_get_error_messages() {
	return [
		// API messages.
		'missing-input-secret'             => __( 'Your secret key is missing.', 'hcaptcha-for-forms-and-more' ),
		'invalid-input-secret'             => __( 'Your secret key is invalid or malformed.', 'hcaptcha-for-forms-and-more' ),
		'missing-input-response'           => __( 'The response parameter (verification token) is missing.', 'hcaptcha-for-forms-and-more' ),
		'invalid-input-response'           => __( 'The response parameter (verification token) is invalid or malformed.', 'hcaptcha-for-forms-and-more' ),
		'bad-request'                      => __( 'The request is invalid or malformed.', 'hcaptcha-for-forms-and-more' ),
		'invalid-or-already-seen-response' => __( 'The response parameter has already been checked, or has another issue.', 'hcaptcha-for-forms-and-more' ),
		'not-using-dummy-passcode'         => __( 'You have used a testing sitekey but have not used its matching secret.', 'hcaptcha-for-forms-and-more' ),
		'sitekey-secret-mismatch'          => __( 'The sitekey is not registered with the provided secret.', 'hcaptcha-for-forms-and-more' ),
		// Plugin messages.
		'empty'                            => __( 'Please complete the hCaptcha.', 'hcaptcha-for-forms-and-more' ),
		'fail'                             => __( 'The hCaptcha is invalid.', 'hcaptcha-for-forms-and-more' ),
		'bad-nonce'                        => __( 'Bad hCaptcha nonce!', 'hcaptcha-for-forms-and-more' ),
	];
}

/**
 * Get hCaptcha error message.
 *
 * @param array $error_codes Error codes.
 *
 * @return string
 */
function hcap_get_error_message( $error_codes ) {
	$errors = hcap_get_error_messages();

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

if ( ! function_exists( 'hcaptcha_request_verify' ) ) {
	/**
	 * Verify hCaptcha response.
	 *
	 * @param string|null $hcaptcha_response hCaptcha response.
	 *
	 * @return null|string Null on success, error message on failure.
	 */
	function hcaptcha_request_verify( $hcaptcha_response ) {
		$hcaptcha_response_sanitized = htmlspecialchars(
			filter_var( $hcaptcha_response, FILTER_SANITIZE_FULL_SPECIAL_CHARS )
		);

		$errors = hcap_get_error_messages();

		$empty_message = $errors['empty'];
		$fail_message  = $errors['fail'];

		if ( '' === $hcaptcha_response_sanitized ) {
			return apply_filters( 'hcap_verify_request', $empty_message, [] );
		}

		$params = [
			'secret'   => hcaptcha()->settings()->get_secret_key(),
			'response' => $hcaptcha_response_sanitized,
		];

		$ip = hcap_get_user_ip();

		if ( $ip ) {
			$params['remoteip'] = $ip;
		}

		$raw_response = wp_remote_post(
			'https://api.hcaptcha.com/siteverify',
			[ 'body' => $params ]
		);

		$raw_body = wp_remote_retrieve_body( $raw_response );

		if ( empty( $raw_body ) ) {
			return apply_filters( 'hcap_verify_request', $fail_message, [] );
		}

		$body = json_decode( $raw_body, true );

		// Success.
		$result      = null;
		$error_codes = [];

		if ( ! isset( $body['success'] ) || true !== (bool) $body['success'] ) {
			$error_codes = isset( $body['error-codes'] ) ? $body['error-codes'] : [ 'fail' ];
			$result      = isset( $body['error-codes'] ) ? hcap_get_error_message( $body['error-codes'] ) : $fail_message;
		}

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
	function hcaptcha_verify_post( $nonce_field_name = HCAPTCHA_NONCE, $nonce_action_name = HCAPTCHA_ACTION ) {

		$hcaptcha_response = isset( $_POST['h-captcha-response'] ) ?
			filter_var( wp_unslash( $_POST['h-captcha-response'] ), FILTER_SANITIZE_FULL_SPECIAL_CHARS ) :
			'';

		$hcaptcha_nonce = isset( $_POST[ $nonce_field_name ] ) ?
			filter_var( wp_unslash( $_POST[ $nonce_field_name ] ), FILTER_SANITIZE_FULL_SPECIAL_CHARS ) :
			'';

		// Verify nonce for logged-in users only.
		if ( is_user_logged_in() && ! wp_verify_nonce( $hcaptcha_nonce, $nonce_action_name ) ) {
			$errors = hcap_get_error_messages();

			return apply_filters( 'hcap_verify_request', $errors['bad-nonce'], [ 'bad-nonce' ] );
		}

		return hcaptcha_request_verify( $hcaptcha_response );
	}
}

if ( ! function_exists( 'hcaptcha_get_verify_output' ) ) {
	/**
	 * Get verify output.
	 *
	 * @param string $empty_message     Empty message.
	 * @param string $fail_message      Fail message.
	 * @param string $nonce_field_name  Nonce field name.
	 * @param string $nonce_action_name Nonce action name.
	 *
	 * @return null|string Null on success, error message on failure.
	 */
	function hcaptcha_get_verify_output( $empty_message, $fail_message, $nonce_field_name, $nonce_action_name ) {
		if ( ! empty( $empty_message ) || ! empty( $fail_message ) ) {
			_deprecated_argument( __FUNCTION__, '2.1.0' );
		}

		return hcaptcha_verify_post( $nonce_field_name, $nonce_action_name );
	}
}

if ( ! function_exists( 'hcaptcha_get_verify_message' ) ) {
	/**
	 * Get verify message.
	 *
	 * @param string $nonce_field_name  Nonce field name.
	 * @param string $nonce_action_name Nonce action name.
	 *
	 * @return null|string Null on success, error message on failure.
	 */
	function hcaptcha_get_verify_message( $nonce_field_name, $nonce_action_name ) {
		return hcaptcha_get_verify_output( '', '', $nonce_field_name, $nonce_action_name );
	}
}

if ( ! function_exists( 'hcaptcha_get_verify_message_html' ) ) {
	/**
	 * Get verify message html.
	 *
	 * @param string $nonce_field_name  Nonce field name.
	 * @param string $nonce_action_name Nonce action name.
	 *
	 * @return null|string Null on success, error message on failure.
	 */
	function hcaptcha_get_verify_message_html( $nonce_field_name, $nonce_action_name ) {
		$message = hcaptcha_get_verify_output( '', '', $nonce_field_name, $nonce_action_name );

		if ( null === $message ) {
			return null;
		}

		$message_arr = explode( ';', $message );
		$header      = _n( 'hCaptcha error:', 'hCaptcha errors:', count( $message_arr ), 'hcaptcha-for-forms-and-more' );

		if ( false === strpos( $message, $header ) ) {
			$message = $header . ' ' . $message;
		}

		return str_replace( $header, '<strong>' . $header . '</strong>', $message );
	}
}

if ( ! function_exists( 'hcap_hcaptcha_error_message' ) ) {
	/**
	 * Print error message.
	 *
	 * @param string $hcaptcha_content Content of hCaptcha.
	 *
	 * @return string
	 */
	function hcap_hcaptcha_error_message( $hcaptcha_content = '' ) {
		_deprecated_function( __FUNCTION__, '2.1.0' );

		$message = sprintf(
			'<p id="hcap_error" class="error hcap_error">%s</p>',
			__( 'The hCaptcha is invalid.', 'hcaptcha-for-forms-and-more' )
		);

		return $message . $hcaptcha_content;
	}
}
