<?php
/**
 * Login class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\PaidMembershipsPro;

use HCaptcha\Abstracts\LoginBase;
use HCaptcha\Helpers\Request;
use HCaptcha\Helpers\Utils;

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

		add_filter( 'pmpro_pages_custom_template_path', [ $this, 'filter_custom_template_path' ], 10, 2 );
		add_filter( 'pmpro_pages_shortcode_login', [ $this, 'add_pmpro_captcha' ] );
	}

	/**
	 * Filter custom template path.
	 *
	 * @param array|mixed $default_templates Default templates.
	 * @param string      $page_name         Page name.
	 *
	 * @return mixed
	 */
	public function filter_custom_template_path( $default_templates, string $page_name ) {
		if ( 'login' === $page_name ) {
			// Remove LoginOut action to avoid hCaptcha duplicating.
			Utils::instance()->remove_action_regex(
				'/LoginOut::add_wp_login_out_hcaptcha/',
				'login_form_middle'
			);
		}

		return $default_templates;
	}

	/**
	 * Add captcha.
	 *
	 * @param string|mixed $content Content of the PMPro login page.
	 *
	 * @return string
	 */
	public function add_pmpro_captcha( $content ): string {
		$content = (string) $content;

		if ( ! $this->is_login_limit_exceeded() ) {
			return $content;
		}

		$action         = Request::filter_input( INPUT_GET, 'action' );
		$error_messages = hcap_get_error_messages();

		if ( array_key_exists( $action, $error_messages ) ) {
			$search        = '<div class="pmpro_card pmpro_login_wrap">';
			$error_message = '<div class="pmpro_message pmpro_error">' . $error_messages[ $action ] . '</div>';
			$content       = str_replace( $search, $error_message . $search, $content );
		}

		$hcaptcha = '';

		// Check the login status because the class is always loading when PMPro is active.
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
