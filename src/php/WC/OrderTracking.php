<?php
/**
 * OrderTracking class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\WC;

use HCaptcha\Helpers\API;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Helpers\EntryData;

/**
 * Class OrderTracking
 */
class OrderTracking {
	/**
	 * Marks the widget emitted by the native form hook.
	 */
	private const FORM_MARKER = '<!-- hcaptcha-wc-order-tracking -->';

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
		add_filter( 'do_shortcode_tag', [ $this, 'do_shortcode_tag' ], 10, 4 );
		add_action( 'woocommerce_order_tracking_form', [ $this, 'display_form' ] );
		add_filter( 'woocommerce_shortcode_order_tracking_order_id', [ $this, 'verify_tracking_request' ] );
		add_filter( 'hcap_auto_verify_entry', [ $this, 'add_email' ] );
	}

	/**
	 * Display the widget in native order tracking forms, including direct renders.
	 *
	 * @return void
	 */
	public function display_form(): void {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HCaptcha::form() escapes its generated markup.
		echo $this->get_hcap_form();
	}

	/**
	 * Verify the native tracking request before WooCommerce looks up the order.
	 *
	 * @param int|string $order_id Submitted order ID.
	 *
	 * @return int|string
	 * @noinspection ForgottenDebugOutputInspection
	 * @noinspection PhpUnreachableStatementInspection
	 */
	public function verify_tracking_request( $order_id ) {
		$expected_id = $this->get_expected_id();
		$entry       = $this->add_email( [ 'expected_id' => $expected_id ] );
		$message     = API::verify_post( HCAPTCHA_NONCE, HCAPTCHA_ACTION, $expected_id, $entry );

		if ( null !== $message ) {
			wp_die(
				esc_html( $message ),
				'hCaptcha',
				[
					'back_link' => true,
					'response'  => 403,
				]
			);

			// Keep the native lookup closed if a custom die handler returns.
			return 0; // For testing purposes.
		}

		return $order_id;
	}

	/**
	 * Get the server-selected identity of the native tracking form.
	 *
	 * @return array
	 */
	private function get_expected_id(): array {
		return [
			'source'  => HCaptcha::get_class_source( __CLASS__ ),
			'form_id' => 'order_tracking',
		];
	}

	/**
	 * Add the native tracking email only to the server-registered tracking form.
	 *
	 * @param array $entry Automatic verification entry.
	 *
	 * @return array
	 */
	public function add_email( array $entry ): array {
		$id = (array) ( $entry['expected_id'] ?? [] );

		if (
			'order_tracking' !== ( $id['form_id'] ?? '' ) ||
			HCaptcha::get_class_source( __CLASS__ ) !== ( $id['source'] ?? [] )
		) {
			return $entry;
		}

		// The auto verifier checks its nonce and route; WooCommerce checks the tracking nonce.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$email = isset( $_REQUEST['order_email'] ) && is_string( $_REQUEST['order_email'] ) ? sanitize_email( wp_unslash( $_REQUEST['order_email'] ) ) : '';

		return EntryData::with_emails( $entry, [ $email ] );
	}

	/**
	 * Filters the output created by a shortcode callback.
	 *
	 * @param string|mixed $output Shortcode output.
	 * @param string       $tag    Shortcode name.
	 * @param array|string $attr   Shortcode attributes array or empty string.
	 * @param array        $m      Regular expression match array.
	 *
	 * @return string|mixed
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function do_shortcode_tag( $output, string $tag, $attr, array $m ) {
		if ( 'woocommerce_order_tracking' !== $tag ) {
			return $output;
		}

		if ( false !== strpos( (string) $output, self::FORM_MARKER ) ) {
			return $output;
		}

		return (string) preg_replace(
			'/(<p class="form-row"><button type="submit"|<p class="form-actions">[\S\s]*?<button type="submit")/i',
			$this->get_hcap_form() . '$1',
			(string) $output,
			1
		);
	}

	/**
	 * Get the tracking widget markup for native and fallback placement.
	 *
	 * @return string
	 */
	private function get_hcap_form(): string {
		$args = [
			'action' => HCAPTCHA_ACTION,
			'name'   => HCAPTCHA_NONCE,
			'auto'   => true,
			'id'     => $this->get_expected_id(),
		];

		return (
			self::FORM_MARKER .
			'<div class="form-row"  style="margin-top: 2rem;">' .
			HCaptcha::form( $args ) .
			'</div>'
		);
	}
}
