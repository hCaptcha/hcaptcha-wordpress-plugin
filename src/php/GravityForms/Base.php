<?php
/**
 * Base class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\GravityForms;

/**
 * Class Base.
 */
abstract class Base {

	/**
	 * Nonce action.
	 */
	public const ACTION = 'gravity_forms_form';

	/**
	 * Nonce name.
	 */
	public const NONCE = 'gravity_forms_form_nonce';
}
