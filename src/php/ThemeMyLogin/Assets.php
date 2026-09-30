<?php
/**
 * Assets' class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\ThemeMyLogin;

use HCaptcha\Helpers\HCaptcha;

/**
 * Class Assets.
 */
final class Assets {

	/**
	 * Script handle.
	 */
	private const HANDLE = 'hcaptcha-theme-my-login';

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'wp_print_footer_scripts', [ self::class, 'enqueue_scripts' ], 9 );
		add_filter( 'script_loader_tag', [ self::class, 'add_type_module' ], 10, 3 );
	}

	/**
	 * Enqueue Theme My Login script.
	 *
	 * @return void
	 */
	public static function enqueue_scripts(): void {
		if ( ! hcaptcha()->form_shown ) {
			return;
		}

		$min = hcap_min_suffix();

		wp_enqueue_script(
			self::HANDLE,
			HCAPTCHA_URL . "/assets/js/hcaptcha-theme-my-login$min.js",
			[ 'jquery' ],
			HCAPTCHA_VERSION,
			true
		);
	}

	/**
	 * Add the type="module" attribute to the script tag.
	 *
	 * @param string|mixed $tag    Script tag.
	 * @param string       $handle Script handle.
	 * @param string       $src    Script source.
	 *
	 * @return string
	 * @noinspection PhpUnusedParameterInspection
	 */
	public static function add_type_module( $tag, string $handle, string $src ): string {
		$tag = (string) $tag;

		if ( self::HANDLE !== $handle ) {
			return $tag;
		}

		return HCaptcha::add_type_module( $tag );
	}
}
