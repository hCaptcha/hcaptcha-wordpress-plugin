<?php
/**
 * Register form file.
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
function hcap_wp_register_form() {
	hcap_form_display();
	wp_nonce_field( 'hcaptcha_registration', 'hcaptcha_registration_nonce' );
}

add_filter( 'register_form', 'hcap_wp_register_form' );

/**
 * Verify register captcha.
 *
 * @param WP_Error $errors               A WP_Error object containing any errors encountered during registration.
 * @param string   $sanitized_user_login User's username after it has been sanitized.
 * @param string   $user_email           User's email.
 *
 * @return mixed
 */
function hcap_verify_register_captcha( $errors, $sanitized_user_login, $user_email ) {
	$error_message = hcaptcha_get_verify_message_html(
		'hcaptcha_registration_nonce',
		'hcaptcha_registration'
	);

	if ( null === $error_message ) {
		return $errors;
	}

	$errors->add( 'invalid_captcha', $error_message );

	return $errors;
}

add_filter( 'registration_errors', 'hcap_verify_register_captcha', 10, 3 );
