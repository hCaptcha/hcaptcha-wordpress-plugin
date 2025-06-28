<?php
/**
 * 'Register' class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\WP;

use HCaptcha\Helpers\API;
use HCaptcha\Helpers\HCaptcha;
use WP_Error;

/**
 * Class Register
 */
class Register {
	use Base;

	/**
	 * Nonce action.
	 */
	private const ACTION = 'hcaptcha_registration';

	/**
	 * Nonce name.
	 */
	private const NONCE = 'hcaptcha_registration_nonce';

	/**
	 * WP login action.
	 */
	private const WP_LOGIN_ACTION = 'register';

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
		if ( ! $this->is_login_action() || ! $this->is_login_url() ) {
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
		if ( ! $this->is_login_action() ) {
			return $errors;
		}

		$error_message = API::verify_post( self::NONCE, self::ACTION );

		return HCaptcha::add_error_message( $errors, $error_message );
	}
}
