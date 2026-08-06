<?php
/**
 * EventsTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpLanguageLevelInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Integration\Admin\Events;

use Exception;
use HCaptcha\Admin\Events\Events;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Migrations\Migrations;
use HCaptcha\Settings\General;
use HCaptcha\Settings\PluginSettingsBase;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use tad\FunctionMocker\FunctionMocker;

/**
 * Test EventsTest class.
 *
 * @requires PHP >= 8.0
 *
 * @group    events
 */
class EventsTest extends HCaptchaWPTestCase {

	/**
	 * Set up test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		// Disable temporary tables creating.
		remove_all_filters( 'query', 10 );
	}

	/**
	 * Tear down the test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		unset( $_SERVER['HTTP_USER_AGENT'], $_SERVER['HTTP_TRUE_CLIENT_IP'] );

		$this->drop_table();
		Events::create_table( true );

		delete_option( Migrations::MIGRATED_VERSIONS_OPTION_NAME );
		wp_cache_delete( Migrations::MIGRATED_VERSIONS_OPTION_NAME, 'options' );

		parent::tearDown();
	}

	/**
	 * Test constructor and init_hooks().
	 *
	 * @return void
	 */
	public function test_constructor_and_init_hooks(): void {
		update_option( 'hcaptcha_settings', [ 'statistics' => [ 'on' ] ] );

		hcaptcha()->init_hooks();

		$subject = new Events();

		self::assertSame(
			-1000,
			has_action( 'hcap_verify_request', [ $subject, 'save_event' ] )
		);
	}

	/**
	 * Test save_event().
	 *
	 * @return void
	 * @noinspection JsonEncodingApiUsageInspection
	 */
	public function test_save_event(): void {
		global $wpdb;

		$result      = 'empty';
		$error_codes = [ 'empty' ];
		$error_info  = (object) [ 'codes' => $error_codes ];
		$info        = [
			'id' => [
				'source'  => [],
				'form_id' => 0,
			],
		];
		$user_agent  = 'some user agent string';
		$ip          = '1.1.1.1';
		$option      = [
			'collect_ua'              => [ 'on' ],
			'collect_ip'              => [ 'on' ],
			'anonymous'               => [],
			'trusted_address_headers' => [ 'HTTP_TRUE_CLIENT_IP' ],
		];
		$table_name  = Events::TABLE_NAME;

		$_SERVER['HTTP_USER_AGENT']     = $user_agent;
		$_SERVER['HTTP_TRUE_CLIENT_IP'] = $ip;

		update_option( 'hcaptcha_settings', $option );

		hcaptcha()->init_hooks();

		$subject = new Events();

		$this->drop_table();
		$subject::create_table();
		$subject->save_event( $result, $error_codes, $error_info );

		// Query the database to check if the event was saved.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$event = $wpdb->get_row( "SELECT * FROM $wpdb->prefix$table_name ORDER BY id DESC LIMIT 1" );

		// Check that the event data matches the test data.
		// phpcs:disable WordPress.WP.AlternativeFunctions.json_encode_json_encode
		$this->assertEquals( json_encode( $info['id']['source'] ), $event->source );
		$this->assertEquals( $info['id']['form_id'], $event->form_id );
		$this->assertEquals( $ip, $event->ip );
		$this->assertEquals( $user_agent, $event->user_agent );
		$this->assertEquals( '', $event->uuid );
		$this->assertEquals( json_encode( $error_codes ), $event->error_codes );
		$this->assertEquals( Events::STATUS_ACTIVE, $event->status );
		$this->assertNull( $event->trashed_at_gmt );
		// phpcs:enable WordPress.WP.AlternativeFunctions.json_encode_json_encode

		delete_option( 'hcaptcha_settings' );
	}

	/**
	 * Test test_save_event_with_wrong_params().
	 *
	 * @return void
	 */
	public function test_save_event_with_wrong_params(): void {

		$subject = new Events();

		$this->drop_table();

		$subject->save_event( [ 'not a string' ], [], (object) [] );
	}

