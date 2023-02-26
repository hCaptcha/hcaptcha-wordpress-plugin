<?php
/**
 * Base class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Asgaros;

/**
 * Class Base.
 */
abstract class Base {

	/**
	 * Base constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	private function init_hooks() {
		add_filter( static::ADD_CAPTCHA_HOOK, [ $this, 'add_captcha' ], 10, 4 );
		add_filter( static::VERIFY_HOOK, [ $this, 'verify' ] );
	}

	/**
	 * Add captcha to the new topic form.
	 *
	 * @param string       $output Shortcode output.
	 * @param string       $tag    Shortcode name.
	 * @param array|string $attr   Shortcode attributes array or empty string.
	 * @param array        $m      Regular expression match array.
	 */
	public function add_captcha( $output, $tag, $attr, $m ) {
		if ( 'forum' !== $tag ) {
			return $output;
		}

		$search = '<div class="editor-row editor-row-submit">';

		return str_replace(
			$search,
			'<div class="editor-row editor-row-hcaptcha">' .
			'<div class="right">' .
			hcap_form( static::ACTION, static::NAME ) .
			'</div>' .
			'</div>' .
			$search,
			$output
		);
	}

	/**
	 * Verify new topic captcha.
	 *
	 * @param bool $verified Verified.
	 *
	 * @return bool
	 */
	public function verify( $verified ) {
		global $asgarosforum;

		$error_message = hcaptcha_get_verify_message(
			static::NAME,
			static::ACTION
		);

		if ( null !== $error_message ) {
			$asgarosforum->add_notice( $error_message );

			return false;
		}

		return $verified;
	}
}
