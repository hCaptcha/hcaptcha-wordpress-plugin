<?php
/**
 * Register class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\ClassifiedListing;

use HCaptcha\Helpers\HCaptcha;
use WP_Error;
use WP_User;

/**
 * Class LostPassword.
 */
class LostPassword {

	/**
	 * Nonce action.
	 */
	const ACTION = 'hcaptcha_classified_listing_lost_pass';

	/**
	 * Nonce name.
	 */
	const NONCE = 'hcaptcha_classified_listing_lost_pass_nonce';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Init hooks.
	 */
	protected function init_hooks() {
		add_action( 'rtcl_lost_password_form', [ $this, 'add_captcha' ] );
		add_action( 'lostpassword_post', [ $this, 'verify' ] );
	}

	/**
	 * Add captcha.
	 *
	 * @return void
	 */
	public function add_captcha() {
		$args = [
			'action' => self::ACTION,
			'name'   => self::NONCE,
			'id'     => [
				'source'  => HCaptcha::get_class_source( __CLASS__ ),
				'form_id' => 'password',
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
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$rtcl_register = isset( $_POST['rtcl-lost-password'] ) ?
			sanitize_text_field( wp_unslash( $_POST['rtcl-lost-password'] ) ) :
			'';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( 'Reset Password' !== $rtcl_register ) {
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
