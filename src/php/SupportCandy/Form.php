<?php
/**
 * Form class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\SupportCandy;

/**
 * Class Form.
 * Supports New Ticket form.
 */
class Form extends Base {

	/**
	 * Nonce action.
	 */
	const ACTION = 'hcaptcha_support_candy_new_topic';

	/**
	 * Nonce name.
	 */
	const NAME = 'hcaptcha_support_candy_new_topic_nonce';

	/**
	 * Add captcha hook.
	 */
	const ADD_CAPTCHA_HOOK = 'wpsc_print_tff';

	/**
	 * Verify hook.
	 */
	const VERIFY_HOOK = 'wpsc_set_ticket_form';
}
