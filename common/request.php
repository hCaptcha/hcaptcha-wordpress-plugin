<?php
/**
 * Request file.
 *
 * @package hcaptcha-wp
 */

if ( ! function_exists( 'hcaptcha_request_verify' ) ) {
	/**
	 * Verify hCaptcha response.
	 *
	 * @param string $hcaptcha_response hCaptcha response.
	 *
	 * @return string fail|success
	 */
	function hcaptcha_request_verify( $hcaptcha_response ) {
		$hcaptcha_response_sanitized = htmlspecialchars(
			filter_var( $hcaptcha_response, FILTER_SANITIZE_STRING )
		);

		$hcaptcha_secret_key = get_option( 'hcaptcha_secret_key' );
		$raw_response        = wp_remote_get(
			'https://hcaptcha.com/siteverify?secret=' .
			esc_html( $hcaptcha_secret_key ) . '&response=' . $hcaptcha_response_sanitized
		);
		$raw_body            = wp_remote_retrieve_body( $raw_response );

		if ( empty( $raw_body ) ) {
			return 'fail';
		}

		$body = json_decode( $raw_body, true );

		if ( true !== (bool) $body['success'] ) {
			return 'fail';
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
	function hcaptcha_verify_POST( $nonce_field_name, $nonce_action_name ) {
		// phpcs:enable WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid

		if (
			! isset( $_POST[ $nonce_field_name ], $_POST['h-captcha-response'] ) ||
			empty( $_POST['h-captcha-response'] ) ||
			! wp_verify_nonce( filter_var( wp_unslash( $_POST[ $nonce_field_name ] ), FILTER_SANITIZE_STRING ), $nonce_action_name )
		) {
			return 'empty';
		}

		return hcaptcha_request_verify(
			filter_var( wp_unslash( $_POST['h-captcha-response'] ), FILTER_SANITIZE_STRING )
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
				return null;
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
