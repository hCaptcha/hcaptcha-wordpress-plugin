<?php
/**
 * Login class file.
 *
 * @package hcaptcha-wp
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedNamespaceInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\EssentialAddons;

use Elementor\Widget_Base;
use Essential_Addons_Elementor\Classes\Bootstrap;
use HCaptcha\Abstracts\LoginBase;

/**
 * Class Login.
 */
class Login extends LoginBase {

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	protected function init_hooks(): void {
		parent::init_hooks();

		add_action( 'eael/login-register/before-login-footer', [ $this, 'add_login_hcaptcha' ] );
		add_action( 'eael/login-register/before-login', [ $this, 'verify' ], 10, 3 );
	}

	/**
	 * Add hCaptcha to the login form.
	 *
	 * @param Widget_Base $widget The widget.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_login_hcaptcha( Widget_Base $widget ): void {
		$this->add_captcha();
	}

	/**
	 * Verify hCaptcha.
	 *
	 * @param array     $post      The $_POST data.
	 * @param array     $settings  Elementor widget settings.
	 * @param Bootstrap $bootstrap Bootstrap instance.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function verify( array $post, array $settings, Bootstrap $bootstrap ): void {
		if ( ! $this->is_login_limit_exceeded() ) {
			return;
		}

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
}
