<?php
/**
 * WooCommerce Germanized class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\WCGermanized;

use HCaptcha\Helpers\API;
use HCaptcha\Helpers\EntryData;
use HCaptcha\Helpers\HCaptcha;

/**
 * Class Form.
 */
class ReturnRequest {
	/**
	 * Class constructor.
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
		add_action( 'woocommerce_shiptastic_return_request_form', [ $this, 'before_submit_button' ] );
		add_action( 'woocommerce_shiptastic_return_request_form_end', [ $this, 'after_submit_button' ] );
		add_action( 'wp_loaded', [ $this, 'verify' ] );
	}

	/**
	 * Before Return Request Form submit button.
	 *
	 * @return void
	 */
	public function before_submit_button(): void {
		ob_start();
	}

	/**
	 * After Return Request Form submit button.
	 *
	 * @return void
	 */
	public function after_submit_button(): void {
		$output = ob_get_clean();

		$args = [
			'id' => $this->get_expected_id(),
		];

		// Find the last $search string and insert hcaptcha before it.
		$search  = '<button type="submit"';
		$replace =
			"\n" .
			HCaptcha::form( $args ) .
			"\n" .
			$search;

		$output = str_replace(
			$search,
			$replace,
			$output
		);

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $output;
	}

	/**
	 * Verify captcha.
	 *
	 * @return void
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function verify(): void {
		// Nonce is checked by Germanized.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_POST['return_request'] ) ) {
			return;
		}

		// Match the address selected by the native return-request handler.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$email = isset( $_POST['email'] ) && is_scalar( $_POST['email'] ) ? sanitize_email( wp_unslash( (string) $_POST['email'] ) ) : '';

		$error_message = API::verify(
			EntryData::with_emails(
				[
					'expected_id' => $this->get_expected_id(),
					'data'        => EntryData::from_post( [] ),
				],
				[ $email ]
			)
		);

		if ( null !== $error_message ) {
			wc_add_notice( $error_message, 'error' );

			// Stop form processing.
			unset( $_POST['return_request'] );
		}
	}

	/**
	 * Get the expected form identity.
	 *
	 * @return array
	 */
	private function get_expected_id(): array {
		return [
			'source'  => HCaptcha::get_class_source( self::class ),
			'form_id' => 'return_request',
		];
	}
}
