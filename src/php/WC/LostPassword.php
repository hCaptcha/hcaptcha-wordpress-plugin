<?php
/**
 * LostPassword class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\WC;

use HCaptcha\Abstracts\LostPasswordBase;
use HCaptcha\Helpers\HCaptcha;

/**
 * Class LostPassword
 *
 * This class uses verify hook in WP\LostPassword.
 */
class LostPassword extends LostPasswordBase {
	/**
	 * Nonce action.
	 */
	protected const ACTION = 'hcaptcha_wc_lost_password';

	/**
	 * Nonce name.
	 */
	protected const NONCE = 'hcaptcha_wc_lost_password_nonce';

	/**
	 * Add hCaptcha action.
	 */
	protected const ADD_CAPTCHA_ACTION = 'woocommerce_lostpassword_form';

	/**
	 * $_POST key to check.
	 */
	protected const POST_KEY = 'wc_reset_password';

	/**
	 * $_POST value to check.
	 */
	protected const POST_VALUE = 'true';

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	protected function init_hooks(): void {
		parent::init_hooks();

		add_action( 'wp_head', [ $this, 'print_inline_styles' ], 20 );
	}

	/**
	 * Print inline styles.
	 *
	 * @return void
	 * @noinspection CssUnusedSymbol
	 */
	public function print_inline_styles(): void {
		$css = <<<CSS
	.woocommerce-ResetPassword .h-captcha {
		margin-top: 0.5rem;
	}
CSS;

		HCaptcha::css_display( $css );
	}
}
