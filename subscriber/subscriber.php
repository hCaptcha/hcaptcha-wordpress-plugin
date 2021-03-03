<?php
/**
 * Subscriber file.
 *
 * @package hcaptcha-wp
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	// @codeCoverageIgnoreStart
	exit;
	// @codeCoverageIgnoreEnd
}

/**
 * Subscriber form.
 *
 * @return string
 */
function hcap_subscriber_form() {
	$output = hcap_form();

	$output .= wp_nonce_field(
		'hcaptcha_subscriber_form',
		'hcaptcha_subscriber_form_nonce',
		true,
		false
	);

	return $output;
}

add_filter( 'sbscrbr_add_field', 'hcap_subscriber_form', 10, 0 );

if ( ! function_exists( 'hcap_subscriber_verify' ) ) {
	/**
	 * Verify subscriber captcha.
	 *
	 * @param bool $check_result Check result.
	 *
	 * @return bool|string
	 */
	function hcap_subscriber_verify( $check_result = true ) {
		$error_message = hcaptcha_get_verify_message( 'hcaptcha_subscriber_form_nonce', 'hcaptcha_subscriber_form' );
		if ( null === $error_message ) {
			return $check_result;
		}

		return $error_message;
	}
}

add_filter( 'sbscrbr_check', 'hcap_subscriber_verify' );
