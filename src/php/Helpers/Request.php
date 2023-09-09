<?php
/**
 * Request class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Helpers;

use WP_Rewrite;

/**
 * Class Request.
 */
class Request {

	/**
	 * Check if it is a frontend request.
	 *
	 * @return bool
	 */
	public static function is_frontend(): bool {
		return ! ( self::is_cli() || is_admin() || wp_doing_ajax() || self::is_rest() );
	}

	/**
	 * Check of it is a CLI request
	 *
	 * @return bool
	 */
	public static function is_cli(): bool {
		return defined( 'WP_CLI' ) && constant( 'WP_CLI' );
	}

	/**
	 * Checks if the current request is a WP REST API request.
	 *
	 * Case #1: After WP_REST_Request initialisation
	 * Case #2: Support "plain" permalink settings
	 * Case #3: It can happen that WP_Rewrite is not yet initialized,
	 *          so do this (wp-settings.php)
	 * Case #4: URL Path begins with wp-json/ (your REST prefix)
	 *          Also supports WP installations in sub folders
	 *
	 * @return bool
	 * @author matzeeable
	 */
	public static function is_rest(): bool {
		if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return false;
		}

		// Case #1.
		if ( defined( 'REST_REQUEST' ) && constant( 'REST_REQUEST' ) ) {
			return true;
		}

		// Case #2.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$rest_route = isset( $_GET['rest_route'] ) ?
			filter_input( INPUT_GET, 'rest_route', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) :
			'';

		if ( 0 === strpos( trim( $rest_route, '\\/' ), rest_get_url_prefix() ) ) {
			return true;
		}

		// Case #3.
		global $wp_rewrite;
		if ( null === $wp_rewrite ) {
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$wp_rewrite = new WP_Rewrite();
		}

		// Case #4.
		$current_url = wp_parse_url( add_query_arg( [] ), PHP_URL_PATH );
		$rest_url    = wp_parse_url( trailingslashit( rest_url() ), PHP_URL_PATH );

		return 0 === strpos( $current_url, $rest_url );
	}
}
