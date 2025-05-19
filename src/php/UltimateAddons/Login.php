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

namespace HCaptcha\UltimateAddons;

use Elementor\Element_Base;
use HCaptcha\Helpers\HCaptcha;
use UltimateElementor\Modules\LoginForm\Widgets\LoginForm as UltimateElementorLogin;
use WP_Error;

/**
 * Class Login.
 */
class Login extends Base {

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	protected function init_hooks(): void {
		parent::init_hooks();

		add_action( 'elementor/frontend/widget/before_render', [ $this, 'before_render' ] );
		add_action( 'elementor/frontend/widget/after_render', [ $this, 'add_login_hcaptcha' ] );
		add_filter( 'wp_authenticate_user', [ $this, 'verify' ], 10, 2 );
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
		if ( ! is_a( $element, UltimateElementorLogin::class ) ) {
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
	public function add_login_hcaptcha( Element_Base $element ): void {
		if ( ! is_a( $element, UltimateElementorLogin::class ) ) {
			return;
		}

		$form = (string) ob_get_clean();

		if ( ! $this->is_login_limit_exceeded() ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $form;

			return;
		}

		$hcaptcha = $this->get_hcap_form();
		$hcaptcha =
			'<div class="elementor-field-group elementor-column elementor-col-100 elementor-hcaptcha">' .
			$hcaptcha .
			'</div>';

		$pattern     = '/(<div class="elementor-field-group.+?<button type="submit")/s';
		$replacement = $hcaptcha . "\n$1";
		$form        = preg_replace( $pattern, $replacement, $form );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $form;
	}

	/**
	 * Verify a login form.
	 *
	 * @param WP_User|WP_Error $user     WP_User or WP_Error object
	 *                                   if a previous callback failed authentication.
	 * @param string           $password Password to check against the user.
	 *
	 * @return WP_User|WP_Error|void
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function verify( $user, string $password ) {
		if ( ! doing_action( 'wp_ajax_nopriv_uael_login_form_submit' ) ) {
			return $user;
		}

		if ( ! $this->is_login_limit_exceeded() ) {
			return $user;
		}

		$error_message = hcaptcha_verify_post(
			self::NONCE,
			self::ACTION
		);

		if ( null === $error_message ) {
			return $user;
		}

		wp_send_json_error( $error_message );
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
	.uael-login-form .h-captcha {
		margin-bottom: 0;
	}
';

		HCaptcha::css_display( $css );
	}
}
