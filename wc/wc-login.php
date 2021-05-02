<?php
/**
 * WooCommerce login form file.
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
 * Login form.
 */
function hcap_display_wc_login() {
	hcap_form_display();
	wp_nonce_field( 'hcaptcha_login', 'hcaptcha_login_nonce' );
}

add_action( 'woocommerce_login_form', 'hcap_display_wc_login', 10, 0 );

/**
 * Verify login form.
 *
 * @param WP_Error $validation_error Validation error.
 *
 * @return WP_Error
 */
function hcap_verify_wc_login_captcha( $validation_error ) {
	remove_filter( 'wp_authenticate_user', 'hcap_verify_login_captcha' );

	$error_message = hcaptcha_get_verify_message(
		'hcaptcha_login_nonce',
		'hcaptcha_login'
	);

	if ( null === $error_message ) {
		return $validation_error;
	}

	$validation_error->add( 'hcaptcha_error', $error_message );

	return $validation_error;
}

add_filter( 'woocommerce_process_login_errors', 'hcap_verify_wc_login_captcha' );
