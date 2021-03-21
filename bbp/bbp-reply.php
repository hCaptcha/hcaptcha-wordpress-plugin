<?php
/**
 * BBPress new topic form file.
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
 * BBPress reply form.
 */
function hcap_display_bbp_reply() {
	hcap_form_display();
	wp_nonce_field( 'hcaptcha_bbp_reply', 'hcaptcha_bbp_reply_nonce' );
}

add_action( 'bbp_theme_after_reply_form_content', 'hcap_display_bbp_reply', 10, 0 );

/**
 * Verify BBPress reply captcha.
 *
 * @return bool
 */
function hcap_verify_bbp_reply_captcha() {
	$error_message = hcaptcha_get_verify_message(
		'hcaptcha_bbp_reply_nonce',
		'hcaptcha_bbp_reply'
	);
	if ( null === $error_message ) {
		return true;
	}

	bbp_add_error( 'hcap_error', $error_message );

	return false;
}

add_action( 'bbp_new_reply_pre_extras', 'hcap_verify_bbp_reply_captcha' );
