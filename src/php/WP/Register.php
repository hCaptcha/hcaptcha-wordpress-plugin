<?php
/**
 * 'Register' class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\WP;

use HCaptcha\Abstracts\RegisterBase;
use HCaptcha\Helpers\API;
use HCaptcha\Helpers\HCaptcha;
use WP_Error;

/**
 * Class Register
 */
class Register extends RegisterBase {
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
	protected function init_hooks(): void {
		parent::init_hooks();

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
			'id'     => $this->get_expected_id(),
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

		$ownership = $this->get_request_ownership();

		if ( false === $ownership ) {
			return $errors;
		}

		if ( null === $ownership ) {
			return HCaptcha::add_error_message( $errors, hcap_get_error_messages()['bad-signature'] );
		}

		$error_message = API::verify(
			[
				'nonce_name'   => self::NONCE,
				'nonce_action' => self::ACTION,
				'expected_id'  => $this->get_expected_id(),
			]
		);

		return HCaptcha::add_error_message( $errors, $error_message );
	}

	/**
	 * Get expected hCaptcha widget id.
	 *
	 * @return array
	 */
	protected function get_expected_id(): array {
		return [
			'source'  => HCaptcha::get_class_source( __CLASS__ ),
			'form_id' => 'register',
		];
	}
}
