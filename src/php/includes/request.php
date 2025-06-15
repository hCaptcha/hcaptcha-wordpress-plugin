<?php
/**
 * Request file.
 *
 * @package hcaptcha-wp
 */

use HCaptcha\Helpers\API;

/**
 * Determines the user's actual IP address and attempts to partially
 * anonymize an IP address by converting it to a network ID.
 *
 * Based on the code of the \WP_Community_Events::get_unsafe_client_ip.
 * Returns a string with the IP address or false for local IPs.
 *
 * @param bool $filter_out_local Whether to filter out local addresses.
 *
 * @return string|false
 */
function hcap_get_user_ip( bool $filter_out_local = true ) {
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
	return (
	$filter_out_local
		? filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE )
		: filter_var( $ip, FILTER_VALIDATE_IP )
	);
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
			'spam'                     => __( 'Anti-spam check failed.', 'hcaptcha-for-forms-and-more' ),
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

if ( ! function_exists( 'hcaptcha_get_verify_message' ) ) {
	/**
	 * Verify POST.
	 *
	 * @deprecated 4.15.0 Use \HCaptcha\Helpers\API::verify_post().
	 *
	 * @param string $nonce_field_name  Nonce field name.
	 * @param string $nonce_action_name Nonce action name.
	 *
	 * @return null|string Null on success, error message on failure.
	 */
	function hcaptcha_get_verify_message( string $nonce_field_name, string $nonce_action_name ): ?string {
		_deprecated_function( __FUNCTION__, '4.15.0', 'API::verify_post()' );

		return API::verify_post( $nonce_field_name, $nonce_action_name );
	}
}

if ( ! function_exists( 'hcaptcha_get_verify_message_html' ) ) {
	/**
	 * Get verify message HTML.
	 *
	 * @deprecated 4.15.0 Use \HCaptcha\Helpers\API::verify_post_html().
	 *
	 * @param string $nonce_field_name  Nonce field name.
	 * @param string $nonce_action_name Nonce action name.
	 *
	 * @return null|string Null on success, error message on failure.
	 */
	function hcaptcha_get_verify_message_html( string $nonce_field_name, string $nonce_action_name ): ?string {
		_deprecated_function( __FUNCTION__, '4.15.0', '\HCaptcha\Helpers\API::verify_post_html()' );

		return API::verify_post_html( $nonce_field_name, $nonce_action_name );
	}
}

if ( ! function_exists( 'hcaptcha_verify_post' ) ) {
	/**
	 * Verify POST.
	 *
	 * @deprecated 4.15.0 Use \HCaptcha\Helpers\API::verify_post().
	 *
	 * @param string $nonce_field_name  Nonce field name.
	 * @param string $nonce_action_name Nonce action name.
	 *
	 * @return null|string Null on success, error message on failure.
	 */
	function hcaptcha_verify_post( string $nonce_field_name = HCAPTCHA_NONCE, string $nonce_action_name = HCAPTCHA_ACTION ): ?string {
		_deprecated_function( __FUNCTION__, '4.15.0', '\HCaptcha\Helpers\API::verify_post()' );

		return API::verify_post( $nonce_field_name, $nonce_action_name );
	}
}

if ( ! function_exists( 'hcaptcha_request_verify' ) ) {
	/**
	 * Verify hCaptcha response.
	 *
	 * @deprecated 4.15.0 Use \HCaptcha\Helpers\API::verify_request().
	 *
	 * @param string|null $hcaptcha_response hCaptcha response.
	 *
	 * @return null|string Null on success, error message on failure.
	 * @noinspection PhpMissingParamTypeInspection
	 */
	function hcaptcha_request_verify( $hcaptcha_response = null ): ?string {
		_deprecated_function( __FUNCTION__, '4.15.0', '\HCaptcha\Helpers\API::verify_request()' );

		return API::verify_request( $hcaptcha_response );
	}
}
