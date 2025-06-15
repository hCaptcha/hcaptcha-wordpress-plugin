<?php
/**
 * 'Register' class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\ProfileBuilder;

use HCaptcha\Helpers\API;
use HCaptcha\Helpers\HCaptcha;
use WP_Error;

/**
 * Class Register.
 */
class Register {

	/**
	 * Nonce action.
	 */
	private const ACTION = 'hcaptcha_profile_builder_register';

	/**
	 * Nonce name.
	 */
	private const NONCE = 'hcaptcha_profile_builder_register_nonce';

	/**
	 * The hCaptcha validation error message.
	 *
	 * @var string|null
	 */
	private $error_message;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	protected function init_hooks(): void {
		add_action( 'wppb_register_form_content', [ $this, 'add_captcha' ] );
		add_filter( 'wppb_output_field_errors_filter', [ $this, 'verify' ], 10, 4 );
		add_filter( 'wppb_general_top_error_message', [ $this, 'general_top_error_message' ] );
	}

	/**
	 * Add captcha.
	 *
	 * @param string|mixed $form_content Form content.
	 *
	 * @return string|mixed
	 */
	public function add_captcha( $form_content ) {
		$args = [
			'action' => self::ACTION,
			'name'   => self::NONCE,
			'id'     => [
				'source'  => HCaptcha::get_class_source( __CLASS__ ),
				'form_id' => 'register',
			],
		];

		// Do not close this tag.
		$search = '<p class="form-submit"';

		return str_replace( $search, HCaptcha::form( $args ) . $search, $form_content );
	}

	/**
	 * Verify login form.
	 *
	 * @param string[]|mixed $output_field_errors Validation errors.
	 * @param array          $form_fields         Form fields.
	 * @param array          $global_request      Copy of $_POST.
	 * @param string         $form_type           Form type.
	 *
	 * @return WP_Error|mixed
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function verify( $output_field_errors, array $form_fields, array $global_request, string $form_type ) {
		if ( 'register' !== $form_type ) {
			return $output_field_errors;
		}

		$this->error_message = API::verify_post( self::NONCE, self::ACTION );

		if ( null === $this->error_message ) {
			return $output_field_errors;
		}

		$output_field_errors   = (array) $output_field_errors;
		$output_field_errors[] = $this->error_message;

		return $output_field_errors;
	}

	/**
	 * Error message.
	 *
	 * @param string|mixed $top_error_message Error message.
	 *
	 * @return string|mixed
	 */
	public function general_top_error_message( $top_error_message ) {
		if ( ! $this->error_message ) {
			return $top_error_message;
		}

		return $top_error_message . '<p class="wppb-error">' . $this->error_message . '</p>';
	}
}
