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
	const ACTION = 'gravity_forms_form';

	/**
	 * Nonce name.
	 */
	const NONCE = 'gravity_forms_form_nonce';
}
