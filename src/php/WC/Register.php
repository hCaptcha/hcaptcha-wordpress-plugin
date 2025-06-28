<?php
/**
 * 'Register' class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\WC;

use HCaptcha\Helpers\API;
use HCaptcha\Helpers\HCaptcha;
use WP_Error;

/**
 * Class Register
 */
class Register {
	/**
	 * Nonce action.
	 */
	private const ACTION = 'hcaptcha_wc_register';

	/**
	 * Nonce name.
	 */
	private const NONCE = 'hcaptcha_wc_register_nonce';

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
		add_action( 'woocommerce_register_form', [ $this, 'add_captcha' ] );
		add_filter( 'woocommerce_process_registration_errors', [ $this, 'verify' ] );
		add_action( 'wp_head', [ $this, 'print_inline_styles' ], 20 );
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
			'id'     => [
				'source'  => HCaptcha::get_class_source( __CLASS__ ),
				'form_id' => 'register',
			],
		];

		HCaptcha::form_display( $args );
	}

	/**
	 * Verify register form.
	 *
	 * @param WP_Error|mixed $validation_error Validation error.
	 *
	 * @return WP_Error|mixed
	 */
	public function verify( $validation_error ) {
		$error_message = API::verify_post( self::NONCE, self::ACTION );

		if ( null === $error_message ) {
			return $validation_error;
		}

		if ( ! is_wp_error( $validation_error ) ) {
			$validation_error = new WP_Error();
		}

		$validation_error->add( 'hcaptcha_error', $error_message );

		return $validation_error;
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
	.woocommerce-form-register .h-captcha {
		margin-top: 2rem;
	}
';

		HCaptcha::css_display( $css );
	}
}
