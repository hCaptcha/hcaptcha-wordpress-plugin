<?php
/**
 * General class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\ThemeMyLogin;

use HCaptcha\WP\Login;
use HCaptcha\WP\LostPassword;
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
	 * Block WP captcha when Theme My Login form is rendered.
	 *
	 * @return void
	 */
	public function block_wp_captcha() {
		$hooks = [
			'login_form'        => Login::class,
			'register_form'     => Register::class,
			'lostpassword_form' => LostPassword::class,
		];

		foreach ( $hooks as $hook_name => $class_name ) {
			$object = hcaptcha()->get( $class_name );

			if ( $object ) {
				remove_action( $hook_name, [ $object, 'add_captcha' ] );
			}
		}
	}
}
