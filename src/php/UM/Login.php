<?php
/**
 * Login class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\UM;

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
		add_action( 'um_after_login_fields', [ $this, 'add_captcha' ] );
		add_action( 'um_submit_form_errors_hook_login', [ $this, 'verify' ] );
	}

	/**
	 * Add captcha.
	 */
	public function add_captcha() {
		hcap_form_display( 'hcaptcha_um_login', 'hcaptcha_um_login_nonce' );
	}

	/**
	 * Verify login form.
	 *
	 * @param array $args Form arguments.
	 *
	 * @return void
	 */
	public function verify( $args ) {
		$um = UM();

		if ( ! $um || ! isset( $args['mode'] ) || 'login' !== $args['mode'] ) {
			return;
		}

		$error_message = hcaptcha_request_verify( $args['h-captcha-response'] );

		if ( 'success' === $error_message ) {
			return;
		}

		$um->form()->add_error( 'hcaptcha_error', $error_message );
	}
}
