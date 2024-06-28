<?php
/**
 * PluginStats class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Admin;

use HCaptcha\Main;
use HCaptcha\Settings\SystemInfo;

/**
 * Class PluginStats.
 */
class PluginStats {

	/**
	 * Event API URL.
	 */
	private const EVENT_API = 'https://a.hcaptcha.com/api/event';

	/**
	 * Event name.
	 */
	private const NAME = 'plugin-stats';

	/**
	 * Report domain.
	 */
	private const DOMAIN = 'wp-plugin.hcaptcha.com';

	/**
	 * Max props to send.
	 */
	private const MAX_PROPS = 30;

	/**
	 * Max prop value length.
	 * (Max prop key length is 300).
	 */
	private const MAX_PROP_VALUE_LENGTH = 2000;

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
	private function init(): void {
		$this->init_hooks();
	}

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		add_action( 'hcap_send_plugin_stats', [ $this, 'send_plugin_stats' ] );
	}

	/**
	 * Send plugin statistics.
	 *
	 * @return void
	 * @noinspection ForgottenDebugOutputInspection
	 */
	public function send_plugin_stats(): void {
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
			'props' => $stats, // Stats.
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

		$message = 'Error sending plugin statistics: ';

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
	 * Get plugin statistics.
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

		$settings   = hcaptcha()->settings();
		$license    = (int) $settings->is_pro() ? 'Pro' : 'Publisher';
		$api_host   = $settings->get( 'api_host' );
		$backend    = $settings->get( 'backend' );
		$enterprise = (
			( ! empty( $api_host ) && Main::API_HOST !== $api_host ) ||
			! empty( $settings->get( 'asset_host' ) ) ||
			! empty( $settings->get( 'endpoint' ) ) ||
			! empty( $settings->get( 'host' ) ) ||
			! empty( $settings->get( 'image_host' ) ) ||
			! empty( $settings->get( 'report_api' ) ) ||
			! empty( $settings->get( 'sentry' ) ) ||
			( ! empty( $backend ) && Main::VERIFY_HOST !== $backend )
		);
		$license    = 'Pro' === $license && $enterprise ? 'Enterprise' : $license;

		$stats['hCaptcha']   = HCAPTCHA_VERSION;
		$stats['License']    = $license;
		$stats['Site key']   = $this->is_not_empty( $settings->get_site_key() );
		$stats['Secret key'] = $this->is_not_empty( $settings->get_secret_key() );
		$stats['Multisite']  = (int) is_multisite();

		[ $fields, $integration_settings ] = $system_info_obj->get_integrations();

		$fields = array_filter(
			$fields,
			static function ( $field ) {
				return ! $field['disabled'];
			}
		);

		$stats['Active'] = $this->get_active( $fields );

		foreach ( $fields as $key => $field ) {
			$stats[ $field['label'] ] = implode( ',', (array) $integration_settings[ $key ] );
		}

		return array_slice( $stats, 0, self::MAX_PROPS );
	}

	/**
	 * Return whether data is not empty.
	 *
	 * @param mixed $data Data.
	 *
	 * @return int
	 */
	private function is_not_empty( $data ): int {
		return (int) ( ! empty( $data ) );
	}

	/**
	 * Get active entities.
	 *
	 * @param array $fields Settings fields.
	 *
	 * @return string
	 */
	private function get_active( array $fields ): string {
		$active = array_values( wp_list_pluck( $fields, 'label' ) );
		$active = array_diff( $active, [ 'WP Core' ] );

		$active_list   = implode( ',', $active );
		$active_length = strlen( $active_list );

		while ( $active_length > self::MAX_PROP_VALUE_LENGTH ) {
			array_pop( $active );
			$active_list   = implode( ',', $active );
			$active_length = strlen( $active_list );
		}

		return $active_list;
	}
}
