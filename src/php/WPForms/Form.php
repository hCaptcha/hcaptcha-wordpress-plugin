<?php
/**
 * Form class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\WPForms;

use HCaptcha\Helpers\HCaptcha;

/**
 * Class Form.
 */
class Form {

	/**
	 * Nonce action.
	 */
	const ACTION = 'hcaptcha_wpforms';

	/**
	 * Nonce name.
	 */
	const NAME = 'hcaptcha_wpforms_nonce';

	/**
	 * Form constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	private function init_hooks() {
		add_action( 'wpforms_display_submit_before', [ $this, 'add_captcha' ] );
		add_action( 'wpforms_process', [ $this, 'verify' ], 10, 3 );
		add_action( 'wp_head', [ $this, 'print_inline_styles' ], 20 );
	}

	/**
	 * Action that fires immediately before the submit button element is displayed.
	 *
	 * @link         https://wpforms.com/developers/wpforms_display_submit_before/
	 *
	 * @param array|mixed $form_data Form data and settings.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_captcha( $form_data ) {
		$args = [
			'action' => self::ACTION,
			'name'   => self::NAME,
			'id'     => [
				'source'  => HCaptcha::get_class_source( static::class ),
				'form_id' => (int) $form_data['id'],
			],
		];

		HCaptcha::form_display( $args );
	}

	/**
	 * Action that fires during form entry processing after initial field validation.
	 *
	 * @link         https://wpforms.com/developers/wpforms_process/
	 *
	 * @param array $fields    Sanitized entry field: values/properties.
	 * @param array $entry     Original $_POST global.
	 * @param array $form_data Form data and settings.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function verify( array $fields, array $entry, array $form_data ) {
		$error_message = hcaptcha_get_verify_message(
			self::NAME,
			self::ACTION
		);

		if ( null !== $error_message ) {
			wpforms()->get( 'process' )->errors[ $form_data['id'] ]['footer'] = $error_message;
		}
	}

	/**
	 * Print inline styles.
	 *
	 * @return void
	 * @noinspection CssUnusedSymbol
	 */
	public function print_inline_styles() {
		$css = <<<CSS
	div.wpforms-container-full .wpforms-form .h-captcha {
		position: relative;
		display: block;
		margin-bottom: 2rem;
		padding: 0;
		clear: both;
	}

	div.wpforms-container-full .wpforms-form .h-captcha[data-size="normal"] {
		width: 303px;
		height: 78px;
	}
	
	div.wpforms-container-full .wpforms-form .h-captcha[data-size="compact"] {
		width: 164px;
		height: 144px;
	}
	
	div.wpforms-container-full .wpforms-form .h-captcha[data-size="invisible"] {
		display: none;
	}

	div.wpforms-container-full .wpforms-form .h-captcha iframe {
		position: relative;
	}
CSS;

		HCaptcha::css_display( $css );
	}
}
