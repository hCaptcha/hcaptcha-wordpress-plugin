<?php
/**
 * Register class file.
 *
 * @package hcaptcha-wp
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUnusedParameterInspection */

namespace HCaptcha\LoginSignupPopup;

use HCaptcha\Helpers\HCaptcha;
use WP_Error;

/**
 * Class Register
 */
class Register {

	/**
	 * Form ID.
	 */
	private const FORM_ID = 'register';

	/**
	 * Nonce action.
	 */
	private const ACTION = 'hcaptcha_login_signup_popup_register';

	/**
	 * Nonce name.
	 */
	private const NONCE = 'hcaptcha_login_signup_popup_register_nonce';

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
	private function init_hooks(): void {
		add_action( 'xoo_el_form_start', [ $this, 'form_start' ], 10, 2 );
		add_action( 'xoo_el_form_end', [ $this, 'add_login_signup_popup_hcaptcha' ], 10, 2 );
		add_filter( 'xoo_el_process_registration_errors', [ $this, 'verify' ], 10, 4 );
		add_action( 'wp_head', [ $this, 'print_inline_styles' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
	}

	/**
	 * Form start.
	 *
	 * @param string $form Form.
	 * @param array  $args Arguments.
	 *
	 * @return void
	 */
	public function form_start( string $form, array $args ): void {
		if ( self::FORM_ID !== $form ) {
			return;
		}

		ob_start();
	}

	/**
	 * Add hCaptcha.
	 *
	 * @param string $form Form.
	 * @param array  $args Arguments.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_login_signup_popup_hcaptcha( string $form, array $args ): void {
		if ( self::FORM_ID !== $form ) {
			return;
		}

		$hcaptcha_args = [
			'action' => self::ACTION,
			'name'   => self::NONCE,
			'id'     => [
				'source'  => HCaptcha::get_class_source( __CLASS__ ),
				'form_id' => self::FORM_ID,
			],
		];

		$hcaptcha = HCaptcha::form( $hcaptcha_args );

		$form = ob_get_clean();

		$search = '<button type="submit"';
		$form   = str_replace( $search, $hcaptcha . "\n" . $search, $form );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $form;
	}

	/**
	 * Verify form.
	 *
	 * @param WP_Error|mixed $error    Error.
	 * @param string         $username Username.
	 * @param string         $password Password.
	 * @param string         $email    Email.
	 *
	 * @return WP_Error
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function verify( $error, string $username, string $password, string $email ): WP_Error {
		if ( ! is_wp_error( $error ) ) {
			$error = new WP_Error();
		}

		$error_message = hcaptcha_verify_post(
			self::NONCE,
			self::ACTION
		);

		if ( null === $error_message ) {
			return $error;
		}

		$code = array_search( $error_message, hcap_get_error_messages(), true ) ?: 'fail';

		return new WP_Error( $code, $error_message, 400 );
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
	.xoo-el-form-container div[data-section="register"] .h-captcha {
		margin-bottom: 25px;
	}
';

		HCaptcha::css_display( $css );
	}

	/**
	 * Enqueue scripts.
	 *
	 * @return void
	 */
	public function enqueue_scripts(): void {
		$min = hcap_min_suffix();

		wp_enqueue_script(
			'hcaptcha-login-signup-popup',
			HCAPTCHA_URL . "/assets/js/hcaptcha-login-signup-popup$min.js",
			[ 'jquery' ],
			HCAPTCHA_VERSION,
			true
		);
	}
}
