<?php
/**
 * Login class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\WC;

use WP_Error;

/**
 * Class Login
 */
class Login {

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
		add_action( 'woocommerce_login_form', [ $this, 'add_captcha' ] );
		add_action( 'woocommerce_process_login_errors', [ $this, 'verify' ] );
	}

	/**
	 * Add captcha.
	 */
	public function add_captcha() {
		hcap_form_display( 'hcaptcha_login', 'hcaptcha_login_nonce' );
	}

	/**
	 * Verify login form.
	 *
	 * @param WP_Error $validation_error Validation error.
	 *
	 * @return WP_Error
	 */
	public function verify( $validation_error ) {
		$error_message = hcaptcha_get_verify_message(
			'hcaptcha_login_nonce',
			'hcaptcha_login'
		);

		if ( null === $error_message ) {
			return $validation_error;
		}

		$validation_error->add( 'hcaptcha_error', $error_message );

		return $validation_error;
	}
}
