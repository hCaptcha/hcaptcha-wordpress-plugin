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
	use Base;

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	protected function init_hooks(): void {
		parent::init_hooks();

		add_action( 'eael/login-register/before-login-footer', [ $this, 'add_login_hcaptcha' ] );
		add_action( 'eael/login-register/before-login', [ $this, 'verify' ], 10, 3 );

		add_filter( 'hcap_print_hcaptcha_scripts', [ $this, 'print_hcaptcha_scripts' ], 0 );
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

		$this->base_verify();
	}
}
