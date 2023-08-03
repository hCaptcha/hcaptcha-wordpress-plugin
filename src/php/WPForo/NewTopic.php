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
	const ACTION = 'hcaptcha_wpforo_new_topic';

	/**
	 * Nonce name.
	 */
	const NAME = 'hcaptcha_wpforo_new_topic_nonce';

	/**
	 * Add captcha hook.
	 */
	const ADD_CAPTCHA_HOOK = 'wpforo_topic_form_buttons_hook';

	/**
	 * Verify hook.
	 */
	const VERIFY_HOOK = 'wpforo_add_topic_data_filter';
}
