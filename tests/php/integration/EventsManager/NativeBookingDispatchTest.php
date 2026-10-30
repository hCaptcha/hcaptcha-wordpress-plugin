<?php
/**
 * NativeBookingDispatchTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */

namespace HCaptcha\Tests\Integration\EventsManager;

use EM_Booking;
use EM_Event;
use EM_Notices;
use EM_Person;
use EM_Ticket;
use HCaptcha\EventsManager\Booking;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;

/**
 * Test the native guest booking validation and registration boundary.
 *
 * @group events-manager
 * @group events-manager-booking
 */
class NativeBookingDispatchTest extends HCaptchaPluginWPTestCase {

	/**
	 * Plugin relative path.
	 *
	 * @var string
	 */
	protected static $plugin = 'events-manager/events-manager.php';

	/**
	 * Capability map initialized by the native plugin lifecycle.
	 *
	 * @var array
	 */
	private static array $native_capabilities = [];

	/**
	 * Saved event fixture.
	 *
	 * @var EM_Event|null
	 */
	private ?EM_Event $event = null;

	/**
	 * Native global state before the test.
	 *
	 * @var array
	 */
	private array $native_globals = [];

	/**
	 * Temporary native tables created by this test.
	 *
	 * @var string[]
	 */
	private array $native_fixture_tables = [];

	/**
	 * Synthetic guest address supplied to the native controller.
	 *
	 * @var string
	 */
	private string $guest_email = '';

	/**
	 * Request before the test.
	 *
	 * @var array
	 */
	private array $request = [];

	/**
	 * Set up the native booking settings.
	 *
	 * @return void
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function setUp(): void {
		parent::setUp();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Save and restore test request state; native dispatch verifies its own nonce.
		$this->request = $_REQUEST;

		foreach ( [ 'EM_Event', 'EM_Booking', 'EM_Person', 'EM_Ticket', 'EM_Notices', 'EM_Mailer', 'EM_SAVING_EVENT', 'em_temp_user_data', 'em_capabilities_array' ] as $name ) {
			$this->native_globals[ $name ] = [
				'exists' => array_key_exists( $name, $GLOBALS ),
				'value'  => $GLOBALS[ $name ] ?? null,
			];
			unset( $GLOBALS[ $name ] );
		}

		$_REQUEST = [];

		if ( ! self::$native_capabilities ) {
			wp_set_current_user( 0 );
			em_init();
			self::$native_capabilities = $GLOBALS['em_capabilities_array'];
		}

		$GLOBALS['em_capabilities_array'] = self::$native_capabilities;
		$this->prepare_native_tables();

		update_option( 'dbem_rsvp_enabled', true );
		update_option( 'dbem_bookings_anonymous', true );
		update_option( 'dbem_bookings_registration_disable', false );
		update_option( 'dbem_bookings_tickets_orderby', 'ticket_price DESC, ticket_name ASC' );
		update_option( 'dbem_require_location', false );

		hcaptcha()->settings()->set( 'honeypot', 'on' );
		hcaptcha()->settings()->set( 'events_manager_status', [ 'booking' ] );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );
	}

	/**
	 * Restore native request state and remove the event fixture.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		global $wpdb;

		if ( $this->event && $this->event->event_id ) {
			wp_set_current_user( $this->event->event_owner );
			$this->event->delete( true );
		}

		foreach ( array_reverse( $this->native_fixture_tables ) as $table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Remove only tables this fixture created.
			$wpdb->query( 'DROP TEMPORARY TABLE IF EXISTS `' . esc_sql( $table ) . '`' );
		}

		foreach ( $this->native_globals as $name => $state ) {
			if ( $state['exists'] ) {
				$GLOBALS[ $name ] = $state['value'];
			} else {
				unset( $GLOBALS[ $name ] );
			}
		}

		$_REQUEST = $this->request;

		parent::tearDown();
	}

	/**
	 * Verification failures stop guest registration before user insertion.
	 *
	 * @dataProvider dp_test_rejected_booking
	 *
	 * @param string $failure    Verification failure.
	 * @param string $error_code Expected verification error code.
	 *
	 * @return void
	 */
	public function test_rejected_booking_does_not_reach_user_insertion( string $failure, string $error_code ): void {
		$this->prepare_booking_request();
		$checked_emails = [];

		switch ( $failure ) {
			case 'missing-response':
				unset( $_POST['h-captcha-response'] );
				break;
			case 'honeypot':
				$_POST['hcap_hp_test'] = 'bot';
				break;
			case 'email':
				$this->guest_email      = 'guest-' . wp_generate_uuid4() . '@sec007-disposable.test';
				$_REQUEST['user_email'] = $this->guest_email;
				$_POST['user_email']    = $this->guest_email;

				hcaptcha()->settings()->set( 'disposable_email', [ 'on' ] );
				add_filter(
					'hcap_disposable_email_domains',
					static function (): array {
						return [ 'sec007-disposable.test' => true ];
					}
				);
				add_filter(
					'hcap_is_disposable_email',
					static function ( $is_disposable, $email ) use ( &$checked_emails ) {
						$checked_emails[] = $email;

						return $is_disposable;
					},
					PHP_INT_MAX,
					2
				);
				break;
			case 'signature':
				$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = 'invalid-widget-signature';
				break;
		}

		$result = $this->dispatch_booking();
		$error  = hcap_get_error_messages()[ $error_code ];

		self::assertTrue( $result['native_validated'], 'The event and ticket must pass native validation before hCaptcha.' );
		self::assertSame( [], $result['native_errors'] );
		self::assertSame( [ $error ], $result['booking']->get_errors() );
		self::assertSame( 0, $result['user_insert_calls'], 'Rejected verification must stop the controller before wp_insert_user().' );
		self::assertFalse( $result['validated'] );
		self::assertSame( 0, $result['booking_save_calls'] );
		self::assertStringContainsString( $error, $GLOBALS['EM_Notices']->get_errors() );

		if ( 'email' === $failure ) {
			self::assertContains( $this->guest_email, $checked_emails );
			self::assertNotContains( ( new EM_Person( 0 ) )->user_email, $checked_emails );
		}

		$this->assert_no_guest_records();
	}

