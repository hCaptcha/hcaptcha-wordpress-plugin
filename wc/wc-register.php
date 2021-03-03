<?php
/**
 * WooCommerce register form file.
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
 * Register form.
 */
function hcap_display_wc_register() {
	hcap_form_display();
	wp_nonce_field( 'hcaptcha_wc_register', 'hcaptcha_wc_register_nonce' );
}

add_action( 'woocommerce_register_form', 'hcap_display_wc_register', 10, 0 );

/**
 * Verify register captcha.
 *
 * @param WP_Error $validation_error Validation Error.
 *
 * @return WP_Error
 */
function hcap_verify_wc_register_captcha( $validation_error ) {
	$error_message = hcaptcha_get_verify_message(
		'hcaptcha_wc_register_nonce',
		'hcaptcha_wc_register'
	);

	if ( null === $error_message ) {
		return $validation_error;
	}

	$validation_error->add( 'hcaptcha_error', $error_message );

	return $validation_error;
}

add_filter( 'woocommerce_process_registration_errors', 'hcap_verify_wc_register_captcha' );
