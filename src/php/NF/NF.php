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
class NF implements Base {

	/**
	 * Dialog scripts and style handle.
	 */
	private const DIALOG_HANDLE = 'kagg-dialog';

	/**
	 * Script handle.
	 */
	private const HANDLE = 'hcaptcha-nf';

	/**
	 * Admin script handle.
	 */
	private const ADMIN_HANDLE = 'admin-nf';

	/**
	 * Script localization object.
	 */
	private const OBJECT = 'HCaptchaAdminNFObject';

	/**
	 * Form id.
	 *
	 * @var int
	 */
	protected $form_id = 0;

	/**
	 * Templates dir.
	 *
	 * @var string
	 */
	protected $templates_dir;

	/**
	 * NF constructor.
	 */
	public function __construct() {
		$this->templates_dir = __DIR__ . '/templates/';

		$this->init_hooks();
	}

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	public function init_hooks(): void {
		$name = Base::NAME;

		add_action( 'toplevel_page_ninja-forms', [ $this, 'admin_template' ], 11 );
		add_action( 'nf_admin_enqueue_scripts', [ $this, 'nf_admin_enqueue_scripts' ] );
		add_filter( 'ninja_forms_register_fields', [ $this, 'register_fields' ] );
		add_filter( 'ninja_forms_field_template_file_paths', [ $this, 'template_file_paths' ] );
		add_action( 'nf_get_form_id', [ $this, 'set_form_id' ] );
		add_filter( "ninja_forms_localize_field_$name", [ $this, 'localize_field' ] );
		add_filter( "ninja_forms_localize_field_{$name}_preview", [ $this, 'localize_field' ] );
		add_action( 'wp_print_footer_scripts', [ $this, 'nf_captcha_script' ], 9 );
		add_filter( 'script_loader_tag', [ $this, 'add_type_module' ], 10, 3 );
	}

	/**
	 * Display template on form edit page.
	 *
	 * @return void
	 */
	public function admin_template(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['form_id'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$template = file_get_contents( $this->templates_dir . 'fields-hcaptcha.html' );

		// Fix bug in Ninja forms.
		// For template script id, they expect field->_name in admin, but field->_type on the frontend.
		// It works for NF fields as all fields have _name === _type.

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo str_replace(
			'tmpl-nf-field-' . Base::TYPE,
			'tmpl-nf-field-' . Base::NAME,
			$template
		);
	}

	/**
	 * Add hCaptcha to the field data.
	 *
	 * @return void
	 */
	public function nf_admin_enqueue_scripts(): void {
		global $wp_scripts;

		// Add hCaptcha to the preloaded form data.
		$data = $wp_scripts->registered['nf-builder']->extra['data'] ?? '';

		if ( ! preg_match( '/var nfDashInlineVars = (.+);/', $data, $m ) ) {
			return;
		}

		$vars  = json_decode( $m[1], true );
		$found = false;

		foreach ( $vars['preloadedFormData']['fields'] as & $field ) {
			// See comment in admin_template().
			if ( Base::NAME === $field['type'] ) {
				$found             = true;
				$search            = 'class="h-captcha"';
				$field['hcaptcha'] = str_replace(
					$search,
					$search . ' style="z-index: 2;"',
					$this->get_hcaptcha( $field['id'] )
				);

				break;
			}
		}

		unset( $field );

		if ( $found ) {
			$data = str_replace( $m[1], wp_json_encode( $vars ), $data );

			$wp_scripts->registered['nf-builder']->extra['data'] = $data;
		}

		// Enqueue admin script.
		$min = hcap_min_suffix();

		wp_enqueue_script(
			self::DIALOG_HANDLE,
			constant( 'HCAPTCHA_URL' ) . "/assets/js/kagg-dialog$min.js",
			[],
			constant( 'HCAPTCHA_VERSION' ),
			true
		);

		wp_enqueue_style(
			self::DIALOG_HANDLE,
			constant( 'HCAPTCHA_URL' ) . "/assets/css/kagg-dialog$min.css",
			[],
			constant( 'HCAPTCHA_VERSION' )
		);

		wp_enqueue_script(
			self::ADMIN_HANDLE,
			HCAPTCHA_URL . "/assets/js/admin-nf$min.js",
			[ self::DIALOG_HANDLE ],
			HCAPTCHA_VERSION,
			true
		);

		wp_localize_script(
			self::ADMIN_HANDLE,
			self::OBJECT,
			[
				'onlyOne'   => __( 'Only one hCaptcha field allowed.', 'hcaptcha-for-forms-and-more' ),
				'OKBtnText' => __( 'OK', 'hcaptcha-for-forms-and-more' ),
			]
		);
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

		$index = array_search( 'recaptcha', array_keys( $fields ), true );
		$index = false === $index ? count( $fields ) : $index;

		return array_merge(
			array_slice( $fields, 0, $index ),
			[ Base::NAME => new Field() ],
			array_slice( $fields, $index )
		);
	}

	/**
	 * Add a template file path.
	 *
	 * @param array|mixed $paths Paths.
	 *
	 * @return array
	 */
	public function template_file_paths( $paths ): array {
		$paths = (array) $paths;

		$paths[] = $this->templates_dir;

		return $paths;
	}

	/**
	 * Get form id.
	 *
	 * @param int $form_id Form id.
	 *
	 * @return void
	 */
	public function set_form_id( int $form_id ): void {
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

		$field['settings']['hcaptcha'] = $field['settings']['hcaptcha'] ?? $this->get_hcaptcha( $field['id'] );

		// Mark hCaptcha as shown in any case. Needed on the preview page.
		hcaptcha()->form_shown = true;

		return $field;
	}

	/**
	 * Get hCaptcha.
	 *
	 * @param int|string $field_id Field id.
	 *
	 * @return string
	 */
	private function get_hcaptcha( $field_id ): string {
		$hcaptcha_id = uniqid( 'hcaptcha-nf-', true );

		// Nonce is checked by Ninja forms.
		$args = [
			'id' => [
				'source'  => HCaptcha::get_class_source( __CLASS__ ),
				'form_id' => $this->form_id,
			],
		];

		$hcaptcha = HCaptcha::form( $args );

		return str_replace(
			'<h-captcha',
			'<h-captcha id="' . $hcaptcha_id . '" data-fieldId="' . $field_id . '"',
			$hcaptcha
		);
	}

	/**
	 * Enqueue script.
	 *
	 * @return void
	 */
	public function nf_captcha_script(): void {
		$min = hcap_min_suffix();

		wp_enqueue_script(
			self::HANDLE,
			HCAPTCHA_URL . "/assets/js/hcaptcha-nf$min.js",
			[ 'jquery', Main::HANDLE, 'nf-front-end', 'nf-front-end-deps' ],
			HCAPTCHA_VERSION,
			true
		);
	}

	/**
	 * Add type="module" attribute to script tag.
	 *
	 * @param string|mixed $tag    Script tag.
	 * @param string       $handle Script handle.
	 * @param string       $src    Script source.
	 *
	 * @return string
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_type_module( $tag, string $handle, string $src ): string {
		$tag = (string) $tag;

		if ( self::HANDLE !== $handle ) {
			return $tag;
		}

		return HCaptcha::add_type_module( $tag );
	}
}
