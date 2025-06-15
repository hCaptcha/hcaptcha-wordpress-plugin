<?php
/**
 * 'Form' class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\ACFE;

use HCaptcha\Helpers\API;
use HCaptcha\Helpers\HCaptcha;

/**
 * Class Form.
 */
class Form {

	/**
	 * Script handle.
	 */
	public const HANDLE = 'hcaptcha-acfe';

	/**
	 * Render hook.
	 */
	public const RENDER_HOOK = 'acf/render_field/type=acfe_recaptcha';

	/**
	 * Validation hook.
	 */
	public const VALIDATION_HOOK = 'acf/validate_value/type=acfe_recaptcha';

	/**
	 * Form id.
	 *
	 * @var int
	 */
	protected $form_id = 0;

	/**
	 * Captcha added.
	 *
	 * @var bool
	 */
	private $captcha_added = false;

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
	public function init_hooks(): void {
		add_action( 'acfe/form/render/before_fields', [ $this, 'before_fields' ] );
		add_action( self::RENDER_HOOK, [ $this, 'remove_recaptcha_render' ], 8 );
		add_action( self::RENDER_HOOK, [ $this, 'add_hcaptcha' ], 11 );
		add_filter( self::VALIDATION_HOOK, [ $this, 'remove_recaptcha_verify' ], 9, 4 );
		add_filter( self::VALIDATION_HOOK, [ $this, 'verify' ], 11, 4 );
		add_action( 'wp_print_footer_scripts', [ $this, 'enqueue_scripts' ], 9 );
	}

	/**
	 * Store form_id on the before_fields hook.
	 *
	 * @param array $args Arguments.
	 *
	 * @return void
	 */
	public function before_fields( array $args ): void {
		$this->form_id = $args['ID'];
	}

	/**
	 * Start the output buffer on processing the reCaptcha field.
	 *
	 * @param array $field Field.
	 *
	 * @return void
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function remove_recaptcha_render( array $field ): void {
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
	public function add_hcaptcha( array $field ): void {
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

		$this->captcha_added = true;
	}

	/**
	 * Remove reCaptcha verify filter.
	 *
	 * @param bool|mixed $valid Whether the field is valid.
	 * @param string     $value Field Value.
	 * @param array      $field Field.
	 * @param string     $input Input name.
	 *
	 * @return bool|mixed
	 * @noinspection PhpUnusedParameterInspection
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function remove_recaptcha_verify( $valid, string $value, array $field, string $input ) {
		$recaptcha = acf_get_field_type( 'acfe_recaptcha' );

		remove_filter( self::VALIDATION_HOOK, [ $recaptcha, 'validate_value' ] );

		return $valid;
	}

	/**
	 * Verify request.
	 *
	 * @param bool|mixed $valid Whether the field is valid.
	 * @param string     $value Field Value.
	 * @param array      $field Field.
	 * @param string     $input Input name.
	 *
	 * @return bool|mixed
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function verify( $valid, string $value, array $field, string $input ) {
		if ( ! $field['required'] ) {
			return $valid;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$this->form_id = isset( $_POST['_acf_post_id'] )
			? (int) sanitize_text_field( wp_unslash( $_POST['_acf_post_id'] ) )
			: 0;
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$id = HCaptcha::get_widget_id();

		// Avoid duplicate token: do not process during ajax validation.
		// Process hcaptcha widget checks when form protection is skipped.
		/** This filter is documented in the HCaptcha\Helpers\HCaptcha class. */
		if ( wp_doing_ajax() && apply_filters( 'hcap_protect_form', true, $id['source'], $id['form_id'] ) ) {
			return $valid;
		}

		return null === API::verify_request( $value );
	}

	/**
	 * Enqueue scripts.
	 *
	 * @return void
	 */
	public function enqueue_scripts(): void {
		if ( ! $this->captcha_added ) {
			return;
		}

		$min = hcap_min_suffix();

		wp_enqueue_script(
			self::HANDLE,
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
	private function is_recaptcha( array $field ): bool {
		return isset( $field['type'] ) && 'acfe_recaptcha' === $field['type'];
	}
}
