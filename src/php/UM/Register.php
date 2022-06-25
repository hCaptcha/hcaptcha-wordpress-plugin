<?php
/**
 * Register class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\UM;

/**
 * Class Register
 */
class Register {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Init hooks.
	 */
	private function init_hooks() {
		$key = 'hcaptcha';

		add_filter( 'um_get_form_fields', [ $this, 'add_captcha' ], 100 );
		add_filter( "um_{$key}_form_edit_field", [ $this, 'display_hcaptcha' ], 10, 2 );
		add_action( 'um_submit_form_errors_hook__registration', [ $this, 'verify' ] );
	}

	/**
	 * Add hCaptcha to form fields.
	 *
	 * @param array $fields Form fields.
	 *
	 * @return array
	 */
	public function add_captcha( $fields ) {
		$max_position = 0;
		$last_key     = '';
		$in_row       = '';
		$in_sub_row   = '';
		$in_column    = '';
		$in_group     = '';

		foreach ( $fields as $key => $field ) {
			if ( ! isset( $field['position'] ) ) {
				continue;
			}

			if ( $field['position'] <= $max_position ) {
				continue;
			}

			$max_position = $field['position'];
			$last_key     = $key;
			$in_row       = $field['in_row'];
			$in_sub_row   = $field['in_sub_row'];
			$in_column    = $field['in_column'];
			$in_group     = $field['in_group'];
		}

		if ( ! $last_key ) {
			return $fields;
		}

		$fields['hcaptcha'] = [
			'title'        => __( 'hCaptcha', 'hcaptcha-for-forms-and-more' ),
			'metakey'      => 'hcaptcha',
			'type'         => 'hcaptcha',
			'label'        => __( 'hCaptcha', 'hcaptcha-for-forms-and-more' ),
			'required'     => 0,
			'public'       => 0,
			'editable'     => 0,
			'account_only' => true,
			'position'     => (string) ( $max_position + 1 ),
			'in_row'       => $in_row,
			'in_sub_row'   => $in_sub_row,
			'in_column'    => $in_column,
			'in_group'     => $in_group,
		];

		return $fields;
	}

	/**
	 * Display hCaptcha.
	 *
	 * @param string $output Output.
	 * @param string $mode   Mode.
	 *
	 * @return string
	 */
	public function display_hcaptcha( $output, $mode ) {
		if ( 'register' !== $mode || '' !== $output ) {
			return $output;
		}

		$output = '<div class="um-field um-field-hcaptcha">';

		$output .= hcap_form( 'hcaptcha_um_registration', 'hcaptcha_um_registration_nonce' );
		$output .= '</div>';

		$um = UM();

		if ( ! $um ) {
			return $output;
		}

		$fields = $um->fields();

		if ( $fields->is_error( 'hcaptcha' ) ) {
			$output .= $fields->field_error( $fields->show_error( 'hcaptcha' ) );
		}

		return $output;
	}

	/**
	 * Verify register form.
	 *
	 * @param array $args Form arguments.
	 *
	 * @return void
	 */
	public function verify( $args ) {
		$um = UM();

		if ( ! $um || ! isset( $args['mode'] ) || 'register' !== $args['mode'] ) {
			return;
		}

		$error_message = hcaptcha_get_verify_message(
			'hcaptcha_um_registration_nonce',
			'hcaptcha_um_registration'
		);

		if ( null === $error_message ) {
			return;
		}

		$um->form()->add_error( 'hcaptcha', $error_message );
	}
}
