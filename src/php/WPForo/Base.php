<?php
/**
 * Base class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\WPForo;

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
		add_action( static::ADD_CAPTCHA_HOOK, [ $this, 'add_captcha' ], 99 );
		add_filter( static::VERIFY_HOOK, [ $this, 'verify' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
	}

	/**
	 * Add captcha to the new topic form.
	 */
	public function add_captcha() {
		hcap_form_display();
		wp_nonce_field( static::ACTION, static::NAME );
	}

	/**
	 * Verify new topic captcha.
	 *
	 * @param mixed $data Data.
	 *
	 * @return mixed|bool
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function verify( $data ) {
		$error_message = hcaptcha_get_verify_message(
			static::NAME,
			static::ACTION
		);

		if ( null !== $error_message ) {
			WPF()->notice->add( $error_message, 'error' );

			return false;
		}

		return $data;
	}

	/**
	 * Enqueue WPForo script.
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		$min = hcap_min_suffix();

		wp_enqueue_script(
			'hcaptcha-wpforo',
			HCAPTCHA_URL . "/assets/js/hcaptcha-wpforo$min.js",
			[ 'jquery', 'wpforo-frontend-js', 'hcaptcha' ],
			HCAPTCHA_VERSION,
			true
		);
	}
}
