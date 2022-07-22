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
	const UM_ACTION = 'um_submit_form_errors_hook__registration';

	/**
	 * UM mode.
	 */
	const UM_MODE = 'register';
}
