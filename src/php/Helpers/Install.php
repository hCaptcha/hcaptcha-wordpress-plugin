<?php
/**
 * `Install` class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Helpers;

use HCaptcha\Admin\Events\Events;

/**
 * Class Install.
 */
class Install {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->init();
	}

	/**
	 * Init class.
	 *
	 * @return void
	 */
	private function init(): void {
		$this->init_hooks();
	}

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		register_activation_hook( HCAPTCHA_FILE, [ $this, 'activation_hook' ] );
	}

	/**
	 * Activation hook.
	 *
	 * @return void
	 */
	public function activation_hook() {
		Events::create_table();

		/**
		 * We can call this method from different places.
		 * During activation, the settings are not yet initialized.
		 */
		if ( ! hcaptcha()->settings() ) {
			hcaptcha()->init_hooks();
		}

		HCaptcha::save_license_level();
	}
}
