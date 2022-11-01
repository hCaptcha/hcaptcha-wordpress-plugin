<?php
/**
 * NF form class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\NF;

/**
 * Class NF
 * Support Ninja Forms.
 */
class NF {

	/**
	 * NF constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Init hooks.
	 */
	public function init_hooks() {
		add_filter( 'ninja_forms_register_fields', [ $this, 'register_fields' ] );
		add_filter( 'ninja_forms_field_template_file_paths', [ $this, 'template_file_paths' ] );
		add_filter( 'ninja_forms_localize_field_hcaptcha-for-ninja-forms', [ $this, 'localize_field' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'nf_captcha_script' ] );
	}

	/**
	 * Filter ninja_forms_register_fields.
	 *
	 * @param array $fields Fields.
	 *
	 * @return array
	 */
	public function register_fields( $fields ) {
		$fields['hcaptcha-for-ninja-forms'] = new Fields();

		return $fields;
	}

	/**
	 * Add template file path.
	 *
	 * @param array $paths Paths.
	 *
	 * @return array
	 */
	public function template_file_paths( $paths ) {
		$paths[] = __DIR__ . '/templates/';

		return $paths;
	}

	/**
	 * Filter ninja_forms_localize_field_hcaptcha-for-ninja-forms.
	 *
	 * @param array $field Field.
	 *
	 * @return array
	 */
	public function localize_field( $field ) {

		$settings                            = hcaptcha()->settings();
		$field['settings']['hcaptcha_id']    = uniqid( 'hcaptcha-nf-', true );
		$field['settings']['hcaptcha_key']   = $settings->get_site_key();
		$field['settings']['hcaptcha_theme'] = $settings->get( 'theme' );
		$hcaptcha_size                       = $settings->get( 'size' );

		// Invisible is not supported by Ninja Forms so far.
		$hcaptcha_size = 'invisible' === $hcaptcha_size ? 'normal' : $hcaptcha_size;

		$field['settings']['hcaptcha_size']        = $hcaptcha_size;
		$field['settings']['hcaptcha_nonce_field'] = wp_nonce_field(
			'hcaptcha_nf',
			'hcaptcha_nf_nonce',
			true,
			false
		);

		hcaptcha()->form_shown = true;

		return $field;
	}

	/**
	 * Enqueue script.
	 */
	public function nf_captcha_script() {
		$min = hcap_min_suffix();

		wp_enqueue_script(
			'hcaptcha-nf',
			HCAPTCHA_URL . "/assets/js/hcaptcha-nf$min.js",
			[ 'nf-front-end' ],
			HCAPTCHA_VERSION,
			true
		);
	}
}
