<?php
/**
 * Login class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\WC;

use HCaptcha\Abstracts\LoginBase;
use HCaptcha\Helpers\HCaptcha;
use WP_Error;

/**
 * Class Login
 */
class Login extends LoginBase {
	/**
	 * Nonce action.
	 */
	const ACTION = 'hcaptcha_login';

	/**
	 * Nonce name.
	 */
	const NONCE = 'hcaptcha_login_nonce';

	/**
	 * Init hooks.
	 */
	protected function init_hooks() {
		parent::init_hooks();

		add_action( 'woocommerce_login_form', [ $this, 'add_captcha' ] );
		add_action( 'woocommerce_process_login_errors', [ $this, 'verify' ] );
	}

	/**
	 * Add captcha.
	 */
	public function add_captcha() {
		if ( $this->is_login_limit_exceeded() ) {
			$args = [
				'action' => self::ACTION,
				'name'   => self::NONCE,
				'id'     => [
					'source'  => HCaptcha::get_class_source( __CLASS__ ),
					'form_id' => 'login',
				],

			];

			HCaptcha::form_display( $args );
		}
	}

	/**
	 * Verify login form.
	 *
	 * @param WP_Error $validation_error Validation error.
	 *
	 * @return WP_Error
	 */
	public function verify( $validation_error ) {
		if ( ! $this->is_login_limit_exceeded() ) {
			return $validation_error;
		}

		$error_message = hcaptcha_get_verify_message(
			self::NONCE,
			self::ACTION
		);

		if ( null === $error_message ) {
			return $validation_error;
		}

		$validation_error->add( 'hcaptcha_error', $error_message );

		return $validation_error;
	}
}
