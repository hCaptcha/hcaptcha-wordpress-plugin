<?php
/**
 * Login class file.
 *
 * @package hcaptcha-wp
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */

namespace HCaptcha\BeaverBuilder;

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
	 * Filters the Beaver Builder Login Form submit button html and adds hcaptcha.
	 *
	 * @param string         $out    Button html.
	 * @param FLButtonModule $module Button module.
	 *
	 * @return string
	 */
	public function add_hcaptcha( $out, $module ) {
		if ( ! $this->is_login_limit_exceeded() ) {
			return $out;
		}

		// Process login form only.
		if ( false === strpos( $out, '<div class="fl-login-form' ) ) {
			return $out;
		}

		// Do not show hCaptcha on logout form.
		if ( preg_match( '/<div class="fl-login-form.+?logout.*?>/', $out ) ) {
			return $out;
		}

		return $this->add_hcap_form( $out, $module );
	}
}
