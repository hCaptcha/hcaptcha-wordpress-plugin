<?php
/**
 * Login class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Affiliates;

use HCaptcha\Abstracts\LoginBase;
use HCaptcha\Helpers\HCaptcha;
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

		add_action( 'wp_head', [ $this, 'print_inline_styles' ], 20 );
		add_filter( 'login_form_top', [ $this, 'add_affiliates_marker' ], 10, 2 );
		add_filter( 'login_form_middle', [ $this, 'add_affiliates_captcha' ], 10, 2 );
		add_filter( 'wp_authenticate_user', [ $this, 'verify' ], 10, 2 );
	}

	/**
	 * Add marker to distinguish the form.
	 *
	 * @param string|mixed $content Content to display. Default empty.
	 * @param array        $args    Array of login form arguments.
	 *
	 * @return string
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_affiliates_marker( $content, array $args ): string {
		$content = (string) $content;

		if ( ! $this->is_affiliates_login_form() ) {
			return $content;
		}

		$marker = '<input id="affiliates-login-form" type="hidden" name="affiliates-login-form">';

		return $content . $marker;
	}

	/**
	 * Add captcha.
	 *
	 * @param string|mixed $content Content to display. Default empty.
	 * @param array        $args    Array of login form arguments.
	 *
	 * @return string
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_affiliates_captcha( $content, array $args ): string {
		$content = (string) $content;

		if ( ! $this->is_affiliates_login_form() ) {
			return $content;
		}

		ob_start();
		$this->add_captcha();

		return $content . ob_get_clean();
	}

	/**
	 * Verify a login form.
	 *
	 * @param WP_User|WP_Error $user     WP_User or WP_Error object
	 *                                   if a previous callback failed authentication.
	 * @param string           $password Password to check against the user.
	 *
	 * @return WP_User|WP_Error
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function verify( $user, string $password ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_POST['affiliates-login-form'] ) ) {
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

	/**
	 * Print inline styles.
	 *
	 * @return void
	 * @noinspection CssUnusedSymbol
	 */
	public function print_inline_styles() {
		$css = <<<CSS
	.affiliates-dashboard .h-captcha {
		margin-top: 2rem;
	}
CSS;

		HCaptcha::css_display( $css );
	}

	/**
	 * Whether we process the Affiliates login form.
	 *
	 * @return bool
	 */
	private function is_affiliates_login_form(): bool {
		return did_action( 'affiliates_dashboard_before_section' );
	}
}
