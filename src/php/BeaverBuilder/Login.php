<?php
/**
 * Login class file.
 *
 * @package hcaptcha-wp
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */

namespace HCaptcha\BeaverBuilder;

use WP_Error;

/**
 * Class Login.
 */
class Login extends Base {
	/**
	 * Nonce action.
	 */
	const ACTION = 'hcaptcha_login';

	/**
	 * Nonce name.
	 */
	const NONCE = 'hcaptcha_login_nonce';

	/**
	 * Add hooks.
	 *
	 * @return void
	 */
	protected function init_hooks() {
		parent::init_hooks();

		add_action( 'wp_authenticate_user', [ $this, 'verify' ], 20, 2 );
	}

	/**
	 * Filters the Beaver Builder Login Form submit button html and adds hcaptcha.
	 *
	 * @param string|mixed   $out    Button html.
	 * @param FLButtonModule $module Button module.
	 *
	 * @return string|mixed
	 */
	public function add_hcaptcha( $out, FLButtonModule $module ) {
		if ( ! $this->is_login_limit_exceeded() ) {
			return $out;
		}

		// Process login form only.
		if ( false === strpos( (string) $out, '<div class="fl-login-form' ) ) {
			return $out;
		}

		// Do not show hCaptcha on logout form.
		if ( preg_match( '/<div class="fl-login-form.+?logout.*?>/', (string) $out ) ) {
			return $out;
		}

		return $this->add_hcap_form( (string) $out, $module );
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
		$action = isset( $_POST['action'] ) ? sanitize_text_field( wp_unslash( $_POST['action'] ) ) : '';

		if ( 'fl_builder_login_form_submit' !== $action ) {
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
}
