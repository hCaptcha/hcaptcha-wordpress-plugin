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
	 * Script handle.
	 */
	private const HANDLE = 'hcaptcha-wpdiscuz-comment';

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
	 * Block reCaptcha.
	 *
	 * @return void
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function block_recaptcha(): void {
		$wpd_options = wpDiscuz()->options ?? null;

		if ( ! $wpd_options ) {
			return;
		}

		$wpd_recaptcha = (array) ( $wpd_options->recaptcha ?? [] );
		$wpd_recaptcha = array_merge(
			$wpd_recaptcha,
			[
				'siteKey'       => '',
				'showForGuests' => 0,
				'showForUsers'  => 0,
			]
		);

		// Block output of reCaptcha by wpDiscuz.
		$wpd_options->recaptcha = $wpd_recaptcha;
	}

	/**
	 * Dequeue reCaptcha script and enqueue the integration script.
	 *
	 * @return void
	 */
	public function enqueue_scripts(): void {
		wp_dequeue_script( 'wpdiscuz-google-recaptcha' );
		wp_deregister_script( 'wpdiscuz-google-recaptcha' );

		$min = hcap_min_suffix();

		wp_enqueue_script(
			self::HANDLE,
			HCAPTCHA_URL . "/assets/js/hcaptcha-wpdiscuz-comment$min.js",
			[],
			HCAPTCHA_VERSION,
			true
		);
	}
}
