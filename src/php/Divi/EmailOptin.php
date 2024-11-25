<?php
/**
 * EmailOptin class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Divi;

use HCaptcha\Helpers\HCaptcha;

/**
 * Class EmailOptin.
 */
class EmailOptin {
	/**
	 * Script handle.
	 */
	public const HANDLE = 'hcaptcha-divi-email-optin';

	/**
	 * Nonce action.
	 */
	public const ACTION = 'hcaptcha_divi_email_optin';

	/**
	 * Nonce name.
	 */
	public const NONCE = 'hcaptcha_divi_email_optin_nonce';

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
		add_filter( 'et_pb_signup_form_field_html_submit_button', [ $this, 'add_captcha' ], 10, 2 );
		add_action( 'wp_ajax_et_pb_submit_subscribe_form', [ $this, 'verify' ], 9 );
		add_action( 'wp_ajax_nopriv_et_pb_submit_subscribe_form', [ $this, 'verify' ], 9 );
		add_action( 'wp_print_footer_scripts', [ $this, 'enqueue_scripts' ], 9 );
		add_filter( 'script_loader_tag', [ $this, 'add_type_module' ], 10, 3 );
	}

	/**
	 * Add hCaptcha to the email optin form.
	 *
	 * @param string|mixed $html              Submit button html.
	 * @param string       $single_name_field Whether a single name field is being used.
	 *                                        Only applicable when "$field" is 'name'.
	 *
	 * @return string
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_captcha( $html, string $single_name_field ): string {
		$args = [
			'action' => self::ACTION,
			'name'   => self::NONCE,
			'id'     => [
				'source'  => HCaptcha::get_class_source( __CLASS__ ),
				'form_id' => 'email_optin',
			],
		];

		$search  = '<p class="et_pb_newsletter_button_wrap">';
		$replace = HCaptcha::form( $args ) . "\n" . $search;

		// Insert hCaptcha.
		return str_replace( $search, $replace, (string) $html );
	}

	/**
	 * Verify email optin form.
	 *
	 * @return void
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function verify(): void {
		$error_message = hcaptcha_get_verify_message_html(
			self::NONCE,
			self::ACTION
		);

		if ( null === $error_message ) {
			return;
		}

		// It is a bug in Divi script, which doesn't handle the error message.
		et_core_die( esc_html( $error_message ) );
	}

	/**
	 * Enqueue Email Optin script.
	 *
	 * @return void
	 */
	public function enqueue_scripts(): void {
		if ( ! hcaptcha()->form_shown ) {
			return;
		}

		$min = hcap_min_suffix();

		wp_enqueue_script(
			self::HANDLE,
			HCAPTCHA_URL . "/assets/js/hcaptcha-divi-email-optin$min.js",
			[ 'jquery' ],
			HCAPTCHA_VERSION,
			true
		);
	}

	/**
	 * Add type="module" attribute to script tag.
	 *
	 * @param string|mixed $tag    Script tag.
	 * @param string       $handle Script handle.
	 * @param string       $src    Script source.
	 *
	 * @return string
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_type_module( $tag, string $handle, string $src ): string {
		$tag = (string) $tag;

		if ( self::HANDLE !== $handle ) {
			return $tag;
		}

		return HCaptcha::add_type_module( $tag );
	}
}
