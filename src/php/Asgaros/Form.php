<?php
/**
 * 'Form' class file.
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
	protected const ACTION = 'hcaptcha_asgaros_new_topic';

	/**
	 * Nonce name.
	 */
	protected const NAME = 'hcaptcha_asgaros_new_topic_nonce';

	/**
	 * Add captcha hook.
	 */
	protected const ADD_CAPTCHA_HOOK = 'do_shortcode_tag';

	/**
	 * Verify hook.
	 */
	protected const VERIFY_HOOK = 'asgarosforum_filter_insert_custom_validation';
}
