<?php
/**
 * Base class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\WPDiscuz;

/**
 * Class Base.
 */
abstract class Base {

	/**
	 * Class constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Add hooks.
	 *
	 * @return void
	 */
	protected function init_hooks(): void {
		add_filter(
			'wpdiscuz_recaptcha_site_key',
			static function () {
				// Block output of reCaptcha by wpDiscuz.
				return '';
			}
		);

		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ], 11 );
	}

	/**
	 * Dequeue recaptcha script.
	 *
	 * @return void
	 */
	public function enqueue_scripts(): void {
		wp_dequeue_script( 'wpdiscuz-google-recaptcha' );
		wp_deregister_script( 'wpdiscuz-google-recaptcha' );
	}
}