	/**
	 * Test save_event() with an invalid widget id.
	 *
	 * @return void
	 */
	public function test_save_event_with_invalid_widget_id(): void {
		global $wpdb;

		$subject     = new Events();
		$forged_id   = [
			'source'  => [ 'forged/source.php' ],
			'form_id' => 'forged',
		];
		$expected_id = [
			'source'  => [ 'WordPress' ],
			'form_id' => 'login',
		];
		$error_info  = (object) [
			'codes'       => [ 'bad-signature' ],
			'expected_id' => $expected_id,
		];

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode, WordPress.Security.NonceVerification.Missing
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = base64_encode( wp_json_encode( $forged_id ) ) . '-invalid';

		$this->drop_table();
		$subject::create_table();
		$subject->save_event( 'bad-signature', [], $error_info );

		$table_name = $wpdb->prefix . Events::TABLE_NAME;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$event = $wpdb->get_row( "SELECT * FROM $table_name ORDER BY id DESC LIMIT 1" );

		self::assertSame( wp_json_encode( $expected_id['source'] ), $event->source );
		self::assertSame( $expected_id['form_id'], $event->form_id );
		self::assertSame( wp_json_encode( $error_info->codes ), $event->error_codes );
	}

	/**
	 * Test save_event() with an invalid widget id and no expected id.
	 *
	 * @return void
	 */
	public function test_save_event_with_invalid_widget_id_and_no_expected_id(): void {
		global $wpdb;

		$subject   = new Events();
		$forged_id = [
			'source'  => [ 'forged/source.php' ],
			'form_id' => 'forged',
		];

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode, WordPress.Security.NonceVerification.Missing
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = base64_encode( wp_json_encode( $forged_id ) ) . '-invalid';

		$this->drop_table();
		$subject::create_table();
		$subject->save_event( 'bad-signature', [], (object) [ 'codes' => [ 'bad-signature' ] ] );

		$table_name = $wpdb->prefix . Events::TABLE_NAME;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$event = $wpdb->get_row( "SELECT * FROM $table_name ORDER BY id DESC LIMIT 1" );

		self::assertSame( wp_json_encode( [ 'Unknown' ] ), $event->source );
		self::assertSame( 'unknown', $event->form_id );
	}

	/**
	 * Test test_save_event_from_check_config().
	 *
	 * @return void
	 */
	public function test_save_event_from_check_config(): void {

		$subject = new Events();

		FunctionMocker::replace(
			'\HCaptcha\Helpers\HCaptcha::decode_id_info',
			[
				'valid' => true,
				'id'    =>
					[
						'source'  => [ General::class ],
						'form_id' => General::CHECK_CONFIG_FORM_ID,
					],
			]
		);

		$this->drop_table();

		$subject->save_event( null, [], (object) [] );
	}

	/**
	 * Test get events().
	 *
	 * @return void
	 */
	public function test_get_events(): void {
		$date = wp_date( 'Y-m-d H:i:s' );

		$expected = [
			'items' => [
				(object) [
					'id'             => '1',
					'source'         => '[]',
					'form_id'        => '0',
					'ip'             => '',
					'user_agent'     => '',
					'uuid'           => '',
					'error_codes'    => '["empty"]',
					'date_gmt'       => $date,
					'status'         => Events::STATUS_ACTIVE,
					'trashed_at_gmt' => null,
				],
				(object) [
					'id'             => '2',
					'source'         => '[]',
					'form_id'        => '0',
					'ip'             => '',
					'user_agent'     => '',
					'uuid'           => '',
					'error_codes'    => '[]',
					'date_gmt'       => $date,
					'status'         => Events::STATUS_ACTIVE,
					'trashed_at_gmt' => null,
				],
			],
			'total' => 2,
		];

		$subject = new Events();

		$this->drop_table();
		$subject::create_table();
		$subject->save_event( 'empty', [], (object) [ 'codes' => [ 'empty' ] ] );

		// Avoid caching in Events.
		$subject = new Events();

		$subject->save_event( 'success', [], (object) [] );

		$actual = $subject::get_events();

		// Make sure the dates are the same (no more than 10 sec difference).
		self::assertTrue(
			( strtotime( $actual['items'][0]->date_gmt ) - strtotime( $expected['items'][0]->date_gmt ) ) < 10
		);
		self::assertTrue(
			( strtotime( $actual['items'][1]->date_gmt ) - strtotime( $expected['items'][1]->date_gmt ) ) < 10
		);

		$actual['items'][0]->date_gmt = $expected['items'][0]->date_gmt;
		$actual['items'][1]->date_gmt = $expected['items'][1]->date_gmt;

		self::assertEquals( $expected, $actual );
	}

