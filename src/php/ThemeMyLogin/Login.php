<?php
/**
 * The Login class file.
 *
 * @package hcaptcha-wp
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */

namespace HCaptcha\ThemeMyLogin;

use HCaptcha\Abstracts\LoginBase;
use HCaptcha\Helpers\API;
use Theme_My_Login_Form;
use WP_Error;
use WP_User;

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
		Assets::init();

		add_action( 'login_form', [ $this, 'add_captcha' ] );
		add_action( 'tml_render_form', [ $this, 'set_form_transport' ], 10, 2 );
		add_filter( 'wp_authenticate_user', [ $this, 'verify' ], 10, 2 );
	}

	/**
	 * Refresh adaptive login forms so a newly required challenge can be rendered.
	 *
	 * @param string              $name Form name.
	 * @param Theme_My_Login_Form $form Native form.
	 *
	 * @return void
	 */
	public function set_form_transport( string $name, Theme_My_Login_Form $form ): void {
		if ( 'login' !== $name || $this->is_login_limit_exceeded() ) {
			return;
		}

		$form->add_attribute( 'data-ajax', 0 );
	}

	/**
	 * Add captcha.
	 *
	 * @return void
	 */
	public function add_captcha(): void {
		if ( ! did_action( 'tml_render_form' ) ) {
			return;
		}

		parent::add_captcha();
	}

	/**
	 * Verify a login form.
	 *
	 * @param WP_User|WP_Error $user     WP_User or WP_Error object if a previous callback failed authentication.
	 * @param string           $password Password to check against the user.
	 *
	 * @return WP_User|WP_Error
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function verify( $user, string $password ) {
		if ( ! doing_action( 'tml_action_login' ) && ! doing_action( 'tml_action_ajax_login' ) ) {
			return $user;
		}

		if ( ! $this->is_login_limit_exceeded() ) {
			return $user;
		}

		$error_message = API::verify_post_html(
			self::NONCE,
			self::ACTION,
			$this->get_expected_id()
		);

		if ( null === $error_message ) {
			return $user;
		}

		return new WP_Error( 'invalid_hcaptcha', $error_message, 400 );
	}
}
