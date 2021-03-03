<?php
/**
 * Comment form file.
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
 * Comment form.
 */
function hcap_wp_comment_form() {
	hcap_form_display();
	wp_nonce_field( 'hcaptcha_comment_form', 'hcaptcha_comment_form_nonce' );
}

add_action( 'comment_form_after_fields', 'hcap_wp_comment_form' );

/**
 * Login comment form.
 *
 * @param string $field Field.
 *
 * @return string
 */
function hcap_wp_login_comment_form( $field ) {
	if ( is_user_logged_in() ) {
		$output = $field;

		$output .= hcap_form();
		$output .= wp_nonce_field(
			'hcaptcha_comment_form',
			'hcaptcha_comment_form_nonce',
			true,
			false
		);

		return $output;
	}

	return $field;
}

add_filter( 'comment_form_field_comment', 'hcap_wp_login_comment_form', 10, 1 );

/**
 * Verify comment.
 *
 * @param array $commentdata Comment data.
 *
 * @return mixed
 */
function hcap_verify_comment_captcha( $commentdata ) {
	$error_message = hcaptcha_get_verify_message_html(
		'hcaptcha_comment_form_nonce',
		'hcaptcha_comment_form'
	);

	if ( null === $error_message ) {
		return $commentdata;
	}

	if ( is_admin() ) {
		return $commentdata;
	}

	wp_die( wp_kses_post( $error_message ), 'hCaptcha', array( 'back_link' => true ) );
}

add_filter( 'preprocess_comment', 'hcap_verify_comment_captcha' );
