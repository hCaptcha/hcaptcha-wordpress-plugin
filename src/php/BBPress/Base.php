<?php
/**
 * Base class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\BBPress;

/**
 * Class Base.
 */
abstract class Base {

	/**
	 * Base constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	private function init_hooks() {
		add_action( static::ADD_CAPTCHA_HOOK, [ $this, 'add_captcha' ] );
		add_action( static::VERIFY_HOOK, [ $this, 'verify' ] );
	}

	/**
	 * Add captcha to the form.
	 */
	public function add_captcha() {
		hcap_form_display();
		wp_nonce_field( static::ACTION, static::NAME );
	}


	/**
	 * Verify captcha.
	 *
	 * @return bool
	 */
	public function verify() {
		$error_message = hcaptcha_get_verify_message( static::NAME, static::ACTION );

		if ( null !== $error_message ) {
			bbp_add_error( 'hcap_error', $error_message );

			return false;
		}

		return true;
	}
}
