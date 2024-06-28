<?php
/**
 * Reply class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\BBPress;

/**
 * Class Reply.
 */
class Reply extends Base {

	/**
	 * Nonce action.
	 */
	protected const ACTION = 'hcaptcha_bbp_reply';

	/**
	 * Nonce name.
	 */
	protected const NAME = 'hcaptcha_bbp_reply_nonce';

	/**
	 * Add captcha hook.
	 */
	protected const ADD_CAPTCHA_HOOK = 'bbp_theme_after_reply_form_content';

	/**
	 * Verify hook.
	 */
	protected const VERIFY_HOOK = 'bbp_new_reply_pre_extras';
}
