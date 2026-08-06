<?php
/**
 * The Login class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\WP;

use HCaptcha\Abstracts\LoginBase;

/**
 * Class Login
 */
class Login extends LoginBase {

	/**
	 * Whether a valid signature can skip login verification.
	 *
	 * Re-evaluate the server-side login threshold instead of trusting the
	 * hcaptcha_shown claim from a previously rendered page.
	 *
	 * @return bool
	 */
	protected function can_skip_login_verification(): bool {
		return ! $this->is_login_limit_exceeded();
	}

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	protected function init_hooks(): void {
		parent::init_hooks();

		add_action( 'login_form', [ $this, 'add_captcha' ] );
	}

	/**
	 * Add captcha.
	 *
	 * @return void
	 */
	public function add_captcha(): void {
		if ( ! $this->is_wp_login_form() ) {
			return;
		}

		parent::add_captcha();
	}
}
