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

	/**
	 * Nonce action.
	 */
	const ACTION = 'hcaptcha_wp_lost_password';

	/**
	 * Nonce name.
	 */
	const NONCE = 'hcaptcha_wp_lost_password_nonce';

	/**
	 * Add hCaptcha action.
	 */
	const ADD_CAPTCHA_ACTION = 'lostpassword_form';

	/**
	 * $_POST key to check.
	 */
	const POST_KEY = 'wp-submit';

	/**
	 * $_POST value to check.
	 */
	const POST_VALUE = null;
}
