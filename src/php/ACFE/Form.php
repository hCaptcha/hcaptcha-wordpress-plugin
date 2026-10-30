<?php
/**
 * The Form class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\ACFE;

use HCaptcha\Helpers\API;
use HCaptcha\Helpers\EntryData;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Helpers\Request;
use HCaptcha\Helpers\Utils;

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
	 * Verification result shared by callbacks in one validation stage.
	 *
	 * @var array|null
	 */
	private ?array $form_validation = null;

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
		add_action( 'acfe/form/validate_form', [ $this, 'validate_form' ] );
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
	 * Verify forms with a required CAPTCHA even when its field is omitted from POST.
	 *
	 * @param array $form Registered ACFE form.
	 *
	 * @return void
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function validate_form( array $form ): void {
		if ( ! $this->has_required_recaptcha( $form ) ) {
			return;
		}

		$this->form_validation = null;
		$result                = $this->verify( true, '', [ 'required' => true ], '' );
		$this->form_validation = [
			'form_id'    => (int) ( $form['ID'] ?? 0 ),
			'submission' => $this->get_transient_name( wp_unslash( $_POST ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing -- ACFE verifies its form nonce before this hook.
			'result'     => $result,
			'ajax'       => wp_doing_ajax(),
		];

		if ( true !== $result ) {
			acf_add_validation_error( '', $result );
		}
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

		$form = $this->get_form();

		if ( is_admin() && ( ! wp_doing_ajax() || ! $form ) ) {
			// In admin, the recaptcha field is required and $valid set to false in the acf_validate_value().
			// So, we fix it.
			return true;
		}

		$this->form_id = (int) ( $form['ID'] ?? 0 );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$post_data = wp_unslash( $_POST );

		if (
			null !== $this->form_validation &&
			$this->form_id === $this->form_validation['form_id'] &&
			$this->get_transient_name( $post_data ) === $this->form_validation['submission'] &&
			wp_doing_ajax() === $this->form_validation['ajax']
		) {
			return $this->form_validation['result'];
		}

		$result = $this->verify_submission( $post_data );

		if ( wp_doing_ajax() ) {
			// API::verify() clears the consumed proof from POST; bind the in-request result to that state.
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$validated_data        = wp_unslash( $_POST );
			$this->form_validation = [
				'form_id'    => $this->form_id,
				'submission' => $this->get_transient_name( $validated_data ),
				'result'     => $result,
				'ajax'       => true,
			];
		}

		return $result;
	}

	/**
	 * Verify the current ACFE submission across its AJAX and final POST stages.
	 *
	 * @param array $post_data Unslashed POST data.
	 *
	 * @return bool|string
	 */
	private function verify_submission( array $post_data ) {
		$transient_name = $this->get_transient_name( $post_data );

		// ACFE uses a two-step submission flow: AJAX field validation first, then a direct POST submission.
		// The token/honeypot can only be verified once,
		// so we verify during the AJAX step and cache the result in a transient.
		// On the subsequent direct POST, we retrieve the cached result instead of verifying again
		// (which would fail on a consumed token).
		if ( wp_doing_ajax() ) {
			$result = API::verify( $this->get_entry( $post_data ) ) ?: true;

			set_transient( $transient_name, [ $this->form_id, $result ], 30 );

			return $result;
		}

		$transient = get_transient( $transient_name );

		delete_transient( $transient_name );

		$form_id = $transient[0] ?? false;
		$result  = $transient[1] ?? false;

		if ( $form_id === $this->form_id ) {
			return $result;
		}

		// Some ACFE forms submit directly without the AJAX validation step.
		// In that case, verify the current POST once instead of requiring a transient.
		return API::verify( $this->get_entry( $post_data ) ) ?: true;
	}

	/**
	 * Bind the cached validation result to the same tokens and submitted answers.
	 *
	 * @param array $post_data Post data.
	 *
	 * @return string
	 */
	private function get_transient_name( array $post_data ): string {
		$submission = [
			$this->form_id,
			$post_data['h-captcha-response'] ?? '',
			$post_data['hcap_fst_token'] ?? '',
			$post_data['acf'] ?? [],
		];

		return self::TRANSIENT . '_' . hash( 'sha256', wp_json_encode( $submission ) );
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
			'expected_id'        => $this->get_expected_id(),
		];
	}

	/**
	 * Get expected hCaptcha widget id.
	 *
	 * @return array
	 */
	private function get_expected_id(): array {
		return [
			'source'  => HCaptcha::get_class_source( __CLASS__ ),
			'form_id' => $this->form_id,
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
			if ( '_validate_email' === $field_key ) {
				continue;
			}

			$value = $this->sanitize_acf_value( $value );

			if ( null === $value || '' === $value || [] === $value ) {
				continue;
			}

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

			$data_key = $field_name ?: $field_key;
			$data_key = $label ?: $data_key;

			if ( ! EntryData::is_safe_field( (string) $data_key, (string) $type, (string) $field_name, (string) $label ) ) {
				continue;
			}

			$label_name = strtolower( $label );

			if ( 'email' === $type && is_scalar( $value ) ) {
				$data['email'] = $value;
			}

			if ( ( 'name' === $field_name || 'name' === $label_name ) && is_scalar( $value ) ) {
				$name[] = $value;
			}

			EntryData::add_field( $data, (string) $data_key, $value, (string) $field_key );
		}

		EntryData::add_name( $data, $name );

		return $data;
	}

	/**
	 * Preserve nested ACF answers while excluding sensitive subfields.
	 *
	 * @param mixed $value Submitted ACF value.
	 *
	 * @return array|string|null
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	private function sanitize_acf_value( $value ) {
		if ( ! is_array( $value ) ) {
			return EntryData::sanitize_value( $value );
		}

		$result = [];

		foreach ( $value as $key => $item ) {
			$field = acf_get_field( $key );
			$field = is_array( $field ) ? $field : [];

			if ( EntryData::has_sensitive_field(
				(string) $key,
				(string) ( $field['name'] ?? '' ),
				(string) ( $field['label'] ?? '' ),
				(string) ( $field['type'] ?? '' )
			) ) {
				continue;
			}

			$sanitized = $this->sanitize_acf_value( $item );

			if ( null !== $sanitized ) {
				$result[ $key ] = $sanitized;
			}
		}

		return $result;
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
		$form = Utils::json_decode_arr( acf_decrypt( $acf_form ) );

		return $form ?: false;
	}

	/**
	 * Find required CAPTCHA fields from the form's registered field groups.
	 *
	 * @param array $form Registered ACFE form.
	 *
	 * @return bool
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	private function has_required_recaptcha( array $form ): bool {
		$field_groups = acfe_as_array( $form['field_groups'] ?? [] );

		if ( ! empty( $form['post_field_groups'] ) ) {
			$field_groups = wp_list_pluck( acf_get_field_groups( [ 'post_id' => $form['post_field_groups'] ] ), 'key' );
		}

		foreach ( $field_groups as $group_key ) {
			$fields = acf_get_fields( $group_key );

			if ( ! $fields ) {
				continue;
			}

			foreach ( acfe_get_fields_details_recursive( $fields ) as $details ) {
				$field = $details['field'];

				if ( $this->is_recaptcha( $field ) && ! empty( $field['required'] ) ) {
					return true;
				}
			}
		}

		return false;
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

		$min  = hcap_min_suffix();
		$deps = [ 'jquery', 'hcaptcha' ];

		if ( wp_script_is( 'acf-extended-input', 'registered' ) ) {
			$deps[] = 'acf-extended-input';
		}

		wp_enqueue_script(
			self::HANDLE,
			HCAPTCHA_URL . "/assets/js/hcaptcha-acfe$min.js",
			$deps,
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
