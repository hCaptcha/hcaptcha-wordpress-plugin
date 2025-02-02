<?php
/**
 * Base class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\SupportCandy;

use HCaptcha\Helpers\HCaptcha;

/**
 * Class Base.
 */
abstract class Base {

	/**
	 * Whether supportcandy shortcode was used.
	 *
	 * @var bool
	 */
	private $did_support_candy_shortcode_tag_filter = false;

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
	private function init_hooks(): void {
		add_action( static::ADD_CAPTCHA_HOOK, [ $this, 'add_captcha' ] );
		add_action( 'wp_ajax_' . static::VERIFY_HOOK, [ $this, 'verify' ], 9 );
		add_action( 'wp_ajax_nopriv_' . static::VERIFY_HOOK, [ $this, 'verify' ], 9 );
		add_filter( 'do_shortcode_tag', [ $this, 'support_candy_shortcode_tag' ], 10, 4 );
		add_action( 'hcap_print_hcaptcha_scripts', [ $this, 'print_hcaptcha_scripts' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'wp_head', [ $this, 'print_inline_styles' ], 20 );
	}

	/**
	 * Add captcha to the form.
	 *
	 * @return void
	 */
	public function add_captcha(): void {
		$args = [
			'action' => static::ACTION,
			'name'   => static::NAME,
			'id'     => [
				'source'  => HCaptcha::get_class_source( static::class ),
				'form_id' => 'form',
			],
		];

		HCaptcha::form_display( $args );
	}

	/**
	 * Verify captcha.
	 *
	 * @return void
	 */
	public function verify(): void {
		$error_message = hcaptcha_get_verify_message(
			static::NAME,
			static::ACTION
		);

		if ( null !== $error_message ) {
			wp_send_json_error( $error_message, 400 );
		}
	}

	/**
	 * Catch Support Candy do shortcode tag filter.
	 *
	 * @param string|mixed $output Shortcode output.
	 * @param string       $tag    Shortcode name.
	 * @param array|string $attr   Shortcode attributes array or empty string.
	 * @param array        $m      Regular expression match array.
	 *
	 * @return string|mixed
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function support_candy_shortcode_tag( $output, string $tag, $attr, array $m ) {
		if ( 'supportcandy' === $tag ) {
			$this->did_support_candy_shortcode_tag_filter = true;
		}

		return $output;
	}

	/**
	 * Filter print hCaptcha scripts status and return true if SupportCandy shortcode was used.
	 *
	 * @param bool|mixed $status Print scripts status.
	 *
	 * @return bool|mixed
	 */
	public function print_hcaptcha_scripts( $status ) {
		return $this->did_support_candy_shortcode_tag_filter ? true : $status;
	}

	/**
	 * Enqueue Support Candy script.
	 *
	 * @return void
	 */
	public function enqueue_scripts(): void {
		$min = hcap_min_suffix();

		wp_enqueue_script(
			'hcaptcha-support-candy',
			HCAPTCHA_URL . "/assets/js/hcaptcha-support-candy$min.js",
			[ 'jquery', 'hcaptcha' ],
			HCAPTCHA_VERSION,
			true
		);
	}

	/**
	 * Print inline styles.
	 *
	 * @return void
	 * @noinspection CssUnusedSymbol
	 */
	public function print_inline_styles(): void {
		static $style_shown;

		if ( $style_shown ) {
			return;
		}

		$style_shown = true;

		/* language=CSS */
		$css = '
	form.wpsc-create-ticket .h-captcha {
		margin: 0 15px 15px 15px;
	}
';

		HCaptcha::css_display( $css );
	}
}
