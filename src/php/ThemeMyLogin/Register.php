<?php
/**
 * 'Register' class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\ThemeMyLogin;

use HCaptcha\Helpers\API;
use HCaptcha\Helpers\HCaptcha;
use WP_Error;

/**
 * Class Register
 */
class Register {

	/**
	 * Nonce action.
	 */
	private const ACTION = 'hcaptcha_theme_my_login_register';

	/**
	 * Nonce name.
	 */
	private const NONCE = 'hcaptcha_theme_my_login_register_nonce';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		add_action( 'register_form', [ $this, 'add_captcha' ] );
		add_filter( 'registration_errors', [ $this, 'verify' ], 10, 3 );
	}

	/**
	 * Add captcha.
	 *
	 * @return void
	 */
	public function add_captcha(): void {
		if ( ! did_action( 'tml_render_form' ) ) {
			return;
		}

		$args = [
			'action' => self::ACTION,
			'name'   => self::NONCE,
			'id'     => [
				'source'  => HCaptcha::get_class_source( __CLASS__ ),
				'form_id' => 'register',
			],
		];

		HCaptcha::form_display( $args );
	}

	/**
	 * Verify register captcha.
	 *
	 * @param WP_Error|mixed $errors               A WP_Error object containing any errors encountered during
	 *                                             registration.
	 * @param string         $sanitized_user_login User's username after it has been sanitized.
	 * @param string         $user_email           User's email.
	 *
	 * @return WP_Error|mixed
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function verify( $errors, string $sanitized_user_login, string $user_email ) {
		if ( ! doing_action( 'tml_action_register' ) ) {
			return $errors;
		}

		$error_message = API::verify_post_html( self::NONCE, self::ACTION );

		if ( null === $error_message ) {
			return $errors;
		}

		if ( ! is_wp_error( $errors ) ) {
			$errors = new WP_Error();
		}

		$errors->add( 'invalid_captcha', $error_message );

		return $errors;
	}
}
