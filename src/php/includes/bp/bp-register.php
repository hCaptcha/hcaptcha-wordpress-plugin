<?php
/**
 * BuddyPress register form file.
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
 * BuddyPress register form.
 */
function hcap_display_bp_register() {
	global $bp;

	if ( ! empty( $bp->signup->errors['hcaptcha_response_verify'] ) ) {
		$output = '<div class="error">';

		$output .= $bp->signup->errors['hcaptcha_response_verify'];
		$output .= '</div>';

		echo wp_kses_post( $output );
	}

	hcap_form_display();
	wp_nonce_field( 'hcaptcha_bp_register', 'hcaptcha_bp_register_nonce' );
}

add_action( 'bp_before_registration_submit_buttons', 'hcap_display_bp_register', 10, 0 );

/**
 * Verify BuddyPress register captcha.
 *
 * @return bool
 */
function hcap_verify_bp_register_captcha() {
	global $bp;

	$error_message = hcaptcha_get_verify_message(
		'hcaptcha_bp_register_nonce',
		'hcaptcha_bp_register'
	);

	if ( null === $error_message ) {
		return true;
	}

	$bp->signup->errors['hcaptcha_response_verify'] = $error_message;

	return false;
}

add_action( 'bp_signup_validate', 'hcap_verify_bp_register_captcha' );
