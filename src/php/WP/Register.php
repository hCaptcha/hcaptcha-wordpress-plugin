<?php
/**
 * Register class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\WP;

use HCaptcha\Helpers\HCaptcha;
use WP_Error;

/**
 * Class Register
 */
class Register {
	/**
	 * Nonce action.
	 */
	const ACTION = 'hcaptcha_registration';

	/**
	 * Nonce name.
	 */
	const NONCE = 'hcaptcha_registration_nonce';

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
		add_action( 'register_form', [ $this, 'add_captcha' ] );
		add_action( 'registration_errors', [ $this, 'verify' ], 10, 3 );
	}

	/**
	 * Add captcha.
	 */
	public function add_captcha() {
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
	 * @param WP_Error $errors               A WP_Error object containing any errors encountered during registration.
	 * @param string   $sanitized_user_login User's username after it has been sanitized.
	 * @param string   $user_email           User's email.
	 *
	 * @return WP_Error
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function verify( $errors, $sanitized_user_login, $user_email ) {
		$error_message = hcaptcha_get_verify_message_html(
			self::NONCE,
			self::ACTION
		);

		if ( null === $error_message ) {
			return $errors;
		}

		$errors->add( 'invalid_captcha', $error_message );

		return $errors;
	}
}
