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
	protected const ACTION = 'hcaptcha_classified_listing_lost_password';

	/**
	 * Nonce name.
	 */
	protected const NONCE = 'hcaptcha_classified_listing_lost_password_nonce';

	/**
	 * Add hCaptcha action.
	 */
	protected const ADD_CAPTCHA_ACTION = 'rtcl_lost_password_form';

	/**
	 * $_POST key to check.
	 */
	protected const POST_KEY = 'rtcl-lost-password';

	/**
	 * $_POST value to check.
	 */
	protected const POST_VALUE = 'Reset Password';
}
