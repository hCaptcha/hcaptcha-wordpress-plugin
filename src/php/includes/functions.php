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
 * @param array|string $atts The hCaptcha shortcode attributes.
 *
 * @return string
 */
function hcap_shortcode( $atts ): string {
	$settings       = hcaptcha()->settings();
	$hcaptcha_force = $settings->is_on( 'force' );
	$hcaptcha_theme = $settings->get_theme();
	$hcaptcha_size  = $settings->get( 'size' );

	$atts = HCaptcha::unflatten_array( $atts, '--' );

	/**
	 * Do not set the default size here.
	 * If size is not normal|compact|invisible, it will be taken from plugin settings in HCaptcha::form().
	 * Same for theme and force.
	 */
	$atts = shortcode_atts(
		[
			'action'  => HCAPTCHA_ACTION,
			'name'    => HCAPTCHA_NONCE,
			'auto'    => false,
			'ajax'    => false,
			'force'   => $hcaptcha_force,
			'theme'   => $hcaptcha_theme,
			'size'    => $hcaptcha_size,
			'id'      => [],
			'protect' => true,
		],
		$atts
	);

	/**
	 * Filters the content of the hCaptcha form.
	 *
	 * @param string $form The hCaptcha form.
	 * @param array  $atts The hCaptcha shortcode attributes.
	 */
	return (string) apply_filters( 'hcap_hcaptcha_content', HCaptcha::form( $atts ), $atts );
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
