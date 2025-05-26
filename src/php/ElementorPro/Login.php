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

namespace HCaptcha\ElementorPro;

use ElementorPro\Modules\Forms\Widgets\Login as ElementorProLogin;
use Elementor\Element_Base;
use HCaptcha\Abstracts\LoginBase;
use HCaptcha\Helpers\HCaptcha;

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

		add_action( 'elementor/frontend/widget/before_render', [ $this, 'before_render' ] );
		add_action( 'elementor/frontend/widget/after_render', [ $this, 'add_elementor_login_hcaptcha' ] );

		add_action( 'wp_head', [ $this, 'print_inline_styles' ] );
	}

	/**
	 * Before frontend element render.
	 *
	 * @param Element_Base $element The element.
	 *
	 * @return void
	 */
	public function before_render( Element_Base $element ): void {
		if ( ! is_a( $element, ElementorProLogin::class ) ) {
			return;
		}

		ob_start();
	}

	/**
	 * After frontend element render.
	 *
	 * @param Element_Base $element The element.
	 *
	 * @return void
	 */
	public function add_elementor_login_hcaptcha( Element_Base $element ): void {
		if ( ! is_a( $element, ElementorProLogin::class ) ) {
			return;
		}

		$form = (string) ob_get_clean();

		if ( ! $this->is_login_limit_exceeded() ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $form;

			return;
		}

		$hcaptcha = '';

		// Check the login status, because class is always loading when Elementor Pro is active.
		if ( hcaptcha()->settings()->is( 'elementor_pro_status', 'login' ) ) {
			ob_start();
			$this->add_captcha();

			$hcaptcha = (string) ob_get_clean();
			$hcaptcha = '<div class="elementor-field-group elementor-column elementor-col-100">' . $hcaptcha . '</div>';
		}

		ob_start();

		/**
		 * Display hCaptcha signature.
		 */
		do_action( 'hcap_signature' );

		$signatures = (string) ob_get_clean();

		$pattern     = '/(<div class="elementor-field-group.+?<button type="submit")/s';
		$replacement = $hcaptcha . $signatures . "\n$1";
		$form        = preg_replace( $pattern, $replacement, $form );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $form;
	}

	/**
	 * Print inline styles.
	 *
	 * @return void
	 * @noinspection CssUnusedSymbol
	 */
	public function print_inline_styles(): void {
		/* language=CSS */
		$css = '
	.elementor-widget-login .h-captcha {
		margin-bottom: 0;
	}
';

		HCaptcha::css_display( $css );
	}
}
