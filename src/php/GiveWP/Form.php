<?php
/**
 * 'Form' class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\GiveWP;

/**
 * Class Form.
 * Supports a Donation form.
 */
class Form extends Base {

	/**
	 * Nonce action.
	 */
	protected const ACTION = 'hcaptcha_give_wp_form';

	/**
	 * Nonce name.
	 */
	protected const NAME = 'hcaptcha_give_wp_form_nonce';

	/**
	 * Add captcha hook.
	 */
	protected const ADD_CAPTCHA_HOOK = 'give_donation_form_user_info';

	/**
	 * Verify hook.
	 */
	protected const VERIFY_HOOK = 'give_checkout_error_checks';
}
