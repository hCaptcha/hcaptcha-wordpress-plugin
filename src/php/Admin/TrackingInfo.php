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
	const EVENT_API = 'https://a.hcaptcha.com/api/event';

	/**
	 * Event name.
	 */
	const NAME = 'pageview';

	/**
	 * Report domain.
	 */
	const DOMAIN = 'wp-plugin.hcaptcha.com';

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
		add_action( 'hcap_send_tracking_info', [ $this, 'send_tracking_info' ] );
	}

	/**
	 * Send tracking info.
	 *
	 * @return void
	 * @noinspection ForgottenDebugOutputInspection
	 */
	public function send_tracking_info() {
		$tracking_info = $this->get_tracking_info();

		$url     = self::EVENT_API;
		$headers = [
			'Content-Type'    => 'application/json',
			'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
			'X-Forwarded-For' => hcap_get_user_ip() ?: '127.0.0.1',
		];
		$domain  = self::DOMAIN;
		$params  = [
			'd'     => $domain, // Domain.
			'n'     => self::NAME, // Name.
			'u'     => home_url( 'tracking-info' ), // URL.
			'r'     => null, // Referer.
			'w'     => 1024, // Some window inner width.
			'props' => $tracking_info, // Info.
		];

		$result = wp_remote_post(
			$url,
			[
				'headers' => $headers,
				'body'    => wp_json_encode( $params ),
			]
		);

		if ( ! ( defined( 'WP_DEBUG' ) && constant( 'WP_DEBUG' ) ) ) {
			// @codeCoverageIgnoreStart
			return;
			// @codeCoverageIgnoreEnd
		}

		$message = 'Error sending tracking info: ';

		if ( is_wp_error( $result ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( $message . $result->get_error_message() );

			return;
		}

		$code = $result['response']['code'] ?? 0;

		if ( 202 !== $code ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
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

		$system_info   = explode( "\n", $system_info_obj->integration_info() );
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

		$settings = hcaptcha()->settings();

		$tracking_info = $tracking_info[''];

		$tracking_info['hCaptcha']   = HCAPTCHA_VERSION;
		$tracking_info['Pro']        = hcaptcha()->is_pro();
		$tracking_info['Site key']   = $this->is_empty( $settings->get_site_key() );
		$tracking_info['Secret key'] = $this->is_empty( $settings->get_secret_key() );
		$tracking_info['Multisite']  = is_multisite();
		$tracking_info['Enterprise'] = (
			! empty( $settings->get( 'api_host' ) ) ||
			! empty( $settings->get( 'asset_host' ) ) ||
			! empty( $settings->get( 'endpoint' ) ) ||
			! empty( $settings->get( 'host' ) ) ||
			! empty( $settings->get( 'image_host' ) ) ||
			! empty( $settings->get( 'report_api' ) ) ||
			! empty( $settings->get( 'sentry' ) ) ||
			! empty( $settings->get( 'backend' ) )
		);

		return $tracking_info;
	}

	/**
	 * Return whether data is empty.
	 *
	 * @param mixed $data Data.
	 *
	 * @return string
	 */
	private function is_empty( $data ): string {
		return empty( $data ) ? 'Not set' : 'Set';
	}
}
