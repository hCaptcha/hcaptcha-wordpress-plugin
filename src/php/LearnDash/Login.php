<?php
/**
 * Login class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\LearnDash;

use HCaptcha\Abstracts\LoginBase;
use HCaptcha\Helpers\HCaptcha;

/**
 * Class Login.
 */
class Login extends LoginBase {

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	protected function init_hooks(): void {
		parent::init_hooks();

		add_filter( 'login_form_middle', [ $this, 'add_learn_dash_captcha' ], 10, 2 );
	}

	/**
	 * Add captcha.
	 *
	 * @param string|mixed $content Content to display. Default empty.
	 * @param array        $args    Array of login form arguments.
	 *
	 * @return string
	 * @noinspection PhpMissingParamTypeInspection
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_learn_dash_captcha( $content, $args ): string {
		$content = (string) $content;

		if ( ! $this->is_learn_dash_login_form() ) {
			return $content;
		}

		ob_start();
		$this->add_captcha();

		return $content . ob_get_clean();
	}

	/**
	 * Whether we process the Learn Dash login form.
	 *
	 * @return bool
	 */
	private function is_learn_dash_login_form(): bool {
		return HCaptcha::did_filter( 'learndash-login-form-args' );
	}
}
