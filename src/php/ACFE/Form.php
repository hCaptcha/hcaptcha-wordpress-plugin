<?php
/**
 * Form class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\ACFE;

/**
 * Class Form.
 */
class Form {

	/**
	 * Render hook.
	 */
	const RENDER_HOOK = 'acf/render_field/type=acfe_recaptcha';

	/**
	 * Validation hook.
	 */
	const VALIDATION_HOOK = 'acf/validate_value/type=acfe_recaptcha';

	/**
	 * Form constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Add hooks.
	 *
	 * @return void
	 */
	public function init_hooks() {
		add_action( self::RENDER_HOOK, [ $this, 'remove_recaptcha_render' ], 8 );
		add_action( self::RENDER_HOOK, [ $this, 'add_hcaptcha' ], 11 );
		add_filter( self::VALIDATION_HOOK, [ $this, 'remove_recaptcha_verify' ], 9, 4 );
		add_filter( self::VALIDATION_HOOK, [ $this, 'verify' ], 11, 4 );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
	}

	/**
	 * Start output buffer on processing the reCaptcha field.
	 *
	 * @param array $field Field.
	 *
	 * @return void
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function remove_recaptcha_render( $field ) {
		if ( ! $this->is_recaptcha( $field ) ) {
			return;
		}

		$recaptcha = acf_get_field_type( 'acfe_recaptcha' );

		remove_action( self::RENDER_HOOK, [ $recaptcha, 'render_field' ], 9 );
	}

	/**
	 * Replaces reCaptcha field by hCaptcha.
	 *
	 * @param array $field Field.
	 *
	 * @return void
	 */
	public function add_hcaptcha( $field ) {
		if ( ! $this->is_recaptcha( $field ) ) {
			return;
		}

		$form =
			'<div class="acf-input-wrap acfe-field-recaptcha"> ' .
			'<div>' . hcap_form() . '</div>' .
			'<input type="hidden" id="acf-' . $field['key'] . '" name="' . $field['name'] . '">' .
			'</div>';

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $form;
	}

	/**
	 * Remove reCaptcha verify filter.
	 *
	 * @param bool   $valid Whether field is valid.
	 * @param string $value Field Value.
	 * @param array  $field Field.
	 * @param string $input Input name.
	 *
	 * @return bool
	 * @noinspection PhpUnusedParameterInspection
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function remove_recaptcha_verify( $valid, $value, $field, $input ) {
		$recaptcha = acf_get_field_type( 'acfe_recaptcha' );

		remove_filter( self::VALIDATION_HOOK, [ $recaptcha, 'validate_value' ] );

		return $valid;
	}

	/**
	 * Verify request.
	 *
	 * @param bool   $valid Whether field is valid.
	 * @param string $value Field Value.
	 * @param array  $field Field.
	 * @param string $input Input name.
	 *
	 * @return bool
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function verify( $valid, $value, $field, $input ) {
		if ( ! $field['required'] ) {
			return $valid;
		}

		// Avoid duplicate token: do not process during ajax validation.
		if ( wp_doing_ajax() ) {
			return $valid;
		}

		return null === hcaptcha_request_verify( $value );
	}

	/**
	 * Enqueue scripts.
	 */
	public function enqueue_scripts() {
		$min = hcap_min_suffix();

		wp_enqueue_script(
			'hcaptcha-acfe',
			HCAPTCHA_URL . "/assets/js/hcaptcha-acfe$min.js",
			[ 'jquery', 'hcaptcha' ],
			HCAPTCHA_VERSION,
			true
		);
	}

	/**
	 * Whether it is the reCaptcha field.
	 *
	 * @param array $field Field.
	 *
	 * @return bool
	 */
	private function is_recaptcha( $field ) {
		return isset( $field['type'] ) && 'acfe_recaptcha' === $field['type'];
	}
}
