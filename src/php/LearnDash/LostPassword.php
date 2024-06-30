<?php
/**
 * LostPassword class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\LearnDash;

use HCaptcha\Helpers\HCaptcha;

/**
 * Class LostPassword.
 */
class LostPassword {

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
		add_filter( 'do_shortcode_tag', [ $this, 'add_captcha' ], 10, 4 );
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
	public function add_captcha( $output, string $tag, $attr, array $m ) {
		if ( 'ld_reset_password' !== $tag ) {
			return $output;
		}

		$args = [
			'action' => HCAPTCHA_ACTION,
			'name'   => HCAPTCHA_NONCE,
			'auto'   => true,
			'id'     => [
				'source'  => HCaptcha::get_class_source( __CLASS__ ),
				'form_id' => 'lost_password',
			],
		];

		$search  = '<input type="submit"';
		$replace = HCaptcha::form( $args ) . $search;

		$output = (string) str_replace(
			$search,
			$replace,
			(string) $output
		);

		/** This action is documented in src/php/Sendinblue/Sendinblue.php */
		do_action( 'hcap_auto_verify_register', $output );

		return $output;
	}
}
