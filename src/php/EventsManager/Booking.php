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
use EM_Event;
use HCaptcha\Helpers\API;
use HCaptcha\Helpers\HCaptcha;

/**
 * Class Booking.
 */
class Booking {

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
		$args = [
			'action' => self::ACTION,
			'name'   => self::NONCE,
			'id'     => [
				'source'  => HCaptcha::get_class_source( __CLASS__ ),
				'form_id' => $event->event_id,
			],
		];

		?>
		<div class="em-booking-section">
			<?php HCaptcha::form_display( $args ); ?>
		</div>
		<?php
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
		$error_message = API::verify_post( self::NONCE, self::ACTION );

		if ( null === $error_message ) {
			return $validated;
		}

		$booking->add_error( $error_message );

		return $validated;
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
