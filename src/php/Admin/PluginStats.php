<?php
/**
 * PluginStats class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Admin;

use HCaptcha\Settings\SystemInfo;

/**
 * Class PluginStats.
 */
class PluginStats {

	/**
	 * Event API URL.
	 */
	const EVENT_API = 'https://a.hcaptcha.com/api/event';

	/**
	 * Event name.
	 */
	const NAME = 'tracking-info';

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
		add_action( 'hcap_send_plugin_stats', [ $this, 'send_plugin_stats' ] );
	}

	/**
	 * Send tracking info.
	 *
	 * @return void
	 * @noinspection ForgottenDebugOutputInspection
	 */
	public function send_plugin_stats() {
		$stats = $this->get_plugin_stats();

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
			'u'     => home_url( self::NAME ), // URL.
			'r'     => null, // Referer.
			'w'     => 1024, // Some window inner width.
			'props' => $stats, // Info.
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
	public function get_plugin_stats(): array {
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

		$integration_info = explode( "\n", $system_info_obj->integration_info() );
		$integrations     = [];
		$current_root     = &$integrations;
		$header1          = '';
		$array_name       = '';

		foreach ( $integration_info as $item ) {
			if ( ! $item || 0 === strpos( $item, '### ' ) ) {
				continue;
			}

			if ( 0 === strpos( $item, '-- ' ) ) {
				$header1 = preg_replace( '/-- (.+) --/', '$1', $item );

				$integrations[ $header1 ] = [];

				$current_root = & $integrations[ $header1 ];

				continue;
			}

			if ( 0 === strpos( $item, '--- ' ) ) {
				$header2 = preg_replace( '/--- (.+) ---/', '$1', $item );

				$integrations[ $header1 ][ $header2 ] = [];

				$current_root = & $integrations[ $header1 ][ $header2 ];

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

		$integrations = $integrations[''];
		$stats        = [];

		$stats        = array_merge(
			$stats,
			array_fill_keys( array_keys( $integrations['Active plugins and themes'] ), 'Active' )
		);
		$stats        = array_merge(
			$stats,
			array_fill_keys( array_keys( $integrations['Inactive plugins and themes'] ), 'Inactive' )
		);
		$integrations = array_merge(
			$integrations['Active plugins and themes'],
			$integrations['Inactive plugins and themes']
		);

		$flat_integrations = [];

		foreach ( $integrations as $integration => $forms ) {
			foreach ( $forms as $key => $value ) {
				$flat_integrations[ $integration . ': ' . $key ] = $value;
			}
		}

		$stats               = array_merge( $stats, $flat_integrations );
		$stats['hCaptcha']   = HCAPTCHA_VERSION;
		$stats['Pro']        = hcaptcha()->is_pro();
		$stats['Site key']   = $this->is_empty( $settings->get_site_key() );
		$stats['Secret key'] = $this->is_empty( $settings->get_secret_key() );
		$stats['Multisite']  = is_multisite();
		$stats['Enterprise'] = (
			! empty( $settings->get( 'api_host' ) ) ||
			! empty( $settings->get( 'asset_host' ) ) ||
			! empty( $settings->get( 'endpoint' ) ) ||
			! empty( $settings->get( 'host' ) ) ||
			! empty( $settings->get( 'image_host' ) ) ||
			! empty( $settings->get( 'report_api' ) ) ||
			! empty( $settings->get( 'sentry' ) ) ||
			! empty( $settings->get( 'backend' ) )
		);

		return $stats;
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
