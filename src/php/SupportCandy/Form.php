<?php
/**
 * 'Form' class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\SupportCandy;

/**
 * Class Form.
 * Supports a New Ticket form.
 */
class Form extends Base {

	/**
	 * Nonce action.
	 */
	protected const ACTION = 'hcaptcha_support_candy_new_topic';

	/**
	 * Nonce name.
	 */
	protected const NAME = 'hcaptcha_support_candy_new_topic_nonce';

	/**
	 * Add captcha hook.
	 */
	protected const ADD_CAPTCHA_HOOK = 'wpsc_print_tff';

	/**
	 * Verify hook.
	 */
	protected const VERIFY_HOOK = 'wpsc_set_ticket_form';
}
