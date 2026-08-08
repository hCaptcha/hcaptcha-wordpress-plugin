<?php
/**
 * General class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Wordfence;

use HCaptcha\Helpers\HCaptcha;
use HCaptcha\WP\Login;

/**
 * Class General
 */
class General {

	/**
	 * Admin script handle.
	 */
	private const ADMIN_HANDLE = 'admin-wordfence';

	/**
	 * Script localization object.
	 */
	private const OBJECT = 'HCaptchaWordfenceObject';

	/**
	 * Class constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	protected function init_hooks(): void {
		if ( hcaptcha()->settings()->is( 'wordfence_status', 'login' ) ) {
			// Disable reCAPTCHA compatibility, otherwise a Wordfence login script fails and cannot show 2FA.
			hcaptcha()->settings()->set( 'recaptcha_compat_off', [ 'on' ] );

			add_action( 'login_enqueue_scripts', [ $this, 'remove_wordfence_recaptcha_script' ], 20 );
			add_filter( 'wordfence_ls_require_captcha', [ $this, 'block_wordfence_recaptcha' ] );
			add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );
		} else {
			add_action( 'plugins_loaded', [ $this, 'remove_wp_login_hcaptcha_hooks' ] );
		}

		add_action( 'login_head', [ $this, 'print_inline_styles' ], 20 );
	}

	/**
	 * Remove Wordfence login CAPTCHA scripts.
	 *
	 * @return void
	 */
	public function remove_wordfence_recaptcha_script(): void {
		$handles = [
			'wordfence-ls-recaptcha',
			'wordfence-ls-hcaptcha',
		];

		foreach ( $handles as $handle ) {
			wp_dequeue_script( $handle );
			wp_deregister_script( $handle );
		}
	}

	/**
	 * Do not require Wordfence captcha.
	 *
	 * @return false
	 */
	public function block_wordfence_recaptcha(): bool {

		return false;
	}

	/**
	 * Enqueue the Wordfence admin script.
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 *
	 * @return void
	 */
	public function admin_enqueue_scripts( string $hook_suffix ): void {
		$wordfence_pages = [
			'toplevel_page_WFLS',
			'wordfence_page_WFLS',
		];

		if ( ! in_array( $hook_suffix, $wordfence_pages, true ) ) {
			return;
		}

		$min = hcap_min_suffix();

		wp_enqueue_script(
			self::ADMIN_HANDLE,
			constant( 'HCAPTCHA_URL' ) . "/assets/js/admin-wordfence$min.js",
			[ 'jquery' ],
			constant( 'HCAPTCHA_VERSION' ),
			true
		);

		$notice = HCaptcha::get_hcaptcha_plugin_notice();

		wp_localize_script(
			self::ADMIN_HANDLE,
			self::OBJECT,
			[
				'noticeLabel'       => $notice['label'],
				'noticeDescription' => $notice['description'],
			]
		);
	}

	/**
	 * Block hCaptcha on WP login page.
	 *
	 * @return void
	 */
	public function remove_wp_login_hcaptcha_hooks(): void {
		$wp_login = hcaptcha()->get( Login::class );

		if ( ! $wp_login ) {
			return;
		}

		remove_action( 'login_form', [ $wp_login, 'add_captcha' ] );
		remove_filter( 'wp_authenticate_user', [ $wp_login, 'check_signature' ], PHP_INT_MAX );
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
	#loginform[style="position: relative;"] > .h-captcha {
	    visibility: hidden !important;
	}
';

		HCaptcha::css_display( $css );
	}
}
