<?php
/**
 * 'Register' class file.
 *
 * @package hcaptcha-wp
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */

namespace HCaptcha\LearnPress;

use HCaptcha\Helpers\API;
use HCaptcha\Helpers\EntryData;
use HCaptcha\Helpers\HCaptcha;
use LP_Request;

/**
 * Class Register
 */
class Register {

	/**
	 * Nonce action.
	 */
	private const ACTION = 'hcaptcha_learn_press_register';

	/**
	 * Nonce name.
	 */
	private const NONCE = 'hcaptcha_learn_press_register_nonce';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		add_action( 'register_form', [ $this, 'add_hcaptcha' ] );
		add_action( 'wp_loaded', [ $this, 'verify' ], 0 );
	}

	/**
	 * Add hCaptcha.
	 *
	 * @return void
	 */
	public function add_hcaptcha(): void {
		if (
			! did_action( 'learn-press/after-form-register-fields' ) ||
			doing_action( 'learn-press/after-checkout-form' )
		) {
			return;
		}

		$args = [
			'action' => self::ACTION,
			'name'   => self::NONCE,
			'id'     => $this->get_expected_id(),
		];

		HCaptcha::form_display( $args );
	}

	/**
	 * Verify hCaptcha.
	 *
	 * @return void
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function verify(): void {
		// Use the same nonce source and normalization as native registration.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Type check only; LearnPress unslashes and verifies the nonce.
		$nonce = $_REQUEST['learn-press-register-nonce'] ?? '';

		if ( ! is_string( $nonce ) || ! LP_Request::verify_nonce( 'learn-press-register' ) ) {
			// Not submitted from the LearnPress Register form.
			return;
		}

		// Match native registration's text normalization followed by email sanitation.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$email = isset( $_POST['reg_email'] ) && is_scalar( $_POST['reg_email'] ) ? sanitize_email( sanitize_text_field( wp_unslash( (string) $_POST['reg_email'] ) ) ) : '';

		$error_message = API::verify_post(
			self::NONCE,
			self::ACTION,
			$this->get_expected_id(),
			EntryData::with_emails( [], [ $email ] )
		);

		if ( null !== $error_message ) {
			$message_data = [
				'status'  => 'error',
				'content' => $error_message,
			];

			learn_press_set_message( $message_data );
			remove_action( 'wp_loaded', [ 'LP_Forms_Handler', 'init' ] );
		}
	}

	/**
	 * Get the expected form identity.
	 *
	 * @return array
	 */
	private function get_expected_id(): array {
		return [
			'source'  => HCaptcha::get_class_source( __CLASS__ ),
			'form_id' => 'register',
		];
	}
}
