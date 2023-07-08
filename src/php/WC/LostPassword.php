<?php
/**
 * LostPassword class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\WC;

use HCaptcha\Abstracts\LostPasswordBase;

/**
 * Class LostPassword
 *
 * This class uses verify hook in WP\LostPassword.
 */
class LostPassword extends LostPasswordBase {
	/**
	 * Nonce action.
	 */
	const ACTION = 'hcaptcha_wc_lost_password';

	/**
	 * Nonce name.
	 */
	const NONCE = 'hcaptcha_wc_lost_password_nonce';

	/**
	 * Add hCaptcha action.
	 */
	const ADD_CAPTCHA_ACTION = 'woocommerce_lostpassword_form';

	/**
	 * $_POST key to check.
	 */
	const POST_KEY = 'wc_reset_password';

	/**
	 * $_POST value to check.
	 */
	const POST_VALUE = 'true';
}
