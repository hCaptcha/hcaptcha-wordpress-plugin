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
	protected const HANDLE = 'hcaptcha-beaver-builder';

	/**
	 * Add hooks.
	 *
	 * @return void
	 */
	protected function init_hooks(): void {
		add_action( 'wp_print_footer_scripts', [ $this, 'enqueue_scripts' ], 9 );
		add_filter( 'script_loader_tag', [ $this, 'add_type_module' ], 10, 3 );
	}

	/**
	 * Add hcaptcha.
	 *
	 * @param string                $out    Button html.
	 * @param FLBuilderModule|mixed $module Button module.
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
	public function enqueue_scripts(): void {
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
	 * Add type="module" attribute to script tag.
	 *
	 * @param string|mixed $tag    Script tag.
	 * @param string       $handle Script handle.
	 * @param string       $src    Script source.
	 *
	 * @return string
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_type_module( $tag, string $handle, string $src ): string {
		$tag = (string) $tag;

		if ( static::HANDLE !== $handle ) {
			return $tag;
		}

		return HCaptcha::add_type_module( $tag );
	}
}
