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

use HCaptcha\Helpers\Request;

use Perfmatters\General;
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
	protected function get_login_url(): string {
		if ( function_exists( 'perfmatters_login_url' ) ) {
			// Integration with the Perfmatters plugin.
			return (string) wp_parse_url( perfmatters_login_url(), PHP_URL_PATH );
		}

		if (
			is_callable( [ '\Perfmatters\General', 'login_url' ] ) &&
			is_callable( [ '\Perfmatters\General', 'login_slug' ] ) &&
			General::login_slug()
		) {
			// Integration with the Perfmatters plugin since 2.5.8.
			return (string) wp_parse_url( General::login_url(), PHP_URL_PATH );
		}

		if ( class_exists( Plugin::class ) ) {
			// Integration with the WPS Hide Login plugin.
			return (string) wp_parse_url( Plugin::get_instance()->new_login_url(), PHP_URL_PATH );
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
		$login_url   = $this->get_login_url();

		return $request_uri && $login_url && ( false !== strpos( $request_uri, $login_url ) );
	}

	/**
	 * Get action.
	 *
	 * @return string
	 */
	private function get_action(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$input_type = isset( $_POST['action'] ) ? INPUT_POST : INPUT_GET;
		$action     = Request::filter_input( $input_type, 'action' );

		return is_string( $action ) ? $action : '';
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
