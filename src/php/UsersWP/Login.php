<?php
/**
 * Login class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\UsersWP;

use HCaptcha\Abstracts\LoginBase;
use WP_Error;
use WP_User;

/**
 * Class Login
 */
class Login extends LoginBase {

	/**
	 * Nonce action.
	 */
	const ACTION = 'hcaptcha_users_wp_login';

	/**
	 * Nonce name.
	 */
	const NONCE = 'hcaptcha_users_wp_login_nonce';

	/**
	 * UsersWP action.
	 */
	const USERS_WP_ACTION = 'login';

	/**
	 * Init hooks.
	 */
	protected function init_hooks() {
		parent::init_hooks();

		add_action( 'uwp_template_before', [ $this, 'uwp_template_before' ] );
		add_action( 'uwp_template_after', [ $this, 'uwp_template_after' ] );
		add_filter( 'uwp_validate_result', [ $this, 'verify' ], 10, 3 );
		add_action( 'wp_enqueue_scripts', [ Common::class, 'enqueue_scripts' ] );
	}

	/**
	 * Start output buffer at the beginning of the template.
	 *
	 * @param string $name Template name.
	 *
	 * @return void
	 */
	public function uwp_template_before( string $name ) {
		if ( self::USERS_WP_ACTION !== $name ) {
			return;
		}

		ob_start();
	}

	/**
	 * Get output buffer at the end of the template and add captcha.
	 *
	 * @param string $name Template name.
	 *
	 * @return void
	 */
	public function uwp_template_after( string $name ) {
		if ( self::USERS_WP_ACTION !== $name ) {
			return;
		}

		$template = (string) ob_get_clean();

		ob_start();

		$this->add_captcha();

		$captcha = (string) ob_get_clean();
		$search  = '<button type="submit"';

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo str_replace( $search, $captcha . $search, $template );
	}

	/**
	 * Verify login form.
	 *
	 * @param array|WP_Error|mixed $result Validation result.
	 * @param string               $action Action name.
	 * @param array                $data POST data.
	 *
	 * @return WP_User|WP_Error
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function verify( $result, $action, $data ) {
		if ( self::USERS_WP_ACTION !== $action ) {
			return $result;
		}

		if ( ! $this->is_login_limit_exceeded() ) {
			return $result;
		}

		$error_message = hcaptcha_get_verify_message_html(
			self::NONCE,
			self::ACTION
		);

		if ( null === $error_message ) {
			return $result;
		}

		return new WP_Error( 'invalid_hcaptcha', $error_message, 400 );
	}
}
