<?php
/**
 * Base class file.
 *
 * @package hcaptcha-wp
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */

namespace HCaptcha\BeaverBuilder;

use HCaptcha\Abstracts\LoginBase;

/**
 * Class Base.
 */
abstract class Base extends LoginBase {
	/**
	 * Script handle.
	 */
	const HANDLE = 'hcaptcha-beaver-builder';

	/**
	 * Add hooks.
	 *
	 * @return void
	 */
	protected function init_hooks() {
		add_filter( 'fl_builder_render_module_content', [ $this, 'add_hcaptcha' ], 10, 2 );
		add_action( 'wp_print_footer_scripts', [ $this, 'enqueue_scripts' ], 9 );
	}

	/**
	 * Enqueue Beaver Builder script.
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		if ( ! hcaptcha()->form_shown ) {
			return;
		}

		$min = hcap_min_suffix();

		wp_enqueue_script(
			self::HANDLE,
			HCAPTCHA_URL . "/assets/js/hcaptcha-beaver-builder$min.js",
			[ 'jquery' ],
			HCAPTCHA_VERSION,
			true
		);
	}

	/**
	 * Add hcaptcha.
	 *
	 * @param string         $out    Button html.
	 * @param FLButtonModule $module Button module.
	 *
	 * @return string
	 * @noinspection PhpUnusedParameterInspection
	 */
	protected function add_hcap_form( $out, $module ) {
		$hcaptcha =
			'<div class="fl-input-group fl-hcaptcha">' .
			hcap_form( static::ACTION, static::NONCE ) .
			'</div>';

		$button_pattern = '<div class="fl-button-wrap';

		return str_replace( $button_pattern, $hcaptcha . $button_pattern, $out );
	}
}
