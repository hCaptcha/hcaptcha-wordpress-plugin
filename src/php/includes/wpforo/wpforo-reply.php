<?php
/**
 * WPForo reply file.
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
 * Reply form.
 */
function hcap_wpforo_reply_form() {
	hcap_form_display();
	wp_nonce_field( 'hcaptcha_wpforo_reply', 'hcaptcha_wpforo_reply_nonce' );
}

add_action( 'wpforo_reply_form_buttons_hook', 'hcap_wpforo_reply_form', 99, 0 );

if ( ! function_exists( 'hcap_verify_wpforo_reply_captcha' ) ) {
	/**
	 * Verify reply captcha.
	 *
	 * @param mixed $data Data.
	 *
	 * @return mixed|bool
	 */
	function hcap_verify_wpforo_reply_captcha( $data ) {
		$error_message = hcaptcha_get_verify_message(
			'hcaptcha_wpforo_reply_nonce',
			'hcaptcha_wpforo_reply'
		);

		if ( null === $error_message ) {
			return $data;
		}

		WPF()->notice->add( $error_message, 'error' );

		return false;
	}
}

add_filter( 'wpforo_add_post_data_filter', 'hcap_verify_wpforo_reply_captcha', 10, 1 );
