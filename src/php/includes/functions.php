<?php
/**
 * Functions file.
 *
 * @package hcaptcha-wp
 */

use HCaptcha\Helpers\HCaptcha;

/**
 * Display hCaptcha shortcode.
 *
 * @param array|string $atts hcaptcha shortcode attributes.
 *
 * @return string
 */
function hcap_shortcode( $atts ): string {
	$settings       = hcaptcha()->settings();
	$hcaptcha_force = $settings->is_on( 'force' );
	$hcaptcha_size  = $settings->get( 'size' );

	/**
	 * Do not set the default size here.
	 * If size is not normal|compact|invisible, it will be taken from plugin settings in HCaptcha::form().
	 */
	$atts = shortcode_atts(
		[
			'action'  => HCAPTCHA_ACTION,
			'name'    => HCAPTCHA_NONCE,
			'auto'    => false,
			'force'   => $hcaptcha_force,
			'size'    => $hcaptcha_size,
			'id'      => [],
			'protect' => true,
		],
		$atts
	);

	/**
	 * Filters the content of the hcaptcha form.
	 *
	 * @param string $form The hcaptcha form.
	 */
	return (string) apply_filters( 'hcap_hcaptcha_content', HCaptcha::form( $atts ) );
}

add_shortcode( 'hcaptcha', 'hcap_shortcode' );

/**
 * Get min suffix.
 *
 * @return string
 */
function hcap_min_suffix(): string {
	return defined( 'SCRIPT_DEBUG' ) && constant( 'SCRIPT_DEBUG' ) ? '' : '.min';
}
