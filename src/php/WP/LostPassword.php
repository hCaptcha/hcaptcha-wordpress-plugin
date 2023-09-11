<?php
/**
 * LostPassword class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\WP;

use HCaptcha\Abstracts\LostPasswordBase;

/**
 * Class LostPassword
 */
class LostPassword extends LostPasswordBase {

	/**
	 * Nonce action.
	 */
	const ACTION = 'hcaptcha_wp_lost_password';

	/**
	 * Nonce name.
	 */
	const NONCE = 'hcaptcha_wp_lost_password_nonce';

	/**
	 * Add hCaptcha action.
	 */
	const ADD_CAPTCHA_ACTION = 'lostpassword_form';

	/**
	 * $_POST key to check.
	 */
	const POST_KEY = 'wp-submit';

	/**
	 * $_POST value to check.
	 */
	const POST_VALUE = null;

	/**
	 * WP login URL.
	 */
	const WP_LOGIN_URL = '/wp-login.php';

	/**
	 * WP login action.
	 */
	const WP_LOGIN_ACTION = 'lostpassword';

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

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';

		if ( self::WP_LOGIN_ACTION !== $action ) {
			return;
		}

		parent::add_captcha();
	}
}
