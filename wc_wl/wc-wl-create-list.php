<?php
/**
 * WooCommerce Wishlist file.
 *
 * Add integration for WooCommerce Wishlists plugin.
 * See: https://woocommerce.com/products/woocommerce-wishlists/
 *
 * @package hcaptcha-wp
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC Wishlist form.
 *
 * @param mixed $valid_captcha Valid captcha.
 *
 * @return mixed|bool
 */
function hcap_verify_wc_wl_create_list_captcha( $valid_captcha ) {
	$error_message = hcaptcha_get_verify_message(
		'hcaptcha_wc_create_wishlist_nonce',
		'hcaptcha_wc_create_wishlist'
	);

	if ( null === $error_message ) {
		return $valid_captcha;
	}

	wc_add_notice( $error_message, 'error' );

	return false;
}

add_filter( 'woocommerce_validate_wishlist_create', 'hcap_verify_wc_wl_create_list_captcha' );
