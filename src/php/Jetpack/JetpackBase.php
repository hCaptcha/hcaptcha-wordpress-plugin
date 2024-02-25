<?php
/**
 * Jetpack class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Jetpack;

use HCaptcha\Helpers\HCaptcha;
use WP_Error;

/**
 * Class Jetpack
 */
abstract class JetpackBase {

	/**
	 * Nonce action.
	 */
	const ACTION = 'hcaptcha_jetpack';

	/**
	 * Nonce name.
	 */
	const NAME = 'hcaptcha_jetpack_nonce';

	/**
	 * Error message.
	 *
	 * @var string|null
	 */
	protected $error_message;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Init hooks.
	 */
	private function init_hooks() {
		add_filter( 'the_content', [ $this, 'add_captcha' ] );
		add_filter( 'widget_text', [ $this, 'add_captcha' ], 0 );

		add_filter( 'widget_text', 'shortcode_unautop' );
		add_filter( 'widget_text', 'do_shortcode' );

		add_filter( 'jetpack_contact_form_is_spam', [ $this, 'verify' ], 100, 2 );

		add_action( 'wp_head', [ $this, 'print_inline_styles' ] );
	}

	/**
	 * Add hCaptcha to a Jetpack form.
	 *
	 * @param string|mixed $content Content.
	 *
	 * @return string
	 */
	abstract public function add_captcha( $content ): string;

	/**
	 * Verify hCaptcha answer from the Jetpack Contact Form.
	 *
	 * @param bool|mixed $is_spam Is spam.
	 *
	 * @return bool|WP_Error|mixed
	 */
	public function verify( $is_spam = false ) {
		$this->error_message = hcaptcha_get_verify_message(
			static::NAME,
			static::ACTION
		);

		if ( null === $this->error_message ) {
			return $is_spam;
		}

		$error = new WP_Error();
		$error->add( 'invalid_hcaptcha', $this->error_message );
		add_filter( 'hcap_hcaptcha_content', [ $this, 'error_message' ] );

		return $error;
	}

	/**
	 * Print error message.
	 *
	 * @param string|mixed $hcaptcha_content Content of hCaptcha.
	 *
	 * @return string|mixed
	 */
	public function error_message( $hcaptcha_content = '' ) {
		if ( null === $this->error_message ) {
			return $hcaptcha_content;
		}

		$message = <<< HTML
<div class="contact-form__input-error">
	<span class="contact-form__warning-icon">
		<span class="visually-hidden">Warning.</span>
		<i aria-hidden="true"></i>
	</span>
	<span>$this->error_message</span>
</div>
HTML;

		return $hcaptcha_content . $message;
	}

	/**
	 * Print inline styles.
	 *
	 * @return void
	 * @noinspection CssUnusedSymbol CssUnusedSymbol.
	 */
	public function print_inline_styles() {
		$css = <<<CSS
	form.contact-form .grunion-field-wrap .h-captcha,
	form.wp-block-jetpack-contact-form .grunion-field-wrap .h-captcha {
		margin-bottom: 0;
	}
CSS;

		HCaptcha::css_display( $css );
	}
}
