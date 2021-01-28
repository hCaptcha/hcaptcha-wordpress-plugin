<?php
/**
 * Lost password form file.
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
 * Display on lost password form.
 */
function hcaptcha_lost_password_display() {
	hcap_form_display();
	wp_nonce_field( 'hcaptcha_lost_password', 'hcaptcha_lost_password_nonce' );
}

/**
 * Verify lost password form.
 *
 * @param WP_Error $error Error.
 *
 * @return WP_Error
 */
function hcaptcha_lost_password_verify( $error ) {
	$error_message = hcaptcha_get_verify_message_html( 'hcaptcha_lost_password_nonce', 'hcaptcha_lost_password' );

	if ( null !== $error_message ) {
		$error->add( 'invalid_captcha', $error_message );
	}

	return $error;
}
