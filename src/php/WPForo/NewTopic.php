<?php
/**
 * NewTopic class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\WPForo;

/**
 * Class NewTopic.
 */
class NewTopic extends Base {

	/**
	 * Nonce action.
	 */
	protected const ACTION = 'hcaptcha_wpforo_new_topic';

	/**
	 * Nonce name.
	 */
	protected const NAME = 'hcaptcha_wpforo_new_topic_nonce';

	/**
	 * Add captcha hook.
	 */
	public const ADD_CAPTCHA_HOOK = 'wpforo_topic_form_buttons_hook';

	/**
	 * Verify hook.
	 */
	protected const VERIFY_HOOK = 'wpforo_add_topic_data_filter';
}
