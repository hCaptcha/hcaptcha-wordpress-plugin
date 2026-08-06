<?php
/**
 * MigrationsTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */

namespace HCaptcha\Tests\Integration\Migrations;

use ActionScheduler_Store;
use HCaptcha\Admin\Events\Events;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Migrations\Migrations;
use HCaptcha\Settings\PluginSettingsBase;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use Mockery;
use ReflectionException;
use tad\FunctionMocker\FunctionMocker;

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

		$this->drop_events_table();
	}

	/**
	 * Tear down the test.
	 */
	public function tearDown(): void {
		unset( $_GET['service-worker'], $GLOBALS['current_screen'] );
		delete_transient( 'hcaptcha_async_migrate_4_11_0' );
		delete_transient( 'hcaptcha_async_migrate_5_0_0' );
		delete_transient( 'hcaptcha_async_migrate_5_1_0' );

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

		if ( version_compare( '5.1.0', $plugin_major_version, '<=' ) ) {
			$expected_option['5.1.0'] = $time;
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
		set_transient( 'hcaptcha_async_migrate_5_1_0', Migrations::COMPLETED );

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
	 * Test load_action_scheduler().
	 *
	 * @return void
	 */
	public function test_load_action_scheduler(): void {
		$subject = new Migrations();

		$subject->load_action_scheduler();

		self::assertTrue( function_exists( 'as_get_scheduled_actions' ) );
	}

	/**
	 * Test migration methods without an upgrade version are skipped.
	 *
	 * @return void
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_migrations_without_upgrade_version_are_skipped(): void {
		$subject = new class() extends Migrations {

			/**
			 * Migration without a version in the method name.
			 *
			 * @return bool|null
			 * @noinspection PhpUnused
			 */
			public function migrate_without_version(): ?bool {
				return true;
			}
		};
		$method  = $this->set_method_accessibility( $subject, 'maybe_prepare_migration_option' );

		delete_option( Migrations::MIGRATED_VERSIONS_OPTION_NAME );

		$method->invoke( $subject );

		$migrated = get_option( Migrations::MIGRATED_VERSIONS_OPTION_NAME, [] );

		self::assertIsArray( $migrated );
		self::assertArrayNotHasKey( '', $migrated );

		$subject->migrate();

		self::assertArrayNotHasKey( '', get_option( Migrations::MIGRATED_VERSIONS_OPTION_NAME, [] ) );
	}

	/**
	 * Test maybe_create_tables() returns after the table check was already done.
	 *
	 * @return void
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_maybe_create_tables_returns_after_first_check(): void {
		set_current_screen( 'some-screen' );

		$subject = new Migrations();
		$method  = $this->set_method_accessibility( $subject, 'maybe_create_tables' );

		$method->invoke( $subject );

		self::assertTrue( $this->get_protected_property( $subject, 'tables_check_done' ) );
	}

	/**
	 * Test async_migrate_4_11_0().
	 *
	 * @return void
	 */
	public function test_async_migrate_4_11_0(): void {
		global $wpdb;

		$table_name = $wpdb->prefix . Events::TABLE_NAME;
		$subject    = new Migrations();

		Events::create_table();

		add_action( 'async_migrate_4_11_0', [ $subject, 'async_migrate_4_11_0' ] );

		do_action( 'async_migrate_4_11_0' );

		$indexes = $this->get_index_sub_parts(
			$table_name,
			[
				'idx_date_source_form',
				'hcaptcha_id',
			]
		);

		self::assertSame( Migrations::COMPLETED, (int) get_transient( 'hcaptcha_async_migrate_4_11_0' ) );
		self::assertArrayHasKey( 'idx_date_source_form', $indexes );
		self::assertArrayNotHasKey( 'hcaptcha_id', $indexes );

		Events::create_table( true );
	}

	/**
	 * Test async_migrate_5_0_0().
	 *
	 * @return void
	 */
	public function test_async_migrate_5_0_0(): void {
		global $wpdb;

		$table_name = $wpdb->prefix . Events::TABLE_NAME;
		$subject    = new Migrations();
		$filter     = static function (): array {
			return [ 'HTTP_CF_CONNECTING_IP' ];
		};

		$this->create_legacy_events_table();
		add_filter( 'hcap_trusted_address_headers', $filter );
		add_action( 'async_migrate_5_0_0', [ $subject, 'async_migrate_5_0_0' ] );

		try {
			do_action( 'async_migrate_5_0_0' );
		} finally {
			remove_filter( 'hcap_trusted_address_headers', $filter );
		}

		$option = get_option( PluginSettingsBase::OPTION_NAME, [] );

		self::assertSame( [ 'HTTP_CF_CONNECTING_IP' ], $option['trusted_address_headers'] );
		self::assertSame( Migrations::COMPLETED, (int) get_transient( 'hcaptcha_async_migrate_5_0_0' ) );
		self::assertContains( 'status', $this->get_column_names( $table_name ) );
		self::assertContains( 'trashed_at_gmt', $this->get_column_names( $table_name ) );
		self::assertSame(
			[
				'status_date_gmt'    => [
					'status'   => null,
					'date_gmt' => null,
				],
				'status_source_form' => [
					'status'  => null,
					'source'  => 191,
					'form_id' => null,
				],
			],
			$this->get_index_sub_parts(
				$table_name,
				[
					'status_date_gmt',
					'status_source_form',
				]
			)
		);
	}

	/**
	 * Test async_migrate_5_1_0().
	 *
	 * @return void
	 */
	public function test_async_migrate_5_1_0(): void {
		global $wpdb;

		$table_name = $wpdb->prefix . Events::TABLE_NAME;
		$subject    = new Migrations();

		Events::create_table();
		add_action( 'async_migrate_5_1_0', [ $subject, 'async_migrate_5_1_0' ] );

		do_action( 'async_migrate_5_1_0' );

		self::assertSame( Migrations::COMPLETED, (int) get_transient( 'hcaptcha_async_migrate_5_1_0' ) );
		self::assertSame(
			[
				'source'               => [
					'source' => 191,
				],
				'hcaptcha_id'          => [
					'source'  => 191,
					'form_id' => null,
				],
				'idx_date_source_form' => [
					'date_gmt' => null,
					'source'   => 191,
					'form_id'  => null,
				],
				'status_source_form'   => [
					'status'  => null,
					'source'  => 191,
					'form_id' => null,
				],
			],
			$this->get_index_sub_parts(
				$table_name,
				[
					'source',
					'hcaptcha_id',
					'idx_date_source_form',
					'status_source_form',
				]
			)
		);
	}

	/**
	 * Test async migration scheduling records failed Action Scheduler status.
	 *
	 * @return void
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_run_async_sets_failed_transient_when_action_creation_fails(): void {
		$subject      = new Migrations();
		$method       = $this->set_method_accessibility( $subject, 'run_async' );
		$hook         = 'async_migrate_custom_async';
		$transient    = 'hcaptcha_' . $hook;
		$enqueue_args = [];
		$filter       = static function ( $pre, string $hook, array $args, string $group, int $priority, bool $unique ) use ( &$enqueue_args ): int {
			$enqueue_args = compact( 'hook', 'args', 'group', 'priority', 'unique' );

			return 0;
		};

		$subject->load_action_scheduler();
		delete_transient( $transient );
		add_filter( 'pre_as_enqueue_async_action', $filter, 10, 6 );

		try {
			$result = $method->invoke( $subject, 'migrate_custom_async' );

			self::assertNull( $result );
			self::assertSame( Migrations::STARTED, (int) get_transient( $transient ) );

			$this->run_last_init_callback_at_priority_20();

			self::assertSame( Migrations::FAILED, (int) get_transient( $transient ) );
			self::assertSame(
				[
					'hook'     => $hook,
					'args'     => [],
					'group'    => 'hcaptcha',
					'priority' => 10,
					'unique'   => true,
				],
				$enqueue_args
			);
		} finally {
			remove_filter( 'pre_as_enqueue_async_action', $filter );
			delete_transient( $transient );
		}
	}

	/**
	 * Test create_as_action() with an existing started action.
	 *
	 * @return void
	 * @throws ReflectionException Reflection exception.
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function test_create_as_action_returns_started_for_existing_action(): void {
		$subject = new Migrations();
		$method  = $this->set_method_accessibility( $subject, 'create_as_action' );
		$hook    = 'hcaptcha_existing_action_test';
		$group   = 'hcaptcha';

		$subject->load_action_scheduler();

		$action_id = as_enqueue_async_action( $hook, [], $group, true );

		try {
			self::assertGreaterThan( 0, $action_id );
			self::assertSame( Migrations::STARTED, $method->invoke( $subject, $hook, [], $group ) );
		} finally {
			as_unschedule_all_actions( $hook, [], $group );
		}
	}

	/**
	 * Test add_trusted_address_headers() returns on settings that do not need migration.
	 *
	 * @return void
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_add_trusted_address_headers_returns_when_settings_do_not_need_migration(): void {
		$subject = new Migrations();
		$method  = $this->set_method_accessibility( $subject, 'add_trusted_address_headers' );

		update_option( PluginSettingsBase::OPTION_NAME, 'not-an-array' );

		$method->invoke( $subject );

		self::assertSame( 'not-an-array', get_option( PluginSettingsBase::OPTION_NAME ) );

		update_option(
			PluginSettingsBase::OPTION_NAME,
			[
				'trusted_address_headers' => [ 'HTTP_X_FORWARDED_FOR' ],
			]
		);

		$method->invoke( $subject );

		self::assertSame(
			[
				'trusted_address_headers' => [ 'HTTP_X_FORWARDED_FOR' ],
			],
			get_option( PluginSettingsBase::OPTION_NAME )
		);
	}

	/**
	 * Test add_events_trash_folder() returns when the Events table is not available.
	 *
	 * @return void
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_add_events_trash_folder_returns_when_table_is_missing(): void {
		$subject = new Migrations();
		$method  = $this->set_method_accessibility( $subject, 'add_events_trash_folder' );

		FunctionMocker::replace( '\HCaptcha\Admin\Events\Events::create_table' );
		FunctionMocker::replace( '\HCaptcha\Admin\Events\Events::table_exists', false );

		$method->invoke( $subject );

		self::assertFalse( Events::table_exists() );
	}

	/**
	 * Test table helper early returns.
	 *
	 * @return void
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_table_helpers_return_when_schema_items_already_match(): void {
		global $wpdb;

		$subject       = new Migrations();
		$table_name    = $wpdb->prefix . Events::TABLE_NAME;
		$add_index     = $this->set_method_accessibility( $subject, 'add_index' );
		$replace_index = $this->set_method_accessibility( $subject, 'replace_index' );
		$add_column    = $this->set_method_accessibility( $subject, 'add_column' );

		Events::create_table();

		$add_index->invoke( $subject, $table_name, 'date_gmt', 'date_gmt' );
		$replace_index->invoke( $subject, $table_name, 'missing_migration_index', 'source(191)', false );
		$add_column->invoke( $subject, $table_name, 'status', "VARCHAR(20) NOT NULL DEFAULT 'active'" );

		self::assertContains( 'status', $this->get_column_names( $table_name ) );
		self::assertArrayHasKey( 'date_gmt', $this->get_index_sub_parts( $table_name, [ 'date_gmt' ] ) );
	}

	/**
	 * Test update_events_source_indexes() returns when the Events table is unavailable.
	 *
	 * @return void
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_update_events_source_indexes_returns_when_table_is_missing(): void {
		$subject = new Migrations();
		$method  = $this->set_method_accessibility( $subject, 'update_events_source_indexes' );

		FunctionMocker::replace( '\HCaptcha\Admin\Events\Events::create_table' );
		FunctionMocker::replace( '\HCaptcha\Admin\Events\Events::table_exists', false );

		$method->invoke( $subject );

		self::assertFalse( Events::table_exists() );
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
		    KEY source (source(191)),
		    KEY form_id (form_id),
		    KEY hcaptcha_id (source(191), form_id),
		    KEY ip (ip),
		    KEY uuid (uuid),
		    KEY date_gmt (date_gmt),
		    KEY status_date_gmt (status, date_gmt),
		    KEY status_source_form (status, source(191), form_id)
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
	 * Test update_events_source_indexes().
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_update_events_source_indexes(): void {
		global $wpdb;

		$method_name = 'update_events_source_indexes';
		$subject     = new Migrations();
		$table_name  = $wpdb->prefix . Events::TABLE_NAME;

		$this->drop_events_table();

		Events::create_table();

		$method = $this->set_method_accessibility( $subject, $method_name );

		$method->invoke( $subject );

		self::assertSame(
			[
				'source'               => [
					'source' => 191,
				],
				'hcaptcha_id'          => [
					'source'  => 191,
					'form_id' => null,
				],
				'idx_date_source_form' => [
					'date_gmt' => null,
					'source'   => 191,
					'form_id'  => null,
				],
				'status_source_form'   => [
					'status'  => null,
					'source'  => 191,
					'form_id' => null,
				],
			],
			$this->get_index_sub_parts(
				$table_name,
				[
					'source',
					'hcaptcha_id',
					'idx_date_source_form',
					'status_source_form',
				]
			)
		);
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
	 * Create a legacy Events table without Trash Folder columns and indexes.
	 *
	 * @return void
	 */
	private function create_legacy_events_table(): void {
		global $wpdb;

		$table_name          = $wpdb->prefix . Events::TABLE_NAME;
		$charset_collate     = $wpdb->get_charset_collate();
		$source_index_length = Events::SOURCE_INDEX_LENGTH;

		$this->drop_events_table();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter
		$wpdb->query(
			"CREATE TABLE $table_name (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				source VARCHAR(256) NOT NULL,
				form_id VARCHAR(20) NOT NULL,
				ip VARCHAR(39) NOT NULL,
				user_agent VARCHAR(256) NOT NULL,
				uuid VARCHAR(36) NOT NULL,
				error_codes VARCHAR(256) NOT NULL,
				date_gmt DATETIME NOT NULL,
				PRIMARY KEY (id),
				KEY source (source($source_index_length)),
				KEY form_id (form_id),
				KEY hcaptcha_id (source($source_index_length), form_id),
				KEY date_gmt (date_gmt)
			) $charset_collate"
		);
		// phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange
	}

	/**
	 * Get table column names.
	 *
	 * @param string $table_name Table name.
	 *
	 * @return string[]
	 */
	private function get_column_names( string $table_name ): array {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$rows = (array) $wpdb->get_results( "SHOW COLUMNS FROM $table_name", ARRAY_A );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return array_map(
			static function ( array $row ): string {
				return $row['Field'];
			},
			$rows
		);
	}

	/**
	 * Run the last init callback registered at priority 20.
	 *
	 * @return void
	 */
	private function run_last_init_callback_at_priority_20(): void {
		$callbacks = $GLOBALS['wp_filter']['init']->callbacks[20] ?? [];

		self::assertNotEmpty( $callbacks );

		$callback = end( $callbacks );
		$function = $callback['function'] ?? null;

		self::assertIsCallable( $function );

		$function();
	}

	/**
	 * Get index sub-parts.
	 *
	 * @param string   $table_name  Table name.
	 * @param string[] $index_names Index names.
	 *
	 * @return array
	 */
	private function get_index_sub_parts( string $table_name, array $index_names ): array {
		global $wpdb;

		$index_map = array_fill_keys( $index_names, true );
		$sub_parts = array_fill_keys( $index_names, [] );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = (array) $wpdb->get_results(
			$wpdb->prepare(
				'SELECT INDEX_NAME, COLUMN_NAME, SUB_PART
					FROM INFORMATION_SCHEMA.STATISTICS
					WHERE table_schema = DATABASE()
						AND table_name = %s
					ORDER BY INDEX_NAME, SEQ_IN_INDEX',
				$table_name
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		foreach ( $rows as $row ) {
			$index_name = $row['INDEX_NAME'];

			if ( ! isset( $index_map[ $index_name ] ) ) {
				continue;
			}

			$sub_parts[ $index_name ][ $row['COLUMN_NAME'] ] = null === $row['SUB_PART'] ?
				null :
				(int) $row['SUB_PART'];
		}

		return array_filter(
			$sub_parts,
			static function ( array $parts ): bool {
				return [] !== $parts;
			}
		);
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
