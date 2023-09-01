<?php
/**
 * General class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\ThemeMyLogin;

use HCaptcha\WP\Login;
use HCaptcha\WP\Register;

/**
 * Class General.
 */
class General {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Init hooks.
	 */
	private function init_hooks() {
		add_action( 'tml_render_form', [ $this, 'block_wp_captcha' ] );
	}

	/**
	 * Block WP captcha.
	 *
	 * @return void
	 */
	public function block_wp_captcha() {
		$wp_login = hcaptcha()->get( Login::class );

		if ( $wp_login ) {
			remove_action( 'login_form', [ $wp_login, 'add_captcha' ] );
		}

		$wp_register = hcaptcha()->get( Register::class );

		if ( $wp_register ) {
			remove_action( 'register_form', [ $wp_register, 'add_captcha' ] );
		}
	}
}
