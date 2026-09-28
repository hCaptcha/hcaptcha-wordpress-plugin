<?php
/**
 * Functions file.
 *
 * @package hcaptcha-wp
 */

use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Helpers\Utils;

// This bootstrap guard runs before per-test coverage starts.
// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar
// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

/**
 * Display hCaptcha shortcode.
 *
 * @param array|string $atts The hCaptcha shortcode attributes.
 *
 * @return string
 */
function hcap_shortcode( $atts ): string {
	$settings = hcaptcha()->settings();

	if ( ! $settings ) {
		// @codeCoverageIgnoreStart
		return '';
		// @codeCoverageIgnoreEnd
	}

	$hcaptcha_force = $settings->is_on( 'force' );
	$hcaptcha_theme = $settings->get_theme() ?: 'light';
	$hcaptcha_size  = $settings->get( 'size' ) ?: 'normal';

	$atts         = Utils::unflatten_array( $atts, '--' );
	$has_honeypot = array_key_exists( 'honeypot', $atts );
	$has_auto     = array_key_exists( 'auto', $atts );

	/**
	 * Do not set the default size here.
	 * If size is not normal|compact|invisible, it will be taken from plugin settings in HCaptcha::form().
	 * Same for theme and force.
	 */
	$atts = shortcode_atts(
		[
			'action'   => HCAPTCHA_ACTION,
			'name'     => HCAPTCHA_NONCE,
			'auto'     => false,
			'ajax'     => false,
			'force'    => $hcaptcha_force,
			'theme'    => $hcaptcha_theme,
			'size'     => $hcaptcha_size,
			'honeypot' => null,
			'id'       => [],
			'protect'  => true,
		],
		$atts
	);

	// Keep the documented ajax shortcode shorthand compatible with earlier versions.
	if ( ! $has_auto && filter_var( $atts['ajax'], FILTER_VALIDATE_BOOLEAN ) ) {
		$atts['auto'] = true;
	}

	if ( ! $has_honeypot ) {
		unset( $atts['honeypot'] );
	}

	/**
	 * Filters the content of the hCaptcha form.
	 *
	 * @param string $form The hCaptcha form.
	 * @param array  $atts The hCaptcha shortcode attributes.
	 */
	return (string) apply_filters( 'hcap_hcaptcha_content', HCaptcha::form( $atts ), $atts );
}

// Registration is asserted in FunctionsTest::setUpBeforeClass(), before coverage starts.
// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar
// @codeCoverageIgnoreStart
add_shortcode( 'hcaptcha', 'hcap_shortcode' );
// @codeCoverageIgnoreEnd

/**
 * Get min suffix.
 *
 * @return string
 */
function hcap_min_suffix(): string {
	return defined( 'SCRIPT_DEBUG' ) && constant( 'SCRIPT_DEBUG' ) ? '' : '.min';
}
