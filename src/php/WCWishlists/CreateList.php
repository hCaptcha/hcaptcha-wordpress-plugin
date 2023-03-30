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

use HCaptcha\Helpers\HCaptcha;

/**
 * Class Create List.
 */
class CreateList {
	/**
	 * Nonce action.
	 */
	const ACTION = 'hcaptcha_wc_create_wishlists_action';

	/**
	 * Nonce name.
	 */
	const NONCE = 'hcaptcha_wc_create_wishlists_nonce';

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

		$args = [
			'action' => self::ACTION,
			'name'   => self::NONCE,
		];

		// Find last $search string and insert hcaptcha before it.
		$search  = '<p class="form-row">';
		$replace =
			"\n" .
			HCaptcha::form( $args ) .
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
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function verify( $valid_captcha ) {
		$error_message = hcaptcha_get_verify_message(
			self::NONCE,
			self::ACTION
		);

		if ( null !== $error_message ) {
			wc_add_notice( $error_message, 'error' );

			return false;
		}

		return $valid_captcha;
	}
}
