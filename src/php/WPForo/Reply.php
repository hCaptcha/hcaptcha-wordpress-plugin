<?php
/**
 * Reply class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\WPForo;

/**
 * Class Reply.
 */
class Reply extends Base {

	/**
	 * Nonce action.
	 */
	protected const ACTION = 'hcaptcha_wpforo_reply';

	/**
	 * Nonce name.
	 */
	protected const NAME = 'hcaptcha_wpforo_reply_nonce';

	/**
	 * Add captcha hook.
	 */
	public const ADD_CAPTCHA_HOOK = 'wpforo_reply_form_buttons_hook';

	/**
	 * Verify hook.
	 */
	protected const VERIFY_HOOK = 'wpforo_add_post_data_filter';
}
