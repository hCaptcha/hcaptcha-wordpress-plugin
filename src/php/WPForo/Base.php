<?php
/**
 * Base class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\WPForo;

use HCaptcha\Helpers\HCaptcha;

/**
 * Class Base.
 */
abstract class Base {

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
		add_action( static::ADD_CAPTCHA_HOOK, [ $this, 'add_captcha' ], 99 );
		add_filter( static::VERIFY_HOOK, [ $this, 'verify' ] );
		add_filter( 'hcap_print_hcaptcha_scripts', [ $this, 'print_hcaptcha_scripts' ], 0 );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'wp_head', [ $this, 'print_inline_styles' ], 20 );
	}

	/**
	 * Add captcha to the new topic form.
	 *
	 * @param array|int $topic Topic info.
	 */
	public function add_captcha( $topic ): void {
		$form_id = 0;

		if ( current_action() === Reply::ADD_CAPTCHA_HOOK ) {
			$form_id = (int) $topic['topicid'];
		}

		if ( current_action() === NewTopic::ADD_CAPTCHA_HOOK ) {
			$form_id = 'new_topic';
		}

		$args = [
			'action' => static::ACTION,
			'name'   => static::NAME,
			'id'     => [
				'source'  => HCaptcha::get_class_source( static::class ),
				'form_id' => $form_id,
			],
		];

		HCaptcha::form_display( $args );
	}

	/**
	 * Verify new topic captcha.
	 *
	 * @param mixed $data Data.
	 *
	 * @return mixed|bool
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function verify( $data ) {
		$error_message = hcaptcha_get_verify_message(
			static::NAME,
			static::ACTION
		);

		if ( null !== $error_message ) {
			WPF()->notice->add( $error_message, 'error' );

			return false;
		}

		return $data;
	}

	/**
	 * Filter print hCaptcha scripts status and return true if WPForo template filter was used.
	 *
	 * @param bool|mixed $status Print scripts status.
	 *
	 * @return bool
	 */
	public function print_hcaptcha_scripts( $status ): bool {
		return HCaptcha::did_filter( 'wpforo_template' ) || $status;
	}

	/**
	 * Enqueue WPForo script.
	 *
	 * @return void
	 */
	public function enqueue_scripts(): void {
		$min = hcap_min_suffix();

		wp_enqueue_script(
			'hcaptcha-wpforo',
			HCAPTCHA_URL . "/assets/js/hcaptcha-wpforo$min.js",
			[ 'jquery', 'wpforo-frontend-js', 'hcaptcha' ],
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
	#wpforo #wpforo-wrap div .h-captcha {
		position: relative;
		display: block;
		margin-bottom: 2rem;
		padding: 0;
		clear: both;
	}

	#wpforo #wpforo-wrap.wpft-topic div .h-captcha,
	#wpforo #wpforo-wrap.wpft-forum div .h-captcha {
		margin: 0 -20px;
	}
';

		HCaptcha::css_display( $css );
	}
}
