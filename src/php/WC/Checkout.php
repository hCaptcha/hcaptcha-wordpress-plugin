<?php
/**
 * Checkout class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\WC;

/**
 * Class Checkout
 */
class Checkout {

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
		add_action( 'woocommerce_after_checkout_billing_form', [ $this, 'add_captcha' ] );
		add_action( 'woocommerce_checkout_process', [ $this, 'verify' ] );
	}

	/**
	 * Add captcha.
	 */
	public function add_captcha() {
		hcap_form_display( 'hcaptcha_wc_checkout', 'hcaptcha_wc_checkout_nonce' );
	}

	/**
	 * Verify checkout form.
	 */
	public function verify() {
		$error_message = hcaptcha_get_verify_message(
			'hcaptcha_wc_checkout_nonce',
			'hcaptcha_wc_checkout'
		);

		if ( null !== $error_message ) {
			wc_add_notice( $error_message, 'error' );
		}
	}
}
