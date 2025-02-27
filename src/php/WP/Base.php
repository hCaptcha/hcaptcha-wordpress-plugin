<?php
/**
 * Base trait file.
 *
 * @package hcaptcha-wp
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */
/** @noinspection PhpUndefinedNamespaceInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\WP;

use WPS\WPS_Hide_Login\Plugin;

/**
 * Base trait.
 */
trait Base {
	/**
	 * Get login URL.
	 *
	 * @return string
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	private function get_login_url(): string {
		if ( class_exists( Plugin::class ) ) {
			// Integration with WPS Hide Login plugin.
			return wp_parse_url( Plugin::get_instance()->new_login_url(), PHP_URL_PATH );
		}

		if ( function_exists( 'perfmatters_login_url' ) ) {
			// Integration with Perfmatters plugin.
			return wp_parse_url( perfmatters_login_url(), PHP_URL_PATH );
		}

		return '/wp-login.php';
	}

	/**
	 * Check if the current request is the login URL.
	 *
	 * @return bool
	 */
	private function is_login_url(): bool {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ?
			filter_var( wp_unslash( $_SERVER['REQUEST_URI'] ), FILTER_SANITIZE_FULL_SPECIAL_CHARS ) :
			'';

		$request_uri = wp_parse_url( $request_uri, PHP_URL_PATH );

		return false !== strpos( $request_uri, $this->get_login_url() );
	}

	/**
	 * Get action.
	 *
	 * @return string
	 */
	private function get_action(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';
	}

	/**
	 * Whether the current request is the login action.
	 *
	 * @return bool
	 */
	private function is_login_action(): bool {
		return self::WP_LOGIN_ACTION === $this->get_action();
	}
}
