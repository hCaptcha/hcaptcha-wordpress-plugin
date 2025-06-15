<?php
/**
 * Login class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\MemberPress;

use HCaptcha\Abstracts\LoginBase;
use HCaptcha\Helpers\API;
use HCaptcha\Helpers\HCaptcha;
use WP_Error;
use WP_User;

/**
 * Class Login
 */
class Login extends LoginBase {
	/**
	 * Nonce action.
	 */
	protected const ACTION = 'hcaptcha_memberpress_login';

	/**
	 * Nonce name.
	 */
	protected const NONCE = 'hcaptcha_memberpress_login_nonce';

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	protected function init_hooks(): void {
		parent::init_hooks();

		add_action( 'mepr-login-form-before-submit', [ $this, 'add_captcha' ] );
		add_filter( 'wp_authenticate_user', [ $this, 'verify' ], 10, 2 );
	}

	/**
	 * Verify a login form.
	 *
	 * @since        1.0
	 *
	 * @param WP_User|WP_Error $user     WP_User or WP_Error object if a previous callback failed authentication.
	 * @param string           $password Password to check against the user.
	 *
	 * @return WP_User|WP_Error
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function verify( $user, string $password ) {
		if ( ! HCaptcha::did_filter( 'mepr-validate-login' ) ) {
			return $user;
		}

		if ( ! $this->is_login_limit_exceeded() ) {
			return $user;
		}

		$error_message = API::verify_post_html( self::NONCE, self::ACTION );

		if ( null === $error_message ) {
			return $user;
		}

		return new WP_Error( 'invalid_hcaptcha', $error_message, 400 );
	}
}
