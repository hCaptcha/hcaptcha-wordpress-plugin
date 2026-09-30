<?php
/**
 * PluginStatsTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\Admin;

use HCaptcha\Admin\PluginStats;
use HCaptcha\Admin\Privacy;
use HCaptcha\Helpers\Playground;
use HCaptcha\Settings\General;
use HCaptcha\Settings\SystemInfo;
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
	 * Original request environment.
	 *
	 * @var array
	 */
	private array $server;

	/**
	 * Set up test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		$this->server = $_SERVER;
	}

	/**
	 * Tear down test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		$_SERVER = $this->server;

		remove_all_filters( 'hcap_allow_send_plugin_stats' );
		remove_all_filters( 'pre_http_request' );
		remove_all_filters( 'pre_option_home' );

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
	 * @param array  $server      Request environment.
	 * @param string $expected_ip Expected forwarded IP or fallback.
	 * @param bool   $force_send  Whether to send before consent is persisted.
	 * @param bool   $anonymous   Whether local event hashing is enabled.
	 *
	 * @dataProvider dp_test_send_plugin_stats_payload
	 *
	 * @return void
	 * @noinspection JsonEncodingApiUsageInspection
	 */
	public function test_send_plugin_stats_sends_successful_request( array $server, string $expected_ip, bool $force_send, bool $anonymous ): void {
		$request    = [];
		$site_key   = 'site-key-must-not-be-sent';
		$secret_key = 'secret-key-must-not-be-sent';

		$_SERVER = $server;
		add_filter(
			'pre_option_home',
			static function (): string {
				return 'https://telemetry.example.test/installation';
			}
		);

		// Prevent the settings transition from sending before the interceptor is installed.
		add_filter( 'hcap_allow_send_plugin_stats', '__return_false' );

		update_option(
			'hcaptcha_settings',
			[
				'statistics'              => $force_send ? [] : [ 'on' ],
				'anonymous'               => $anonymous ? [ 'on' ] : [],
				'collect_ip'              => [],
				'collect_ua'              => [],
				'trusted_address_headers' => [],
				'mode'                    => General::MODE_LIVE,
				'site_key'                => $site_key,
				'secret_key'              => $secret_key,
			]
		);
		hcaptcha()->init_hooks();
		remove_all_filters( 'hcap_allow_send_plugin_stats' );

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

		( new PluginStats() )->send_plugin_stats( $force_send );

		self::assertSame( 'https://a.hcaptcha.com/api/event', $request[0] );
		self::assertSame(
			[
				'Content-Type'    => 'application/json',
				'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
				'X-Forwarded-For' => $expected_ip,
			],
			$request[1]['headers']
		);

		$body = json_decode( $request[1]['body'], true );

		self::assertSame( [ 'd', 'n', 'u', 'r', 'w', 'props' ], array_keys( $body ) );
		self::assertSame( 'wp-plugin.hcaptcha.com', $body['d'] );
		self::assertSame( 'plugin-stats', $body['n'] );
		self::assertSame( 'https://telemetry.example.test/installation/plugin-stats', $body['u'] );
		self::assertNull( $body['r'] );
		self::assertSame( 1024, $body['w'] );
		self::assertIsArray( $body['props'] );
		self::assertArrayHasKey( 'hCaptcha', $body['props'] );
		self::assertSame( 1, $body['props']['Site key'] );
		self::assertSame( 1, $body['props']['Secret key'] );
		self::assertSame( $this->get_allowed_stats_keys(), array_keys( $body['props'] ) );
		self::assertStringNotContainsString( '127.0.0.1', $request[1]['body'] );
		self::assertStringNotContainsString( $site_key, wp_json_encode( $request ) );
		self::assertStringNotContainsString( $secret_key, wp_json_encode( $request ) );
		self::assertSame( [], $request[1]['cookies'] );

		foreach ( $server as $value ) {
			self::assertStringNotContainsString( $value, $request[1]['body'] );
		}
	}

	/**
	 * Data provider for test_send_plugin_stats_sends_successful_request().
	 *
	 * @return array
	 */
	public function dp_test_send_plugin_stats_payload(): array {
		$contexts = [
			'frontend' => [
				[
					'REMOTE_ADDR'          => '203.0.113.42',
					'HTTP_X_FORWARDED_FOR' => '198.51.100.17',
					'HTTP_USER_AGENT'      => 'visitor-browser-must-not-be-sent',
				],
				'203.0.113.42',
			],
			'admin'    => [
				[
					'REMOTE_ADDR' => '2606:4700:4700::1111',
					'REQUEST_URI' => '/wp-admin/options.php',
				],
				'2606:4700:4700::1111',
			],
			'cron'     => [
				[
					'REMOTE_ADDR' => '127.0.0.1',
					'REQUEST_URI' => '/wp-cron.php',
				],
				'127.0.0.1',
			],
			'CLI'      => [ [], '127.0.0.1' ],
		];
		$cases    = [];

		foreach ( $contexts as $context => [ $server, $expected_ip ] ) {
			$cases[ $context . ' ordinary' ]        = [ $server, $expected_ip, false, false ];
			$cases[ $context . ' ordinary hashed' ] = [ $server, $expected_ip, false, true ];
			$cases[ $context . ' forced' ]          = [ $server, $expected_ip, true, false ];
			$cases[ $context . ' forced hashed' ]   = [ $server, $expected_ip, true, true ];
		}

		return $cases;
	}

	/**
	 * Test consent, force, and filter behavior.
	 *
	 * @param bool      $statistics    Whether Statistics is enabled.
	 * @param bool      $force_send    Whether to force the send.
	 * @param bool|null $filter_result Optional filter result.
	 * @param int       $expected      Expected request count.
	 *
	 * @dataProvider dp_test_send_plugin_stats_consent
	 *
	 * @return void
	 */
	public function test_send_plugin_stats_consent(
		bool $statistics,
		bool $force_send,
		?bool $filter_result,
		int $expected
	): void {
		add_filter( 'hcap_allow_send_plugin_stats', '__return_false' );

		update_option( 'hcaptcha_settings', [ 'statistics' => $statistics ? [ 'on' ] : [] ] );
		hcaptcha()->init_hooks();

		remove_all_filters( 'hcap_allow_send_plugin_stats' );
		$filter_allow = null;

		if ( null !== $filter_result ) {
			add_filter(
				'hcap_allow_send_plugin_stats',
				static function ( bool $allow ) use ( $filter_result, &$filter_allow ): bool {
					$filter_allow = $allow;

					return $filter_result;
				}
			);
		}

		$requests = 0;

		add_filter(
			'pre_http_request',
			static function () use ( &$requests ) {
				++$requests;

				return [
					'response' => [ 'code' => 202 ],
					'body'     => '',
				];
			}
		);

		remove_all_actions( 'hcap_send_plugin_stats' );
		new PluginStats();
		do_action( 'hcap_send_plugin_stats', $force_send );

		self::assertSame( $expected, $requests );

		if ( null !== $filter_result ) {
			self::assertSame( $force_send || $statistics, $filter_allow );
		}
	}

	/**
	 * Data provider for test_send_plugin_stats_consent().
	 *
	 * @return array
	 */
	public function dp_test_send_plugin_stats_consent(): array {
		return [
			'Statistics disabled'          => [ false, false, null, 0 ],
			'Statistics enabled'           => [ true, false, null, 1 ],
			'Forced while disabled'        => [ false, true, null, 1 ],
			'Filter allows while disabled' => [ false, false, true, 1 ],
			'Filter blocks while enabled'  => [ true, false, false, 0 ],
			'Filter blocks forced send'    => [ false, true, false, 0 ],
		];
	}

	/**
	 * Test that the native opt-in caller sends before the setting is saved.
	 *
	 * @return void
	 */
	public function test_enabling_statistics_sends_before_saving(): void {
		update_option( 'hcaptcha_settings', [ 'statistics' => [] ] );
		hcaptcha()->init_hooks();
		remove_all_actions( 'hcap_send_plugin_stats' );
		new PluginStats();

		$requests = 0;
		add_filter(
			'pre_http_request',
			static function () use ( &$requests ) {
				++$requests;
				self::assertFalse( hcaptcha()->settings()->is_on( 'statistics' ) );

				return [ 'response' => [ 'code' => 202 ] ];
			}
		);

		$general = new General();
		$value   = [ 'statistics' => [ 'on' ] ];
		self::assertSame( $value, $general->maybe_send_stats( $value, [ 'statistics' => [] ] ) );
		self::assertSame( 1, $requests );
	}

	/**
	 * Test Playground blocks ordinary and forced reports.
	 *
	 * @return void
	 */
	public function test_playground_blocks_reports(): void {
		$home_url = static function (): string {
			return 'https://playground.wordpress.net/';
		};
		add_filter( 'home_url', $home_url );
		new Playground();
		remove_filter( 'home_url', $home_url );

		$requests = 0;
		add_filter(
			'pre_http_request',
			static function () use ( &$requests ) {
				++$requests;

				return [ 'response' => [ 'code' => 202 ] ];
			}
		);

		update_option( 'hcaptcha_settings', [ 'statistics' => [ 'on' ] ] );
		hcaptcha()->init_hooks();
		$subject = new PluginStats();
		$subject->send_plugin_stats();
		$subject->send_plugin_stats( true );

		self::assertSame( 0, $requests );
	}

	/**
	 * Test consent, privacy, and readme disclose the intercepted report contract.
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_telemetry_disclosures(): void {
		$fields = $this->get_protected_property( new General(), 'form_fields' );
		$helper = $fields['statistics']['helper'];
		$claims = [
			'visitor IP of the current request (X-Forwarded-For)',
			'full site URL with /plugin-stats appended',
			'https://a.hcaptcha.com/api/event',
			'help prioritize plugin development',
			'plugin version, license type, active integrations and their enabled forms, multisite status',
			'site/secret key presence flags, without key values',
			'when Statistics is turned on and on initialization after plugin updates',
			'which may be an administrator or cron request',
			'127.0.0.1 is sent as a fallback',
			'ordinary network metadata, such as the server IP',
			'Collect Anonymously, Collect IP, and Collect User Agent affect only local event storage',
			'Custom code can force or filter sends even with Statistics off',
		];
		$copies = [
			( new Privacy() )->get_privacy_message(),
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Read the shipped local documentation.
			file_get_contents( HCAPTCHA_PATH . '/readme.txt' ),
		];

		self::assertStringContainsString( 'stores local event data', $helper );
		self::assertStringContainsString( "current request's visitor IP, site URL, and plugin configuration", $helper );
		self::assertStringContainsString( 'hCaptcha site and secret key values are not sent', $helper );

		foreach ( $copies as $copy ) {
			foreach ( $claims as $claim ) {
				self::assertStringContainsString( $claim, $copy );
			}

			self::assertStringNotContainsString( 'non-personal', $copy );
			self::assertStringNotContainsString( 'not including any end user data', $copy );
		}

		self::assertStringNotContainsString( 'non-personal', $helper );
		self::assertStringNotContainsString( 'not including any end user data', $helper );

		self::assertStringContainsString( 'does not anonymize or disable remote Statistics reports', $fields['anonymous']['helper'] );
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
		self::assertSame( 'Pro', $stats['License'] );
		self::assertSame( 1, $stats['Site key'] );
		self::assertSame( 1, $stats['Secret key'] );
		self::assertSame( (int) is_multisite(), $stats['Multisite'] );
		self::assertArrayHasKey( 'Active', $stats );
		self::assertLessThanOrEqual( 30, count( $stats ) );
		self::assertSame( $this->get_allowed_stats_keys(), array_keys( $stats ) );
		self::assertNotContains( 'site-key', $stats, true );
		self::assertNotContains( 'secret-key', $stats, true );
	}

	/**
	 * Test get_plugin_stats() with a detected Enterprise license.
	 */
	public function test_get_plugin_stats_with_detected_enterprise_license(): void {
		update_option(
			'hcaptcha_settings',
			[
				'mode'       => General::MODE_LIVE,
				'license'    => 'enterprise',
				'site_key'   => 'site-key',
				'secret_key' => 'secret-key',
			]
		);
		hcaptcha()->init_hooks();

		$stats = ( new PluginStats() )->get_plugin_stats();

		self::assertSame( 'Enterprise', $stats['License'] );
	}

	/**
	 * Get the allowed configuration fields for the active integrations.
	 *
	 * @return array
	 */
	private function get_allowed_stats_keys(): array {
		$settings_tab = hcaptcha()->settings()->get_tabs();
		$system_info  = null;

		foreach ( $settings_tab as $tab ) {
			if ( is_a( $tab, SystemInfo::class ) ) {
				$system_info = $tab;

				break;
			}
		}

		self::assertNotNull( $system_info );

		[ $fields ]   = $system_info->get_integrations();
		$fields       = array_filter(
			$fields,
			static function ( $field ) {
				return ! $field['disabled'];
			}
		);
		$allowed_keys = array_merge(
			[ 'hCaptcha', 'License', 'Site key', 'Secret key', 'Multisite', 'Active' ],
			array_values( wp_list_pluck( $fields, 'label' ) )
		);
		return array_slice( $allowed_keys, 0, 30 );
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
