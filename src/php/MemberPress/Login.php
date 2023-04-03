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
	 * Nonce action.
	 */
	const ACTION = 'hcaptcha_memberpress_register';

	/**
	 * Nonce name.
	 */
	const NONCE = 'hcaptcha_memberpress_register_nonce';

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
			'action' => self::ACTION,
			'name'   => self::NONCE,
		];

		HCaptcha::form_display( $args );
	}
}
