<?php
/**
 * Jetpack class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Jetpack;

use WP_Error;

/**
 * Class Jetpack
 */
abstract class JetpackBase {

	/**
	 * Error message.
	 *
	 * @var string|null
	 */
	private $error_message;

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
		add_filter( 'the_content', [ $this, 'jetpack_form' ] );
		add_filter( 'widget_text', [ $this, 'jetpack_form' ], 0 );

		add_filter( 'widget_text', 'shortcode_unautop' );
		add_filter( 'widget_text', 'do_shortcode' );

		add_filter( 'jetpack_contact_form_is_spam', [ $this, 'jetpack_verify' ], 100, 2 );
	}

	/**
	 * Add hCaptcha to Jetpack form.
	 *
	 * @param string $content Content.
	 *
	 * @return string|string[]|null
	 */
	abstract public function jetpack_form( $content );

	/**
	 * Verify hCaptcha answer from the Jetpack Contact Form.
	 *
	 * @param bool $is_spam Is spam.
	 *
	 * @return bool|WP_Error
	 */
	public function jetpack_verify( $is_spam = false ) {
		$this->error_message = hcaptcha_get_verify_message(
			'hcaptcha_jetpack_nonce',
			'hcaptcha_jetpack'
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
	 * @param string $hcaptcha_content Content of hCaptcha.
	 *
	 * @return string
	 */
	public function error_message( $hcaptcha_content = '' ) {
		if ( null === $this->error_message ) {
			return $hcaptcha_content;
		}

		$message = sprintf(
			'<p id="hcap_error" class="error hcap_error">%s</p>',
			esc_html( $this->error_message )
		);

		return $message . $hcaptcha_content;
	}
}
