<?php
/**
 * The Form class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Jetpack;

use HCaptcha\Helpers\Utils;

/**
 * Class Form
 */
class Form extends Base {

	/**
	 * Add hCaptcha to a Jetpack contact form.
	 *
	 * @param string|mixed $content Content.
	 *
	 * @return string
	 * @noinspection UnnecessaryCastingInspection
	 */
	public function add_hcaptcha( $content ): string {
		$content = (string) $content;

		// Jetpack classic form.
		$content = (string) preg_replace_callback(
			"~<form [\s\S]*?class='contact-form[\s\S]*?(<button type='submit')[\s\S]*?</form>~",
			[ $this, 'replace_callback' ],
			$content
		);

		// Jetpack block form.
		return (string) preg_replace_callback(
			'~<form [\s\S]*?wp-block-jetpack-contact-form[\s\S]*?(<div class="(?:[^"]*\s)?wp-block-(?:jetpack-)?button(?:\s[^"]*)?"[\s\S]*?<button [\s\S]*?type="submit"[\s\S]*?</button>)[\s\S]*?</form>~',
			[ $this, 'replace_callback' ],
			$content
		);
	}

	/**
	 * Add or normalize hCaptcha in the provided Jetpack contact form.
	 *
	 * @param array $matches Matches.
	 *
	 * @return string
	 */
	public function replace_callback( array $matches ): string {
		$hash = $this->get_form_hash( $matches[0] );
		$args = $this->get_args( $hash );

		if ( has_shortcode( $matches[0], 'hcaptcha' ) ) {
			return $this->replace_hcaptcha_shortcode( $matches[0], $args );
		}

		if (
			false !== strpos( $matches[0], '<h-captcha' ) ||
			false !== strpos( $matches[0], 'hcaptcha-widget-id' )
		) {
			return $this->replace_hcaptcha_markup( $matches[0], $args );
		}

		$hcaptcha = $this->get_hcaptcha_with_error_message( $args );

		return str_replace(
			$matches[1],
			$hcaptcha . $matches[1],
			$matches[0]
		);
	}

	/**
	 * Replace a manual hCaptcha shortcode with Jetpack hCaptcha markup.
	 *
	 * @param string $form Form.
	 * @param array  $args hCaptcha arguments.
	 *
	 * @return string
	 * @noinspection UnnecessaryCastingInspection
	 */
	private function replace_hcaptcha_shortcode( string $form, array $args ): string {
		$regex = get_shortcode_regex( [ 'hcaptcha' ] );

		return (string) preg_replace_callback(
			"/$regex/",
			function ( array $matches ) use ( $args ): string {
				$shortcode_atts = $this->get_hcaptcha_shortcode_atts( $matches[0] );
				$args           = array_merge( $shortcode_atts, $args );

				return $this->get_hcaptcha_with_error_message( $args );
			},
			$form,
			1
		);
	}

	/**
	 * Replace the existing manual hCaptcha markup with Jetpack hCaptcha markup.
	 *
	 * @param string $form Form.
	 * @param array  $args hCaptcha arguments.
	 *
	 * @return string
	 */
	private function replace_hcaptcha_markup( string $form, array $args ): string {
		$args     = array_merge( $this->get_hcaptcha_markup_atts( $form ), $args );
		$hcaptcha = $this->get_hcaptcha_with_error_message( $args );

		$error_message = '(?:\s*<div\b(?=[^>]*\bclass=["\'][^"\']*\bcontact-form__input-error\b)[^>]*>[\s\S]*?</div>\s*)?';
		$patterns      = [
			'~<div\b(?=[^>]*\bclass=(["\'])[^"\']*\bgrunion-field-hcaptcha-wrap\b[^"\']*\1)[^>]*>[\s\S]*?</div>' . $error_message . '~',
			'~\s*<input\b(?=[^>]*\bname=(["\'])hcaptcha-widget-id\1)[^>]*>\s*<h-captcha\b[\s\S]*?</h-captcha>\s*(?:<input\b(?=[^>]*\bname=(["\'])(?:hcaptcha(?:_jetpack)?_nonce|_wp_http_referer|hcap_hp_[^"\']+|hcap_hp_sig)\2)[^>]*>\s*)*' . $error_message . '~',
			'~\s*<h-captcha\b[\s\S]*?</h-captcha>' . $error_message . '~',
		];

		foreach ( $patterns as $pattern ) {
			$updated_form = preg_replace( $pattern, $hcaptcha, $form, 1 );

			if ( null !== $updated_form && $updated_form !== $form ) {
				return $updated_form;
			}
		}

		return $form;
	}

	/**
	 * Get hCaptcha shortcode attributes.
	 *
	 * @param string $shortcode hCaptcha shortcode.
	 *
	 * @return array
	 */
	private function get_hcaptcha_shortcode_atts( string $shortcode ): array {
		$shortcode = preg_replace( '/\s*\[|]\s*/', '', $shortcode );
		$atts      = shortcode_parse_atts( $shortcode );
		$atts      = is_array( $atts ) ? $atts : [];

		unset( $atts[0] );

		return Utils::unflatten_array( $atts, '--' );
	}

	/**
	 * Get attributes from the existing hCaptcha markup.
	 *
	 * @param string $form Form.
	 *
	 * @return array
	 */
	private function get_hcaptcha_markup_atts( string $form ): array {
		if ( ! preg_match( '~<h-captcha\b([^>]*)>~', $form, $matches ) ) {
			return [];
		}

		$attributes = [
			'theme' => 'data-theme',
			'size'  => 'data-size',
			'force' => 'data-force',
			'auto'  => 'data-auto',
			'ajax'  => 'data-ajax',
		];
		$atts       = [];

		foreach ( $attributes as $name => $attribute ) {
			if ( ! preg_match( '/\s' . $attribute . '=(["\'])(.*?)\1/', $matches[1], $attribute_matches ) ) {
				continue;
			}

			$atts[ $name ] = html_entity_decode( $attribute_matches[2], ENT_QUOTES | ENT_HTML5 );
		}

		return $atts;
	}

	/**
	 * Get hCaptcha markup with an optional error message.
	 *
	 * @param array $args hCaptcha arguments.
	 *
	 * @return string
	 */
	private function get_hcaptcha_with_error_message( array $args ): string {
		$hcaptcha = $this->get_hcaptcha( $args );

		return (string) $this->error_message( $hcaptcha, $args );
	}
}
