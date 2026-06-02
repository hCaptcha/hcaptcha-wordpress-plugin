<?php
/**
 * Base trait file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\EssentialAddons;

use HCaptcha\Helpers\API;
use HCaptcha\Helpers\Pages;

/**
 * Base trait.
 */
trait Base {
	/**
	 * Print hCaptcha script on the edit page.
	 *
	 * @param bool|mixed $status Current print status.
	 *
	 * @return bool
	 */
	public function print_hcaptcha_scripts( $status ): bool {
		if ( ! Pages::is_elementor_preview_page() ) {
			return (bool) $status;
		}

		return true;
	}

	/**
	 * Enqueue Essential Addons script.
	 *
	 * @return void
	 */
	public function enqueue_scripts(): void {
		if ( ! hcaptcha()->form_shown ) {
			return;
		}

		$min = hcap_min_suffix();

		wp_enqueue_script(
			'hcaptcha-essential-addons',
			HCAPTCHA_URL . "/assets/js/hcaptcha-essential-addons$min.js",
			[ 'jquery', 'wp-hooks' ],
			HCAPTCHA_VERSION,
			true
		);
	}

	/**
	 * Verify hCaptcha.
	 *
	 * @return void
	 */
	private function base_verify(): void {
		$error_message = API::verify(
			[
				'nonce_name'   => self::NONCE,
				'nonce_action' => self::ACTION,
				'expected_id'  => $this->get_expected_id(),
			]
		);

		if ( null === $error_message ) {
			return;
		}

		if ( wp_doing_ajax() ) {
			wp_send_json_error( $error_message );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$widget_id = isset( $_POST['widget_id'] ) ? sanitize_text_field( wp_unslash( $_POST['widget_id'] ) ) : 0;

		setcookie( 'eael_login_error_' . $widget_id, $error_message );

		if ( isset( $_SERVER['HTTP_REFERER'] ) ) {
			wp_safe_redirect( wp_unslash( $_SERVER['HTTP_REFERER'] ) );

			$this->exit();
		}
	}

	/**
	 * Wrapper for exit(). Used for tests.
	 *
	 * @return void
	 */
	protected function exit(): void {
		// @codeCoverageIgnoreStart
		exit();
		// @codeCoverageIgnoreEnd
	}

	/**
	 * Get expected hCaptcha widget id.
	 *
	 * @return array
	 */
	abstract protected function get_expected_id(): array;
}
