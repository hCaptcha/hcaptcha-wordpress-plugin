<?php
/**
 * Base class file.
 *
 * @package hcaptcha-wp
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */

namespace HCaptcha\UltimateAddons;

use Elementor\Element_Base;
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
	protected const HANDLE = 'hcaptcha-ultimate-addons';

	/**
	 * Before frontend element render.
	 *
	 * @param Element_Base $element The element.
	 *
	 * @return void
	 */
	abstract public function before_render( Element_Base $element ): void;

	/**
	 * After frontend element render.
	 *
	 * @param Element_Base $element The element.
	 *
	 * @return void
	 */
	abstract public function add_hcaptcha( Element_Base $element ): void;

	/**
	 * Print inline styles.
	 *
	 * @return void
	 */
	abstract public function print_inline_styles(): void;

	/**
	 * Add hooks.
	 *
	 * @return void
	 */
	protected function init_hooks(): void {
		add_action( 'elementor/frontend/widget/before_render', [ $this, 'before_render' ] );
		add_action( 'elementor/frontend/widget/after_render', [ $this, 'add_hcaptcha' ] );

		add_action( 'wp_head', [ $this, 'print_inline_styles' ] );
		add_action( 'wp_print_footer_scripts', [ $this, 'enqueue_scripts' ], 9 );
		add_filter( 'script_loader_tag', [ $this, 'add_type_module' ], 10, 3 );
	}

	/**
	 * Add hcaptcha.
	 *
	 * @return string
	 */
	protected function get_hcap_form(): string {
		$form_id = false !== strpos( static::ACTION, 'login' ) ? 'login' : 'register';
		$args    = [
			'action' => static::ACTION,
			'name'   => static::NONCE,
			'id'     => [
				'source'  => HCaptcha::get_class_source( static::class ),
				'form_id' => $form_id,
			],
		];

		return (
			'<div class="elementor-field-group elementor-column elementor-col-100 elementor-hcaptcha">' .
			'<div class="uael-urf-field-wrapper">' .
			HCaptcha::form( $args ) .
			'</div>' .
			'</div>'
		);
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

		wp_dequeue_script( 'uael-google-recaptcha' );
		wp_deregister_script( 'uael-google-recaptcha' );

		$min = hcap_min_suffix();

		wp_enqueue_script(
			self::HANDLE,
			HCAPTCHA_URL . "/assets/js/hcaptcha-ultimate-addons$min.js",
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
