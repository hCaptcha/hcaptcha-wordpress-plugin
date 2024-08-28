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
	protected const ACTION = 'hcaptcha_jetpack';

	/**
	 * Nonce name.
	 */
	protected const NAME = 'hcaptcha_jetpack_nonce';

	/**
	 * Error message.
	 *
	 * @var string|null
	 */
	protected $error_message;

	/**
	 * Errored form hash.
	 *
	 * @var string|null
	 */
	protected $error_form_hash;

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
	private function init_hooks(): void {
		// This filter works for a Jetpack classic and block form on a page or in a template.
		add_filter( 'jetpack_contact_form_html', [ $this, 'add_captcha' ] );

		// This filter works for a Jetpack form in a classic widget.
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

		$this->error_form_hash = $this->get_submitted_form_hash();

		$error = new WP_Error();

		$error->add( 'invalid_hcaptcha', $this->error_message );
		add_filter( 'hcap_hcaptcha_content', [ $this, 'error_message' ] );

		return $error;
	}

	/**
	 * Print error message.
	 *
	 * @param string|mixed $hcaptcha The hCaptcha form.
	 * @param array        $atts     The hCaptcha shortcode attributes.
	 *
	 * @return string|mixed
	 */
	public function error_message( $hcaptcha = '', array $atts = [] ) {
		if ( null === $this->error_message ) {
			return $hcaptcha;
		}

		$hash = $atts['id']['form_id'] ?? '';
		$hash = str_replace( 'contact_', '', $hash );

		if ( $hash !== $this->error_form_hash ) {
			return $hcaptcha;
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

		return $hcaptcha . $message;
	}

	/**
	 * Print inline styles.
	 *
	 * @return void
	 * @noinspection CssUnusedSymbol CssUnusedSymbol.
	 */
	public function print_inline_styles(): void {
		$css = <<<CSS
	form.contact-form .grunion-field-wrap .h-captcha,
	form.wp-block-jetpack-contact-form .grunion-field-wrap .h-captcha {
		margin-bottom: 0;
	}
CSS;

		HCaptcha::css_display( $css );
	}

	/**
	 * Get form hash.
	 *
	 * @param string $form Jetpack form.
	 *
	 * @return string
	 */
	protected function get_form_hash( string $form ): string {
		return preg_match( "/name='contact-form-hash' value='(.+)'/", $form, $m )
			? '_' . $m[1]
			: '';
	}

	/**
	 * Get form hash.
	 *
	 * @return string|null
	 */
	private function get_submitted_form_hash(): ?string {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		return isset( $_POST['contact-form-hash'] )
			? sanitize_text_field( wp_unslash( $_POST['contact-form-hash'] ) )
			: null;
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}
}
