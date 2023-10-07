<?php
/**
 * Login class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\LearnDash;

use HCaptcha\Abstracts\LoginBase;
use WP_Error;
use WP_User;

/**
 * Class Login.
 */
class Login extends LoginBase {

	/**
	 * Init hooks.
	 */
	protected function init_hooks() {
		parent::init_hooks();

		add_filter( 'login_form_middle', [ $this, 'add_learn_dash_captcha' ], 10, 2 );

		// Check login status, because class is always loading when LearDash plugin is active.
		if ( hcaptcha()->settings()->is( 'learn_dash_status', 'login' ) ) {
			add_filter( 'wp_authenticate_user', [ $this, 'verify' ], 10, 2 );
		} else {
			add_filter( 'hcap_protect_form', [ $this, 'protect_form' ], 10, 3 );
		}
	}

	/**
	 * Add captcha.
	 *
	 * @param string|mixed $content Content to display. Default empty.
	 * @param array        $args    Array of login form arguments.
	 *
	 * @return string
	 * @noinspection PhpMissingParamTypeInspection
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_learn_dash_captcha( $content, $args ): string {
		ob_start();
		$this->add_captcha();

		return (string) ob_get_clean();
	}

	/**
	 * Verify login form.
	 *
	 * @param WP_User|WP_Error $user     WP_User or WP_Error object if a previous
	 *                                   callback failed authentication.
	 * @param string           $password Password to check against the user.
	 *
	 * @return WP_User|WP_Error
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function verify( $user, string $password ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_POST['learndash-login-form'] ) ) {
			return $user;
		}

		if ( ! $this->is_login_limit_exceeded() ) {
			return $user;
		}

		$error_message = hcaptcha_verify_post(
			self::NONCE,
			self::ACTION
		);

		if ( null === $error_message ) {
			return $user;
		}

		$code = array_search( $error_message, hcap_get_error_messages(), true ) ?: 'fail';

		return new WP_Error( $code, $error_message, 400 );
	}
}
