<?php
/**
 * Base class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\CF7;

/**
 * Base class.
 */
class Base {

	/**
	 * Whether hCaptcha should be auto-added to any form.
	 *
	 * @var bool
	 */
	protected $mode_auto = false;

	/**
	 * Whether hCaptcha can be embedded into form in the form editor.
	 *
	 * @var bool
	 */
	protected $mode_embed = false;

	/**
	 * Whether to show the live hCaptcha form in the form editor.
	 *
	 * @var bool
	 */
	protected $mode_live = false;

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
	public function init_hooks(): void {
		$this->mode_auto  = hcaptcha()->settings()->is( 'cf7_status', 'form' );
		$this->mode_embed = hcaptcha()->settings()->is( 'cf7_status', 'embed' );
		$this->mode_live  = hcaptcha()->settings()->is( 'cf7_status', 'live' );

		if ( ! ( $this->mode_auto || $this->mode_embed ) ) {
			return;
		}

		add_filter( 'option_wpcf7', [ $this, 'option_wpcf7' ], 10, 2 );
	}

	/**
	 * Filter wpcf7 option and deactivate reCaptcha service.
	 *
	 * @param array|mixed  $value  Option value.
	 * @param string|mixed $option Option name.
	 *
	 * @return array|mixed
	 */
	public function option_wpcf7( $value, $option ) {
		if ( 'wpcf7' !== $option ) {
			return $value;
		}

		if ( isset( $value['recaptcha'] ) ) {
			unset( $value['recaptcha'] );
		}

		return $value;
	}

	/**
	 * Whether the form contains a Stripe element.
	 *
	 * @param string $output Output.
	 *
	 * @return bool
	 */
	protected function has_stripe_element( string $output ): bool {
		return false !== strpos( $output, '<div class="wpcf7-stripe">' );
	}
}
