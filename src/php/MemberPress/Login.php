<?php
/**
 * Login class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\MemberPress;

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
		add_action( 'mepr-login-form-before-submit', [ $this, 'add_captcha' ] );
	}

	/**
	 * Add hCaptcha to the Register form.
	 */
	public function add_captcha() {
		hcap_form_display( 'hcaptcha_memberpress_register', 'hcaptcha_memberpress_register_nonce' );
	}
}
