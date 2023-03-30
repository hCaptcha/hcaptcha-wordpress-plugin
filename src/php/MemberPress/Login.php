<?php
/**
 * Login class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\MemberPress;

use HCaptcha\Helpers\HCaptcha;

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
		$args = [
			'action' => 'hcaptcha_memberpress_register',
			'name'   => 'hcaptcha_memberpress_register_nonce',
		];

		HCaptcha::form_display( $args );
	}
}
