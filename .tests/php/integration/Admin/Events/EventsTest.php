<?php
/**
 * EventsTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\Admin\Events;

use HCaptcha\Admin\Events\Events;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;

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

		$subject = new Events();

		$subject->save_event( $result, $error_codes );

		// Query the database to check if the event was saved.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$event = $wpdb->get_row( "SELECT * FROM {$wpdb->prefix}hcaptcha_events ORDER BY id DESC LIMIT 1" );

		// Check that the event data matches the test data.
		// phpcs:disable WordPress.WP.AlternativeFunctions.json_encode_json_encode
		$this->assertEquals( json_encode( $info['id']['source'] ), $event->source );
		$this->assertEquals( $info['id']['form_id'], $event->form_id );
		$this->assertEquals( '', $event->ip );
		$this->assertEquals( '', $event->user_agent );
		$this->assertEquals( '', $event->uuid );
		$this->assertEquals( json_encode( $error_codes ), $event->error_codes );
		// phpcs:enable WordPress.WP.AlternativeFunctions.json_encode_json_encode
	}
}
