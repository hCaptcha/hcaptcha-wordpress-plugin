<?php
/**
 * LostPassword class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\WP;

use HCaptcha\Helpers\HCaptcha;
use WP_Error;

/**
 * Class LostPassword
 */
class LostPassword {

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
		add_action( 'lostpassword_form', [ $this, 'add_captcha' ] );
		add_action( 'lostpassword_post', [ $this, 'verify' ] );
	}

	/**
	 * Add captcha.
	 */
	public function add_captcha() {
		$args = [
			'action' => 'hcaptcha_lost_password',
			'name'   => 'hcaptcha_lost_password_nonce',
		];

		HCaptcha::form_display( $args );
	}

	/**
	 * Verify lost password form.
	 *
	 * @param WP_Error $error Error.
	 *
	 * @return WP_Error
	 */
	public function verify( $error ) {
		$error_message = hcaptcha_get_verify_message_html(
			'hcaptcha_lost_password_nonce',
			'hcaptcha_lost_password'
		);

		if ( null !== $error_message ) {
			$error->add( 'invalid_captcha', $error_message );
		}

		return $error;
	}
}
