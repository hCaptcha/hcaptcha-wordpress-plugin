<?php
/**
 * Functions file.
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
 * Action that fires immediately before the submit button element is displayed.
 *
 * @link  https://wpforms.com/developers/wpforms_display_submit_before/
 *
 * @param array $form_data Form data and settings...
 */
function hcaptcha_wpforms_display( $form_data ) {
	hcap_form_display( 'hcaptcha_wpforms', 'hcaptcha_wpforms_nonce' );
}

add_filter( 'wpforms_display_submit_before', 'hcaptcha_wpforms_display', 10, 1 );

/**
 * Action that fires during form entry processing after initial field validation.
 *
 * @link   https://wpforms.com/developers/wpforms_process/
 *
 * @param  array $fields    Sanitized entry field. values/properties.
 * @param  array $entry     Original $_POST global.
 * @param  array $form_data Form data and settings.
 *
 * @return array|null
 */
function hcaptcha_wpforms_validate( $fields, $entry, $form_data ) {
	$error_message = hcaptcha_get_verify_message(
		'hcaptcha_wpforms_nonce',
		'hcaptcha_wpforms'
	);

	if ( null === $error_message ) {
		return $fields;
	}

	wpforms()->process->errors[ $form_data['id'] ]['footer'] = __( 'Captcha Failed', 'hcaptcha-for-forms-and-more' );

	return null;
}

add_filter( 'wpforms_process', 'hcaptcha_wpforms_validate', 10, 3 );
