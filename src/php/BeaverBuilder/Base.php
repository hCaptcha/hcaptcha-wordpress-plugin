<?php
/**
 * Base class file.
 *
 * @package hcaptcha-wp
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */

namespace HCaptcha\BeaverBuilder;

use FLBuilderModule;
use HCaptcha\Abstracts\LoginBase;
use HCaptcha\Helpers\HCaptcha;

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
		add_action( 'wp_print_footer_scripts', [ $this, 'enqueue_scripts' ], 9 );
	}

	/**
	 * Add hcaptcha.
	 *
	 * @param string          $out    Button html.
	 * @param FLBuilderModule $module Button module.
	 *
	 * @return string
	 * @noinspection PhpUnusedParameterInspection
	 */
	protected function add_hcap_form( string $out, $module ): string {
		$form_id = false !== strpos( static::ACTION, 'login' ) ? 'login' : 'contact';
		$args    = [
			'action' => static::ACTION,
			'name'   => static::NONCE,
			'id'     => [
				'source'  => HCaptcha::get_class_source( static::class ),
				'form_id' => $form_id,
			],
		];

		$hcaptcha =
			'<div class="fl-input-group fl-hcaptcha">' .
			HCaptcha::form( $args ) .
			'</div>';

		$button_pattern = '<div class="fl-button-wrap';

		return str_replace( $button_pattern, $hcaptcha . $button_pattern, $out );
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
}
