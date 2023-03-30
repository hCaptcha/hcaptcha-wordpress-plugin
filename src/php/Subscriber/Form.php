<?php
/**
 * Form class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Subscriber;

use HCaptcha\Helpers\HCaptcha;

/**
 * Class Form.
 */
class Form {

	/**
	 * Nonce action.
	 */
	const ACTION = 'hcaptcha_subscriber_form';

	/**
	 * Nonce name.
	 */
	const NAME = 'hcaptcha_subscriber_form_nonce';

	/**
	 * Form constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	private function init_hooks() {
		add_filter( 'sbscrbr_add_field', [ $this, 'add_captcha' ] );
		add_filter( 'sbscrbr_check', [ $this, 'verify' ] );
	}

	/**
	 * Add captcha to the subscriber form.
	 *
	 * @param string $content Subscriber form content.
	 *
	 * @return string
	 */
	public function add_captcha( $content ) {
		$args = [
			'action' => self::ACTION,
			'name'   => self::NAME,
		];

		return $content . HCaptcha::form( $args );
	}

	/**
	 * Verify subscriber captcha.
	 *
	 * @param bool $check_result Check result.
	 *
	 * @return bool|string
	 */
	public function verify( $check_result ) {
		$error_message = hcaptcha_get_verify_message( self::NAME, self::ACTION );

		if ( null !== $error_message ) {
			return $error_message;
		}

		return $check_result;
	}
}
