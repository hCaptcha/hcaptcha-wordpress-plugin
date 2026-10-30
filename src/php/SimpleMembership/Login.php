<?php
/**
 * The Login class file.
 *
 * @package hcaptcha-wp
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */

namespace HCaptcha\SimpleMembership;

use HCaptcha\Abstracts\LoginBase;
use HCaptcha\Helpers\API;
use HCaptcha\Helpers\HCaptcha;
use SwpmAuth;

/**
 * Class Login.
 */
class Login extends LoginBase {

	/**
	 * Whether this native request has already recorded its failed login.
	 *
	 * @var bool
	 */
	private bool $native_failure_counted = false;

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	protected function init_hooks(): void {
		parent::init_hooks();

		add_filter( 'do_shortcode_tag', [ $this, 'add_hcaptcha' ], 10, 4 );
		add_filter( 'swpm_validate_login_form_submission', [ $this, 'verify' ] );
		add_action( 'swpm_authenticate_failed', [ $this, 'native_login_failed' ] );
		add_action( 'swpm_login_failed', [ $this, 'native_login_failed' ] );
		add_action( 'swpm_after_login', [ $this, 'native_login_success' ] );
		add_action( 'swpm_after_login_authentication', [ $this, 'native_login_success_after_auth' ], 20 );
		add_action( 'wp_head', [ $this, 'print_inline_styles' ], 20 );
	}

	/**
	 * Count paired Simple Membership failure events once per native request.
	 *
	 * @return void
	 */
	public function native_login_failed(): void {
		if ( $this->native_failure_counted || ! $this->is_native_login_request() ) {
			return;
		}

		$this->native_failure_counted = true;
		$this->login_failed( '' );
	}

	/**
	 * Reset attempts after a successful native member login.
	 *
	 * @return void
	 */
	public function native_login_success(): void {
		if ( ! $this->is_native_login_request() ) {
			return;
		}

		$this->login_attempts->reset( $this->ip, $this->login_attempts_reset_token );
	}

	/**
	 * Reset when SWPM returns early for a member already signed in to WordPress.
	 *
	 * The callback runs after SWPM's own handler, which can veto a login and
	 * log the member out. Standard successful logins reset on swpm_after_login,
	 * which also runs before SWPM redirects and exits.
	 *
	 * @return void
	 */
	public function native_login_success_after_auth(): void {
		if ( ! class_exists( 'SwpmAuth', false ) || ! SwpmAuth::get_instance()->is_logged_in() ) {
			return;
		}

		$this->native_login_success();
	}

	/**
	 * Whether Simple Membership is processing its own submitted credentials.
	 *
	 * @return bool
	 */
	private function is_native_login_request(): bool {
		// SWPM validates these fields; here they only identify its native login.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		return ( isset( $_POST['swpm_user_name'] ) || isset( $_POST['swpm_password'] ) ) &&
			! doing_action( 'wp_authenticate' ) &&
			! doing_filter( 'authenticate' );
	}

	/**
	 * Filters the output created by a shortcode callback and adds hCaptcha.
	 *
	 * @param string|mixed $output Shortcode output.
	 * @param string       $tag    Shortcode name.
	 * @param array|string $attr   Shortcode attribute array or empty string.
	 * @param array        $m      Regular expression match array.
	 *
	 * @return string|mixed
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_hcaptcha( $output, string $tag, $attr, array $m ) {
		if ( 'swpm_login_form' !== $tag ) {
			return $output;
		}

		if ( ! $this->is_login_limit_exceeded() ) {
			return $output;
		}

		ob_start();

		$this->add_captcha();
		$hcaptcha = (string) ob_get_clean();

		ob_start();

		/**
		 * Display hCaptcha signature.
		 */
		do_action( 'hcap_signature' );

		$signatures = (string) ob_get_clean();

		$pattern     = '/(<div class="swpm-login-submit")/';
		$replacement = $hcaptcha . $signatures . "\n$1";

		// Insert hCaptcha.
		return (string) preg_replace( $pattern, $replacement, $output );
	}

	/**
	 * Verify a login form.
	 *
	 * @param string|mixed $error_message Error message.
	 *
	 * @return string
	 */
	public function verify( $error_message ): string {
		if ( ! $this->is_login_limit_exceeded() ) {
			return (string) $error_message;
		}

		$error_message = API::verify_post( self::NONCE, self::ACTION, $this->get_expected_id() );

		return (string) $error_message;
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
	#swpm-login-form .h-captcha {
		margin: 10px 0;
	}
';

		HCaptcha::css_display( $css );
	}
}
