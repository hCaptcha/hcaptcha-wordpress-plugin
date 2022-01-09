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
 * @link  https://fluentforms.com/docs/fluentform_after_form_render
 * @param array $form Form data and settings...
 */
function hcaptcha_fluentform_display( $form ) {
	hcap_form_display( 'hcaptcha_fluentform', 'hcaptcha_fluentform_nounce' );
}

add_action( 'fluentform_form_element_start', 'hcaptcha_fluentform_display', 10, 1 );
add_action( 'fluentform_after_form_render', 'hcaptcha_fluentform_display', 10, 1 );

/**
 * Action that fires during form entry processing after initial field validation.
 *
 * @link  https://fluentforms.com/docs/fluentform_before_insert_submission
 *
 * @param  array $insertData    Sanitized entry field. values/properties.
 * @param  array $data          Original $_POST global.
 * @param  array $form          Form data and settings.
 *
 * @return void
 */
function hcaptcha_fluentform_validate( $insertData, $data, $form ) {
	$error_message = hcaptcha_get_verify_message(
		'hcaptcha_fluentform_nounce',
		'hcaptcha_fluentform'
	);

	if ( null === $error_message ) {
		return;
	}

	wp_send_json([
		'errors' => [
			'g-recaptcha-response' => [
				$error_message
			]
		]
	], 422);

	return;
}

add_action( 'fluentform_before_insert_submission', 'hcaptcha_fluentform_validate', 10, 3 );
