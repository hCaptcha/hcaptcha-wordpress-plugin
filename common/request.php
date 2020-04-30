<?php
if ( ! function_exists( 'hcaptcha_request_verify' ) ) {
	/**
	 * @param $hcaptcha_response
	 *
	 * @return string fail|success
	 */
	function hcaptcha_request_verify( $hcaptcha_response ) {
		$hcaptcha_response_sanitized = htmlspecialchars( sanitize_text_field( $hcaptcha_response ) );

		$hcaptcha_secret_key = get_option( 'hcaptcha_secret_key' );
		$rawResponse            = wp_remote_get( 'https://hcaptcha.com/siteverify?secret=' . $hcaptcha_secret_key . '&response=' . $hcaptcha_response_sanitized );
		$rawBody = wp_remote_retrieve_body($rawResponse);
		if ( empty($rawBody) ) {
			return 'fail';
		}
		$body = json_decode( $rawBody, true );
		if ( $body['success'] != true ) {
			return 'fail';
		}

		return 'success';
	}
}
if ( ! function_exists( 'hcaptcha_verify_POST' ) ) {
	/**
	 * @param $nonceFieldName
	 * @param $nonceActionName
	 *
	 * @return string fail|success|empty
	 */
	function hcaptcha_verify_POST( $nonceFieldName, $nonceActionName ) {
		if ( ! isset( $_POST[ $nonceFieldName ] )
		     || ! wp_verify_nonce( $_POST[ $nonceFieldName ], $nonceActionName )
		     || ! isset( $_POST['h-captcha-response'] )
		     || empty( $_POST['h-captcha-response'] ) ) {
			return 'empty';
		}

		return hcaptcha_request_verify( $_POST['h-captcha-response'] );
	}
}
if ( ! function_exists( 'hcaptcha_get_verify_output' ) ) {
	/**
	 * @param $emptyMessage
	 * @param $failMessage
	 * @param $nonceFieldName
	 * @param $nonceActionName
	 *
	 * @return null|string null|$emptyMessage|$failMessage
	 */
	function hcaptcha_get_verify_output( $emptyMessage, $failMessage, $nonceFieldName, $nonceActionName ) {
		$result = hcaptcha_verify_POST( $nonceFieldName, $nonceActionName );
		switch ( $result ) {
			case 'empty':
				return $emptyMessage;
			case 'fail':
				return $failMessage;
			default:
				return null;
		}
	}
}

if ( ! function_exists( 'hcaptcha_get_verify_message' ) ) {
	/**
	 * @param $nonceFieldName
	 * @param $nonceActionName
	 *
	 * @return null|string null|$emptyMessage|$failMessage
	 */
	function hcaptcha_get_verify_message( $nonceFieldName, $nonceActionName ) {
		return hcaptcha_get_verify_output(
			__( 'Please complete the captcha.', 'hcaptcha-wp' ),
			__( 'The Captcha is invalid.', 'hcaptcha-wp' ),
			$nonceFieldName,
			$nonceActionName
		);
	}
}
if ( ! function_exists( 'hcaptcha_get_verify_message_html' ) ) {
	/**
	 * @param $nonceFieldName
	 * @param $nonceActionName
	 *
	 * @return null|string null|$emptyMessage|$failMessage
	 */
	function hcaptcha_get_verify_message_html( $nonceFieldName, $nonceActionName ) {
		return hcaptcha_get_verify_output(
			__( '<strong>Error</strong>: Please complete the captcha.', 'hcaptcha-wp' ),
			__( '<strong>Error</strong>: The Captcha is invalid.', 'hcaptcha-wp' ),
			$nonceFieldName,
			$nonceActionName
		);
	}
}

