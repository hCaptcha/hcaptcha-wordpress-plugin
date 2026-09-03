<?php
/**
 * Register class' file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\BBPress;

use HCaptcha\Abstracts\RegisterBase;
use HCaptcha\Helpers\API;
use HCaptcha\Helpers\HCaptcha;
use WP_Error;

/**
 * Class Register.
 */
class Register extends RegisterBase {

	/**
	 * Nonce action.
	 */
	private const ACTION = 'hcaptcha_bbp_register';

	/**
	 * Nonce name.
	 */
	private const NONCE = 'hcaptcha_bbp_register_nonce';

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

		add_filter( 'do_shortcode_tag', [ $this, 'add_captcha' ], 10, 4 );
		add_filter( 'registration_errors', [ $this, 'verify' ], 10, 3 );
	}

	/**
	 * Filters the output created by a shortcode callback.
	 *
	 * @param string|mixed $output Shortcode output.
	 * @param string       $tag    Shortcode name.
	 * @param array|string $attr   Shortcode attribute array or empty string.
	 * @param array        $m      Regular expression match array.
	 *
	 * @return string|mixed
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_captcha( $output, string $tag, $attr, array $m ) {
		if ( 'bbp-register' !== $tag || is_user_logged_in() ) {
			return $output;
		}

		$args = [
			'action' => self::ACTION,
			'name'   => self::NONCE,
			'id'     => $this->get_expected_id(),
		];

		$hcaptcha = HCaptcha::form( $args );

		$pattern     = '/(<button type="submit")/';
		$replacement = $hcaptcha . "\n$1";

		// Insert hCaptcha.
		return (string) preg_replace( $pattern, $replacement, $output );
	}

	/**
	 * Verify register captcha.
	 *
	 * @param WP_Error|mixed $errors               A WP_Error object containing any errors encountered during
	 *                                             registration.
	 * @param string         $sanitized_user_login User's username after it has been sanitized.
	 * @param string         $user_email           User's email.
	 *
	 * @return WP_Error
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function verify( $errors, string $sanitized_user_login, string $user_email ): WP_Error {
		$ownership = $this->get_request_ownership();

		if ( false === $ownership ) {
			return $errors;
		}

		if ( null === $ownership ) {
			return HCaptcha::add_error_message( $errors, hcap_get_error_messages()['bad-signature'] );
		}

		$error_message = API::verify( $this->get_entry() );

		return HCaptcha::add_error_message( $errors, $error_message );
	}

	/**
	 * Get hCaptcha verification entry.
	 *
	 * @return array
	 */
	private function get_entry(): array {
		return [
			'nonce_name'   => self::NONCE,
			'nonce_action' => self::ACTION,
			'expected_id'  => $this->get_expected_id(),
		];
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