	/**
	 * Test get forms().
	 *
	 * @return void
	 */
	public function test_get_forms(): void {
		global $wpdb;

		$date = wp_date( 'Y-m-d H:i:s' );

		$expected = [
			'items'  =>
				[
					(object) [
						'source'  => '[]',
						'form_id' => '0',
						'served'  => '2',
						'id'      => '1',
					],
				],
			'total'  => 1,
			'served' =>
				[
					(object) [
						'date_gmt' => $date,
					],

					(object) [
						'date_gmt' => $date,
					],
				],
		];

		$subject = new Events();

		$this->drop_table();
		$subject::create_table();
		$subject->save_event( 'empty', [], (object) [ 'codes' => [ 'empty' ] ] );

		// Avoid caching in Events.
		$subject = new Events();

		$subject->save_event( 'success', [], (object) [] );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$wpdb->prefix . Events::TABLE_NAME,
			[
				'source'         => '[]',
				'form_id'        => '0',
				'ip'             => '',
				'user_agent'     => '',
				'uuid'           => '',
				'error_codes'    => '[]',
				'date_gmt'       => '2020-01-01 00:00:00',
				'status'         => Events::STATUS_ACTIVE,
				'trashed_at_gmt' => null,
			]
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$wpdb->prefix . Events::TABLE_NAME,
			[
				'source'         => '[]',
				'form_id'        => '0',
				'ip'             => '',
				'user_agent'     => '',
				'uuid'           => '',
				'error_codes'    => '[]',
				'date_gmt'       => $date,
				'status'         => Events::STATUS_TRASH,
				'trashed_at_gmt' => $date,
			]
		);

		$actual = $subject::get_forms();

		// Make sure the dates are the same (no more than 10 sec difference).
		self::assertTrue(
			( strtotime( $actual['served'][0]->date_gmt ) - strtotime( $expected['served'][0]->date_gmt ) ) < 10
		);
		self::assertTrue(
			( strtotime( $actual['served'][1]->date_gmt ) - strtotime( $expected['served'][1]->date_gmt ) ) < 10
		);

		$actual['served'][0]->date_gmt = $expected['served'][0]->date_gmt;
		$actual['served'][1]->date_gmt = $expected['served'][1]->date_gmt;

