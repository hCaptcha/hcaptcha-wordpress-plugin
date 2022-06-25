<?php
/**
 * LostPassword class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\UM;

/**
 * Class LostPassword
 */
class LostPassword extends Base {

	/**
	 * UM action.
	 */
	const UM_ACTION = 'um_reset_password_errors_hook';

	/**
	 * UM mode.
	 */
	const UM_MODE = 'password';

	/**
	 * Init hooks.
	 */
	protected function init_hooks() {
		parent::init_hooks();

		add_action( 'um_after_password_reset_fields', [ $this, 'um_after_password_reset_fields' ] );
	}

	/**
	 * Display hCaptcha after password reset fields.
	 *
	 * @param array $args Arguments.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function um_after_password_reset_fields( $args ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->display_captcha( '', self::UM_MODE );
	}
}
