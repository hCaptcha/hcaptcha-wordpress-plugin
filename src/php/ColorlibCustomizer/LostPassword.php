<?php
/**
 * LostPassword class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\ColorlibCustomizer;

/**
 * Class LostPassword
 */
class LostPassword extends Base {

	/**
	 * Get login style.
	 *
	 * @param string $hcaptcha_size hCaptcha widget size.
	 *
	 * @return string
	 */
	protected function get_style( string $hcaptcha_size ): string {
		ob_start();

		switch ( $hcaptcha_size ) {
			case 'normal':
				?>
				<!--suppress CssUnusedSymbol -->
				<style>
					.ml-container #login {
						min-width: 350px;
					}
					.ml-container #lostpasswordform {
						height: unset;
					}
				</style>
				<?php
				break;
			case 'compact':
				?>
				<style>
					.ml-container #lostpasswordform {
						height: unset;
					}
				</style>
				<?php
				break;
			case 'invisible':
			default:
				break;
		}

		return ob_get_clean();
	}
}
