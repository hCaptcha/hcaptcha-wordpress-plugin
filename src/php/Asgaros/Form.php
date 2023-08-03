<?php
/**
 * Form class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Asgaros;

/**
 * Class Form.
 * Supports New Topic and Reply forms.
 */
class Form extends Base {

	/**
	 * Nonce action.
	 */
	const ACTION = 'hcaptcha_asgaros_new_topic';

	/**
	 * Nonce name.
	 */
	const NAME = 'hcaptcha_asgaros_new_topic_nonce';

	/**
	 * Add captcha hook.
	 */
	const ADD_CAPTCHA_HOOK = 'do_shortcode_tag';

	/**
	 * Verify hook.
	 */
	const VERIFY_HOOK = 'asgarosforum_filter_insert_custom_validation';
}
