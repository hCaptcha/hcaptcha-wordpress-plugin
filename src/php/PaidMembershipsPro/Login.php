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
	 * Init hooks.
	 */
	protected function init_hooks() {
		parent::init_hooks();

		$pmpro_page_name = 'login';

		add_filter( 'pmpro_pages_shortcode_' . $pmpro_page_name, [ $this, 'add_pmpro_captcha' ] );

		// Check login status, because class is always loading when Divi theme is active.
		if ( hcaptcha()->settings()->is( 'paid_memberships_pro_status', 'login' ) ) {
			add_filter( 'wp_authenticate_user', [ $this, 'verify' ], 10, 2 );
		} else {
			add_filter( 'hcap_protect_form', [ $this, 'protect_form' ], 10, 3 );
		}
	}

	/**
	 * Add captcha.
	 *
	 * @param string|mixed $content Content of the PMPro login page.
	 *
	 * @return string|mixed
	 */
	public function add_pmpro_captcha( $content ) {
		if ( ! $this->is_login_limit_exceeded() ) {
			return $content;
		}

		$content = (string) $content;

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$action = isset( $_GET['action'] ) ?
			sanitize_text_field( wp_unslash( $_GET['action'] ) ) :
			'';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$error_messages = hcap_get_error_messages();

		if ( array_key_exists( $action, $error_messages ) ) {
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
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! ( isset( $_POST['pmpro_login_form_used'] ) && '1' === $_POST['pmpro_login_form_used'] ) ) {
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

		$code = array_search( $error_message, hcap_get_error_messages(), true ) ?: 'fail';

		return new WP_Error( $code, $error_message, 400 );
	}
}
