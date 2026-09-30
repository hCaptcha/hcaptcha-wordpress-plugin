<?php
/**
 * The Login class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\ClassifiedListing;

use HCaptcha\Abstracts\LoginBase;
use WP_Error;
use WP_User;

/**
 * Class Login.
 */
class Login extends LoginBase {
	use RefreshTokens;

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	protected function init_hooks(): void {
		parent::init_hooks();

		add_action( 'rtcl_login_form', [ $this, 'add_captcha' ] );
		add_filter( 'wp_authenticate_user', [ $this, 'verify' ], 10, 2 );
	}

	/**
	 * Add hCaptcha to the login form.
	 *
	 * @return void
	 */
	public function add_captcha(): void {
		$this->enqueue_refresh_tokens_script();

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
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_POST['rtcl-login-nonce'] ) ) {
			return $user;
		}

		return $this->login_base_verify( $user, $password );
	}
}
