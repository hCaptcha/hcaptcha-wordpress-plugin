<?php
/**
 * JetpackContactForm class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Jetpack;

use HCaptcha\Helpers\HCaptcha;

/**
 * Class JetpackContactForm
 */
class JetpackForm extends JetpackBase {

	/**
	 * Add hCaptcha to a Jetpack contact form.
	 *
	 * @param string|mixed $content Content.
	 *
	 * @return string
	 */
	public function add_captcha( $content ): string {
		$content = (string) $content;

		// Jetpack classic form.
		$content = (string) preg_replace_callback(
			'~(\[contact-form[\s\S]*?][\s\S]*?)(\[/contact-form])~',
			[ $this, 'classic_callback' ],
			$content
		);

		// Jetpack block form.
		return (string) preg_replace_callback(
			'~<form [\s\S]*?wp-block-jetpack-contact-form[\s\S]*?(<div class="wp-block-jetpack-button wp-block-button"[\s\S]*?<button [\s\S]*?type="submit"[\s\S]*?</button>)[\s\S]*?</form>~',
			[ $this, 'block_callback' ],
			$content
		);
	}

	/**
	 * Add hCaptcha shortcode to the provided shortcode for a Jetpack classic contact form.
	 *
	 * @param array $matches Matches.
	 *
	 * @return string
	 */
	public function classic_callback( array $matches ): string {
		$hcaptcha = $this->prepare_hcaptcha( $matches );

		if ( '' === $hcaptcha ) {
			return $matches[0];
		}

		return $matches[1] . $hcaptcha . $matches[2];
	}

	/**
	 * Add hCaptcha shortcode to the provided shortcode for a Jetpack block contact form.
	 *
	 * @param array $matches Matches.
	 *
	 * @return string
	 */
	public function block_callback( array $matches ): string {
		$hcaptcha = $this->prepare_hcaptcha( $matches );

		if ( '' === $hcaptcha ) {
			return $matches[0];
		}

		return str_replace(
			$matches[1],
			$hcaptcha . $matches[1],
			$matches[0]
		);
	}

	private function prepare_hcaptcha( array $matches ): string {
		if ( has_shortcode( $matches[0], 'hcaptcha' ) ) {
			return '';
		}

		$hash = $this->get_form_hash( $matches[0] );

		$args = [
			'action' => self::ACTION,
			'name'   => self::NAME,
			'id'     => [
				'source'  => HCaptcha::get_class_source( __CLASS__ ),
				'form_id' => 'contact' . $hash,
			],
		];

		$hcaptcha = '<div class="grunion-field-wrap">' . HCaptcha::form( $args ) . '</div>';

		if ( false !== strpos( $matches[0], $hcaptcha ) ) {
			return '';
		}

		return $this->error_message( $hcaptcha, $args );
	}
}
