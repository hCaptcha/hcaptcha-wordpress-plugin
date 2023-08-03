<?php
/**
 * Base class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\GiveWP;

use HCaptcha\Helpers\HCaptcha;

/**
 * Class Base.
 */
abstract class Base {

	/**
	 * Base constructor.
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
		add_action( static::ADD_CAPTCHA_HOOK, [ $this, 'add_captcha' ] );
		add_action( static::VERIFY_HOOK, [ $this, 'verify' ] );
	}

	/**
	 * Add captcha to the form.
	 *
	 * @param int $form_id Form id.
	 *
	 * @return void
	 */
	public function add_captcha( int $form_id ) {
		$args = [
			'action' => static::ACTION,
			'name'   => static::NAME,
			'id'     => [
				'source'  => HCaptcha::get_class_source( static::class ),
				'form_id' => $form_id,
			],
		];

		HCaptcha::form_display( $args );
	}

	/**
	 * Verify captcha.
	 *
	 * @param bool|array $valid_data Validate fields.
	 *
	 * @return void
	 * @noinspection PhpUndefinedFunctionInspection
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function verify( $valid_data ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$action = isset( $_POST['action'] ) ? sanitize_text_field( wp_unslash( $_POST['action'] ) ) : '';

		if ( 'give_process_donation' !== $action ) {
			return;
		}

		$error_message = hcaptcha_get_verify_message(
			static::NAME,
			static::ACTION
		);

		if ( null !== $error_message ) {
			give_set_error( 'invalid_hcaptcha', $error_message );
		}
	}
}
