<?php
/**
 * Register class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\ClassifiedListing;

use HCaptcha\Helpers\HCaptcha;
use WP_Error;

/**
 * Class Register.
 */
class Register {

	/**
	 * Nonce action.
	 */
	private const ACTION = 'hcaptcha_classified_listing_register';

	/**
	 * Nonce name.
	 */
	private const NONCE = 'hcaptcha_classified_listing_register_nonce';

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
	protected function init_hooks(): void {
		add_action( 'rtcl_register_form', [ $this, 'add_captcha' ] );
		add_filter( 'rtcl_process_registration_errors', [ $this, 'verify' ], 10, 5 );
	}

	/**
	 * Add captcha.
	 *
	 * @return void
	 */
	public function add_captcha(): void {
		$args = [
			'action' => self::ACTION,
			'name'   => self::NONCE,
			'id'     => [
				'source'  => HCaptcha::get_class_source( __CLASS__ ),
				'form_id' => 'register',
			],
		];

		HCaptcha::form_display( $args );
	}

	/**
	 * Verify login form.
	 *
	 * @param WP_Error|mixed $validation_error Validation error.
	 * @param string         $email            Email.
	 * @param string         $username         Username.
	 * @param string         $password         Password.
	 * @param array          $post             $_POST array.
	 *
	 * @return WP_Error|mixed
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function verify( $validation_error, string $email, string $username, string $password, array $post ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$rtcl_register = isset( $_POST['rtcl-register'] ) ?
			sanitize_text_field( wp_unslash( $_POST['rtcl-register'] ) ) :
			'';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( 'Register' !== $rtcl_register ) {
			return $validation_error;
		}

		$error_message = hcaptcha_verify_post(
			self::NONCE,
			self::ACTION
		);

		if ( null === $error_message ) {
			return $validation_error;
		}

		$code = array_search( $error_message, hcap_get_error_messages(), true ) ?: 'fail';

		return new WP_Error( $code, $error_message, 400 );
	}
}
