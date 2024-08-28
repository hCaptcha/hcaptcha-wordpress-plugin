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
		$this->init_hooks();
	}

	/**
	 * Add hooks.
	 *
	 * @return void
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	protected function init_hooks(): void {
		if ( ! function_exists( 'wpDiscuz' ) ) {
			return;
		}

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

		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ], 11 );
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
