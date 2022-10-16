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
 * Get hCaptcha error message.
 *
 * @param array $error_codes Error codes.
 *
 * @return string
 */
function hcap_get_error_message( $error_codes ) {
	$errors = [
		'missing-input-secret'             => __( 'Your secret key is missing.', 'hcaptcha-for-forms-and-more' ),
		'invalid-input-secret'             => __( 'Your secret key is invalid or malformed.', 'hcaptcha-for-forms-and-more' ),
		'missing-input-response'           => __( 'The response parameter (verification token) is missing.', 'hcaptcha-for-forms-and-more' ),
		'invalid-input-response'           => __( 'The response parameter (verification token) is invalid or malformed.', 'hcaptcha-for-forms-and-more' ),
		'bad-request'                      => __( 'The request is invalid or malformed.', 'hcaptcha-for-forms-and-more' ),
		'invalid-or-already-seen-response' => __( 'The response parameter has already been checked, or has another issue.', 'hcaptcha-for-forms-and-more' ),
		'not-using-dummy-passcode'         => __( 'You have used a testing sitekey but have not used its matching secret.', 'hcaptcha-for-forms-and-more' ),
		'sitekey-secret-mismatch'          => __( 'The sitekey is not registered with the provided secret.', 'hcaptcha-for-forms-and-more' ),
	];

	$message_arr = [];

	foreach ( $error_codes as $error_code ) {
		if ( array_key_exists( $error_code, $errors ) ) {
			$message_arr[] = $errors[ $error_code ];
		}
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
	 * @return string fail|success|error message
	 */
	function hcaptcha_request_verify( $hcaptcha_response ) {
		$hcaptcha_response_sanitized = htmlspecialchars(
			filter_var( $hcaptcha_response, FILTER_SANITIZE_FULL_SPECIAL_CHARS )
		);

		if ( '' === $hcaptcha_response_sanitized ) {
			return 'fail';
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
			return 'fail';
		}

		$body = json_decode( $raw_body, true );

		if ( true !== (bool) $body['success'] ) {
			return isset( $body['error-codes'] ) ? hcap_get_error_message( $body['error-codes'] ) : 'fail';
		}

		return 'success';
	}
}

if ( ! function_exists( 'hcaptcha_verify_POST' ) ) {
	// phpcs:disable WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid

	/**
	 * Verify POST.
	 *
	 * @param string $nonce_field_name  Nonce field name.
	 * @param string $nonce_action_name Nonce action name.
	 *
	 * @return string fail|success|empty
	 */
	function hcaptcha_verify_POST( $nonce_field_name = HCAPTCHA_NONCE, $nonce_action_name = HCAPTCHA_ACTION ) {
		// phpcs:enable WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid

		if (
			! isset( $_POST[ $nonce_field_name ], $_POST['h-captcha-response'] ) ||
			empty( $_POST['h-captcha-response'] ) ||
			(
				// Verify nonce for logged-in users only.
				is_user_logged_in() &&
				! wp_verify_nonce( filter_var( wp_unslash( $_POST[ $nonce_field_name ] ), FILTER_SANITIZE_FULL_SPECIAL_CHARS ), $nonce_action_name )
			)
		) {
			return 'empty';
		}

		return hcaptcha_request_verify(
			filter_var( wp_unslash( $_POST['h-captcha-response'] ), FILTER_SANITIZE_FULL_SPECIAL_CHARS )
		);
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
	 * @return null|string
	 */
	function hcaptcha_get_verify_output( $empty_message, $fail_message, $nonce_field_name, $nonce_action_name ) {
		$result = hcaptcha_verify_POST( $nonce_field_name, $nonce_action_name );

		switch ( $result ) {
			case 'empty':
				return $empty_message;
			case 'fail':
				return $fail_message;
			default:
				return $result ?: null;
		}
	}
}

if ( ! function_exists( 'hcaptcha_get_verify_message' ) ) {
	/**
	 * Get verify message.
	 *
	 * @param string $nonce_field_name  Nonce field name.
	 * @param string $nonce_action_name Nonce action name.
	 *
	 * @return null|string
	 */
	function hcaptcha_get_verify_message( $nonce_field_name, $nonce_action_name ) {
		return hcaptcha_get_verify_output(
			__( 'Please complete the captcha.', 'hcaptcha-for-forms-and-more' ),
			__( 'The Captcha is invalid.', 'hcaptcha-for-forms-and-more' ),
			$nonce_field_name,
			$nonce_action_name
		);
	}
}

if ( ! function_exists( 'hcaptcha_get_verify_message_html' ) ) {
	/**
	 * Get verify message html.
	 *
	 * @param string $nonce_field_name  Nonce field name.
	 * @param string $nonce_action_name Nonce action name.
	 *
	 * @return null|string
	 */
	function hcaptcha_get_verify_message_html( $nonce_field_name, $nonce_action_name ) {
		return hcaptcha_get_verify_output(
			__( '<strong>Error</strong>: Please complete the captcha.', 'hcaptcha-for-forms-and-more' ),
			__( '<strong>Error</strong>: The Captcha is invalid.', 'hcaptcha-for-forms-and-more' ),
			$nonce_field_name,
			$nonce_action_name
		);
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
		$message = sprintf(
			'<p id="hcap_error" class="error hcap_error">%s</p>',
			__( 'The Captcha is invalid.', 'hcaptcha-for-forms-and-more' )
		);

		return $message . $hcaptcha_content;
	}
}

