<?php
/**
 * General class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Wordfence;

use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Helpers\Request;
use HCaptcha\WP\Login;
use HCaptcha\WP\Register;

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
		add_filter( 'hcap_wp_login_protection_enabled', [ $this, 'filter_wp_login_protection_enabled' ] );

		if ( hcaptcha()->settings()->is( 'wordfence_status', 'login' ) ) {
			// Disable reCAPTCHA compatibility, otherwise a Wordfence login script fails and cannot show 2FA.
			hcaptcha()->settings()->set( 'recaptcha_compat_off', [ 'on' ] );

			add_action( 'login_enqueue_scripts', [ $this, 'remove_wordfence_recaptcha_script' ], 20 );
			add_filter( 'wordfence_ls_require_captcha', [ $this, 'block_wordfence_recaptcha' ] );

			if ( $this->has_native_hcaptcha_support() ) {
				add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );
			}
		} else {
			add_action( 'plugins_loaded', [ $this, 'remove_wp_login_hcaptcha_hooks' ] );
		}

		add_action( 'login_head', [ $this, 'print_inline_styles' ], 20 );
	}

	/**
	 * Apply the current Wordfence setting to signed native login protection.
	 *
	 * @param bool $enabled Whether native login protection is enabled.
	 *
	 * @return bool
	 */
	public function filter_wp_login_protection_enabled( bool $enabled ): bool {
		return $enabled && hcaptcha()->settings()->is( 'wordfence_status', 'login' );
	}

	/**
	 * Remove Wordfence CAPTCHA scripts from protected native registration.
	 *
	 * @return void
	 */
	public function remove_wordfence_recaptcha_script(): void {
		// The Wordfence login AJAX preflight still requires its native CAPTCHA token.
		if ( ! $this->is_native_registration_protected() ) {
			return;
		}

		$handles = [
			'wordfence-ls-recaptcha',
			'wordfence-ls-hcaptcha',
			'wordfence-ls-login',
		];

		foreach ( $handles as $handle ) {
			wp_dequeue_script( $handle );
			wp_deregister_script( $handle );
		}
	}

	/**
	 * Require Wordfence CAPTCHA unless the current operation has an hCaptcha verifier.
	 *
	 * @param bool $required Whether Wordfence requires CAPTCHA.
	 *
	 * @return bool
	 */
	public function block_wordfence_recaptcha( bool $required ): bool {
		if ( ! $required || ! hcaptcha()->settings()->is( 'wordfence_status', 'login' ) ) {
			return $required;
		}

		return ! ( doing_action( 'register_post' ) && $this->is_native_registration_protected() );
	}

	/**
	 * Whether the native WordPress registration request has its hCaptcha verifier.
	 *
	 * @return bool
	 */
	private function is_native_registration_protected(): bool {
		$register = hcaptcha()->get( Register::class );
		// The registration verifier checks the action and its nonce before accepting the form.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$input_type = isset( $_POST['action'] ) ? INPUT_POST : INPUT_GET;

		if (
			! $register instanceof Register ||
			false === has_filter( 'registration_errors', [ $register, 'verify' ] ) ||
			! did_action( 'login_init' ) ||
			! did_action( 'login_form_register' ) ||
			! HCaptcha::did_filter( 'login_link_separator' ) ||
			'register' !== Request::filter_input( $input_type, 'action' ) ||
			! hcaptcha()->settings()->is( 'wp_status', 'register' )
		) {
			return false;
		}

		$id_info = HCaptcha::decode_id_info();

		if ( ! $id_info['valid'] ) {
			return true;
		}

		$owner = (string) apply_filters( 'hcap_registration_request_owner', '', $id_info['id'] );

		return ! $owner || Register::class === $owner;
	}

	/**
	 * Enqueue the Wordfence admin script.
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 *
	 * @return void
	 */
	public function admin_enqueue_scripts( string $hook_suffix ): void {
		if ( ! $this->has_native_hcaptcha_support() ) {
			return;
		}

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

		wp_localize_script(
			self::ADMIN_HANDLE,
			self::OBJECT,
			[
				'noticeLabel'       => __( 'hCaptcha integration is active', 'hcaptcha-for-forms-and-more' ),
				'noticeDescription' => __( 'Wordfence CAPTCHA settings still apply to forms without hCaptcha protection.', 'hcaptcha-for-forms-and-more' ),
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

	/**
	 * Determine whether Wordfence has native hCaptcha support.
	 *
	 * @return bool
	 */
	private function has_native_hcaptcha_support(): bool {
		return defined( 'WordfenceLS\\Controller_CAPTCHA::PROVIDER_HCAPTCHA' ) &&
			defined( 'WordfenceLS\\Controller_Settings::OPTION_ENABLE_HCAPTCHA' ) &&
			defined( 'WordfenceLS\\Controller_Settings::OPTION_HCAPTCHA_SITE_KEY' ) &&
			defined( 'WordfenceLS\\Controller_Settings::OPTION_HCAPTCHA_SECRET' );
	}
}
