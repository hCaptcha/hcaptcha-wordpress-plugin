<?php
/**
 * MigrationsTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\Migrations;

use HCaptcha\Admin\Events\Events;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Migrations\Migrations;
use HCaptcha\Settings\PluginSettingsBase;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use Mockery;
use ReflectionException;

/**
 * Test MigrationsTest class.
 *
 * @group migrations
 */
class MigrationsTest extends HCaptchaWPTestCase {

	/**
	 * Setup test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		delete_option( 'hcaptcha_settings' );

		// Disable temporary tables creating.
		remove_all_filters( 'query', 10 );
	}

	/**
	 * Tear down the test.
	 */
	public function tearDown(): void {
		unset( $_GET['service-worker'], $GLOBALS['current_screen'] );

		parent::tearDown();
	}

	/**
	 * Test init() and init_hooks().
	 *
	 * @param bool     $worker   The service-worker is set.
	 * @param bool     $admin    In admin.
	 * @param bool|int $expected Expected value.
	 *
	 * @return void
	 * @dataProvider dp_test_init_and_init_hooks
	 */
	public function test_init_and_init_hooks( bool $worker, bool $admin, $expected ): void {
		if ( $worker ) {
			$_GET['service-worker'] = 'some';
		}

		if ( $admin ) {
			set_current_screen( 'some-screen' );
		}

		$subject = new Migrations();

		self::assertSame( $expected, has_action( 'plugins_loaded', [ $subject, 'migrate' ] ) );
	}

	/**
	 * Data provider for test_init_and_init_hooks().
	 *
	 * @return array
	 */
	public function dp_test_init_and_init_hooks(): array {
		return [
			[ false, false, false ],
			[ true, false, false ],
			[ false, true, -PHP_INT_MAX ],
			[ true, true, false ],
		];
	}

	/**
	 * Test migrate().
	 *
	 * @return void
	 */
	public function test_migrate(): void {
		$time                 = time();
		$size                 = 'normal';
		$plugin_major_version = explode( '-', HCAPTCHA_VERSION )[0];
		$expected_option      = [
			'2.0.0'               => $time,
			'3.6.0'               => $time,
			'4.0.0'               => $time,
			'4.6.0'               => $time,
			'4.11.0'              => $time,
			$plugin_major_version => $time,
		];
		$expected_settings    = [
			'site_key'                        => '',
			'secret_key'                      => '',
			'theme'                           => '',
			'size'                            => $size,
			'language'                        => '',
			'off_when_logged_in'              => [],
			'recaptcha_compat_off'            => [],
			'wp_status'                       => [],
			'bbp_status'                      => [],
			'bp_status'                       => [],
			'cf7_status'                      => [ 'live' ],
			'divi_status'                     => [],
			'elementor_pro_status'            => [],
			'fluent_status'                   => [],
			'gravity_status'                  => [],
			'jetpack_status'                  => [],
			'mailchimp_status'                => [],
			'memberpress_status'              => [],
			'ninja_status'                    => [],
			'subscriber_status'               => [],
			'ultimate_member_status'          => [],
			'woocommerce_status'              => [],
			'woocommerce_wishlists_status'    => [],
			'wpforms_status'                  => [ 'form' ],
			'wpforo_status'                   => [],
			Events::TABLE_CREATED_OPTION_NAME => 'on',
		];

		if ( version_compare( '5.0.0', $plugin_major_version, '<=' ) ) {
			$expected_option['5.0.0'] = $time;
		}

		uksort( $expected_option, 'version_compare' );

		update_option( 'hcaptcha_size', $size );
		update_option( 'hcaptcha_wpforms_status', 'on' );

		self::assertSame( [], get_option( 'hcaptcha_settings', [] ) );

		$subject = new Migrations();

		delete_option( $subject::MIGRATED_VERSIONS_OPTION_NAME );

		self::assertSame( [], get_option( $subject::MIGRATED_VERSIONS_OPTION_NAME, [] ) );

		// Do not run async migrations via Action Scheduler.
		set_transient( 'hcaptcha_async_migrate_4_11_0', Migrations::COMPLETED );
		set_transient( 'hcaptcha_async_migrate_5_0_0', Migrations::COMPLETED );

		$subject->migrate();

		self::assertSame( 10, has_action( 'init', [ $subject, 'send_plugin_stats' ] ) );

		self::assertTrue( $this->compare_migrated( $expected_option, get_option( $subject::MIGRATED_VERSIONS_OPTION_NAME, [] ) ) );
		self::assertSame( $expected_settings, get_option( 'hcaptcha_settings', [] ) );
		self::assertTrue( Events::table_exists() );
		self::assertFalse( get_option( 'hcaptcha_size' ) );
		self::assertFalse( get_option( 'hcaptcha_wpforms_status' ) );

		// No migrations on the second run.
		$subject = new Migrations();

		$subject->migrate();

		self::assertTrue( $this->compare_migrated( $expected_option, get_option( $subject::MIGRATED_VERSIONS_OPTION_NAME, [] ) ) );
	}

