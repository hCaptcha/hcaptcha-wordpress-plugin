<?php
/**
 * Login class file.
 *
 * @package hcaptcha-wp
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedNamespaceInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\WP;

use HCaptcha\Abstracts\LoginBase;
use WordfenceLS\Controller_WordfenceLS;
use WP_Error;
use WP_User;

/**
 * Class Login
 */
class Login extends LoginBase {

	/**
	 * WP login URL.
	 */
	const WP_LOGIN_URL = '/wp-login.php';

	/**
	 * Init hooks.
	 */
	protected function init_hooks() {
		parent::init_hooks();

		add_action( 'login_form', [ $this, 'add_captcha' ] );
		add_filter( 'wp_authenticate_user', [ $this, 'verify' ], 10, 2 );
	}

	/**
	 * Add captcha.
	 *
	 * @return void
	 */
	public function add_captcha() {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ?
			filter_var( wp_unslash( $_SERVER['REQUEST_URI'] ), FILTER_SANITIZE_FULL_SPECIAL_CHARS ) :
			'';

		$request_uri = wp_parse_url( $request_uri, PHP_URL_PATH );

		if ( false === strpos( $request_uri, self::WP_LOGIN_URL ) ) {
			return;
		}

		parent::add_captcha();
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
		if ( ! $this->is_login_limit_exceeded() ) {
			return $user;
		}

		$error_message = hcaptcha_get_verify_message_html(
			self::NONCE,
			self::ACTION
		);

		if ( null === $error_message ) {
			return $user;
		}

		return new WP_Error( 'invalid_hcaptcha', $error_message, 400 );
	}
}
