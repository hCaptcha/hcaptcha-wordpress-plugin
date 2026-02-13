<?php
/**
 * The Form class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\ACFE;

use HCaptcha\Helpers\API;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Helpers\Request;

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
	 * Transient name.
	 */
	private const TRANSIENT = 'hcaptcha_acfe';

	/**
	 * Form id.
	 *
	 * @var int
	 */
	protected int $form_id = 0;

	/**
	 * Captcha added.
	 *
	 * @var bool
	 */
	private bool $captcha_added = false;

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
		$this->form_id = (int) $args['ID'];
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
	 * @return bool|string
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function verify( $valid, string $value, array $field, string $input ) {
		if ( ! $field['required'] ) {
			return $valid;
		}

		$form          = $this->get_form();
		$this->form_id = $form['ID'] ?? 0;

		// Avoid duplicate token: process during ajax validation only.
		if ( wp_doing_ajax() ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$post_data = wp_unslash( $_POST );
			$result    = API::verify( $this->get_entry( $post_data ) ) ?: true;

			set_transient( self::TRANSIENT, [ $this->form_id, $result ], 300 );

			return $result;
		}

		$transient = get_transient( self::TRANSIENT );
		$form_id   = $transient[0] ?? false;
		$result    = $transient[1] ?? false;

		return $form_id === $this->form_id ? $result : false;
	}

	/**
	 * Get entry.
	 *
	 * @param array $post_data Post data.
	 *
	 * @return array
	 */
	private function get_entry( array $post_data ): array {
		$form_post = get_post( $this->form_id );
		$acf_data  = $post_data['acf'] ?? [];

		return [
			'nonce_name'         => null,
			'nonce_action'       => null,
			'h-captcha-response' => $post_data['h-captcha-response'] ?? '',
			'form_date_gmt'      => $form_post->post_modified_gmt ?? null,
			'post_data'          => $post_data,
			'data'               => $this->get_data( $acf_data ),
		];
	}

	/**
	 * Get data.
	 *
	 * @param array $acf_data ACF form data.
	 *
	 * @return array
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	private function get_data( array $acf_data ): array {
		$data = [];
		$name = [];

		foreach ( $acf_data as $field_key => $value ) {
			if ( '_validate_email' === $field_key || '' === $value ) {
				continue;
			}

			$value = implode( ' ', (array) $value );

			$acf_field = acf_get_field( $field_key );
			$acf_field = is_array( $acf_field ) ? $acf_field : [];
			$acf_field = wp_parse_args(
				$acf_field,
				[
					'label' => '',
					'type'  => '',
					'name'  => '',
				]
			);

			$label      = $acf_field['label'];
			$type       = $acf_field['type'];
			$field_name = $acf_field['name'];
			$data_key   = $label ?: ( $field_name ?: $field_key );

			if ( '' === $data_key ) {
				continue;
			}

			$label_name = strtolower( $label );

			if ( 'email' === $type ) {
				$data['email'] = $value;
			}

			if ( 'name' === $field_name || 'name' === $label_name ) {
				$name[] = $value;
			}

			$data[ $data_key ] = $value;
		}

		$data['name'] = implode( ' ', $name ) ?: null;

		return $data;
	}

	/**
	 * Get form.
	 *
	 * @return array|false
	 *
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	private function get_form() {
		$acf_form = Request::filter_input( INPUT_POST, '_acf_form' );

		if ( ! $acf_form ) {
			return false;
		}

		// Load registered form using id.
		$form = acf()->form_front->get_form( $acf_form );

		if ( $form ) {
			return $form;
		}

		// Fallback to encrypted JSON.
		$form = json_decode( acf_decrypt( $acf_form ), true );

		return $form ?: false;
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
