<?php
/**
 * LostPassword class file.
 *
 * @package hcaptcha-wp
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */

namespace HCaptcha\SimpleMembership;

use HCaptcha\Helpers\API;
use HCaptcha\Helpers\EntryData;
use HCaptcha\Helpers\HCaptcha;
use SimpleWpMembership;

/**
 * Class LostPassword.
 */
class LostPassword {

	/**
	 * Nonce action.
	 */
	private const ACTION = 'hcaptcha_simple_membership_register';

	/**
	 * Nonce name.
	 */
	private const NONCE = 'hcaptcha_simple_membership_register_nonce';

	/**
	 * Constructor.
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
		add_action( 'do_shortcode_tag', [ $this, 'add_hcaptcha' ], 10, 4 );
		add_filter( 'swpm_validate_pass_reset_form_submission', [ $this, 'verify' ] );
		add_action( 'wp', [ $this, 'verify_link_reset' ], 9 );
		add_action( 'wp_head', [ $this, 'print_inline_styles' ], 20 );
	}

	/**
	 * Verify the final reset form before Simple Membership changes the password.
	 *
	 * @return void
	 * @noinspection ForgottenDebugOutputInspection
	 */
	public function verify_link_reset(): void {
		// Simple Membership reads the unmodified HTTP field via filter_input().
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$submit = filter_input( INPUT_POST, 'swpm-password-reset-using-link' );

		if ( '0' === $submit || '' === $submit || ! is_string( $submit ) || is_admin() ) {
			return;
		}

		$error_message = $this->verify();

		if ( '' === $error_message ) {
			return;
		}

		global $simple_membership;

		if ( ! $simple_membership instanceof SimpleWpMembership || ! remove_action( 'wp', [ $simple_membership, 'handle_wp_tasks' ] ) ) {
			wp_die( esc_html( $error_message ), '', [ 'response' => 403 ] );
		}

		// Match the native error display in reset_password_using_link.php.
		set_transient( 'swpm-passsword-reset-error', esc_html( $error_message ), MINUTE_IN_SECONDS );
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
		if ( 'swpm_reset_form' !== $tag ) {
			return $output;
		}

		$args = [
			'action' => self::ACTION,
			'name'   => self::NONCE,
			'id'     => $this->get_expected_id(),
		];

		$hcaptcha = HCaptcha::form( $args );

		$pattern     = '/(<div class="swpm-pw-reset-submit-button">)/';
		$replacement = $hcaptcha . "\n$1";

		// Insert hCaptcha.
		return (string) preg_replace( $pattern, $replacement, $output );
	}

	/**
	 * Verify a password reset form.
	 *
	 * @return string
	 */
	public function verify(): string {
		// The native password-reset handler sanitizes this named field.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$email = isset( $_POST['swpm_reset_email'] ) && is_scalar( $_POST['swpm_reset_email'] ) ? sanitize_email( wp_unslash( (string) $_POST['swpm_reset_email'] ) ) : '';

		$error_message = API::verify_post(
			self::NONCE,
			self::ACTION,
			$this->get_expected_id(),
			EntryData::with_emails( [], [ $email ] )
		);

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
	#swpm-pw-reset-form .h-captcha {
		margin: 10px 0;
	}
';

		HCaptcha::css_display( $css );
	}

	/**
	 * Get the expected form identity.
	 *
	 * @return array
	 */
	private function get_expected_id(): array {
		return [
			'source'  => HCaptcha::get_class_source( __CLASS__ ),
			'form_id' => 'lost_password',
		];
	}
}
