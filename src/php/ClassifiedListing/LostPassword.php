<?php
/**
 * LostPassword class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\ClassifiedListing;

use HCaptcha\Abstracts\LostPasswordBase;

/**
 * Class LostPassword.
 */
class LostPassword extends LostPasswordBase {

	/**
	 * Nonce action.
	 */
	const ACTION = 'hcaptcha_classified_listing_lost_password';

	/**
	 * Nonce name.
	 */
	const NONCE = 'hcaptcha_classified_listing_lost_password_nonce';

	/**
	 * Add hCaptcha action.
	 */
	const ADD_CAPTCHA_ACTION = 'rtcl_lost_password_form';

	/**
	 * $_POST key to check.
	 */
	const POST_KEY = 'rtcl-lost-password';

	/**
	 * $_POST value to check.
	 */
	const POST_VALUE = 'Reset Password';
}
