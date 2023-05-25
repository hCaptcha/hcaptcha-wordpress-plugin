<?php
/**
 * LostPassword class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\WP;

use HCaptcha\Helpers\HCaptcha;
use HCaptcha\WC\LostPassword as WCLostPassword;
use WP_Error;

/**
 * Class LostPassword
 */
class LostPassword {
	/**
	 * Nonce action.
	 */
	const ACTION = 'hcaptcha_lost_password';

	/**
	 * Nonce name.
	 */
	const NONCE = 'hcaptcha_lost_password_nonce';

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
			'action' => self::ACTION,
			'name'   => self::NONCE,
			'id'     => [
				'source'  => HCaptcha::get_class_source( __CLASS__ ),
				'form_id' => 'lost_password',
			],
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
		if (
			// Nonce is checked by WC.
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			isset( $_POST['wc_reset_password'] ) &&
			! hcaptcha()->settings()->is( 'woocommerce_status', 'lost_pass' )
		) {
			return $error;
		}

		$error_message = hcaptcha_get_verify_message_html(
			self::NONCE,
			self::ACTION
		);

		if ( null !== $error_message ) {
			$error->add( 'invalid_captcha', $error_message );
		}

		return $error;
	}
}
