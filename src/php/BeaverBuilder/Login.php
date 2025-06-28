<?php
/**
 * Login class file.
 *
 * @package hcaptcha-wp
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */

namespace HCaptcha\BeaverBuilder;

use FLBuilderModule;
use HCaptcha\Helpers\API;
use WP_Error;
use WP_User;

/**
 * Class Login.
 */
class Login extends Base {

	/**
	 * Add hooks.
	 *
	 * @return void
	 */
	protected function init_hooks(): void {
		parent::init_hooks();

		add_filter( 'fl_builder_render_module_content', [ $this, 'add_beaver_builder_captcha' ], 10, 2 );
		add_filter( 'wp_authenticate_user', [ $this, 'verify' ], 10, 2 );
	}

	/**
	 * Filters the Beaver Builder Login Form submit button HTML and adds hcaptcha.
	 *
	 * @param string|mixed    $out    Button html.
	 * @param FLBuilderModule $module Button module.
	 *
	 * @return string|mixed
	 */
	public function add_beaver_builder_captcha( $out, FLBuilderModule $module ) {
		if ( ! $this->is_login_limit_exceeded() ) {
			return $out;
		}

		// Process login form only.
		if ( false === strpos( (string) $out, '<div class="fl-login-form' ) ) {
			return $out;
		}

		// Do not show hCaptcha on a logout form.
		if ( preg_match( '/<div class="fl-login-form.+?logout.*?>/', (string) $out ) ) {
			return $out;
		}

		return $this->add_hcap_form( (string) $out, $module );
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
		if ( ! doing_action( 'wp_ajax_nopriv_fl_builder_login_form_submit' ) ) {
			return $user;
		}

		if ( ! $this->is_login_limit_exceeded() ) {
			return $user;
		}

		$error_message = API::verify_post_html( self::NONCE, self::ACTION );

		if ( null === $error_message ) {
			return $user;
		}

		return new WP_Error( 'invalid_hcaptcha', $error_message, 400 );
	}
}
