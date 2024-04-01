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

	/**
	 * Nonce action.
	 */
	const ACTION = 'hcaptcha_essential_addons_register';

	/**
	 * Nonce name.
	 */
	const NONCE = 'hcaptcha_essential_addons_register_nonce';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Init hooks.
	 */
	protected function init_hooks() {
		add_action( 'eael/login-register/after-password-field', [ $this, 'add_register_hcaptcha' ] );
		add_action( 'eael/login-register/before-register', [ $this, 'verify' ] );

		add_action( 'wp_head', [ $this, 'print_inline_styles' ] );
	}

	/**
	 * Add hCaptcha to the login form.
	 *
	 * @param Widget_Base $widget The widget.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_register_hcaptcha( Widget_Base $widget ) {
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
	public function verify() {
		$error_message = hcaptcha_verify_post(
			self::NONCE,
			self::ACTION
		);

		if ( null === $error_message ) {
			return;
		}

		if ( wp_doing_ajax() ) {
			wp_send_json_error( $error_message );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$widget_id = isset( $_POST['widget_id'] ) ? sanitize_text_field( wp_unslash( $_POST['widget_id'] ) ) : 0;

		setcookie( 'eael_login_error_' . $widget_id, $error_message );

		if ( isset( $_SERVER['HTTP_REFERER'] ) ) {
			wp_safe_redirect( wp_unslash( $_SERVER['HTTP_REFERER'] ) );

			exit();
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
	#eael-register-form .h-captcha {
		margin-top: 1rem;
		margin-bottom: 0;
	}
CSS;

		HCaptcha::css_display( $css );
	}
}
