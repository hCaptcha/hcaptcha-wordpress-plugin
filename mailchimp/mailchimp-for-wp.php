<?php
/**
 * Mailchimp form file.
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
 * Add MailChimp form error message.
 *
 * @param array $messages Messages.
 *
 * @return mixed
 */
function hcap_add_mc4wp_error_message( $messages ) {
	$messages['invalid_hcaptcha'] = array(
		'type' => 'error',
		'text' => __( 'The Captcha is invalid.', 'hcaptcha-for-forms-and-more' ),
	);

	return $messages;
}

add_filter( 'mc4wp_form_messages', 'hcap_add_mc4wp_error_message' );

if ( ! function_exists( 'hcap_mailchimp_wp_form' ) ) {
	/**
	 * MailChimp form.
	 *
	 * @param string $content Content.
	 * @param string $form    Form.
	 * @param string $element Element.
	 *
	 * @return string
	 */
	function hcap_mailchimp_wp_form( $content = '', $form = '', $element = '' ) {
		$content = str_replace(
			'<input type="submit"',
			hcap_shortcode() .
			wp_nonce_field( 'hcaptcha_mailchimp', 'hcaptcha_mailchimp_nonce', true, false ) .
			'<input type="submit"',
			$content
		);

		return $content;
	}
}

add_action( 'mc4wp_form_content', 'hcap_mailchimp_wp_form', 20, 3 );

/**
 * Verify MailChimp captcha.
 *
 * @return int|string
 */
function hcap_mc4wp_error() {
	$error_message = hcaptcha_verify_POST(
		'hcaptcha_mailchimp_nonce',
		'hcaptcha_mailchimp'
	);

	if ( 'success' !== $error_message ) {
		return 'invalid_hcaptcha';
	}

	return 1;
}

add_filter( 'mc4wp_valid_form_request', 'hcap_mc4wp_error', 10, 2 );
