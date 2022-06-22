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
		add_action( 'um_after_register_fields', [ $this, 'add_captcha' ] );
		add_action( 'um_submit_form_errors_hook__registration', [ $this, 'verify' ] );
	}

	/**
	 * Add captcha.
	 */
	public function add_captcha() {
		hcap_form_display( 'hcaptcha_um_registration', 'hcaptcha_um_registration_nonce' );
	}

	/**
	 * Verify register form.
	 *
	 * @param WP_Error $validation_error Validation error.
	 *
	 * @return WP_Error
	 */
	public function verify( $post ) {
        if ( isset( $post['mode'] ) && $post['mode'] == 'register') {
            $error_message = $error_message = hcaptcha_request_verify($post['h-captcha-response']);

            if ( 'success' !== $error_message ) {
                UM()->form()->add_error( 'hcaptcha_error', $error_message );
                return;
            }
        }
	}
}
