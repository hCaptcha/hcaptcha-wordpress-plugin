<?php
/**
 * CF7 form class file.
 *
 * @package hcaptcha-wp
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */

namespace HCaptcha\CF7;

use WPCF7_Submission;
use WPCF7_Validation;

/**
 * Class CF7.
 */
class CF7 {
	const HANDLE    = 'hcaptcha-cf7';
	const SHORTCODE = 'cf7-hcaptcha';
	const DATA_NAME = 'hcap-cf7';

	/**
	 * CF7 constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Init hooks.
	 */
	public function init_hooks() {
		add_filter( 'wpcf7_form_elements', [ $this, 'wpcf7_form_elements' ] );
		add_shortcode( self::SHORTCODE, [ $this, 'cf7_hcaptcha_shortcode' ] );
		add_filter( 'wpcf7_validate', [ $this, 'verify_hcaptcha' ], 20, 2 );
		add_action( 'wp_print_footer_scripts', [ $this, 'enqueue_scripts' ], 9 );
	}

	/**
	 * Add CF7 form element.
	 *
	 * @param string $form CF7 form.
	 *
	 * @return string
	 */
	public function wpcf7_form_elements( $form ) {
		if ( strpos( $form, '[' . self::SHORTCODE . ']' ) === false ) {
			$form = str_replace(
				'<input type="submit"',
				'[' . self::SHORTCODE . ']<input type="submit"',
				$form
			);
		}

		return do_shortcode( $form );
	}

	/**
	 * CF7 hCaptcha shortcode.
	 *
	 * @param array $atts Attributes.
	 *
	 * @return string
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function cf7_hcaptcha_shortcode( $atts ) {
		$settings          = hcaptcha()->settings();
		$hcaptcha_site_key = $settings->get_site_key();
		$hcaptcha_theme    = $settings->get( 'theme' );
		$hcaptcha_size     = $settings->get( 'size' );

		$callback = 'invisible' === $hcaptcha_size ? '" data-callback="hCaptchaSubmit' : '';

		hcaptcha()->form_shown = true;

		/**
		 * CF7 works via REST API, where current user is set to 0 (not logged in) if nonce is not present.
		 * However, we can add standard nonce for the action 'wp_rest' and rest_cookie_check_errors() provides the check.
		 */
		return (
			'<span class="wpcf7-form-control-wrap" data-name="' . self::DATA_NAME . '">' .
			'<span id="' . uniqid( 'hcap_cf7-', true ) .
			'" class="wpcf7-form-control h-captcha" data-sitekey="' . esc_html( $hcaptcha_site_key ) .
			'" data-theme="' . esc_html( $hcaptcha_theme ) .
			$callback .
			'" data-size="' . esc_html( $hcaptcha_size ) . '">' .
			'</span>' .
			'</span>' .
			wp_nonce_field( 'wp_rest', '_wpnonce', true, false )
		);
	}

	/**
	 * Verify CF7 recaptcha.
	 *
	 * @param WPCF7_Validation $result Result.
	 * @param WPCF7_FormTag    $tag    Tag.
	 *
	 * @return WPCF7_Validation
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function verify_hcaptcha( $result, $tag ) {
		$submission = WPCF7_Submission::get_instance();

		if ( null === $submission ) {
			return $result;
		}

		$data     = $submission->get_posted_data();
		$wpcf7_id = filter_var(
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
			isset( $_POST['_wpcf7'] ) ? wp_unslash( $_POST['_wpcf7'] ) : 0,
			FILTER_VALIDATE_INT
		);

		if ( empty( $wpcf7_id ) ) {
			return $result;
		}

		$cf7_text          = do_shortcode( '[contact-form-7 id="' . $wpcf7_id . '"]' );
		$hcaptcha_site_key = hcaptcha()->settings()->get_site_key();

		if ( empty( $hcaptcha_site_key ) || false === strpos( $cf7_text, $hcaptcha_site_key ) ) {
			return $result;
		}

		$response = empty( $data['h-captcha-response'] ) ? '' : $data['h-captcha-response'];

		$captcha_result = hcaptcha_request_verify( $response );

		if ( null !== $captcha_result ) {
			$result->invalidate(
				[
					'type' => 'hcaptcha',
					'name' => self::DATA_NAME,
				],
				$captcha_result
			);
		}

		return $result;
	}

	/**
	 * Enqueue CF7 scripts.
	 */
	public function enqueue_scripts() {
		if ( ! hcaptcha()->form_shown ) {
			return;
		}

		$min = hcap_min_suffix();

		wp_enqueue_script(
			self::HANDLE,
			HCAPTCHA_URL . "/assets/js/hcaptcha-cf7$min.js",
			[],
			HCAPTCHA_VERSION,
			true
		);
	}
}
