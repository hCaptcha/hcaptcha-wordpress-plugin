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
	 * @param WP_Error $validation_error Validation error.
	 *
	 * @return WP_Error
	 */
	public function verify( $post ) {
        if ( isset( $post['mode'] ) && $post['mode'] == 'login') {
            $error_message = hcaptcha_request_verify($post['h-captcha-response']);

            if ( 'success' !== $error_message ) {
                UM()->form()->add_error( 'hcaptcha_error', $error_message );
                return;
            }
        }
	}
}
