<?php
/**
 * Form class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\SimpleBasicContactForm;

use HCaptcha\Helpers\HCaptcha;

/**
 * Class Form.
 */
class Form {

	/**
	 * Nonce action.
	 */
	private const ACTION = 'hcaptcha_simple_basic_contact_form';

	/**
	 * Nonce name.
	 */
	private const NONCE = 'hcaptcha_simple_basic_contact_form_nonce';

	/**
	 * Captcha error message.
	 *
	 * @var string|null
	 */
	private $error_message;

	/**
	 * Form constructor.
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
		add_filter( 'scf_filter_contact_form', [ $this, 'add_captcha' ] );
		add_filter( 'pre_do_shortcode_tag', [ $this, 'verify' ], 10, 4 );
	}

	/**
	 * Add captcha.
	 *
	 * @param string|mixed $scf_form Form html.
	 *
	 * @return string
	 */
	public function add_captcha( $scf_form ): string {
		if ( null !== $this->error_message ) {
			$scf_form = preg_replace(
				'#<p class="scf_error">.*?</p>#s',
				'<p class="scf_error">' . $this->error_message . '</p>',
				(string) $scf_form
			);
		}

		$args = [
			'action' => self::ACTION,
			'name'   => self::NONCE,
			'id'     => [
				'source'  => HCaptcha::get_class_source( __CLASS__ ),
				'form_id' => 0,
			],
		];

		$search = '<div class="scf-submit">';

		return str_replace(
			$search,
			HCaptcha::form( $args ) . $search,
			(string) $scf_form
		);
	}

	/**
	 * Filters the output created by a shortcode callback.
	 *
	 * @param string|mixed $output Shortcode output.
	 * @param string       $tag    Shortcode name.
	 * @param array|string $attr   Shortcode attributes array or empty string.
	 * @param array        $m      Regular expression match array.
	 *
	 * @return string|mixed
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function verify( $output, string $tag, $attr, array $m ) {
		if ( 'simple_contact_form' !== $tag ) {
			return $output;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$scf_key = isset( $_POST['scf-key'] ) ?
			sanitize_text_field( wp_unslash( $_POST['scf-key'] ) ) :
			'';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( 'process' !== $scf_key ) {
			return $output;
		}

		$this->error_message = hcaptcha_verify_post( self::NONCE, self::ACTION );

		if ( null !== $this->error_message ) {
			// Cause nonce error in the plugin.
			unset( $_POST['scf-nonce'] );
		}

		return $output;
	}
}
