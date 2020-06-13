<?php
/**
 * Ninja Form file.
 *
 * @package hcaptcha-wp
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter(
	'ninja_forms_register_fields',
	function ( $fields ) {
		require_once plugin_dir_path( __FILE__ ) . 'class-nf-hcaptcha.php';
		$fields['hcaptcha-for-ninja-forms'] = new HCaptchaFieldsForNF();

		return $fields;
	}
);

/**
 * Add template file path.
 *
 * @param array $paths Paths.
 *
 * @return mixed
 */
function hcap_nf_template_file_paths( $paths ) {
	$paths[] = dirname( __FILE__ ) . '/templates/';

	return $paths;
}

add_filter( 'ninja_forms_field_template_file_paths', 'hcap_nf_template_file_paths' );

add_filter(
	'ninja_forms_localize_field_hcaptcha-for-ninja-forms',
	function ( $field ) {
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
	},
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
		[ 'nf-front-end' ],
		HCAPTCHA_VERSION,
		true
	);

	wp_add_inline_script(
		'nf-hcaptcha-js',
		'setTimeout(function(){window.hcaptcha.render("nf-hcaptcha")}, 1000);'
	);
}

add_action( 'wp_enqueue_scripts', 'hcap_nf_captcha_script' );
