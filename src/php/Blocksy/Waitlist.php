<?php
/**
 * Waitlist class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Blocksy;

use HCaptcha\Helpers\API;
use HCaptcha\Helpers\HCaptcha;
use WP_Error;

/**
 * Class Waitlist.
 */
class Waitlist {
	/**
	 * Nonce action.
	 */
	private const ACTION = 'hcaptcha_blocksy_waitlist';

	/**
	 * Nonce name.
	 */
	private const NONCE = 'hcaptcha_blocksy_waitlist_nonce';

	/**
	 * Class constructor.
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
		add_action( 'blocksy:woocommerce:product:custom:layer', [ $this, 'before_layer' ], 0 );
		add_action( 'blocksy:woocommerce:product:custom:layer', [ $this, 'after_layer' ], 20 );

		add_filter( 'blocksy:ext:woocommerce-extra:waitlist:subscribe:validate', [ $this, 'verify' ], 10, 3 );

		add_action( 'wp_head', [ $this, 'print_inline_styles' ] );
	}

	/**
	 * Before Blocksy Waitlist layer.
	 *
	 * @param array|mixed $layer Layer.
	 *
	 * @return void
	 */
	public function before_layer( $layer ): void {
		$layer_id = $layer['id'] ?? '';

		if ( 'product_waitlist' !== $layer_id ) {
			return;
		}

		ob_start();
	}

	/**
	 * After Blocksy Waitlist layer.
	 *
	 * @param array|mixed $layer Layer.
	 *
	 * @return void
	 */
	public function after_layer( $layer ): void {
		$layer_id = $layer['id'] ?? '';

		if ( 'product_waitlist' !== $layer_id ) {
			return;
		}

		$output = ob_get_clean();

		$args = [
			'action' => self::ACTION,
			'name'   => self::NONCE,
			'id'     => [
				'source'  => HCaptcha::get_class_source( __CLASS__ ),
				'form_id' => $layer['__id'],
			],
		];

		// Find the last $search string and insert hcaptcha before it.
		$search  = '<button class="ct-button" type="submit">';
		$replace = HCaptcha::form( $args ) . "\n" . $search;

		$output = str_replace(
			$search,
			$replace,
			$output
		);

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $output;
	}

	/**
	 * Verify captcha.
	 *
	 * @param ?WP_Error|mixed $value      Validation value.
	 * @param int             $product_id Product ID.
	 * @param string          $email      Email.
	 *
	 * @return ?WP_Error
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function verify( $value, int $product_id, string $email ): ?WP_Error {
		if ( ! ( null === $value || $value instanceof WP_Error ) ) {
			$value = null;
		}

		$error_message = API::verify_post( self::NONCE, self::ACTION );

		if ( null !== $error_message ) {
			$value = $value ?? new WP_Error( 'hcaptcha_error', $error_message );
			$value->add( 'hcaptcha_error', $error_message );
		}

		return $value;
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
	.ct-product-waitlist-form input[type="email"] {
		grid-row: 1;
	}

	.ct-product-waitlist-form h-captcha {
		grid-row: 2;
		margin-bottom: 0;
	}

	.ct-product-waitlist-form button {
		grid-row: 3;
	}
';

		HCaptcha::css_display( $css );
	}
}
