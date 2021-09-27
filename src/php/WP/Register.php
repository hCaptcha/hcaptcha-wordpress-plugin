<?php
/**
 * Register class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\WP;

use WP_Error;

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
		add_action( 'register_form', [ $this, 'add_captcha' ] );
		add_action( 'registration_errors', [ $this, 'verify' ], 10, 3 );
	}

	/**
	 * Add captcha.
	 */
	public function add_captcha() {
		hcap_form_display( 'hcaptcha_registration', 'hcaptcha_registration_nonce' );
	}

	/**
	 * Verify register captcha.
	 *
	 * @param WP_Error $errors               A WP_Error object containing any errors encountered during registration.
	 * @param string   $sanitized_user_login User's username after it has been sanitized.
	 * @param string   $user_email           User's email.
	 *
	 * @return WP_Error
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function verify( $errors, $sanitized_user_login, $user_email ) {
		$error_message = hcaptcha_get_verify_message_html(
			'hcaptcha_registration_nonce',
			'hcaptcha_registration'
		);

		if ( null === $error_message ) {
			return $errors;
		}

		$errors->add( 'invalid_captcha', $error_message );

		return $errors;
	}
}