	/**
	 * Booking verification failures.
	 *
	 * @return array
	 */
	public function dp_test_rejected_booking(): array {
		return [
			'missing CAPTCHA response' => [ 'missing-response', 'empty' ],
			'filled honeypot'          => [ 'honeypot', 'spam' ],
			'rejected native email'    => [ 'email', 'disposable-email' ],
			'invalid widget signature' => [ 'signature', 'bad-signature' ],
		];
	}

	/**
	 * A valid request reaches the native user insertion boundary.
	 *
	 * @return void
	 */
	public function test_valid_booking_reaches_native_user_insertion(): void {
		$this->prepare_booking_request();

		$result = $this->dispatch_booking();

		self::assertTrue( $result['native_validated'] );
		self::assertTrue( $result['validated'] );
		self::assertSame( [], $result['booking']->get_errors() );
		self::assertSame( 1, $result['user_insert_calls'] );
		self::assertSame( 0, $result['booking_save_calls'], 'The empty-login sentinel must stop registration without saving a booking.' );
		$this->assert_no_guest_records();
	}

	/**
	 * Successful CAPTCHA verification preserves native rejection.
	 *
	 * @return void
	 */
	public function test_successful_verification_preserves_native_false(): void {
		$this->prepare_booking_request();
		$_REQUEST['em_tickets'] = [];

		$result = $this->dispatch_booking();

		self::assertFalse( $result['native_validated'] );
		self::assertFalse( $result['validated'] );
		self::assertNotEmpty( $result['native_errors'] );
		self::assertSame( $result['native_errors'], $result['booking']->get_errors() );
		self::assertSame( 0, $result['user_insert_calls'] );
		self::assertSame( 0, $result['booking_save_calls'] );
		$this->assert_no_guest_records();
	}

	/**
	 * Create missing native tables used by the fixture and its cleanup.
	 *
	 * @return void
	 * @noinspection PhpUndefinedConstantInspection
	 */
	private function prepare_native_tables(): void {
		global $wpdb;

		require_once EM_DIR . '/em-install.php';
		$test_prefix   = $wpdb->prefix;
		$native_prefix = substr( EM_EVENTS_TABLE, 0, -strlen( 'em_events' ) );

		$tables = [
			EM_EVENTS_TABLE                => 'em_create_events_table',
			EM_TIMERANGES_TABLE            => 'em_create_timeslots_table',
			EM_EVENT_TIMESLOTS_TABLE       => 'em_create_event_timeslots_table',
			EM_META_TABLE                  => 'em_create_events_meta_table',
			EM_LOCATIONS_TABLE             => 'em_create_locations_table',
			EM_BOOKINGS_TABLE              => 'em_create_bookings_table',
			EM_BOOKINGS_META_TABLE         => 'em_create_bookings_meta_table',
			EM_TICKETS_TABLE               => 'em_create_tickets_table',
			EM_TICKETS_BOOKINGS_TABLE      => 'em_create_tickets_bookings_table',
			EM_TICKETS_BOOKINGS_META_TABLE => 'em_create_tickets_bookings_meta_table',
		];

		try {
			// Events Manager's constants were defined before the test runner changed the WordPress table prefix.
			$wpdb->set_prefix( $native_prefix );

			foreach ( $tables as $table => $create_table ) {
				// The WordPress test harness creates temporary tables, which SHOW TABLES omits.
				$lookup        = 'SHOW COLUMNS FROM `' . esc_sql( $table ) . '`';
				$errors_hidden = $wpdb->suppress_errors();
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The identifier comes from native plugin table constants above.
				$exists = null !== $wpdb->get_var( $lookup );
				$wpdb->suppress_errors( $errors_hidden );

				if ( ! $exists ) {
					$create_table();
					$this->native_fixture_tables[] = $table;
				}

				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The identifier comes from native plugin table constants above.
				self::assertNotNull( $wpdb->get_var( $lookup ), 'The native fixture table must exist: ' . $table . '. ' . $wpdb->last_error );
			}
		} finally {
			$wpdb->set_prefix( $test_prefix );
		}
	}

