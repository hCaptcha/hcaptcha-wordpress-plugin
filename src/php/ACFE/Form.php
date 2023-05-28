<?php
/**
 * Form class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\ACFE;

use HCaptcha\Helpers\HCaptcha;

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
	 * Form id.
	 *
	 * @var int
	 */
	private $form_id = 0;

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
		add_action( 'acfe/form/render/before_fields', [ $this, 'before_fields' ] );
		add_action( self::RENDER_HOOK, [ $this, 'remove_recaptcha_render' ], 8 );
		add_action( self::RENDER_HOOK, [ $this, 'add_hcaptcha' ], 11 );
		add_filter( self::VALIDATION_HOOK, [ $this, 'remove_recaptcha_verify' ], 9, 4 );
		add_filter( self::VALIDATION_HOOK, [ $this, 'verify' ], 11, 4 );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
	}

	/**
	 * Store form_id on before_fields hook.
	 *
	 * @param array $args Arguments.
	 *
	 * @return void
	 */
	public function before_fields( $args ) {
		$this->form_id = $args['ID'];
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

		$args = [
			'id' => [
				'source'  => HCaptcha::get_class_source( __CLASS__ ),
				'form_id' => $this->form_id,
			],
		];

		$form =
			'<div class="acf-input-wrap acfe-field-recaptcha"> ' .
			'<div>' . HCaptcha::form( $args ) . '</div>' .
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

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$this->form_id = isset( $_POST['_acf_post_id'] ) ?
			(int) sanitize_text_field( wp_unslash( $_POST['_acf_post_id'] ) ) :
			0;
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$id = HCaptcha::get_widget_id();

		// Avoid duplicate token: do not process during ajax validation.
		// Process hcaptcha widget check when form protection is skipped.
		if ( wp_doing_ajax() && apply_filters( 'hcap_protect_form', true, $id['source'], $id['form_id'] ) ) {
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
