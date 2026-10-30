<?php
/**
 * The Login class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\ClassifiedListing;

use HCaptcha\Abstracts\LoginBase;
use WP_Error;

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
		add_filter( 'rtcl_process_login_errors', [ $this, 'verify' ], 10, 3 );
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
	 * @param WP_Error $errors   Native Classified Listing validation errors.
	 * @param string   $username Submitted username.
	 * @param string   $password Submitted password.
	 *
	 * @return WP_Error
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function verify( WP_Error $errors, string $username, string $password ): WP_Error {
		return $this->login_base_verify( $errors, $password );
	}
}
