<?php
/**
 * File of the Booking class.
 *
 * @package hcaptcha-wp
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */

namespace HCaptcha\EventsManager;

use EM_Booking;
use EM_Bookings;
use EM_Event;
use HCaptcha\Helpers\API;
use HCaptcha\Helpers\EntryData;
use HCaptcha\Helpers\HCaptcha;

/**
 * Class Booking.
 */
class Booking {

	/**
	 * Script handle.
	 */
	private const HANDLE = 'hcaptcha-events-manager';

	/**
	 * Nonce action.
	 */
	private const ACTION = 'hcaptcha_events_manager';

	/**
	 * Nonce name.
	 */
	private const NONCE = 'hcaptcha_events_manager_nonce';

	/**
	 * Form constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Add hooks.
	 *
	 * @return void
	 */
	public function init_hooks(): void {
		add_action( 'em_booking_form_before_buttons', [ $this, 'add_hcaptcha' ] );
		add_filter( 'em_booking_validate', [ $this, 'verify' ], 10, 2 );
		add_action( 'wp_head', [ $this, 'print_inline_styles' ], 20 );
	}

	/**
	 * Add hCaptcha.
	 *
	 * @param EM_Event $event Event.
	 *
	 * @return void
	 */
	public function add_hcaptcha( EM_Event $event ): void {
		$this->enqueue_scripts();

		$args = [
			'action' => self::ACTION,
			'name'   => self::NONCE,
			'id'     => $this->get_id( $event->event_id ),
		];

		?>
		<div class="em-booking-section">
			<?php HCaptcha::form_display( $args ); ?>
		</div>
		<?php
	}

	/**
	 * Enqueue Events Manager script.
	 *
	 * @return void
	 */
	public function enqueue_scripts(): void {
		$min = hcap_min_suffix();

		wp_enqueue_script(
			self::HANDLE,
			HCAPTCHA_URL . "/assets/js/hcaptcha-events-manager$min.js",
			[ 'jquery' ],
			HCAPTCHA_VERSION,
			true
		);
	}

	/**
	 * Verify hCaptcha.
	 *
	 * @param bool|mixed $validated Validated.
	 * @param EM_Booking $booking   Booking.
	 *
	 * @return bool
	 */
	public function verify( $validated, EM_Booking $booking ): bool {
		$event       = $booking->get_event();
		$expected_id = $this->get_id( $event instanceof EM_Event ? $event->event_id : 0 );
		$person      = $booking->get_person();
		$email       = $person->user_email ?? '';

		// Guest registration reads this native field after booking validation, while get_person() still has a placeholder.
		if ( ! is_user_logged_in() || EM_Bookings::is_registration_forced() ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Native booking controller verifies its nonce; the exact address is unslashed below.
			$native_email = $_REQUEST['user_email'] ?? null;

			if ( is_string( $native_email ) ) {
				$email = trim( wp_unslash( $native_email ) );
			}
		}

		$error_message = API::verify_post(
			self::NONCE,
			self::ACTION,
			$expected_id,
			EntryData::with_emails( [], [ $email ] )
		);

		if ( null === $error_message ) {
			return $validated;
		}

		$booking->add_error( $error_message );

		return false;
	}

	/**
	 * Get the widget identity for an event.
	 *
	 * @param int|string $event_id Event ID.
	 *
	 * @return array
	 */
	private function get_id( $event_id ): array {
		return [
			'source'  => HCaptcha::get_class_source( __CLASS__ ),
			'form_id' => $event_id,
		];
	}

	/**
	 * Print inline styles.
	 *
	 * @return void
	 * @noinspection CssUnusedSymbol
	 */
	public function print_inline_styles(): void {
		/* language=CSS */
		$css = '
	.em-booking-section .h-captcha {
		margin-bottom: 0;
	}
';

		HCaptcha::css_display( $css );
	}
}
