<?php
/**
 * Base class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\WPDiscuz;

/**
 * Class Base.
 */
abstract class Base {

	/**
	 * Class constructor.
	 */
	public function __construct() {
		if ( ! function_exists( 'wpDiscuz' ) ) {
			return;
		}

		$this->init_hooks();
	}

	/**
	 * Add hooks.
	 *
	 * @return void
	 */
	protected function init_hooks(): void {
		add_action( 'init', [ $this, 'block_recaptcha' ], 12 );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ], 11 );
	}

	/**
	 * Block recaptcha.
	 *
	 * @return void
	 */
	public function block_recaptcha(): void {
		$wpd_recaptcha = wpDiscuz()->options->recaptcha;
		$wpd_recaptcha = array_merge(
			$wpd_recaptcha,
			[
				'siteKey'       => '',
				'showForGuests' => 0,
				'showForUsers'  => 0,
			]
		);

		// Block output of reCaptcha by wpDiscuz.
		wpDiscuz()->options->recaptcha = $wpd_recaptcha;
	}

	/**
	 * Dequeue recaptcha script.
	 *
	 * @return void
	 */
	public function enqueue_scripts(): void {
		wp_dequeue_script( 'wpdiscuz-google-recaptcha' );
		wp_deregister_script( 'wpdiscuz-google-recaptcha' );
	}
}