		self::assertEquals( $expected, $actual );
	}

	/**
	 * Test create_table().
	 *
	 * @return void
	 */
	public function test_create_table(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$actual_query    = [];
		$expected_query  = "CREATE TABLE {$wpdb->prefix}hcaptcha_events (
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
		) $charset_collate";
		$filter          = static function ( $queries ) use ( &$actual_query ) {
			$actual_query = $queries;

			return $queries;
		};

		$this->drop_table();

		add_filter( 'dbdelta_queries', $filter );

		Events::create_table();

		remove_filter( 'dbdelta_queries', $filter );

		$expected_query = str_replace( "\r\n", "\n", $expected_query );
		$actual_query   = array_map(
			static function ( string $query ): string {
				return str_replace( "\r\n", "\n", $query );
			},
			array_values( $actual_query )
		);

		$this->assertSame( [ $expected_query ], $actual_query );
		self::assertTrue( Events::table_exists() );
	}

	/**
	 * Test create_table() marks an existing table without running dbDelta().
	 *
	 * @return void
	 */
	public function test_create_table_marks_existing_table_without_dbdelta(): void {
		$actual_query = [];
		$filter       = static function ( $queries ) use ( &$actual_query ) {
			$actual_query = $queries;

			return $queries;
		};

		$this->drop_table();

		Events::create_table();

		self::assertTrue( Events::table_exists() );

		$this->clear_events_table_created();

		self::assertFalse( Events::table_exists() );

		add_filter( 'dbdelta_queries', $filter );

		Events::create_table();

		remove_filter( 'dbdelta_queries', $filter );

		self::assertSame( [], $actual_query );
		self::assertTrue( Events::table_exists() );
	}

	/**
	 * Test create_table() can force a physical table check.
	 *
	 * @return void
	 */
	public function test_create_table_force_ignores_created_marker(): void {
		$actual_query = [];
		$filter       = static function ( $queries ) use ( &$actual_query ) {
			$actual_query = $queries;

			return $queries;
		};

		$this->drop_table();
		$this->mark_events_table_created();

		self::assertTrue( Events::table_exists() );

		add_filter( 'dbdelta_queries', $filter );

		Events::create_table();

		remove_filter( 'dbdelta_queries', $filter );

		self::assertSame( [], $actual_query );
		self::assertFalse( $this->database_table_exists() );

		$actual_query = [];

		add_filter( 'dbdelta_queries', $filter );

		Events::create_table( true );

		remove_filter( 'dbdelta_queries', $filter );

		self::assertNotSame( [], $actual_query );
		self::assertTrue( $this->database_table_exists() );
		self::assertTrue( Events::table_exists() );
	}

	/**
	 * Test events queries are empty when the table is missing.
	 *
	 * @return void
	 */
	public function test_missing_table_queries_return_empty(): void {
		global $wpdb;

		$this->drop_table();

		$wpdb->last_error = '';

		self::assertFalse( Events::table_exists() );
		self::assertSame(
			[
				'items' => [],
				'total' => 0,
			],
			Events::get_events()
		);
		self::assertSame(
			[
				'items'  => [],
				'total'  => 0,
				'served' => [],
			],
			Events::get_forms()
		);
		self::assertSame(
			[
				Events::STATUS_ACTIVE => 0,
				Events::STATUS_TRASH  => 0,
			],
			Events::get_status_counts()
		);

		Events::cleanup_trash();

		self::assertSame( '', $wpdb->last_error );
	}

	/**
	 * Test cleanup_trash() does not query events when statistics are off.
	 *
	 * @return void
	 */
	public function test_cleanup_trash_does_not_query_events_when_statistics_are_off(): void {
		global $wpdb;

		update_option( 'hcaptcha_versions', [ '5.0.0' => time() ] );
		wp_cache_set( 'hcaptcha_versions', [ '5.0.0' => time() ], 'options' );
		update_option( 'hcaptcha_settings', [ 'statistics' => [] ] );
		hcaptcha()->init_hooks();

		Events::create_table();

		$wpdb->last_query = '';

		Events::cleanup_trash();

		self::assertStringNotContainsString( 'DELETE FROM', $wpdb->last_query );

		delete_option( 'hcaptcha_versions' );
		wp_cache_delete( 'hcaptcha_versions', 'options' );
	}

	/**
	 * Test get_where_date_gmt().
	 *
	 * @return void
	 */
	public function test_get_where_date_gmt(): void {
		$dates = [ '2024-05-01', '2024-05-02' ];

		$expected = "date_gmt BETWEEN '2024-05-01 00:00:00' AND '2024-05-02 23:59:59'";

		$subject = new Events();

		$actual = $subject::get_where_date_gmt( [ 'dates' => $dates ] );

		self::assertEquals( $expected, $actual );

		$actual = $subject::get_where_date_gmt( [ 'dates' => [] ] );

		self::assertEquals( '1=1', $actual );
	}

	/**
	 * Test save_event() returns when an event was already saved.
	 *
	 * @return void
	 */
	public function test_save_event_returns_when_already_saved(): void {
		$subject = new Events();

		$subject->save_event( 'empty', [], (object) [] );

		self::assertSame( 'success', $subject->save_event( 'success', [], (object) [] ) );
	}

	/**
	 * Test save_event() anonymizes collected IP and user agent.
	 *
	 * @return void
	 */
	public function test_save_event_anonymizes_ip_and_user_agent(): void {
		global $wpdb;

		$user_agent = 'anonymous user agent';
		$ip         = '2.2.2.2';

		$_SERVER['HTTP_USER_AGENT']     = $user_agent;
		$_SERVER['HTTP_TRUE_CLIENT_IP'] = $ip;

		update_option(
			'hcaptcha_settings',
			[
				'collect_ua'              => [ 'on' ],
				'collect_ip'              => [ 'on' ],
				'anonymous'               => [ 'on' ],
				'trusted_address_headers' => [ 'HTTP_TRUE_CLIENT_IP' ],
			]
		);
		hcaptcha()->init_hooks();

		$this->drop_table();
		Events::create_table();

		( new Events() )->save_event( null, [], (object) [] );

		$table_name = Events::TABLE_NAME;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$event = $wpdb->get_row( "SELECT * FROM $wpdb->prefix$table_name ORDER BY id DESC LIMIT 1" );

		self::assertSame( wp_hash( $ip ), $event->ip );
		self::assertSame( wp_hash( $user_agent ), $event->user_agent );
	}

	/**
	 * Test trash queries return empty when legacy schema is marked as not ready.
	 *
	 * @return void
	 */
	public function test_trash_queries_return_empty_when_schema_is_not_ready(): void {
		$this->drop_table();
		Events::create_table();
		$this->set_trash_schema_ready( false );

		self::assertSame(
			[
				'items' => [],
				'total' => 0,
			],
			Events::get_events( [ 'status' => Events::STATUS_TRASH ] )
		);
		self::assertSame(
			[
				'items'  => [],
				'total'  => 0,
				'served' => [],
			],
			Events::get_forms( [ 'status' => Events::STATUS_TRASH ] )
		);
	}

	/**
	 * Test get_events() handles a failed SQL query.
	 *
	 * @return void
	 */
	public function test_get_events_returns_empty_on_query_failure(): void {
		$this->drop_table();
		Events::create_table();

		self::assertSame(
			[
				'items' => [],
				'total' => 0,
			],
			Events::get_events( [ 'columns' => [ 'missing_column' ] ] )
		);
	}

	/**
	 * Test get_status_counts() with a legacy schema.
	 *
	 * @return void
	 */
	public function test_get_status_counts_without_trash_schema(): void {
		$this->drop_table();
		Events::create_table();
		$this->set_trash_schema_ready( false );
		$this->insert_event();
		$this->insert_event( [ 'status' => Events::STATUS_TRASH ] );

		self::assertSame(
			[
				Events::STATUS_ACTIVE => 2,
				Events::STATUS_TRASH  => 0,
			],
			Events::get_status_counts()
		);
	}

	/**
	 * Test get_status_counts() with a trash schema.
	 *
	 * @return void
	 */
	public function test_get_status_counts_with_trash_schema(): void {
		$this->drop_table();
		Events::create_table();
		$this->set_trash_schema_ready( true );
		$this->insert_event();
		$this->insert_event( [ 'status' => Events::STATUS_TRASH ] );

		self::assertSame(
			[
				Events::STATUS_ACTIVE => 1,
				Events::STATUS_TRASH  => 1,
			],
			Events::get_status_counts()
		);
	}

	/**
	 * Test create_table() force path when the created marker is absent.
	 *
	 * @return void
	 */
	public function test_create_table_force_without_created_marker(): void {
		$this->drop_table();
		delete_option( PluginSettingsBase::OPTION_NAME );

		Events::create_table( true );

		self::assertTrue( $this->database_table_exists() );
		self::assertTrue( Events::table_exists() );
	}

	/**
	 * Test cleanup_trash() returns when statistics are on but the table is missing.
	 *
	 * @return void
	 */
	public function test_cleanup_trash_returns_when_table_is_missing(): void {
		global $wpdb;

		update_option( 'hcaptcha_settings', [ 'statistics' => [ 'on' ] ] );
		hcaptcha()->init_hooks();
		$this->set_trash_schema_ready( true );
		$this->drop_table();

		$wpdb->last_query = '';

		Events::cleanup_trash();

		self::assertStringNotContainsString( 'DELETE FROM', $wpdb->last_query );
	}

	/**
	 * Test cleanup_trash() deletes expired trashed events.
	 *
	 * @return void
	 */
	public function test_cleanup_trash_deletes_expired_trash(): void {
		global $wpdb;

		update_option( 'hcaptcha_settings', [ 'statistics' => [ 'on' ] ] );
		hcaptcha()->init_hooks();
		$this->set_trash_schema_ready( true );
		$this->drop_table();
		Events::create_table();

		$old_date = gmdate( 'Y-m-d H:i:s', time() - ( Events::TRASH_RETENTION_DAYS + 1 ) * DAY_IN_SECONDS );
		$new_date = gmdate( 'Y-m-d H:i:s' );

		$this->insert_event();
		$this->insert_event(
			[
				'status'         => Events::STATUS_TRASH,
				'trashed_at_gmt' => $old_date,
			]
		);
		$this->insert_event(
			[
				'status'         => Events::STATUS_TRASH,
				'trashed_at_gmt' => $new_date,
			]
		);

		Events::cleanup_trash();

		$table_name = $wpdb->prefix . Events::TABLE_NAME;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$trash_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table_name WHERE status = 'trash'" );

		self::assertSame( 1, $trash_count );
	}

	/**
	 * Test get_where_date_gmt_nested() without dates.
	 *
	 * @return void
	 */
	public function test_get_where_date_gmt_nested_without_dates(): void {
		self::assertSame( '1=1', Events::get_where_date_gmt_nested( [ 'dates' => [] ] ) );
	}

	/**
	 * Test get_default_dates() handles DateTime modification errors.
	 *
	 * @return void
	 */
	public function test_get_default_dates_handles_modify_exception(): void {
		FunctionMocker::replace(
			'date_create_immutable',
			static function () {
				return new class() {
					/**
					 * Modify date.
					 *
					 * @return void
					 * @throws Exception Date exception.
					 * @noinspection ThrowRawExceptionInspection
					 */
					public function modify(): void {
						throw new Exception( 'Bad date.' );
					}

					// phpcs:disable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- Testing DateTimeImmutable-compatible API.
					/**
					 * Set time.
					 *
					 * @return self
					 * @noinspection PhpUnused
					 */
					public function setTime(): self {
						// phpcs:ignore PHPCompatibility.FunctionDeclarations.NewClosure.ThisFoundInStatic -- False positive inside anonymous class.
						return $this;
					}
					// phpcs:enable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid

					/**
					 * Format date.
					 *
					 * @return string
					 */
					public function format(): string {
						return '2026-01-02';
					}
				};
			}
		);

		self::assertSame( [ '2026-01-02', '2026-01-02' ], Events::get_default_dates() );
	}

	/**
	 * Test get_events() with legacy schema and invalid status.
	 *
	 * @return void
	 */
	public function test_get_events_with_legacy_schema_and_invalid_status(): void {
		$this->drop_table();
		Events::create_table();
		$this->set_trash_schema_ready( false );
		$this->insert_event();

		$events = Events::get_events( [ 'status' => 'bad-status' ] );

		self::assertSame( 1, $events['total'] );
	}

	/**
	 * Drop the table.
	 *
	 * @return void
	 */
	private function drop_table(): void {
		global $wpdb;

		$table_name = Events::TABLE_NAME;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS $wpdb->prefix$table_name" );
		$this->clear_events_table_created();
	}

	/**
	 * Whether the physical events table exists.
	 *
	 * @return bool
	 */
	private function database_table_exists(): bool {
		global $wpdb;

		$table_name = $wpdb->prefix . Events::TABLE_NAME;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );
	}

	/**
	 * Mark the events table as created.
	 *
	 * @return void
	 */
	private function mark_events_table_created(): void {
		$settings = get_option( PluginSettingsBase::OPTION_NAME, [] );
		$settings = is_array( $settings ) ? $settings : [];

		$settings[ Events::TABLE_CREATED_OPTION_NAME ] = 'on';

		update_option( PluginSettingsBase::OPTION_NAME, $settings );
	}

	/**
	 * Set a trash schema readiness marker.
	 *
	 * @param bool $ready Whether trash schema is ready.
	 *
	 * @return void
	 */
	private function set_trash_schema_ready( bool $ready ): void {
		$versions = [ '5.0.0' => $ready ? time() : -1 ];

		update_option( Migrations::MIGRATED_VERSIONS_OPTION_NAME, $versions );
		wp_cache_set( Migrations::MIGRATED_VERSIONS_OPTION_NAME, $versions, 'options' );
	}

	/**
	 * Insert an event row.
	 *
	 * @param array $data Event data.
	 *
	 * @return void
	 */
	private function insert_event( array $data = [] ): void {
		global $wpdb;

		$data = array_merge(
			[
				'source'         => '[]',
				'form_id'        => '0',
				'ip'             => '',
				'user_agent'     => '',
				'uuid'           => '',
				'error_codes'    => '[]',
				'date_gmt'       => gmdate( 'Y-m-d H:i:s' ),
				'status'         => Events::STATUS_ACTIVE,
				'trashed_at_gmt' => null,
			],
			$data
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert( $wpdb->prefix . Events::TABLE_NAME, $data );
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
}
