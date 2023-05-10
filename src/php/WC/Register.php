<?php
/**
 * Register class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\WC;

use HCaptcha\Helpers\HCaptcha;
use WP_Error;

/**
 * Class Register
 */
class Register {
	/**
	 * Nonce action.
	 */
	const ACTION = 'hcaptcha_wc_register';

	/**
	 * Nonce name.
	 */
	const NONCE = 'hcaptcha_wc_register_nonce';

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
		add_action( 'woocommerce_register_form', [ $this, 'add_captcha' ] );
		add_action( 'woocommerce_process_registration_errors', [ $this, 'verify' ] );
	}

	/**
	 * Add captcha.
	 */
	public function add_captcha() {
		$args = [
			'action' => self::ACTION,
			'name'   => self::NONCE,
			'id'     => [
				'source'  => HCaptcha::get_class_source( __CLASS__ ),
				'form_id' => 'register',
			],
		];

		HCaptcha::form_display( $args );
	}

	/**
	 * Verify register form.
	 *
	 * @param WP_Error $validation_error Validation error.
	 *
	 * @return WP_Error
	 */
	public function verify( $validation_error ) {
		$error_message = hcaptcha_get_verify_message(
			self::NONCE,
			self::ACTION
		);

		if ( null === $error_message ) {
			return $validation_error;
		}

		$validation_error->add( 'hcaptcha_error', $error_message );

		return $validation_error;
	}
}
