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
	 * Nonce action.
	 */
	const ACTION = 'hcaptcha_login';

	/**
	 * Nonce action.
	 */
	const NONCE = 'hcaptcha_login_nonce';

	/**
	 * Init hooks.
	 */
	protected function init_hooks() {
		parent::init_hooks();

		add_action( 'login_form', [ $this, 'add_captcha' ] );
		add_action( 'wp_authenticate_user', [ $this, 'verify' ], 10, 2 );

		if ( ! class_exists( Controller_WordfenceLS::class ) ) {
			return;
		}

		add_action( 'login_enqueue_scripts', [ $this, 'remove_wordfence_scripts' ], 0 );
		add_filter( 'wordfence_ls_require_captcha', [ $this, 'wordfence_ls_require_captcha' ] );
	}

	/**
	 * Add captcha.
	 */
	public function add_captcha() {
		if ( ! $this->is_login_limit_exceeded() ) {
			return;
		}

		$args = [
			'action' => self::ACTION,
			'name'   => self::NONCE,
			'id'     => [
				'source'  => HCaptcha::get_class_source( __CLASS__ ),
				'form_id' => 'login',
			],
		];

		HCaptcha::form_display( $args );
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
	public function verify( $user, $password ) {
		if ( false === strpos( wp_get_raw_referer(), '/wp-login.php' ) ) {
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
	 * Remove Wordfence login scripts.
	 *
	 * @return void
	 */
	public function remove_wordfence_scripts() {
		$controller_wordfence_ls = Controller_WordfenceLS::shared();

		remove_action( 'login_enqueue_scripts', [ $controller_wordfence_ls, '_login_enqueue_scripts' ] );
	}

	/**
	 * Do not require Wordfence captcha.
	 *
	 * @return false
	 */
	public function wordfence_ls_require_captcha() {

		return false;
	}
}
