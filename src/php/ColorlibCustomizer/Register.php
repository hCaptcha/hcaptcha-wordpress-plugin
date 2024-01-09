<?php
/**
 * Register class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\ColorlibCustomizer;

/**
 * Class Register
 */
class Register extends Base {

	/**
	 * Get register style.
	 *
	 * @param string $hcaptcha_size hCaptcha widget size.
	 *
	 * @return string
	 * @noinspection CssUnusedSymbol
	 */
	protected function get_style( string $hcaptcha_size ): string {
		$css = parent::get_style( $hcaptcha_size );

		switch ( $hcaptcha_size ) {
			case 'compact':
			case 'normal':
				$css .= <<<CSS
	.ml-container #registerform {
		height: unset;
	}
CSS;
				break;
			case 'invisible':
			default:
				break;
		}

		return $css;
	}
}
