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
		$error_message = hcaptcha_get_verify_message(
			'hcaptcha_jetpack_nonce',
			'hcaptcha_jetpack'
		);

		if ( null === $error_message ) {
			return $is_spam;
		}

		$error = new WP_Error();
		$error->add( 'invalid_hcaptcha', $error_message );
		add_filter( 'hcap_hcaptcha_content', 'hcap_hcaptcha_error_message', 10, 1 );

		return $error;
	}
}
