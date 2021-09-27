<?php
/**
 * JetpackContactForm class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Jetpack;

/**
 * Class JetpackContactForm
 */
class JetpackForm extends JetpackBase {

	/**
	 * Add hCaptcha to Jetpack contact form.
	 *
	 * @param string $content Content.
	 *
	 * @return string|string[]|null
	 */
	public function jetpack_form( $content ) {
		// Jetpack classic form.
		$content = preg_replace_callback(
			'~(\[contact-form[\s\S]*?][\s\S]*?)(\[/contact-form])~',
			[ $this, 'classic_callback' ],
			$content
		);

		// Jetpack block form.
		return preg_replace_callback(
			'~<form [\s\S]*?wp-block-jetpack-contact-form[\s\S]*?(<button [\s\S]*?type="submit"[\s\S]*?</button>)[\s\S]*?</form>~',
			[ $this, 'block_callback' ],
			$content
		);
	}

	/**
	 * Add hCaptcha shortcode to the provided shortcode for Jetpack classic contact form.
	 *
	 * @param array $matches Matches.
	 *
	 * @return string
	 */
	public function classic_callback( $matches ) {
		$hcaptcha_shortcode = '[hcaptcha]';

		if ( preg_match( '~\[hcaptcha]~', $matches[0] ) ) {
			$hcaptcha_shortcode = '';
		}

		return (
			$matches[1] .
			$hcaptcha_shortcode .
			wp_nonce_field( 'hcaptcha_jetpack', 'hcaptcha_jetpack_nonce', true, false ) .
			$matches[2]
		);
	}

	/**
	 * Add hCaptcha shortcode to the provided shortcode for Jetpack block contact form.
	 *
	 * @param array $matches Matches.
	 *
	 * @return string
	 */
	public function block_callback( $matches ) {
		$replace = $matches[1] . wp_nonce_field( 'hcaptcha_jetpack', 'hcaptcha_jetpack_nonce', true, false );

		if ( ! preg_match( '~\[hcaptcha]~', $matches[0] ) ) {
			$replace = '[hcaptcha]' . $replace;
		}

		return str_replace(
			$matches[1],
			$replace,
			$matches[0]
		);
	}
}
