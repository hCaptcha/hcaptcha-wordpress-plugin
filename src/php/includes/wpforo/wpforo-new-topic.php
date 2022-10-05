<?php
/**
 * WPForo new topic file.
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
 * New topic form.
 */
function hcap_wpforo_topic_form() {
	hcap_form_display();
	wp_nonce_field( 'hcaptcha_wpforo_new_topic', 'hcaptcha_wpforo_new_topic_nonce' );
}

add_action( 'wpforo_topic_form_buttons_hook', 'hcap_wpforo_topic_form', 99, 0 );

if ( ! function_exists( 'hcap_verify_wpforo_topic_captcha' ) ) {
	/**
	 * Verify new topic captcha.
	 *
	 * @param mixed $data Data.
	 *
	 * @return mixed|bool
	 */
	function hcap_verify_wpforo_topic_captcha( $data ) {
		$error_message = hcaptcha_get_verify_message(
			'hcaptcha_wpforo_new_topic_nonce',
			'hcaptcha_wpforo_new_topic'
		);

		if ( null === $error_message ) {
			return $data;
		}

		WPF()->notice->add( $error_message, 'error' );

		return false;
	}
}

add_filter( 'wpforo_add_topic_data_filter', 'hcap_verify_wpforo_topic_captcha', 10, 1 );

/**
 * Enqueue WPForo script.
 *
 * @return void
 */
function hcap_wpforo_topic_enqueue_scripts() {
	$min = hcap_min_suffix();

	wp_enqueue_script(
		'hcaptcha-wpforo',
		HCAPTCHA_URL . "/assets/js/hcaptcha-wpforo$min.js",
		[ 'jquery', 'wpforo-frontend-js' ],
		HCAPTCHA_VERSION,
		true
	);
}

add_action( 'wp_enqueue_scripts', 'hcap_wpforo_topic_enqueue_scripts' );
