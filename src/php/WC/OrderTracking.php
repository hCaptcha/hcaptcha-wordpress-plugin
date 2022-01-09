<?php
/**
 * OrderTracking class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\WC;

/**
 * Class OrderTracking
 */
class OrderTracking {

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
		add_filter( 'do_shortcode_tag', [ $this, 'do_shortcode_tag' ], 10, 4 );
	}

	/**
	 * Filters the output created by a shortcode callback.
	 *
	 * @param string       $output Shortcode output.
	 * @param string       $tag    Shortcode name.
	 * @param array|string $attr   Shortcode attributes array or empty string.
	 * @param array        $m      Regular expression match array.
	 *
	 * @return string
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function do_shortcode_tag( $output, $tag, $attr, $m ) {
		if ( 'woocommerce_order_tracking' !== $tag ) {
			return $output;
		}

		$hcap_form =
			'<div class="form-row"  style="margin-top: 2rem;">' .
			hcap_form( HCAPTCHA_ACTION, HCAPTCHA_NONCE, true ) .
			'</div>';

		return preg_replace(
			'/(<p class="form-row"><button type="submit"|<p class="form-actions">[\S\s]*?<button type="submit")/i',
			$hcap_form . '$1',
			$output,
			1
		);
	}
}
