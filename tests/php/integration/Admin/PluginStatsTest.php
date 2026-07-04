<?php
/**
 * PluginStatsTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\Admin;

use HCaptcha\Admin\PluginStats;
use HCaptcha\Settings\General;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use ReflectionException;
use WP_Error;

/**
 * Test PluginStats class.
 *
 * @group admin
 * @group plugin-stats
 */
class PluginStatsTest extends HCaptchaWPTestCase {

	/**
	 * Tear down test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		remove_all_filters( 'hcap_allow_send_plugin_stats' );
		remove_all_filters( 'pre_http_request' );

		parent::tearDown();
	}

	/**
	 * Test constructor and hooks.
	 *
	 * @return void
	 */
	public function test_constructor_and_hooks(): void {
		$subject = new PluginStats();

		self::assertSame( 10, has_action( 'hcap_send_plugin_stats', [ $subject, 'send_plugin_stats' ] ) );
	}

	/**
	 * Test send_plugin_stats() when sending is not allowed.
	 *
	 * @return void
	 */
	public function test_send_plugin_stats_returns_when_not_allowed(): void {
		$called = false;

		update_option( 'hcaptcha_settings', [ 'statistics' => [] ] );
		hcaptcha()->init_hooks();

		add_filter(
			'pre_http_request',
			static function () use ( &$called ) {
				$called = true;

				return null;
			}
		);

		( new PluginStats() )->send_plugin_stats();

		self::assertFalse( $called );
	}

	/**
	 * Test send_plugin_stats() sends the expected request payload.
	 *
	 * @return void
	 * @noinspection JsonEncodingApiUsageInspection
	 */
	public function test_send_plugin_stats_sends_successful_request(): void {
		$request = [];

		add_filter(
			'pre_http_request',
			static function ( $preempt, array $parsed_args, string $url ) use ( &$request ) {
				$request = [ $url, $parsed_args ];

				return [
					'response' => [ 'code' => 202 ],
					'body'     => '',
				];
			},
			10,
			3
		);

		( new PluginStats() )->send_plugin_stats( true );

		self::assertSame( 'https://a.hcaptcha.com/api/event', $request[0] );
		self::assertSame( 'application/json', $request[1]['headers']['Content-Type'] );
		self::assertSame( '127.0.0.1', $request[1]['headers']['X-Forwarded-For'] );

		$body = json_decode( $request[1]['body'], true );

		self::assertSame( 'wp-plugin.hcaptcha.com', $body['d'] );
		self::assertSame( 'plugin-stats', $body['n'] );
		self::assertIsArray( $body['props'] );
		self::assertArrayHasKey( 'hCaptcha', $body['props'] );
	}

	/**
	 * Test send_plugin_stats() handles a WP_Error response in debug mode.
	 *
	 * @return void
	 */
	public function test_send_plugin_stats_handles_wp_error_response(): void {
		add_filter(
			'pre_http_request',
			static function () {
				return new WP_Error( 'hcaptcha_error', 'Stats endpoint unavailable.' );
			}
		);

		( new PluginStats() )->send_plugin_stats( true );

		self::assertTrue( true );
	}

	/**
	 * Test send_plugin_stats() handles an unexpected HTTP response code.
	 *
	 * @return void
	 */
	public function test_send_plugin_stats_handles_unexpected_response_code(): void {
		add_filter(
			'pre_http_request',
			static function () {
				return [
					'response' => [ 'code' => 500 ],
					'body'     => '',
				];
			}
		);

		( new PluginStats() )->send_plugin_stats( true );

		self::assertTrue( true );
	}

	/**
	 * Test get_plugin_stats().
	 *
	 * @return void
	 */
	public function test_get_plugin_stats(): void {
		update_option(
			'hcaptcha_settings',
			[
				'mode'       => General::MODE_LIVE,
				'license'    => 'pro',
				'site_key'   => 'site-key',
				'secret_key' => 'secret-key',
				'api_host'   => 'https://enterprise-api.example.test',
			]
		);
		hcaptcha()->init_hooks();

		$stats = ( new PluginStats() )->get_plugin_stats();

		self::assertSame( HCAPTCHA_VERSION, $stats['hCaptcha'] );
		self::assertSame( 'Enterprise', $stats['License'] );
		self::assertSame( 1, $stats['Site key'] );
		self::assertSame( 1, $stats['Secret key'] );
		self::assertSame( (int) is_multisite(), $stats['Multisite'] );
		self::assertArrayHasKey( 'Active', $stats );
		self::assertLessThanOrEqual( 30, count( $stats ) );
	}

	/**
	 * Test get_plugin_stats() when the SystemInfo tab is unavailable.
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_get_plugin_stats_without_system_info_tab(): void {
		$settings = hcaptcha()->settings();
		$tabs     = $this->get_protected_property( $settings, 'tabs' );

		$this->set_protected_property( $settings, 'tabs', [] );

		try {
			self::assertSame( [], ( new PluginStats() )->get_plugin_stats() );
		} finally {
			$this->set_protected_property( $settings, 'tabs', $tabs );
		}
	}

	/**
	 * Test get_active() removes WP Core and limits the returned string length.
	 *
	 * @return void
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_get_active_limits_value_length(): void {
		$subject = new PluginStats();
		$method  = $this->set_method_accessibility( $subject, 'get_active' );
		$fields  = [
			'wp-core' => [ 'label' => 'WP Core' ],
		];

		for ( $i = 0; $i < 80; $i++ ) {
			$fields[ 'field-' . $i ] = [
				'label' => str_repeat( 'A', 40 ) . $i,
			];
		}

		$active = $method->invoke( $subject, $fields );

		self::assertStringNotContainsString( 'WP Core', $active );
		self::assertLessThanOrEqual( 2000, strlen( $active ) );
	}
}