	/**
	 * Save a native event and ticket and prepare a protected guest request.
	 *
	 * @return void
	 */
	private function prepare_booking_request(): void {
		wp_set_current_user( $this->factory()->user->create( [ 'role' => 'administrator' ] ) );

		foreach ( [ 'edit_events', 'edit_others_events', 'publish_events', 'manage_bookings', 'manage_others_bookings', 'delete_events', 'delete_others_events' ] as $capability ) {
			wp_get_current_user()->add_cap( $capability );
		}

		$date = gmdate( 'Y-m-d', time() + WEEK_IN_SECONDS );

		$this->event                   = new EM_Event();
		$this->event->event_name       = 'hCaptcha guest booking regression';
		$this->event->event_start_date = $date;
		$this->event->event_end_date   = $date;
		$this->event->event_start_time = '12:00:00';
		$this->event->event_end_time   = '13:00:00';
		$this->event->event_rsvp       = 1;
		$this->event->event_spaces     = 10;
		$this->event->location_id      = 0;
		$this->event->force_status     = 'publish';

		$ticket                              = new EM_Ticket();
		$ticket->event                       = $this->event;
		$ticket->ticket_status               = 1;
		$ticket->ticket_spaces               = 10;
		$this->event->get_tickets()->tickets = [ $ticket ];

		self::assertTrue( $this->event->save(), implode( ' ', $this->event->get_errors() ) );
		self::assertNotEmpty( $ticket->ticket_id );

		$event_id = $this->event->event_id;
		wp_cache_delete( $event_id, 'em_events' );
		$this->event = new EM_Event( $event_id );

		wp_set_current_user( 0 );
		unset( $GLOBALS['EM_Event'], $GLOBALS['EM_Booking'], $GLOBALS['EM_Person'], $GLOBALS['EM_Ticket'] );
		$GLOBALS['EM_Notices'] = new EM_Notices();
		$this->guest_email     = 'guest-' . wp_generate_uuid4() . '@example.com';

		$_REQUEST = [
			'action'     => 'booking_add',
			'_wpnonce'   => wp_create_nonce( 'booking_add' ),
			'event_id'   => $event_id,
			'em_tickets' => [ $ticket->ticket_id => [ 'spaces' => 1 ] ],
			'user_email' => $this->guest_email,
			'user_name'  => 'Guest Booker',
		];
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Native controller validates the test nonce during dispatch.
		$_POST = $_REQUEST;

		$this->prepare_verify_post( 'hcaptcha_events_manager_nonce', 'hcaptcha_events_manager' );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'events-manager/events-manager.php' ],
				'form_id' => $event_id,
			]
		);
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );
	}

	/**
	 * Run the actual native controller with an account-creation sentinel.
	 *
	 * @return array
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	private function dispatch_booking(): array {
		$native_validated   = null;
		$native_errors      = null;
		$validated          = null;
		$booking            = null;
		$user_insert_calls  = 0;
		$booking_save_calls = 0;

		$before_verification = static function ( $value, EM_Booking $subject ) use ( &$native_validated, &$native_errors ) {
			$native_validated = $value;
			$native_errors    = $subject->get_errors();

			return $value;
		};
		$booking_action      = static function ( EM_Event $event, EM_Booking $subject, $value ) use ( &$booking, &$validated ): void {
			$booking   = $subject;
			$validated = $value;
		};
		$user_insert         = static function () use ( &$user_insert_calls ): string {
			++$user_insert_calls;

			// Core rejects the empty login before insertion, so no account or notification is created.
			return '';
		};
		$booking_save        = static function () use ( &$booking_save_calls ): void {
			++$booking_save_calls;
		};

		add_filter( 'em_booking_validate', $before_verification, 1, 2 );
		add_action( 'em_booking_add', $booking_action, 10, 3 );
		add_filter( 'pre_user_login', $user_insert );
		add_action( 'em_booking_save_pre', $booking_save );
		new Booking();

		$buffer_level = ob_get_level();
		ob_start();

		try {
			em_init_actions_start();
		} finally {
			while ( ob_get_level() > $buffer_level ) {
				ob_end_clean();
			}

			remove_filter( 'em_booking_validate', $before_verification, 1 );
			remove_action( 'em_booking_add', $booking_action );
			remove_filter( 'pre_user_login', $user_insert );
			remove_action( 'em_booking_save_pre', $booking_save );
		}

		self::assertInstanceOf( EM_Booking::class, $booking );

		return compact( 'native_validated', 'native_errors', 'validated', 'booking', 'user_insert_calls', 'booking_save_calls' );
	}

	/**
	 * Assert the guest request persisted neither an account nor a booking.
	 *
	 * @return void
	 * @noinspection PhpUndefinedConstantInspection
	 */
	private function assert_no_guest_records(): void {
		global $wpdb;

		self::assertFalse( email_exists( $this->guest_email ) );
		self::assertSame(
			'0',
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- EM_BOOKINGS_TABLE is defined by the installed native plugin.
			$wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . EM_BOOKINGS_TABLE . ' WHERE event_id = %d', $this->event->event_id ) )
		);
	}
}
