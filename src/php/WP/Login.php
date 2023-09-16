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
use HCaptcha\Helpers\HCaptcha;
use WordfenceLS\Controller_WordfenceLS;
use WP_Error;
use WP_User;

/**
 * Class Login
 */
class Login extends LoginBase {

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
		if ( ! $this->is_wp_login_form() ) {
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
		if (
			! (
				// phpcs:ignore WordPress.Security.NonceVerification.Missing
				isset( $_POST['log'], $_POST['pwd'] ) &&
				$this->is_wp_login_form()
			)
		) {
			return $user;
		}

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

	/**
	 * Whether we process the native WP login form created in wp-login.php.
	 *
	 * @return bool
	 */
	private function is_wp_login_form(): bool {
		return (
			did_action( 'login_init' ) &&
			did_action( 'login_form_login' ) &&
			HCaptcha::did_filter( 'login_link_separator' )
		);
	}
}
