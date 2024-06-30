<?php
/**
 * Register class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\UM;

/**
 * Class Register
 */
class Register extends Base {

	/**
	 * UM action.
	 */
	protected const UM_ACTION = 'um_submit_form_errors_hook__registration';

	/**
	 * UM mode.
	 */
	public const UM_MODE = 'register';
}
