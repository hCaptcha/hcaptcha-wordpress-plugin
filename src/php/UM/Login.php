<?php
/**
 * Login class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\UM;

/**
 * Class Login
 */
class Login extends Base {

	/**
	 * UM action.
	 */
	const UM_ACTION = 'um_submit_form_errors_hook_login';

	/**
	 * UM mode.
	 */
	const UM_MODE = 'login';

	/**
	 * Init hooks.
	 */
	protected function init_hooks() {
		parent::init_hooks();

		add_filter( 'login_errors', [ $this, 'mute_login_hcaptcha_notice' ], 10, 2 );
	}

	/**
	 * Prevent showing hcaptcha error before the login form.
	 *
	 * @param string $message   Message.
	 * @param string $error_key Error_key.
	 *
	 * @return string
	 */
	public function mute_login_hcaptcha_notice( string $message, string $error_key = '' ): string {
		if ( self::KEY !== $error_key ) {
			return $message;
		}

		return '';
	}

	/**
	 * Add hCaptcha to form fields.
	 *
	 * @param array $fields Form fields.
	 *
	 * @return array
	 * @noinspection ReturnTypeCanBeDeclaredInspection
	 */
	public function add_captcha( array $fields ) {
		if ( ! $this->is_login_limit_exceeded() ) {
			return $fields;
		}

		return parent::add_captcha( $fields );
	}

	/**
	 * Verify hCaptcha.
	 *
	 * @param array $submitted_data Submitted data.
	 * @param array $form_data      Form data.
	 *
	 * @return void
	 */
	public function verify( array $submitted_data, array $form_data = [] ) {
		if ( ! $this->is_login_limit_exceeded() ) {
			return;
		}

		parent::verify( $submitted_data, $form_data );
	}
}
