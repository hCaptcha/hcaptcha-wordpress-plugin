<?php
/**
 * WooCommerce checkout form file.
 *
 * @package hcaptcha-wp
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	// @codeCoverageIgnoreStart
	exit;
	// @codeCoverageIgnoreEnd
}

/**
 * Checkout form.
 */
function hcap_display_wc_checkout() {
	hcap_form_display();
	wp_nonce_field( 'hcaptcha_wc_checkout', 'hcaptcha_wc_checkout_nonce' );
}

add_action( 'woocommerce_after_checkout_billing_form', 'hcap_display_wc_checkout', 10, 0 );

/**
 * Verify checkout form.
 */
function hcap_verify_wc_checkout_captcha() {
	$error_message = hcaptcha_get_verify_message(
		'hcaptcha_wc_checkout_nonce',
		'hcaptcha_wc_checkout'
	);

	if ( null !== $error_message ) {
		wc_add_notice( $error_message, 'error' );
	}
}

add_action( 'woocommerce_checkout_process', 'hcap_verify_wc_checkout_captcha' );
