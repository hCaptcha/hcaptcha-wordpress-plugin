<?php
/**
 * LostPassword class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\WP;

use HCaptcha\Abstracts\LostPasswordBase;
use WP_User;

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
	protected const POST_KEY = 'user_login';

	/**
	 * $_POST value to check.
	 */
	protected const POST_VALUE = null;

	/**
	 * Verify a lost password form.
	 *
	 * @param mixed              $errors Error.
	 * @param WP_User|false|null $user   Native resolved recovery recipient.
	 *
	 * @return void
	 */
	public function verify( $errors, $user = null ): void {
		if ( ! $this->is_login_url() || ! $this->is_lost_password_action() ) {
			return;
		}

		parent::verify( $errors, $user );
	}

	/**
	 * Whether native WordPress recovery can handle this request.
	 *
	 * @return bool
	 */
	protected function can_handle_request(): bool {
		return $this->is_login_url() && $this->is_lost_password_action() && parent::can_handle_request();
	}

	/**
	 * Add captcha.
	 *
	 * @return void
	 */
	public function add_captcha(): void {
		if ( ! $this->is_login_url() || ! $this->is_lost_password_action() ) {
			return;
		}

		parent::add_captcha();
	}
}
