<?php
/**
 * NF form class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\NF;

use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Main;

/**
 * Class NF
 * Support Ninja Forms.
 */
class NF {
	/**
	 * Form id.
	 *
	 * @var int
	 */
	private $form_id = 0;

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
		add_action( 'nf_get_form_id', [ $this, 'set_form_id' ] );
		add_filter( 'ninja_forms_localize_field_hcaptcha-for-ninja-forms', [ $this, 'localize_field' ] );
		add_action( 'wp_print_footer_scripts', [ $this, 'nf_captcha_script' ], 9 );
	}

	/**
	 * Filter ninja_forms_register_fields.
	 *
	 * @param array|mixed $fields Fields.
	 *
	 * @return array
	 */
	public function register_fields( $fields ): array {
		$fields = (array) $fields;

		$fields['hcaptcha-for-ninja-forms'] = new Fields();

		return $fields;
	}

	/**
	 * Add template file path.
	 *
	 * @param array|mixed $paths Paths.
	 *
	 * @return array
	 */
	public function template_file_paths( $paths ): array {
		$paths = (array) $paths;

		$paths[] = __DIR__ . '/templates/';

		return $paths;
	}

	/**
	 * Get form id.
	 *
	 * @param int $form_id Form id.
	 *
	 * @return void
	 */
	public function set_form_id( int $form_id ) {
		$this->form_id = $form_id;
	}

	/**
	 * Filter ninja_forms_localize_field_hcaptcha-for-ninja-forms.
	 *
	 * @param array|mixed $field Field.
	 *
	 * @return array
	 */
	public function localize_field( $field ): array {
		$field = (array) $field;

		$settings                         = hcaptcha()->settings();
		$hcaptcha_id                      = uniqid( 'hcaptcha-nf-', true );
		$field['settings']['hcaptcha_id'] = $hcaptcha_id;
		$hcaptcha_size                    = $settings->get( 'size' );

		// Invisible is not supported by Ninja Forms so far.
		$hcaptcha_size = 'invisible' === $hcaptcha_size ? 'normal' : $hcaptcha_size;

		// Nonce is checked by Ninja forms.
		$args = [
			'id'   => [
				'source'  => HCaptcha::get_class_source( __CLASS__ ),
				'form_id' => $this->form_id,
			],
			'size' => $hcaptcha_size,
		];

		$hcaptcha = HCaptcha::form( $args );
		$id       = $field['id'] ?? 0;
		$hcaptcha = str_replace(
			'<div',
			'<div id="' . $hcaptcha_id . '" data-fieldId="' . $id . '"',
			$hcaptcha
		);

		$field['settings']['hcaptcha'] = $hcaptcha;

		hcaptcha()->form_shown = true;

		return $field;
	}

	/**
	 * Enqueue script.
	 *
	 * @return void
	 */
	public function nf_captcha_script() {
		$min = hcap_min_suffix();

		wp_enqueue_script(
			'hcaptcha-nf',
			HCAPTCHA_URL . "/assets/js/hcaptcha-nf$min.js",
			[ 'jquery', Main::HANDLE, 'nf-front-end', 'nf-front-end-deps' ],
			HCAPTCHA_VERSION,
			true
		);
	}
}
