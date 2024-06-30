<?php
/**
 * LostPassword class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\ThemeMyLogin;

use HCaptcha\Abstracts\LostPasswordBase;

/**
 * Class LostPassword
 */
class LostPassword extends LostPasswordBase {

	/**
	 * Nonce action.
	 */
	protected const ACTION = 'hcaptcha_theme_my_login_lost_password';

	/**
	 * Nonce name.
	 */
	protected const NONCE = 'hcaptcha_theme_my_login_lost_password_nonce';

	/**
	 * Add hCaptcha action.
	 */
	protected const ADD_CAPTCHA_ACTION = 'lostpassword_form';

	/**
	 * $_POST key to check.
	 */
	protected const POST_KEY = 'submit';

	/**
	 * $_POST value to check.
	 */
	protected const POST_VALUE = null;

	/**
	 * Add captcha.
	 *
	 * @return void
	 */
	public function add_captcha(): void {
		if ( ! did_action( 'tml_render_form' ) ) {
			return;
		}

		parent::add_captcha();
	}
}
