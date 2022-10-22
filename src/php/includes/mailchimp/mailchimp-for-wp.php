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
 * Add MailChimp form error messages.
 *
 * @param array      $messages Messages.
 * @param MC4WP_Form $form     Form.
 *
 * @return array
 * @noinspection PhpUnusedParameterInspection
 */
function hcap_add_mc4wp_error_message( $messages, $form ) {
	$messages['invalid_hcaptcha'] = array(
		'type' => 'error',
		'text' => __( 'The Captcha is invalid.', 'hcaptcha-for-forms-and-more' ),
	);

	return $messages;
}

add_filter( 'mc4wp_form_messages', 'hcap_add_mc4wp_error_message', 10, 2 );

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
		return str_replace(
			'<input type="submit"',
			hcap_form( 'hcaptcha_mailchimp', 'hcaptcha_mailchimp_nonce' ) .
			'<input type="submit"',
			$content
		);
	}
}

add_action( 'mc4wp_form_content', 'hcap_mailchimp_wp_form', 20, 3 );

/**
 * Verify MailChimp captcha.
 *
 * @param bool  $valid Whether request is valid.
 * @param array $data  Form data.
 *
 * @return null|string
 * @noinspection PhpUnusedParameterInspection
 */
function hcap_mc4wp_error( $valid, $data ) {
	$verify = hcaptcha_verify_post( 'hcaptcha_mailchimp_nonce', 'hcaptcha_mailchimp' );

	if ( null !== $verify ) {
		return $verify;
	}

	return $valid;
}

add_filter( 'mc4wp_valid_form_request', 'hcap_mc4wp_error', 10, 2 );
