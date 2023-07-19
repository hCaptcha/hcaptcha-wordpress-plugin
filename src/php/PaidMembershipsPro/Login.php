<?php
/**
 * Login class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\PaidMembershipsPro;

use HCaptcha\Abstracts\LoginBase;
use HCaptcha\Helpers\HCaptcha;
use WP_Error;
use WP_User;

/**
 * Class Login.
 */
class Login extends LoginBase {

	/**
	 * Nonce action.
	 */
	const ACTION = 'hcaptcha_login';

	/**
	 * Nonce name.
	 */
	const NONCE = 'hcaptcha_login_nonce';

	/**
	 * Init hooks.
	 */
	protected function init_hooks() {
		parent::init_hooks();

		$pmpro_page_name = 'login';

		add_filter( 'pmpro_pages_shortcode_' . $pmpro_page_name, [ $this, 'add_captcha' ] );
		add_action( 'wp_authenticate_user', [ $this, 'verify' ], 10, 2 );
	}

	/**
	 * Add captcha.
	 *
	 * @param string $content Content of the PMPro login page.
	 */
	public function add_captcha( string $content ) {
		if ( ! $this->is_login_limit_exceeded() ) {
			return $content;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$action = isset( $_GET['action'] ) ?
			sanitize_text_field( wp_unslash( $_GET['action'] ) ) :
			'';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$error_messages = hcap_get_error_messages();
		$hcap_error     = array_key_exists( $action, $error_messages );

		if ( $hcap_error ) {
			$search        = '<div class="pmpro_login_wrap">';
			$error_message = '<div class="pmpro_message pmpro_error">' . $error_messages[ $action ] . '</div>';
			$content       = str_replace( $search, $error_message . $search, $content );
		}

		$args = [
			'action' => self::ACTION,
			'name'   => self::NONCE,
			'id'     => [
				'source'  => HCaptcha::get_class_source( __CLASS__ ),
				'form_id' => 'login',
			],
		];

		$search = '<p class="login-submit">';

		return str_replace( $search, HCaptcha::form( $args ) . $search, $content );
	}

	/**
	 * Verify login form.
	 *
	 * @param WP_User|WP_Error $user     WP_User or WP_Error object if a previous
	 *                                   callback failed authentication.
	 * @param string           $password Password to check against the user.
	 *
	 * @return WP_User|WP_Error
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function verify( $user, string $password ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$pmpro_login_form_used = isset( $_POST['pmpro_login_form_used'] ) ?
			sanitize_text_field( wp_unslash( $_POST['pmpro_login_form_used'] ) ) :
			'';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( ! $pmpro_login_form_used ) {
			return $user;
		}

		if ( ! $this->is_login_limit_exceeded() ) {
			return $user;
		}

		$error_message = hcaptcha_verify_post(
			self::NONCE,
			self::ACTION
		);

		if ( null === $error_message ) {
			return $user;
		}

		$code = array_search( $error_message, hcap_get_error_messages(), true );
		$code = $code ?: 'fail';

		return new WP_Error( $code, $error_message, 400 );
	}
}
