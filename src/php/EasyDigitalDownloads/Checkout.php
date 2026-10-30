<?php
/**
 * The Checkout class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\EasyDigitalDownloads;

use HCaptcha\Helpers\API;
use HCaptcha\Helpers\EntryData;
use HCaptcha\Helpers\HCaptcha;

/**
 * Class Checkout.
 */
class Checkout {

	/**
	 * Script handle.
	 */
	private const HANDLE = 'hcaptcha-easy-digital-downloads';

	/**
	 * Nonce action.
	 */
	private const ACTION = 'hcaptcha_easy_digital_downloads_register';

	/**
	 * Nonce name.
	 */
	private const NONCE = 'hcaptcha_easy_digital_downloads_register_nonce';

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
		add_action( 'edd_purchase_form_before_submit', [ $this, 'add_captcha' ] );
		add_filter( 'edd_errors', [ $this, 'verify' ] );
	}

	/**
	 * Add captcha.
	 *
	 * @return void
	 */
	public function add_captcha(): void {
		$this->enqueue_scripts();

		$args = [
			'action' => self::ACTION,
			'name'   => self::NONCE,
			'id'     => $this->get_expected_id(),
		];

		HCaptcha::form_display( $args );
	}

	/**
	 * Enqueue Easy Digital Downloads script.
	 *
	 * @return void
	 */
	public function enqueue_scripts(): void {
		$min = hcap_min_suffix();

		wp_enqueue_script(
			self::HANDLE,
			HCAPTCHA_URL . "/assets/js/hcaptcha-easy-digital-downloads$min.js",
			[ 'jquery' ],
			HCAPTCHA_VERSION,
			true
		);
	}

	/**
	 * Verify the checkout form.
	 *
	 * @param array|mixed $errors Errors.
	 *
	 * @return array|mixed
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function verify( $errors ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$post_value = isset( $_POST['action'] ) ?
			sanitize_text_field( wp_unslash( $_POST['action'] ) ) :
			'';

		// Native purchase and AJAX dispatch can both omit a POST action field.
		if (
			'edd_process_checkout' !== $post_value &&
			! doing_action( 'edd_purchase' ) &&
			! doing_action( 'wp_ajax_edd_process_checkout' ) &&
			! doing_action( 'wp_ajax_nopriv_edd_process_checkout' )
		) {
			return $errors;
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		// Match the native slashed checkout field and its logged-in fallback.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$email = $_POST['edd_email'] ?? wp_get_current_user()->user_email;
		$email = is_scalar( $email ) ? sanitize_email( (string) $email ) : '';

		$error_message = API::verify_post(
			self::NONCE,
			self::ACTION,
			$this->get_expected_id(),
			EntryData::with_emails( [], [ $email ] )
		);

		if ( null === $error_message ) {
			return $errors;
		}

		$code = array_search( $error_message, hcap_get_error_messages(), true ) ?: 'fail';

		$errors          = $errors ? (array) $errors : [];
		$errors[ $code ] = $error_message;

		// EDD renders checkout notices from its stored messages, not this filtered array.
		if ( function_exists( 'edd_set_error' ) ) {
			edd_set_error( $code, $error_message );
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
			'form_id' => 'checkout',
		];
	}
}
