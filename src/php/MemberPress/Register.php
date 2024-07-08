<?php
/**
 * Register class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\MemberPress;

use HCaptcha\Helpers\HCaptcha;

/**
 * Class Register
 */
class Register {
	/**
	 * Nonce action.
	 */
	private const ACTION = 'hcaptcha_memberpress_register';

	/**
	 * Nonce name.
	 */
	private const NONCE = 'hcaptcha_memberpress_register_nonce';

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
		add_action( 'mepr-checkout-before-submit', [ $this, 'add_captcha' ] );
		add_filter( 'mepr-validate-signup', [ $this, 'verify' ] );
	}

	/**
	 * Add hCaptcha to the Register form.
	 *
	 * @return void
	 */
	public function add_captcha(): void {
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
	 * Verify hCaptcha.
	 *
	 * @param array|mixed $errors Errors.
	 *
	 * @return array|mixed
	 */
	public function verify( $errors ) {
		$error_message = hcaptcha_get_verify_message(
			self::NONCE,
			self::ACTION
		);

		if ( null !== $error_message ) {
			$errors[] = $error_message;
		}

		return $errors;
	}
}
