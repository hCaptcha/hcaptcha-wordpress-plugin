<?php
/**
 * LostPassword class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\UM;

use HCaptcha\Helpers\HCaptcha;

/**
 * Class LostPassword
 */
class LostPassword extends Base {

	/**
	 * UM action.
	 */
	protected const UM_ACTION = 'um_reset_password_errors_hook';

	/**
	 * UM mode.
	 */
	public const UM_MODE = 'password';

	/**
	 * Keep recovery bound to its mode instead of a numeric form id.
	 *
	 * UM does not establish a trusted numeric form id for password resets.
	 *
	 * @param array $args Form arguments.
	 *
	 * @return void
	 */
	public function set_form_id( array $args ): void {
		// Recovery always uses the password mode identity.
	}

	/**
	 * Get the fixed recovery widget id.
	 *
	 * UM's password reset handler does not resolve a submitted form id. Neither
	 * posted ids nor numeric ids from custom templates select its policy.
	 *
	 * @param int $submitted_form_id Submitted form id.
	 *
	 * @return array
	 */
	protected function get_expected_id( int $submitted_form_id = 0 ): array {
		return [
			'source'  => HCaptcha::get_class_source( static::class ),
			'form_id' => self::UM_MODE,
		];
	}

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	protected function init_hooks(): void {
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
	public function um_after_password_reset_fields( array $args ): void {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->display_captcha( '', self::UM_MODE );
	}
}
