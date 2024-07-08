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
	protected const ACTION = 'hcaptcha_bbp_new_topic';

	/**
	 * Nonce name.
	 */
	protected const NAME = 'hcaptcha_bbp_new_topic_nonce';

	/**
	 * Add captcha hook.
	 */
	protected const ADD_CAPTCHA_HOOK = 'bbp_theme_after_topic_form_content';

	/**
	 * Verify hook.
	 */
	protected const VERIFY_HOOK = 'bbp_new_topic_pre_extras';
}
