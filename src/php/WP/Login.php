<?php
/**
 * Login class file.
 *
 * @package hcaptcha-wp
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedNamespaceInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\WP;

use HCaptcha\Abstracts\LoginBase;
use WordfenceLS\Controller_WordfenceLS;

/**
 * Class Login
 */
class Login extends LoginBase {

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	protected function init_hooks(): void {
		parent::init_hooks();

		add_action( 'login_form', [ $this, 'add_captcha' ] );
	}

	/**
	 * Add captcha.
	 *
	 * @return void
	 */
	public function add_captcha(): void {
		if ( ! $this->is_wp_login_form() ) {
			return;
		}

		parent::add_captcha();
	}
}
