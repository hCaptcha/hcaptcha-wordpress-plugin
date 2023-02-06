<?php
/**
 * WooCommerce Wishlists class file.
 *
 * Add integration for WooCommerce Wishlists plugin.
 * See: https://woocommerce.com/products/woocommerce-wishlists/
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\WCWishlists;

/**
 * Class Create List.
 */
class CreateList {

	/**
	 * Create List constructor.
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
		add_action( 'woocommerce_wishlists_before_wrapper', [ $this, 'before_wrapper' ] );
		add_action( 'woocommerce_wishlists_after_wrapper', [ $this, 'after_wrapper' ] );
		add_filter( 'woocommerce_validate_wishlist_create', [ $this, 'verify' ] );
	}

	/**
	 * Before WooCommerce Wishlists wrapper action.
	 */
	public function before_wrapper() {
		ob_start();
	}

	/**
	 * After WooCommerce Wishlists wrapper action.
	 */
	public function after_wrapper() {
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

	/**
	 * Verify captcha.
	 *
	 * @param mixed $valid_captcha Valid captcha.
	 *
	 * @return mixed|bool
	 */
	public function verify( $valid_captcha ) {
		$error_message = hcaptcha_get_verify_message(
			'hcaptcha_wc_create_wishlists_nonce',
			'hcaptcha_wc_create_wishlists_action'
		);

		if ( null !== $error_message ) {
			wc_add_notice( $error_message, 'error' );

			return false;
		}

		return $valid_captcha;
	}
}
