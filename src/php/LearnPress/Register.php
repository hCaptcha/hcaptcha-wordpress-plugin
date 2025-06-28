<?php
/**
 * 'Register' class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\LearnPress;

use HCaptcha\Helpers\API;
use HCaptcha\Helpers\HCaptcha;

/**
 * Class Register
 */
class Register {

	/**
	 * Nonce action.
	 */
	private const ACTION = 'hcaptcha_learn_press_register';

	/**
	 * Nonce name.
	 */
	private const NONCE = 'hcaptcha_learn_press_register_nonce';

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
		add_action( 'register_form', [ $this, 'add_hcaptcha' ] );
		add_action( 'wp_loaded', [ $this, 'verify' ], 0 );
	}

	/**
	 * Add hCaptcha.
	 *
	 * @return void
	 */
	public function add_hcaptcha(): void {
		if ( ! did_action( 'learn-press/after-form-register-fields' ) ) {
			return;
		}

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
	 * @return void
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function verify(): void {
		$action = 'learn-press-register';
		$nonce  = isset( $_POST['learn-press-register-nonce'] )
			? sanitize_text_field( wp_unslash( $_POST['learn-press-register-nonce'] ) )
			: '';

		if ( ! wp_verify_nonce( $nonce, $action ) ) {
			// Not submitted from the LearnPress Register form.
			return;
		}

		$error_message = API::verify_post( self::NONCE, self::ACTION );

		if ( null !== $error_message ) {
			$message_data = [
				'status'  => 'error',
				'content' => $error_message,
			];

			learn_press_set_message( $message_data );
			remove_action( 'wp_loaded', [ 'LP_Forms_Handler', 'init' ] );
		}
	}
}
