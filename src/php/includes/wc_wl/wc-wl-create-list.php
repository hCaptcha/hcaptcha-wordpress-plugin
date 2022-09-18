<?php
/**
 * WooCommerce Wishlists file.
 *
 * Add integration for WooCommerce Wishlists plugin.
 * See: https://woocommerce.com/products/woocommerce-wishlists/
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
 * Before WooCommerce Wishlists wrapper action.
 */
function hcap_woocommerce_wishlists_before_wrapper_action() {
	ob_start();
}

add_action( 'woocommerce_wishlists_before_wrapper', 'hcap_woocommerce_wishlists_before_wrapper_action' );

/**
 * After WooCommerce Wishlists wrapper action.
 */
function hcap_woocommerce_wishlists_after_wrapper_action() {
	$wrapper = ob_get_clean();

	// Find last $search string and insert hcaptcha before it.
	$search  = '<p class="form-row">';
	$replace =
		"\n" .
		hcap_form( 'hcaptcha_wc_create_wishlists_action', 'hcaptcha_wc_create_wishlists_nonce' ) .
		"\n" .
		$search;

	$wrapper = preg_replace(
		'/(' . $search . ')(?!.*' . $search . ')/is',
		$replace,
		$wrapper
	);

	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo $wrapper;
}

add_action( 'woocommerce_wishlists_after_wrapper', 'hcap_woocommerce_wishlists_after_wrapper_action' );

/**
 * WC Wishlists form.
 *
 * @param mixed $valid_captcha Valid captcha.
 *
 * @return mixed|bool
 */
function hcap_verify_wc_wl_create_list_captcha( $valid_captcha ) {
	$error_message = hcaptcha_get_verify_message(
		'hcaptcha_wc_create_wishlists_nonce',
		'hcaptcha_wc_create_wishlists_action'
	);

	if ( null === $error_message ) {
		return $valid_captcha;
	}

	wc_add_notice( $error_message, 'error' );

	return false;
}

add_filter( 'woocommerce_validate_wishlist_create', 'hcap_verify_wc_wl_create_list_captcha' );
