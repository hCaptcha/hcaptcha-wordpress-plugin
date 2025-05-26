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
		return ! (
			self::is_xml_rpc() || self::is_cli() || self::is_wc_ajax() ||
			is_admin() || wp_doing_ajax() || wp_doing_cron() ||
			self::is_rest()
		);
	}

	/**
	 * Check if it is the xml-rpc request.
	 *
	 * @return bool
	 */
	public static function is_xml_rpc(): bool {
		return defined( 'XMLRPC_REQUEST' ) && constant( 'XMLRPC_REQUEST' );
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
	 * Check if it is a WooCommerce AJAX request.
	 *
	 * @return bool
	 */
	public static function is_wc_ajax(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return isset( $_GET['wc-ajax'] );
	}

	/**
	 * Checks if the current request is a WP REST API request.
	 *
	 * Case #1: After WP_REST_Request initialization
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

	/**
	 * Check if it is a POST request.
	 *
	 * @return bool
	 */
	public static function is_post(): bool {
		$request_method = isset( $_SERVER['REQUEST_METHOD'] )
			? strtoupper( filter_var( wp_unslash( $_SERVER['REQUEST_METHOD'] ), FILTER_SANITIZE_FULL_SPECIAL_CHARS ) )
			: '';

		return 'POST' === $request_method;
	}

	/**
	 * Filter input in WP style.
	 * Nonce must be checked in the calling function.
	 *
	 * @param int    $type     Input type.
	 * @param string $var_name Variable name.
	 *
	 * @return string
	 */
	public static function filter_input( int $type, string $var_name ): string {
		switch ( $type ) {
			case INPUT_GET:
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				return isset( $_GET[ $var_name ] ) ? sanitize_text_field( wp_unslash( $_GET[ $var_name ] ) ) : '';
			case INPUT_POST:
				// phpcs:ignore WordPress.Security.NonceVerification.Missing
				return isset( $_POST[ $var_name ] ) ? sanitize_text_field( wp_unslash( $_POST[ $var_name ] ) ) : '';
			case INPUT_SERVER:
				return isset( $_SERVER[ $var_name ] ) ? sanitize_text_field( wp_unslash( $_SERVER[ $var_name ] ) ) : '';
			case INPUT_COOKIE:
				return isset( $_COOKIE[ $var_name ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ $var_name ] ) ) : '';
			default:
				return '';
		}
	}

	/**
	 * Check if an IP is in a given range.
	 *
	 * @param string $ip    IP address.
	 * @param string $range IP range.
	 *
	 * @return bool
	 */
	public static function is_ip_in_range( string $ip, string $range ): bool {
		$ip    = trim( $ip );
		$range = trim( $range );

		if ( strpos( $range, '/' ) !== false ) {
			// CIDR.
			[ $subnet, $bits ] = explode( '/', $range );

			$ip     = inet_pton( $ip );
			$subnet = inet_pton( $subnet );

			if ( strlen( $ip ) !== strlen( $subnet ) ) {
				return false; // Different IP type (IPv4 vs IPv6).
			}

			$bin_ip     = unpack( 'A*', $ip )[1];
			$bin_subnet = unpack( 'A*', $subnet )[1];
			$mask       = str_repeat( 'f', $bits >> 2 );

			switch ( $bits % 4 ) {
				case 1:
					$mask .= '8';
					break;
				case 2:
					$mask .= 'c';
					break;
				case 3:
					$mask .= 'e';
					break;
			}

			$mask = str_pad( $mask, strlen( $bin_ip ), '0' );

			return ( $bin_ip & $mask ) === ( $bin_subnet & $mask );
		}

		if ( strpos( $range, '-' ) !== false ) {
			// IP-IP range.
			[ $start, $end ] = explode( '-', $range, 2 );

			$start_dec = inet_pton( trim( $start ) );
			$end_dec   = inet_pton( trim( $end ) );
			$ip_dec    = inet_pton( $ip );

			return ( $ip_dec >= $start_dec && $ip_dec <= $end_dec );
		}

		// Single IP.
		return ( $ip === $range ) && filter_var( $range, FILTER_VALIDATE_IP );
	}
}
