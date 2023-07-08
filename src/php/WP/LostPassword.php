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
	 * @return void
	 */
	public function verify( $error ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_POST['wp-submit'] ) ) {
			return;
		}

		if (
			// Nonce is checked by WC.
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			isset( $_POST['wc_reset_password'] ) &&
			! hcaptcha()->settings()->is( 'woocommerce_status', 'lost_pass' )
		) {
			return;
		}

		$error_message = hcaptcha_verify_post(
			self::NONCE,
			self::ACTION
		);

		if ( null === $error_message ) {
			return;
		}

		$code = array_search( $error_message, hcap_get_error_messages(), true );
		$code = $code ?: 'fail';

		$error->add( $code, $error_message );
	}
}
