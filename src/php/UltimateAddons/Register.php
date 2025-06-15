<?php
/**
 * The Register class file.
 *
 * @package hcaptcha-wp
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedNamespaceInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\UltimateAddons;

use Elementor\Element_Base;
use HCaptcha\Helpers\API;
use HCaptcha\Helpers\HCaptcha;
use UltimateElementor\Modules\RegistrationForm\Widgets\RegistrationForm as UltimateElementorRegistration;

/**
 * Class Register.
 */
class Register extends Base {
	/**
	 * Nonce action.
	 */
	protected const ACTION = 'hcaptcha_ultimate_addons_register';

	/**
	 * Nonce name.
	 */
	protected const NONCE = 'hcaptcha_ultimate_addons_register_nonce';

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	protected function init_hooks(): void {
		parent::init_hooks();

		add_action( 'wp_ajax_uael_register_user', [ $this, 'verify' ], 0 );
		add_action( 'wp_ajax_nopriv_uael_register_user', [ $this, 'verify' ], 0 );
	}

	/**
	 * Before frontend element render.
	 *
	 * @param Element_Base $element The element.
	 *
	 * @return void
	 */
	public function before_render( Element_Base $element ): void {
		if ( ! is_a( $element, UltimateElementorRegistration::class ) ) {
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
	public function add_hcaptcha( Element_Base $element ): void {
		if ( ! is_a( $element, UltimateElementorRegistration::class ) ) {
			return;
		}

		$form = (string) ob_get_clean();

		$hcaptcha    = $this->get_hcap_form();
		$pattern     = '/(<div class="uael-reg-form-submit)/';
		$replacement = $hcaptcha . "\n$1";
		$form        = preg_replace( $pattern, $replacement, $form );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $form;
	}

	/**
	 * Verify a register form.
	 */
	public function verify(): void {
		$error_message = API::verify_post( self::NONCE, self::ACTION );

		if ( null === $error_message ) {
			return;
		}

		wp_send_json_error( [ 'hCaptchaError' => $error_message ] );
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
	.uael-registration-form .h-captcha {
		margin-top: 1rem;
		margin-bottom: 0;
	}
';

		HCaptcha::css_display( $css );
	}
}
