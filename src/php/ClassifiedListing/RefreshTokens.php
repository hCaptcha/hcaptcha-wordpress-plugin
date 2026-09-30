<?php
/**
 * The RefreshTokens trait file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\ClassifiedListing;

/**
 * Trait RefreshTokens.
 */
trait RefreshTokens {
	/**
	 * Enqueue the Classified Listing token refresh script.
	 *
	 * @return void
	 */
	private function enqueue_refresh_tokens_script(): void {
		$min = hcap_min_suffix();

		wp_enqueue_script(
			'hcaptcha-classified-listing',
			HCAPTCHA_URL . "/assets/js/hcaptcha-classified-listing$min.js",
			[ 'jquery' ],
			HCAPTCHA_VERSION,
			true
		);
	}
}
