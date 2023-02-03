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
	 * Init hooks.
	 */
	protected function init_hooks() {
		parent::init_hooks();

		add_action( 'login_form', [ $this, 'add_captcha' ] );
		add_action( 'wp_authenticate_user', [ $this, 'verify' ], 10, 2 );
		add_filter( 'woocommerce_login_credentials', [ $this, 'remove_filter_wp_authenticate_user' ] );
		add_action( 'um_submit_form_errors_hook_login', [ $this, 'remove_filter_wp_authenticate_user' ] );
		add_filter( 'wpforms_user_registration_process_login_process_credentials', [ $this, 'remove_filter_wp_authenticate_user' ] );

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
		if ( $this->is_login_limit_exceeded() ) {
			hcap_form_display( 'hcaptcha_login', 'hcaptcha_login_nonce' );
		}
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
		if ( ! $this->is_login_limit_exceeded() ) {
			return $user;
		}

		$error_message = hcaptcha_get_verify_message_html(
			'hcaptcha_login_nonce',
			'hcaptcha_login'
		);

		if ( null === $error_message ) {
			return $user;
		}

		return new WP_Error( 'invalid_hcaptcha', $error_message, 400 );
	}

	/**
	 * Remove standard WP login captcha if we do logging in via WC.
	 *
	 * @param array $credentials Credentials.
	 *
	 * @return array
	 */
	public function remove_filter_wp_authenticate_user( $credentials ) {
		remove_filter( 'wp_authenticate_user', [ $this, 'verify' ] );

		return $credentials;
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
