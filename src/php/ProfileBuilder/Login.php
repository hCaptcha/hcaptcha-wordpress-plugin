<?php
/**
 * Login class file.
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
	 * Init hooks.
	 *
	 * @return void
	 */
	protected function init_hooks(): void {
		parent::init_hooks();

		add_filter( 'wppb_login_form_before_content_output', [ $this, 'add_wppb_captcha' ], 10, 2 );
		add_filter( 'wp_authenticate_user', [ $this, 'verify' ], 10, 2 );
		add_action( 'wp_head', [ $this, 'print_inline_styles' ], 20 );
	}

	/**
	 * Add captcha.
	 *
	 * @param string|mixed $login_form Login form html.
	 * @param array        $form_args  Form arguments.
	 *
	 * @return string|mixed
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_wppb_captcha( $login_form, array $form_args ) {
		if ( ! $this->is_login_limit_exceeded() ) {
			return $login_form;
		}

		$login_form = (string) $login_form;

		$args = [
			'action' => self::ACTION,
			'name'   => self::NONCE,
			'id'     => [
				'source'  => HCaptcha::get_class_source( __CLASS__ ),
				'form_id' => 'login',
			],
		];

		$search = '<p class="login-submit">';

		return str_replace( $search, HCaptcha::form( $args ) . $search, $login_form );
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
		$css = <<<CSS
	#wppb-loginform .h-captcha {
		margin-bottom: 14px;
	}
CSS;

		HCaptcha::css_display( $css );
	}
}
