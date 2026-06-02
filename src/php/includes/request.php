<?php
/**
 * Request file.
 *
 * @package hcaptcha-wp
 */

use HCaptcha\Helpers\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	// @codeCoverageIgnoreStart
	exit;
	// @codeCoverageIgnoreEnd
}

/**
 * Get supported client IP address headers.
 *
 * @return string[]
 */
function hcap_get_address_headers(): array {
	return [
		'HTTP_TRUE_CLIENT_IP',
		'HTTP_CF_CONNECTING_IP',
		'HTTP_X_REAL_IP',
		'HTTP_CLIENT_IP',
		'HTTP_X_FORWARDED_FOR',
		'HTTP_X_FORWARDED',
		'HTTP_X_CLUSTER_CLIENT_IP',
		'HTTP_FORWARDED_FOR',
		'HTTP_FORWARDED',
	];
}

/**
 * Get client IP address headers enabled in settings.
 *
 * @param string[] $address_headers Supported client IP address headers.
 *
 * @return string[]
 */
function hcap_get_enabled_address_headers( array $address_headers ): array {
	$settings = function_exists( 'hcaptcha' ) ? hcaptcha()->settings() : null;

	if ( ! $settings ) {
		return [];
	}

	if ( hcap_use_legacy_address_headers( $settings->get_raw_settings() ) ) {
		return $address_headers;
	}

	$trusted_address_headers = (array) $settings->get( 'trusted_address_headers' );

	return array_values( array_intersect( $address_headers, $trusted_address_headers ) );
}

/**
 * Filter and normalize address headers.
 *
 * @param string[] $address_headers          Address headers.
 * @param string   $remote_addr              Current REMOTE_ADDR value.
 * @param string[] $standard_address_headers Supported address headers.
 *
 * @return string[]
 */
function hcap_filter_address_headers( array $address_headers, string $remote_addr, array $standard_address_headers ): array {
	/**
	 * Filters the list of address headers to trust.
	 *
	 * @param string[] $address_headers          Enabled address headers.
	 * @param string   $remote_addr              Current REMOTE_ADDR value.
	 * @param string[] $standard_address_headers Supported address headers.
	 */
	$address_headers = (array) apply_filters(
		'hcap_trusted_address_headers',
		$address_headers,
		$remote_addr,
		$standard_address_headers
	);

	$address_headers = array_filter( $address_headers, 'is_string' );

	return array_values( array_unique( $address_headers ) );
}

/**
 * Whether to keep legacy address header behavior before upgrade migration runs.
 *
 * @param array|null $settings Raw settings.
 *
 * @return bool
 */
function hcap_use_legacy_address_headers( ?array $settings ): bool {
	if ( ! is_array( $settings ) || array_key_exists( 'trusted_address_headers', $settings ) ) {
		return false;
	}

	$migrated_versions = (array) get_option( 'hcaptcha_versions', [] );

	return ! array_key_exists( '5.0.0', $migrated_versions );
}

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

	$remote_addr = isset( $_SERVER['REMOTE_ADDR'] )
		? filter_var( wp_unslash( $_SERVER['REMOTE_ADDR'] ), FILTER_SANITIZE_FULL_SPECIAL_CHARS )
		: '';

	$standard_address_headers = hcap_get_address_headers();
	$address_headers          = hcap_get_enabled_address_headers( $standard_address_headers );
	$address_headers          = hcap_filter_address_headers(
		$address_headers,
		$remote_addr,
		$standard_address_headers
	);

	// Fall back to REMOTE_ADDR if no other headers are present.
	$address_headers[] = 'REMOTE_ADDR';
	$address_headers   = array_values( array_unique( $address_headers ) );

	foreach ( $address_headers as $header ) {
		if ( ! array_key_exists( $header, $_SERVER ) ) {
			continue;
		}

		/*
		 * Some address headers can contain a chain of comma-separated addresses.
		 * When a trusted header is enabled, use the first address in the chain.
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
			'fst-no-object'            => __( 'FST object does not exist.', 'hcaptcha-for-forms-and-more' ),
			'fst-too-fast'             => __( 'Form submitted too quickly.', 'hcaptcha-for-forms-and-more' ),
			'fst-replayed-or-expired'  => __( 'Token replayed or expired.', 'hcaptcha-for-forms-and-more' ),
			'fst-expired'              => __( 'Token expired.', 'hcaptcha-for-forms-and-more' ),
			'disposable-email'         => __( 'Please use a permanent email address.', 'hcaptcha-for-forms-and-more' ),
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
 * Get WP_Error from hCaptcha error code.
 *
 * @param string|string[] $error_codes Error codes.
 *
 * @return WP_Error
 */
function hcap_get_wp_error( $error_codes ): WP_Error {
	$error_codes = (array) $error_codes;
	$errors      = hcap_get_error_messages();
	$wp_error    = new WP_Error();

	foreach ( $error_codes as $error_code ) {
		if ( array_key_exists( $error_code, $errors ) ) {
			$wp_error->add( $error_code, $errors[ $error_code ] );
		}
	}

	return $wp_error;
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

	$body = Utils::json_decode_arr( $raw_body );

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
