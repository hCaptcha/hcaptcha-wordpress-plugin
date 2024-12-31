<?php
/**
 * Login class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Tutor;

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

		add_action( 'tutor_login_form_middle', [ $this, 'add_captcha' ] );
		add_filter( 'wp_authenticate_user', [ $this, 'login_base_verify' ], PHP_INT_MAX, 2 );
	}
}
