<?php
/**
 * Login class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\LearnPress;

use HCaptcha\Abstracts\LoginBase;

/**
 * Class Login.
 */
class Login extends LoginBase {

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	protected function init_hooks(): void {
		parent::init_hooks();

		add_action( 'login_form', [ $this, 'add_captcha' ] );
		add_filter( 'wp_authenticate_user', [ $this, 'login_base_verify' ], PHP_INT_MAX, 2 );
	}

	/**
	 * Add hCaptcha.
	 *
	 * @return void
	 */
	public function add_captcha(): void {
		if ( ! did_action( 'learn-press/after-form-login-fields' ) ) {
			return;
		}

		parent::add_captcha();
	}
}
