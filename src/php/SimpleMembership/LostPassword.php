<?php
/**
 * LostPassword class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\SimpleMembership;

use HCaptcha\Helpers\HCaptcha;

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
		add_action( 'wp_head', [ $this, 'print_inline_styles' ], 20 );
	}

	/**
	 * Filters the output created by a shortcode callback and adds hCaptcha.
	 *
	 * @param string|mixed $output Shortcode output.
	 * @param string       $tag    Shortcode name.
	 * @param array|string $attr   Shortcode attributes array or empty string.
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
			'id'     => [
				'source'  => HCaptcha::get_class_source( __CLASS__ ),
				'form_id' => 'lost_password',
			],
		];

		$hcaptcha = HCaptcha::form( $args );

		$pattern     = '/(<div class="swpm-pw-reset-submit-button">)/';
		$replacement = $hcaptcha . "\n$1";

		// Insert hCaptcha.
		return (string) preg_replace( $pattern, $replacement, $output );
	}

	/**
	 * Verify a login form.
	 *
	 * @return string
	 */
	public function verify(): string {
		$error_message = hcaptcha_verify_post(
			self::NONCE,
			self::ACTION
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
}
