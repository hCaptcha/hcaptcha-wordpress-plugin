<?php
/**
 * New Topic class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\BBPress;

/**
 * Class New Topic.
 */
class NewTopic extends Base {

	/**
	 * Nonce action.
	 */
	const ACTION = 'hcaptcha_bbp_new_topic';

	/**
	 * Nonce name.
	 */
	const NAME = 'hcaptcha_bbp_new_topic_nonce';

	/**
	 * Add captcha hook.
	 */
	const ADD_CAPTCHA_HOOK = 'bbp_theme_after_topic_form_content';

	/**
	 * Verify hook.
	 */
	const VERIFY_HOOK = 'bbp_new_topic_pre_extras';
}
