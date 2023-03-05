<?php
/**
 * Base class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\SupportCandy;

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
		add_action( 'wp_ajax_' . static::VERIFY_HOOK, [ $this, 'verify' ], 9 );
		add_action( 'wp_ajax_nopriv_' . static::VERIFY_HOOK, [ $this, 'verify' ], 9 );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
	}

	/**
	 * Add captcha to the form.
	 */
	public function add_captcha() {
		hcap_form_display( static::ACTION, static::NAME );
	}

	/**
	 * Verify captcha.
	 */
	public function verify() {
		$error_message = hcaptcha_get_verify_message(
			static::NAME,
			static::ACTION
		);

		if ( null !== $error_message ) {
			wp_send_json_error( $error_message, 400 );
		}
	}

	/**
	 * Enqueue Support Candy script.
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		$min = hcap_min_suffix();

		wp_enqueue_script(
			'hcaptcha-support-candy',
			HCAPTCHA_URL . "/assets/js/hcaptcha-support-candy$min.js",
			[ 'jquery', 'hcaptcha' ],
			HCAPTCHA_VERSION,
			true
		);
	}
}
