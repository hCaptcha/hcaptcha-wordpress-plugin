<?php
/**
 * Common class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\UsersWP;

/**
 * Common methods for UsersWP classes.
 */
class Common {

	/**
	 * Enqueue scripts.
	 *
	 * @return void
	 */
	public static function enqueue_scripts(): void {
		$min = hcap_min_suffix();

		wp_enqueue_script(
			'hcaptcha-users-wp',
			HCAPTCHA_URL . "/assets/js/hcaptcha-users-wp$min.js",
			[ 'jquery', 'hcaptcha' ],
			HCAPTCHA_VERSION,
			true
		);
	}
}
