<?php
/**
 * BookingTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */

namespace HCaptcha\Tests\Integration\EventsManager;

use EM_Booking;
use EM_Event;
use HCaptcha\EventsManager\Booking;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use Mockery;

/**
 * Test Booking class.
 *
 * @group events-manager
 * @group events-manager-booking
 */
class BookingTest extends HCaptchaPluginWPTestCase {

	/**
	 * Plugin relative path.
	 *
	 * @var string
	 */
	protected static $plugin = 'events-manager/events-manager.php';

	/**
	 * Set up the test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		hcaptcha()->settings()->set( 'honeypot', 'on' );
		hcaptcha()->settings()->set( 'set_min_submit_time', 'on' );
		hcaptcha()->settings()->set( 'events_manager_status', 'booking' );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );
	}

	/**
	 * Test the booking form contains honeypot fields.
	 *
	 * @return void
	 */
	public function test_add_hcaptcha(): void {
		$event = Mockery::mock( EM_Event::class );
		$event->shouldReceive( 'get_event_uid' )->andReturn( 123 );
		$subject = new Booking();

		ob_start();
		$subject->add_hcaptcha( $event );
		$output = (string) ob_get_clean();
		$id     = [
			'source'  => [ 'events-manager/events-manager.php' ],
			'form_id' => 123,
		];

		self::assertStringContainsString( 'h-captcha', $output );
		self::assertStringContainsString( HCaptcha::widget_id_value( $id ), $output );
		self::assertStringContainsString( 'name="hcap_hp_test"', $output );
		self::assertStringContainsString( 'name="hcap_hp_sig"', $output );
		self::assertTrue( wp_script_is( 'hcaptcha-events-manager' ) );
	}

	/**
	 * Test a filled honeypot blocks booking validation.
	 *
	 * @return void
	 */
	public function test_filled_honeypot_is_rejected(): void {
		$this->prepare_verify_post( 'hcaptcha_events_manager_nonce', 'hcaptcha_events_manager' );

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'events-manager/events-manager.php' ],
				'form_id' => 123,
			]
		);
		$_POST['hcap_hp_test']                 = 'bot';

		$booking = Mockery::mock( EM_Booking::class );
		$booking->shouldReceive( 'add_error' )->once()->with( 'Anti-spam check failed.' );

		self::assertTrue( ( new Booking() )->verify( true, $booking ) );
	}
}
