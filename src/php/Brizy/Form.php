<?php
/**
 * 'Form' class file.
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
	 * Script handle.
	 */
	public const HANDLE = 'hcaptcha-brizy';

	/**
	 * Nonce action.
	 */
	protected const ACTION = 'hcaptcha_brizy_form';

	/**
	 * Nonce name.
	 */
	protected const NAME = 'hcaptcha_brizy_nonce';

	/**
	 * Add captcha hook.
	 */
	protected const ADD_CAPTCHA_HOOK = 'brizy_content';

	/**
	 * Verify hook.
	 */
	protected const VERIFY_HOOK = 'brizy_form';
}
