<?php
/**
 * 'Register' class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\MemberPress;

use HCaptcha\Helpers\API;
use HCaptcha\Helpers\EntryData;
use HCaptcha\Helpers\HCaptcha;

/**
 * Class Register
 */
class Register {
	/**
	 * Nonce action.
	 */
	private const ACTION = 'hcaptcha_memberpress_register';

	/**
	 * Nonce name.
	 */
	private const NONCE = 'hcaptcha_memberpress_register_nonce';

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
		add_action( 'mepr-checkout-before-submit', [ $this, 'add_captcha' ] );
		add_filter( 'mepr-validate-signup', [ $this, 'verify' ] );
	}

	/**
	 * Add hCaptcha to the Register form.
	 *
	 * @return void
	 */
	public function add_captcha(): void {
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
	 * @param array|mixed $errors Errors.
	 *
	 * @return array|mixed
	 */
	public function verify( $errors ) {
		// Existing members keep their stored email; new users use the native slashed field.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$email = is_user_logged_in() ? wp_get_current_user()->user_email : ( $_POST['user_email'] ?? '' );
		$email = is_scalar( $email ) ? sanitize_email( (string) $email ) : '';

		$error_message = API::verify_post(
			self::NONCE,
			self::ACTION,
			$this->get_expected_id(),
			EntryData::with_emails( [], [ $email ] )
		);

		if ( null !== $error_message ) {
			$errors[] = $error_message;
		}

		return $errors;
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
