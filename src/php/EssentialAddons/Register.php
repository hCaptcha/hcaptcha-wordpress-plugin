<?php
/**
 * Register class file.
 *
 * @package hcaptcha-wp
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedNamespaceInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\EssentialAddons;

use Elementor\Widget_Base;
use HCaptcha\Helpers\HCaptcha;

/**
 * Class Register.
 */
class Register {
	use Base;

	/**
	 * Nonce action.
	 */
	private const ACTION = 'hcaptcha_essential_addons_register';

	/**
	 * Nonce name.
	 */
	private const NONCE = 'hcaptcha_essential_addons_register_nonce';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	protected function init_hooks(): void {
		add_action( 'eael/login-register/after-password-field', [ $this, 'add_register_hcaptcha' ] );
		add_action( 'eael/login-register/before-register', [ $this, 'verify' ] );

		add_action( 'wp_head', [ $this, 'print_inline_styles' ] );

		add_action( 'hcap_print_hcaptcha_scripts', [ $this, 'print_hcaptcha_scripts' ] );
	}

	/**
	 * Add hCaptcha to the login form.
	 *
	 * @param Widget_Base $widget The widget.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_register_hcaptcha( Widget_Base $widget ): void {
		$args = [
			'action' => static::ACTION,
			'name'   => static::NONCE,
			'id'     => [
				'source'  => HCaptcha::get_class_source( static::class ),
				'form_id' => 'register',
			],
		];

		HCaptcha::form_display( $args );
	}

	/**
	 * Verify hCaptcha.
	 *
	 * @return void
	 */
	public function verify(): void {
		$this->base_verify();
	}

	/**
	 * Print inline styles.
	 *
	 * @return void
	 * @noinspection CssUnusedSymbol
	 */
	public function print_inline_styles(): void {
		$css = <<<CSS
	#eael-register-form .h-captcha {
		margin-top: 1rem;
		margin-bottom: 0;
	}
CSS;

		HCaptcha::css_display( $css );
	}
}
