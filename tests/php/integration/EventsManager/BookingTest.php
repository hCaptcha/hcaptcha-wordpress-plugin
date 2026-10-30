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
use EM_Bookings;
use EM_Event;
use EM_Person;
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
	 * Print the booking form spacing rule.
	 */
	public function test_print_inline_styles(): void {
		ob_start();
		( new Booking() )->print_inline_styles();

		self::assertStringContainsString( '.em-booking-section .h-captcha', ob_get_clean() );
	}

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
		hcaptcha()->settings()->set( 'events_manager_status', [ 'booking' ] );
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
		$event   = Mockery::mock( EM_Event::class );
		$event->shouldReceive( 'get_event_uid' )->andReturn( 123 );
		$booking->shouldReceive( 'get_event' )->once()->andReturn( $event );
		$booking->shouldReceive( 'get_person' )->once()->andReturn( (object) [ 'user_email' => '' ] );
		$booking->shouldReceive( 'add_error' )->once()->with( 'Anti-spam check failed.' );

		self::assertFalse( ( new Booking() )->verify( true, $booking ) );
	}

	/**
	 * A booking without an event ID still checks the signed widget identity.
	 */
	public function test_missing_event_id_rejects_foreign_widget(): void {
		$this->prepare_verify_post( 'hcaptcha_events_manager_nonce', 'hcaptcha_events_manager' );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'events-manager/events-manager.php' ],
				'form_id' => 123,
			]
		);

		$event           = new EM_Event();
		$event->event_id = 0;
		$booking         = Mockery::mock( EM_Booking::class );
		$booking->shouldReceive( 'get_event' )->once()->andReturn( $event );
		$booking->shouldReceive( 'get_person' )->once()->andReturn( (object) [ 'user_email' => '' ] );
		$booking->shouldReceive( 'add_error' )->once()->with( hcap_get_error_messages()['bad-signature'] );

		self::assertFalse( ( new Booking() )->verify( true, $booking ) );
	}

	/**
	 * Validate the resolved booking recipient rather than posted aliases.
	 *
	 * @dataProvider dp_test_native_email_policy
	 *
	 * @param string $email   Native recipient email.
	 * @param bool   $blocked Whether disposal policy rejects it.
	 *
	 * @noinspection PhpUndefinedMethodInspection
	 */
	public function test_native_email_policy( string $email, bool $blocked ): void {
		add_filter(
			'hcap_disposable_email_domains',
			static function (): array {
				return [ 'disposable.example' => true ];
			}
		);
		$_POST['user_email'] = 'safe@example.com';
		$this->prepare_verify_post( 'hcaptcha_events_manager_nonce', 'hcaptcha_events_manager' );
		hcaptcha()->settings()->set( 'disposable_email', [ 'on' ] );
		hcaptcha()->settings()->set( 'events_manager_status', [ 'booking' ] );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );
		self::assertTrue( hcaptcha()->settings()->is_on( 'disposable_email' ) );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'events-manager/events-manager.php' ],
				'form_id' => 123,
			]
		);

		$booking         = Mockery::mock( EM_Booking::class )->makePartial();
		$event           = new EM_Event();
		$event->event_id = 123;
		$booking->shouldReceive( 'get_event' )->once()->andReturn( $event );
		$booking->person       = new EM_Person( 0 );
		$booking->person_id    = 0;
		$booking->booking_meta = [ 'registration' => [ 'user_email' => $email ] ];

		$result = ( new Booking() )->verify( true, $booking );

		self::assertSame( ! $blocked, $result );
		self::assertSame( $blocked ? [ hcap_get_error_messages()['disposable-email'] ] : [], $booking->get_errors() );
	}

	/**
	 * Resolved recipient controls.
	 *
	 * @return array
	 */
	public function dp_test_native_email_policy(): array {
		return [
			'disposable recipient' => [ 'user@disposable.example', true ],
			'permanent recipient'  => [ 'safe@example.com', false ],
			'email-free booking'   => [ '', false ],
		];
	}

	/**
	 * A logged-in booking keeps its resolved person email even if the request carries another address.
	 *
	 * @param string $person_email Resolved booking person's address.
	 * @param string $request_email Unrelated native request address.
	 * @param bool   $blocked       Whether the resolved address is disposable.
	 * @dataProvider dp_logged_in_booking_email
	 */
	public function test_logged_in_booking_uses_person_email( string $person_email, string $request_email, bool $blocked ): void {
		self::assertFalse( EM_Bookings::is_registration_forced() );
		$user_id = $this->factory()->user->create( [ 'user_email' => $person_email ] );
		wp_set_current_user( $user_id );
		self::assertSame( $person_email, get_userdata( $user_id )->user_email );
		add_filter(
			'hcap_disposable_email_domains',
			static function (): array {
				return [ 'disposable.example' => true ];
			}
		);
		$this->prepare_verify_post( 'hcaptcha_events_manager_nonce', 'hcaptcha_events_manager' );
		hcaptcha()->settings()->set( 'disposable_email', [ 'on' ] );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is a decoy field in a native booking test.
		$_REQUEST['user_email']                = $request_email;
		$_POST['user_email']                   = $request_email;
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'events-manager/events-manager.php' ],
				'form_id' => 123,
			]
		);
		self::assertTrue( hcaptcha()->settings()->is_on( 'disposable_email' ) );
		self::assertTrue(
			HCaptcha::is_protection_enabled(
				[
					'source'  => [ 'events-manager/events-manager.php' ],
					'form_id' => 123,
				]
			)
		);

		$event           = new EM_Event();
		$event->event_id = 123;
		$booking         = Mockery::mock( EM_Booking::class );
		$booking->shouldReceive( 'get_event' )->once()->andReturn( $event );
		$booking->shouldReceive( 'get_person' )->once()->andReturn( get_userdata( $user_id ) );
		$booking->shouldReceive( 'add_error' )->times( $blocked ? 1 : 0 );

		self::assertSame( ! $blocked, ( new Booking() )->verify( true, $booking ) );
	}

	/**
	 * Logged-in recipient and decoy request controls.
	 *
	 * @return array
	 */
	public function dp_logged_in_booking_email(): array {
		return [
			'permanent person and disposable decoy' => [ 'reader@example.com', 'user@disposable.example', false ],
			'disposable person and permanent decoy' => [ 'user@disposable.example', 'reader@example.com', true ],
		];
	}
}
