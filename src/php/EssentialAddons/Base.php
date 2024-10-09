<?php
/**
 * Base trait file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\EssentialAddons;

use HCaptcha\Helpers\Pages;
use HCaptcha\Main;

/**
 * Base trait.
 */
trait Base {
	/**
	 * Enqueue admin scripts.
	 *
	 * @return void
	 */
	public function enqueue_scripts(): void {
		if ( ! Pages::is_elementor_preview_page() ) {
			return;
		}

		$min = hcap_min_suffix();

		wp_enqueue_script(
			'admin-essential-addons',
			HCAPTCHA_URL . "/assets/js/admin-essential-addons$min.js",
			[ Main::HANDLE ],
			HCAPTCHA_VERSION,
			true
		);
	}

	/**
	 * Print hCaptcha script on edit page.
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
	 * Verify hCaptcha.
	 *
	 * @return void
	 */
	private function base_verify(): void {
		$error_message = hcaptcha_verify_post(
			self::NONCE,
			self::ACTION
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
}
