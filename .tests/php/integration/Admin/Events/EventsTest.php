<?php
/**
 * EventsTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\Admin\Events;

use HCaptcha\Admin\Events\Events;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use Mockery;

/**
 * Test EventsTest class.
 *
 * @group events
 */
class EventsTest extends HCaptchaWPTestCase {

	/**
	 * Test constructor and init_hooks().
	 *
	 * @return void
	 */
	public function test_constructor_and_init_hooks() {
		update_option( 'hcaptcha_settings', [ 'statistics' => [ 'on' ] ] );

		hcaptcha()->init_hooks();

		$subject = new Events();

		self::assertSame(
			-PHP_INT_MAX,
			has_action( 'hcap_verify_request', [ $subject, 'save_event' ] )
		);
	}

	/**
	 * Test save_event().
	 *
	 * @return void
	 */
	public function test_save_event() {
		global $wpdb;

		$result      = 'empty';
		$error_codes = [ 'empty' ];
		$info        = [
			'id' => [
				'source'  => [],
				'form_id' => 0,
			],
		];
		$user_agent  = 'some user agent string';
		$ip          = '1.1.1.1';
		$option      = [
			'collect_ua' => [ 'on' ],
			'collect_ip' => [ 'on' ],
		];
		$table_name  = Events::TABLE_NAME;

		$_SERVER['HTTP_USER_AGENT']     = $user_agent;
		$_SERVER['HTTP_TRUE_CLIENT_IP'] = $ip;

		update_option( 'hcaptcha_settings', $option );

		hcaptcha()->init_hooks();

		$subject = new Events();

		$this->drop_table();
		$subject::create_table();
		$subject->save_event( $result, $error_codes );

		// Query the database to check if the event was saved.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$event = $wpdb->get_row( "SELECT * FROM {$wpdb->prefix}$table_name ORDER BY id DESC LIMIT 1" );

		// Check that the event data matches the test data.
		// phpcs:disable WordPress.WP.AlternativeFunctions.json_encode_json_encode
		$this->assertEquals( json_encode( $info['id']['source'] ), $event->source );
		$this->assertEquals( $info['id']['form_id'], $event->form_id );
		$this->assertEquals( $ip, $event->ip );
		$this->assertEquals( $user_agent, $event->user_agent );
		$this->assertEquals( '', $event->uuid );
		$this->assertEquals( json_encode( $error_codes ), $event->error_codes );
		// phpcs:enable WordPress.WP.AlternativeFunctions.json_encode_json_encode
	}

	/**
	 * Test test_save_event_with_wrong_params().
	 *
	 * @return void
	 */
	public function test_save_event_with_wrong_params() {

		$subject = new Events();

		$this->drop_table();

		$subject->save_event( [ 'not a string' ], [] );
	}

	/**
	 * Test get events().
	 *
	 * @return void
	 */
	public function test_get_events() {
		$date = wp_date( 'Y-m-d H:i:s' );

		$expected = [
			'items' => [
				(object) [
					'id'          => '1',
					'source'      => '[]',
					'form_id'     => '0',
					'ip'          => '',
					'user_agent'  => '',
					'uuid'        => '',
					'error_codes' => '["empty"]',
					'date_gmt'    => $date,
				],
				(object) [
					'id'          => '2',
					'source'      => '[]',
					'form_id'     => '0',
					'ip'          => '',
					'user_agent'  => '',
					'uuid'        => '',
					'error_codes' => '[]',
					'date_gmt'    => $date,
				],
			],
			'total' => 2,
		];

		$subject = new Events();

		$this->drop_table();
		$subject::create_table();
		$subject->save_event( 'empty', [ 'empty' ] );
		$subject->save_event( 'success', [] );

		$actual = $subject::get_events();

		// Make sure the dates are the same (no more than 10 sec difference).
		self::assertSame(
			0,
			( strtotime( $actual['items'][0]->date_gmt ) - strtotime( $expected['items'][0]->date_gmt ) ) % 10
		);
		self::assertSame(
			0,
			( strtotime( $actual['items'][1]->date_gmt ) - strtotime( $expected['items'][1]->date_gmt ) ) % 10
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
	public function test_get_forms() {
		$date = wp_date( 'Y-m-d H:i:s' );

		$expected = [
			'items'  =>
				[
					(object) [
						'source'  => '[]',
						'form_id' => '0',
						'served'  => '2',
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
		$subject->save_event( 'empty', [ 'empty' ] );
		$subject->save_event( 'success', [] );

		$actual = $subject::get_forms();

		// Make sure the dates are the same (no more than 10 sec difference).
		self::assertSame(
			0,
			( strtotime( $actual['served'][0]->date_gmt ) - strtotime( $expected['served'][0]->date_gmt ) ) % 10
		);
		self::assertSame(
			0,
			( strtotime( $actual['served'][1]->date_gmt ) - strtotime( $expected['served'][1]->date_gmt ) ) % 10
		);

		$actual['served'][0]->date_gmt = $expected['served'][0]->date_gmt;
		$actual['served'][1]->date_gmt = $expected['served'][1]->date_gmt;

		self::assertEquals( $expected, $actual );
	}

	/**
	 * Test get_where_date_gmt().
	 *
	 * @return void
	 */
	public function test_get_where_date_gmt() {
		$dates = [ '2021-01-01', '2021-01-02' ];

		$expected = "date_gmt BETWEEN '2021-01-01 00:00:00' AND '2021-01-02 23:59:59'";

		$subject = new Events();

		$actual = $subject::get_where_date_gmt( [ 'dates' => $dates ] );

		self::assertEquals( $expected, $actual );

		$actual = $subject::get_where_date_gmt( [ 'dates' => [] ] );

		self::assertEquals( '1=1', $actual );
	}

	/**
	 * Drop the table.
	 *
	 * @return void
	 */
	private function drop_table() {
		global $wpdb;

		$table_name = Events::TABLE_NAME;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}$table_name" );
	}
}