	/**
	 * Test init() creates the events table when no migration is pending.
	 *
	 * @return void
	 */
	public function test_init_creates_events_table_when_no_migration_is_pending(): void {
		$this->drop_events_table();

		self::assertFalse( Events::table_exists() );

		$plugin_major_version = explode( '-', HCAPTCHA_VERSION )[0];

		update_option(
			Migrations::MIGRATED_VERSIONS_OPTION_NAME,
			[
				'2.0.0'               => 0,
				'3.6.0'               => 0,
				'4.0.0'               => 0,
				'4.6.0'               => 0,
				'4.11.0'              => 0,
				'5.0.0'               => 0,
				$plugin_major_version => time(),
			]
		);

		set_current_screen( 'some-screen' );
		new Migrations();

		self::assertTrue( Events::table_exists() );
	}

	/**
	 * Compare migrated option data.
	 *
	 * @param array $expected_option Expected option.
	 * @param array $option          Actual option.
	 *
	 * @return bool
	 */
	private function compare_migrated( array $expected_option, array $option ): bool {
		if ( array_keys( $expected_option ) !== array_keys( $option ) ) {
			return false;
		}

		foreach ( $expected_option as $version => $time ) {
			// Due to the glitch with mocking time(), let us allow 5-second time difference.
			if ( $option[ $version ] - $time > 5 ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Test send_plugin_stats().
	 *
	 * @return void
	 */
	public function test_send_plugin_stats(): void {
		$subject = new Migrations();

		self::assertSame( 0, did_action( 'hcap_send_plugin_stats' ) );

		$subject->send_plugin_stats();

		self::assertSame( 1, did_action( 'hcap_send_plugin_stats' ) );
	}

	/**
	 * Test migrate_360() when WPForms status not set.
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_migrate_360_when_wpforms_status_not_set(): void {
		$method  = 'migrate_360';
		$subject = Mockery::mock( Migrations::class )->makePartial();

		$this->set_method_accessibility( $subject, $method );

		$option = get_option( 'hcaptcha_settings', [] );

		$subject->$method();

		self::assertSame( $option, get_option( 'hcaptcha_settings', [] ) );
	}

	/**
	 * Test migrate_4_0_0().
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_migrate_4_0_0(): void {
		global $wpdb;

		$method          = 'migrate_4_0_0';
		$subject         = Mockery::mock( Migrations::class )->makePartial();
		$table_name      = Events::TABLE_NAME;
		$charset_collate = $wpdb->get_charset_collate();
		$actual_query    = '';
		$expected_query  = "CREATE TABLE $wpdb->prefix$table_name (
		    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		    source      VARCHAR(256)    NOT NULL,
		    form_id     VARCHAR(20)     NOT NULL,
		    ip          VARCHAR(39)     NOT NULL,
		    user_agent  VARCHAR(256)    NOT NULL,
		    uuid        VARCHAR(36)     NOT NULL,
		    error_codes VARCHAR(256)    NOT NULL,
		    date_gmt    DATETIME        NOT NULL,
		    status      VARCHAR(20)     NOT NULL DEFAULT 'active',
		    trashed_at_gmt DATETIME     NULL,
		    PRIMARY KEY (id),
		    KEY source (source),
		    KEY form_id (form_id),
		    KEY hcaptcha_id (source, form_id),
		    KEY ip (ip),
		    KEY uuid (uuid),
		    KEY date_gmt (date_gmt),
		    KEY status_date_gmt (status, date_gmt),
		    KEY status_source_form (status, source, form_id)
		) $charset_collate;";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS $wpdb->prefix$table_name" );

		add_filter(
			'dbdelta_queries',
			static function ( $queries ) use ( &$actual_query ) {
				$actual_query = $queries;

				return $queries;
			}
		);

		$this->set_method_accessibility( $subject, $method );

		$subject->$method();

		self::assertSame( array_filter( explode( ';', $expected_query ) ), $actual_query );
	}

	/**
	 * Drop the events table.
	 *
	 * @return void
	 */
	private function drop_events_table(): void {
		global $wpdb;

		$table_name = Events::TABLE_NAME;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS $wpdb->prefix$table_name" );
		$this->clear_events_table_created();
	}

	/**
	 * Clear the events table created marker.
	 *
	 * @return void
	 */
	private function clear_events_table_created(): void {
		$settings = get_option( PluginSettingsBase::OPTION_NAME, [] );
		$settings = is_array( $settings ) ? $settings : [];

		unset( $settings[ Events::TABLE_CREATED_OPTION_NAME ] );

		update_option( PluginSettingsBase::OPTION_NAME, $settings );
	}

	/**
	 * Test migrate_4_6_0().
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_migrate_4_6_0(): void {
		$method  = 'migrate_4_6_0';
		$subject = Mockery::mock( Migrations::class )->makePartial();

		$this->set_method_accessibility( $subject, $method );

		$subject->$method();

		$option = get_option( PluginSettingsBase::OPTION_NAME, [] );

		self::assertSame( [ 'live' ], $option['cf7_status'] );
	}

	/**
	 * Test add_trusted_address_headers().
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_add_trusted_address_headers(): void {
		$method_name = 'add_trusted_address_headers';
		$subject     = new Migrations();
		$filter      = static function () {
			return [
				'HTTP_CF_CONNECTING_IP',
				'HTTP_X_FORWARDED_FOR',
			];
		};

		$method = $this->set_method_accessibility( $subject, $method_name );

		add_filter( 'hcap_trusted_address_headers', $filter );

		$method->invoke( $subject );

		$option = get_option( PluginSettingsBase::OPTION_NAME, [] );

		self::assertSame(
			[
				'HTTP_CF_CONNECTING_IP',
				'HTTP_X_FORWARDED_FOR',
			],
			$option['trusted_address_headers']
		);
		self::assertArrayNotHasKey( Migrations::REVIEW_TRUSTED_ADDRESS_HEADERS_OPTION, $option );

		remove_filter( 'hcap_trusted_address_headers', $filter );
	}

	/**
	 * Test add_trusted_address_headers() without a custom trusted address headers filter.
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_add_trusted_address_headers_without_filter(): void {
		$method_name = 'add_trusted_address_headers';
		$subject     = new Migrations();

		$method = $this->set_method_accessibility( $subject, $method_name );

		$method->invoke( $subject );

		$option = get_option( PluginSettingsBase::OPTION_NAME, [] );

		self::assertSame( [], $option['trusted_address_headers'] );
		self::assertSame( 'on', $option[ Migrations::REVIEW_TRUSTED_ADDRESS_HEADERS_OPTION ] );
	}

	/**
	 * Test save_license_level().
	 *
	 * @param string $license_level License level.
	 *
	 * @return void
	 * @dataProvider dp_test_save_license_level
	 */
	public function test_save_license_level( string $license_level ): void {
		new Migrations();

		$option = get_option( PluginSettingsBase::OPTION_NAME, [] );

		self::assertSame( [], $option );

		switch ( $license_level ) {
			case 'free':
				$result   = [
					'features' => [],
					'pass'     => true,
				];
				$expected = [
					'license' => $license_level,
				];

				break;
			case 'pro':
				$result   = [
					'features' => [
						'custom_theme' => [ 'some theme' ],
					],
					'pass'     => true,
				];
				$expected = [
					'license' => $license_level,
				];

				break;
			case 'error':
				$result['pass']  = false;
				$result['error'] = 'some error';
				$expected        = [];

				break;
			default:
				$result   = [];
				$expected = [];

				break;
		}

		add_filter(
			'pre_http_request',
			static function ( $value, $parsed_args, $url ) use ( $result ) {
				if ( false !== strpos( $url, 'hcaptcha.com' ) ) {
					return [
						'body' => wp_json_encode( $result ),
					];
				}

				return $value;
			},
			10,
			3
		);

		HCaptcha::save_license_level();

		self::assertSame( $expected, get_option( PluginSettingsBase::OPTION_NAME, [] ) );
	}

	/**
	 * Data provider for test_save_license_level().
	 *
	 * @return array
	 */
	public function dp_test_save_license_level(): array {
		return [
			[ 'free' ],
			[ 'pro' ],
			[ 'error' ],
		];
	}
}
