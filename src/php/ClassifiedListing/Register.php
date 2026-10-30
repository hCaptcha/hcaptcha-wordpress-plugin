<?php
/**
 * 'Register' class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\ClassifiedListing;

use HCaptcha\Helpers\API;
use HCaptcha\Helpers\EntryData;
use HCaptcha\Helpers\HCaptcha;
use WP_Error;

/**
 * Class Register.
 */
class Register {
	use RefreshTokens;

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
		$this->enqueue_refresh_tokens_script();

		$args = [
			'action' => self::ACTION,
			'name'   => self::NONCE,
			'id'     => $this->get_expected_id(),
		];

		HCaptcha::form_display( $args );
	}

	/**
	 * Verify an account registration form.
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
		// The shared filter also runs while a guest submits a listing. Only the
		// native account registration routes render this hCaptcha widget.
		if ( empty( $post['rtcl-register'] ) && ! doing_action( 'wp_ajax_nopriv_rtcl_registration_request' ) ) {
			return $validation_error;
		}

		$error_message = API::verify_post(
			self::NONCE,
			self::ACTION,
			$this->get_expected_id(),
			EntryData::with_emails( [], [ sanitize_email( $email ) ] )
		);

		if ( null === $error_message ) {
			return $validation_error;
		}

		$code = array_search( $error_message, hcap_get_error_messages(), true ) ?: 'fail';

		return new WP_Error( $code, $error_message, 400 );
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
