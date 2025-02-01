<?php
/**
 * Login class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\LearnPress;

use HCaptcha\Abstracts\LoginBase;
use HCaptcha\Helpers\HCaptcha;
use WP_Error;
use WP_User;

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
		add_filter( 'wp_authenticate_user', [ $this, 'verify' ], 10, 2 );
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

	/**
	 * Verify a login form.
	 *
	 * @param WP_User|WP_Error $user     WP_User or WP_Error object
	 *                                   if a previous callback failed authentication.
	 * @param string           $password Password to check against the user.
	 *
	 * @return WP_User|WP_Error
	 */
	public function verify( $user, string $password ) {
		if ( ! $this->is_learn_press_form() ) {
			return $user;
		}

		return $this->login_base_verify( $user, $password );
	}

	/**
	 * Whether we process the Learn Press login form.
	 *
	 * @return bool
	 */
	private function is_learn_press_form(): bool {
		return HCaptcha::did_filter( 'learnpress_login_credentials' );
	}
}
