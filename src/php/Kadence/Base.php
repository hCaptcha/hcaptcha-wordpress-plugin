<?php
/**
 * Base class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Kadence;

use HCaptcha\Helpers\Request;

/**
 * Class Base.
 */
class Base {

	/**
	 * Whether a form had any captcha (ReCaptcha, Turnstile, hCaptcha).
	 *
	 * @var bool
	 */
	protected bool $has_captcha = false;

	/**
	 * Add hooks.
	 *
	 * @return void
	 */
	public function init_hooks(): void {
		add_action( 'wp_print_footer_scripts', [ $this, 'dequeue_kadence_captcha_api' ], 8 );

		$submit_actions = [ 'kb_process_ajax_submit', 'kb_process_advanced_form_submit' ];
		$is_submission  = in_array( Request::filter_input( INPUT_POST, 'action' ), $submit_actions, true ) ||
			in_array( Request::filter_input( INPUT_GET, 'action' ), $submit_actions, true );

		if ( $is_submission || Request::is_frontend() ) {
			add_filter(
				'block_parser_class',
				static function () {
					return BlockParser::class;
				}
			);
		}
	}

	/**
	 * Dequeue Kadence hcaptcha API script.
	 *
	 * @return void
	 */
	public function dequeue_kadence_captcha_api(): void {
		if ( ! $this->has_captcha ) {
			return;
		}

		$handles = [
			'form'          => [ 'kadence-blocks-google-recaptcha-v2', 'kadence-blocks-google-recaptcha-v3' ],
			'advanced_form' => [ 'kadence-blocks-recaptcha', 'kadence-blocks-hcaptcha' ],
		];

		foreach ( $handles as $module => $module_handles ) {
			if ( ! hcaptcha()->settings()->is( 'kadence_status', $module ) ) {
				continue;
			}

			foreach ( $module_handles as $handle ) {
				wp_dequeue_script( $handle );
				wp_deregister_script( $handle );
			}
		}
	}
}
