<?php
/**
 * LostPassword class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\UM;

/**
 * Class LostPassword
 */
class LostPassword {

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
		add_action( 'um_after_password_reset_fields', [ $this, 'add_captcha' ] );
		add_action( 'um_reset_password_errors_hook', [ $this, 'verify' ] );
	}

	/**
	 * Add captcha.
	 */
	public function add_captcha() {
		hcap_form_display( 'hcaptcha_um_lost_password', 'hcaptcha_um_lost_password_nonce' );
	}

	/**
	 * Verify lost password form.
	 *
	 * @param array $post Form submitted.
	 *
	 * @return void
	 */
	public function verify( $post ) {
		$um = UM();

		if ( ! $um || ! isset( $post['mode'] ) || 'password' !== $post['mode'] ) {
			return;
		}

		$error_message = hcaptcha_request_verify( $post['h-captcha-response'] );

		if ( 'success' === $error_message ) {
			return;
		}

		$um->form()->add_error( 'hcaptcha_error', $error_message );
	}
}
