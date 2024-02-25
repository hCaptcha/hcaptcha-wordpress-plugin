<?php
/**
 * TrackingInfo class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Admin;

use HCaptcha\Settings\SystemInfo;

/**
 * Class TrackingInfo.
 */
class TrackingInfo {

	/**
	 * Event API URL.
	 */
	const EventAPI = 'https://a.hcaptcha.com/api/event';

	/**
	 * Class constructor.
	 */
	public function __construct() {
		$this->init();
	}

	/**
	 * Init class.
	 *
	 * @return void
	 */
	private function init() {
		$this->init_hooks();
	}

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	private function init_hooks() {
		add_action( 'send_tracking_info', [ $this, 'send_tracking_info' ] );
	}

	/**
	 * Send tracking info.
	 *
	 * @return void
	 */
	public function send_tracking_info() {
		$tracking_info = $this->get_tracking_info();

		$headers = [
			'Content-Type' => 'application/json; charset=utf-8',
			'X-Debug-Request' => '',
		];
		$params  = [
			'd'     => wp_parse_url( home_url(), PHP_URL_HOST ), // Domain.
			'n'     => 'wp-plugin-tracking-info', // Name.
			'u'     => home_url(), // URL.
			'props' => $tracking_info, // Info.
		];

		$result = wp_remote_post(
			self::EventAPI,
			[
				'headers' => $headers,
				'body'    => json_encode( $params ),
			]
		);

		if ( ! ( defined( 'WP_DEBUG' ) && constant( 'WP_DEBUG' ) ) ) {
			// @codeCoverageIgnoreStart
			return;
			// @codeCoverageIgnoreEnd
		}

		$message = 'Error sending tracking info: ';

		if ( is_wp_error( $result ) ) {
			error_log( $message . $result->get_error_message() );
		}

		$code = $result['response']['code'] ?? 0;

		if ( $code !== 202 ) {
			error_log( $message . $code );
		}
	}

	/**
	 * Get tracking info.
	 *
	 * @return array
	 */
	public function get_tracking_info(): array {
		$tabs            = hcaptcha()->settings()->get_tabs();
		$system_info_obj = null;

		foreach ( $tabs as $tab ) {
			if ( is_a( $tab, SystemInfo::class ) ) {
				$system_info_obj = $tab;

				break;
			}
		}

		if ( ! $system_info_obj ) {
			return [];
		}

		$system_info   = explode( "\n", $system_info_obj->get_system_info() );
		$tracking_info = [];
		$current_root  = & $tracking_info;
		$header1       = '';
		$array_name    = '';

		foreach ( $system_info as $item ) {
			if ( ! $item || 0 === strpos( $item, '### ' ) ) {
				continue;
			}

			if ( 0 === strpos( $item, '-- ' ) ) {
				$header1 = preg_replace( '/-- (.+) --/', '$1', $item );

				$tracking_info[ $header1 ] = [];

				$current_root = & $tracking_info[ $header1 ];

				continue;
			}

			if ( 0 === strpos( $item, '--- ' ) ) {
				$header2 = preg_replace( '/--- (.+) ---/', '$1', $item );

				$tracking_info[ $header1 ][ $header2 ] = [];

				$current_root = & $tracking_info[ $header1 ][ $header2 ];

				continue;
			}

			list( $key, $value ) = explode( ': ', $item, 2 );

			$in_array = 0 === strpos( $item, '  ' );

			if ( $in_array ) {
				$current_root[ $array_name ] = is_array( $current_root[ $array_name ] ) ? $current_root[ $array_name ] : [];

				$current_root[ $array_name ][ trim( $key ) ] = trim( $value );

				continue;
			}

			$array_name           = $key;
			$current_root[ $key ] = trim( $value );
		}

		return $tracking_info;
	}
}
