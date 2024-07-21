<?php
/**
 * LostPassword class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\WP;

use HCaptcha\Abstracts\LostPasswordBase;

/**
 * Class LostPassword
 */
class LostPassword extends LostPasswordBase {
	use Base;

	/**
	 * Nonce action.
	 */
	protected const ACTION = 'hcaptcha_wp_lost_password';

	/**
	 * Nonce name.
	 */
	protected const NONCE = 'hcaptcha_wp_lost_password_nonce';

	/**
	 * Add hCaptcha action.
	 */
	protected const ADD_CAPTCHA_ACTION = 'lostpassword_form';

	/**
	 * $_POST key to check.
	 */
	protected const POST_KEY = 'wp-submit';

	/**
	 * $_POST value to check.
	 */
	protected const POST_VALUE = null;

	/**
	 * WP login action.
	 */
	private const WP_LOGIN_ACTION = 'lostpassword';

	/**
	 * Add captcha.
	 *
	 * @return void
	 */
	public function add_captcha(): void {
		if ( ! $this->is_login_url() || ! $this->is_login_action() ) {
			return;
		}

		parent::add_captcha();
	}
}
