<?php
/**
 * Form class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Brizy;

/**
 * Class Form.
 * Supports Brizy form.
 */
class Form extends Base {

	/**
	 * Nonce action.
	 */
	const ACTION = 'hcaptcha_brizy_form';

	/**
	 * Nonce name.
	 */
	const NAME = 'hcaptcha_brizy_nonce';

	/**
	 * Add captcha hook.
	 */
	const ADD_CAPTCHA_HOOK = 'brizy_content';

	/**
	 * Verify hook.
	 */
	const VERIFY_HOOK = 'brizy_form';
}
