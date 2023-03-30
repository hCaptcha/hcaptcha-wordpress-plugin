<?php
/**
 * Checkout class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\WC;

use HCaptcha\Helpers\HCaptcha;

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
		add_action( 'woocommerce_review_order_before_submit', [ $this, 'add_captcha' ] );
		add_action( 'woocommerce_checkout_process', [ $this, 'verify' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
	}

	/**
	 * Add captcha.
	 */
	public function add_captcha() {
		$args = [
			'action' => 'hcaptcha_wc_checkout',
			'name'   => 'hcaptcha_wc_checkout_nonce',
		];

		HCaptcha::form_display( $args );
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

	/**
	 * Enqueue scripts.
	 */
	public function enqueue_scripts() {
		$min = hcap_min_suffix();

		wp_enqueue_script(
			'hcaptcha-wc',
			HCAPTCHA_URL . "/assets/js/hcaptcha-wc$min.js",
			[ 'jquery', 'hcaptcha' ],
			HCAPTCHA_VERSION,
			true
		);
	}
}
