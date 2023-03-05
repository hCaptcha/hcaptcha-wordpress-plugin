<?php
/**
 * Form class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\GiveWP;

/**
 * Class Form.
 * Supports Donation form.
 */
class Form extends Base {

	/**
	 * Nonce action.
	 */
	const ACTION = 'hcaptcha_give_wp_form';

	/**
	 * Nonce name.
	 */
	const NAME = 'hcaptcha_give_wp_form_nonce';

	/**
	 * Add captcha hook.
	 */
	const ADD_CAPTCHA_HOOK = 'give_donation_form_user_info';

	/**
	 * Verify hook.
	 */
	const VERIFY_HOOK = 'give_checkout_error_checks';
}
