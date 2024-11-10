<?php
/**
 * Base class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Kadence;

/**
 * Class Base.
 */
class Base {

	/**
	 * Whether hCaptcha was replaced.
	 *
	 * @var bool
	 */
	protected $has_hcaptcha = false;

	/**
	 * Add hooks.
	 *
	 * @return void
	 */
	public function init_hooks(): void {
		add_action( 'wp_print_footer_scripts', [ $this, 'dequeue_kadence_captcha_api' ], 8 );
	}

	/**
	 * Dequeue Kadence hcaptcha API script.
	 *
	 * @return void
	 */
	public function dequeue_kadence_captcha_api(): void {
		if ( ! $this->has_hcaptcha ) {
			return;
		}

		$handles = [
			'kadence-blocks-recaptcha',
			'kadence-blocks-google-recaptcha-v2',
			'kadence-blocks-google-recaptcha-v3',
			'kadence-blocks-hcaptcha',
		];

		foreach ( $handles as $handle ) {
			wp_dequeue_script( $handle );
			wp_deregister_script( $handle );
		}
	}
}
