<?php
/**
 * 'Register' class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\LearnDash;

use HCaptcha\Abstracts\RegisterBase;
use HCaptcha\Helpers\API;
use HCaptcha\Helpers\HCaptcha;
use WP_Error;

/**
 * Class Register
 */
class Register extends RegisterBase {

	/**
	 * Nonce action.
	 */
	private const ACTION = 'hcaptcha_learn_dash_register';

	/**
	 * Nonce name.
	 */
	private const NONCE = 'hcaptcha_learn_dash_register_nonce';

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

		add_action( 'learndash_registration_form', [ $this, 'add_captcha' ] );
		add_filter( 'registration_errors', [ $this, 'verify' ], 10, 3 );
		add_filter( 'learndash_registration_errors', [ $this, 'add_registration_errors' ] );
		add_action( 'wp_head', [ $this, 'print_inline_styles' ] );
	}

	/**
	 * Add captcha.
	 *
	 * @return void
	 */
	public function add_captcha(): void {
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
		// Nonce is checked in LearnDash.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_POST['learndash-registration-form'] ) ) {
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
	 * Get expected hCaptcha widget ID.
	 *
	 * @return array
	 */
	protected function get_expected_id(): array {
		return [
			'source'  => HCaptcha::get_class_source( __CLASS__ ),
			'form_id' => 'register',
		];
	}

	/**
	 * Add registration errors.
	 *
	 * @param string[]|mixed $registration_errors An array of registration errors and descriptions.
	 *
	 * @return array
	 */
	public function add_registration_errors( $registration_errors ): array {
		return array_merge( (array) $registration_errors, hcap_get_error_messages() );
	}

	/**
	 * Print inline styles.
	 *
	 * @return void
	 * @noinspection CssUnusedSymbol
	 */
	public function print_inline_styles(): void {
		/* language=CSS */
		$css = '
	#learndash_registerform .h-captcha {
		margin-bottom: 0;
	}
';

		HCaptcha::css_display( $css );
	}
}
