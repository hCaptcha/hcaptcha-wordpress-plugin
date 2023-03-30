<?php
/**
 * Register class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\MemberPress;

use HCaptcha\Helpers\HCaptcha;

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
		add_action( 'mepr-checkout-before-submit', [ $this, 'add_captcha' ] );
		add_filter( 'mepr-validate-signup', [ $this, 'verify' ] );
	}

	/**
	 * Add hCaptcha to the Register form.
	 */
	public function add_captcha() {
		$args = [
			'action' => 'hcaptcha_memberpress_register',
			'name'   => 'hcaptcha_memberpress_register_nonce',
		];

		HCaptcha::form_display( $args );
	}

	/**
	 * Verify hCaptcha.
	 *
	 * @param array $errors Errors.
	 *
	 * @return array
	 */
	public function verify( $errors ) {
		$error_message = hcaptcha_get_verify_message(
			'hcaptcha_memberpress_register_nonce',
			'hcaptcha_memberpress_register'
		);

		if ( null !== $error_message ) {
			$errors[] = $error_message;
		}

		return $errors;
	}
}
