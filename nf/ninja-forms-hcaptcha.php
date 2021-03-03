<?php
/**
 * Ninja Form file.
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
 * Filter ninja_forms_register_fields.
 *
 * @param array $fields Fields.
 *
 * @return mixed
 */
function hcap_ninja_forms_register_fields( $fields ) {
	$fields['hcaptcha-for-ninja-forms'] = new HCaptchaFieldsForNF();

	return $fields;
}

add_filter( 'ninja_forms_register_fields', 'hcap_ninja_forms_register_fields' );

/**
 * Add template file path.
 *
 * @param array $paths Paths.
 *
 * @return mixed
 */
function hcap_nf_template_file_paths( $paths ) {
	$paths[] = __DIR__ . '/templates/';

	return $paths;
}

add_filter( 'ninja_forms_field_template_file_paths', 'hcap_nf_template_file_paths' );

/**
 * Filter ninja_forms_localize_field_hcaptcha-for-ninja-forms.
 *
 * @param array $field Field.
 *
 * @return mixed
 */
function ninja_forms_localize_field_hcaptcha_for_ninja_forms_filter( $field ) {
	$field['settings']['hcaptcha_key']         = get_option( 'hcaptcha_api_key' );
	$field['settings']['hcaptcha_theme']       = get_option( 'hcaptcha_theme' );
	$field['settings']['hcaptcha_size']        = get_option( 'hcaptcha_size' );
	$field['settings']['hcaptcha_nonce_field'] = wp_nonce_field(
		'hcaptcha_nf',
		'hcaptcha_nf_nonce',
		true,
		false
	);

	return $field;
}

add_filter(
	'ninja_forms_localize_field_hcaptcha-for-ninja-forms',
	'ninja_forms_localize_field_hcaptcha_for_ninja_forms_filter',
	10,
	1
);

/**
 * Enqueue script.
 */
function hcap_nf_captcha_script() {
	wp_enqueue_script(
		'nf-hcaptcha-js',
		plugin_dir_url( __FILE__ ) . 'nf-hcaptcha.js',
		array( 'nf-front-end' ),
		HCAPTCHA_VERSION,
		true
	);

	wp_add_inline_script(
		'nf-hcaptcha-js',
		'setTimeout(function(){window.hcaptcha.render("nf-hcaptcha")}, 1000);'
	);
}

add_action( 'wp_enqueue_scripts', 'hcap_nf_captcha_script' );
