<?php
/**
 * Login class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\PaidMembershipsPro;

use HCaptcha\Abstracts\LoginBase;

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

		add_filter( 'pmpro_pages_shortcode_login', [ $this, 'add_pmpro_captcha' ] );
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

		$hcaptcha = '';

		// Check the login status because class is always loading when PMPro is active.
		if ( hcaptcha()->settings()->is( 'paid_memberships_pro_status', 'login' ) ) {
			ob_start();
			$this->add_captcha();

			$hcaptcha = (string) ob_get_clean();
		}

		ob_start();

		/**
		 * Display hCaptcha signature.
		 */
		do_action( 'hcap_signature' );

		$signatures = (string) ob_get_clean();

		$search = '<p class="login-submit">';

		return str_replace( $search, $hcaptcha . $signatures . $search, $content );
	}
}
