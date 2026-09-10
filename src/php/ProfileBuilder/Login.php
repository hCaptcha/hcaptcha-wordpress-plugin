<?php
/**
 * The Login class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\ProfileBuilder;

use HCaptcha\Abstracts\LoginBase;
use HCaptcha\Helpers\HCaptcha;
use WP_Error;
use WP_User;

/**
 * Class Login.
 */
class Login extends LoginBase {

	/**
	 * Whether Profile Builder is rendering its login form.
	 *
	 * @var bool
	 */
	private bool $profile_builder_login_form = false;

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	protected function init_hooks(): void {
		parent::init_hooks();

		add_filter( 'wppb_login_form_args', [ $this, 'mark_profile_builder_login_form' ], PHP_INT_MAX );
		add_filter( 'login_form_middle', [ $this, 'add_wppb_captcha' ], 10, 2 );
		add_filter( 'wppb_login_form_before_content_output', [ $this, 'finish_profile_builder_login_form' ], PHP_INT_MAX, 2 );
		add_filter( 'wp_authenticate_user', [ $this, 'verify' ], 10, 2 );
		add_action( 'wp_head', [ $this, 'print_inline_styles' ], 20 );
	}

	/**
	 * Mark the beginning of Profile Builder login form rendering.
	 *
	 * @param array|mixed $form_args Form arguments.
	 *
	 * @return array
	 */
	public function mark_profile_builder_login_form( $form_args ): array {
		$this->profile_builder_login_form = true;

		return (array) $form_args;
	}

	/**
	 * Add captcha.
	 *
	 * @param string|mixed $content   Content to display in the middle of the login form.
	 * @param array        $form_args  Form arguments.
	 *
	 * @return string|mixed
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_wppb_captcha( $content, array $form_args ) {
		$content = (string) $content;

		if ( ! $this->profile_builder_login_form || ! $this->is_login_limit_exceeded() ) {
			return $content;
		}

		return $content . $this->get_hcaptcha();
	}

	/**
	 * Finish Profile Builder login form rendering.
	 *
	 * @param string|mixed $login_form Login form HTML.
	 * @param array        $form_args  Form arguments.
	 *
	 * @return string
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function finish_profile_builder_login_form( $login_form, array $form_args ): string {
		$this->profile_builder_login_form = false;

		return (string) $login_form;
	}

	/**
	 * Verify a login form.
	 *
	 * @param WP_User|WP_Error $user     WP_User or WP_Error object
	 *                                   if a previous callback failed authentication.
	 * @param string           $password Password to check against the user.
	 *
	 * @return WP_User|WP_Error
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function verify( $user, string $password ) {
		if ( ! did_action( 'wppb_process_login_start' ) ) {
			return $user;
		}

		return $this->login_base_verify( $user, $password );
	}

	/**
	 * Print inline styles.
	 *
	 * @return void
	 * @noinspection CssUnusedSymbol
	 */
	public function print_inline_styles(): void {
		/* language=CSS */
		$css = '
	#wppb-loginform .h-captcha {
		margin-bottom: 14px;
	}
';

		HCaptcha::css_display( $css );
	}
}
