<?php
/**
 * CF7 form class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\CF7;

use WPCF7_Submission;
use WPCF7_Validation;

/**
 * Class CF7.
 */
class CF7 {

	/**
	 * Content has cf7-hcaptcha shortcode flag.
	 *
	 * @var boolean
	 */
	private $has_shortcode = false;

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
		add_shortcode( 'cf7-hcaptcha', [ $this, 'cf7_hcaptcha_shortcode' ] );
		add_filter( 'wpcf7_validate', [ $this, 'verify_hcaptcha' ], 20, 2 );
		add_action( 'wp_print_footer_scripts', [ $this, 'enqueue_scrips' ], 9 );
	}

	/**
	 * Add CF7 form element.
	 *
	 * @param mixed $form CF7 form.
	 *
	 * @return string
	 */
	public function wpcf7_form_elements( $form ) {

		/**
		 * The quickest and easiest way to add the hcaptcha shortcode if it's not added in the CF7 form fields.
		 */
		if ( strpos( $form, '[cf7-hcaptcha]' ) === false ) {
			$form = str_replace( '<input type="submit"', '[cf7-hcaptcha]<br><input type="submit"', $form );
		}
		$form = do_shortcode( $form );

		return $form;
	}

	/**
	 * CF7 hCaptcha shortcode.
	 *
	 * @param array $atts Attributes.
	 *
	 * @return string
	 */
	public function cf7_hcaptcha_shortcode( $atts ) {
		$hcaptcha_api_key    = get_option( 'hcaptcha_api_key' );
		$hcaptcha_theme      = get_option( 'hcaptcha_theme' );
		$hcaptcha_size       = get_option( 'hcaptcha_size' );
		$this->has_shortcode = true;

		return (
			'<span class="wpcf7-form-control-wrap hcap_cf7-h-captcha-invalid">' .
			'<span id="' . uniqid( 'hcap_cf7-', true ) .
			'" class="wpcf7-form-control h-captcha hcap_cf7-h-captcha" data-sitekey="' . esc_html( $hcaptcha_api_key ) .
			'" data-theme="' . esc_html( $hcaptcha_theme ) .
			'" data-size="' . esc_html( $hcaptcha_size ) . '">' .
			'</span>' .
			'</span>' .
			wp_nonce_field( 'hcaptcha_contact_form7', 'hcaptcha_contact_form7', true, false )
		);
	}

	/**
	 * Verify CF7 recaptcha.
	 *
	 * @param WPCF7_Validation $result Result.
	 *
	 * @return mixed
	 */
	public function verify_hcaptcha( $result ) {
		// As of CF7 5.1.3, NONCE validation always fails. Returning to false value shows the error, found in issue #12
		// if (!isset($_POST['hcaptcha_contact_form7_nonce']) || (isset($_POST['hcaptcha_contact_form7_nonce']) && !wp_verify_nonce($_POST['hcaptcha_contact_form7'], 'hcaptcha_contact_form7'))) {
		// return false;
		// }
		//
		// CF7 author's comments: "any good effect expected from a nonce is limited when it is used for a publicly-open contact form that anyone can submit,
		// and undesirable side effects have been seen in some cases.”
		//
		// Our comments: hCaptcha passcodes are one-time use, so effectively serve as a nonce anyway.

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

		$cf7_text         = do_shortcode( '[contact-form-7 id="' . $wpcf7_id . '"]' );
		$hcaptcha_api_key = get_option( 'hcaptcha_api_key' );
		if ( empty( $hcaptcha_api_key ) || false === strpos( $cf7_text, $hcaptcha_api_key ) ) {
			return $result;
		}

		if ( empty( $data['h-captcha-response'] ) ) {
			$result->invalidate(
				[
					'type' => 'captcha',
					'name' => 'hcap_cf7-h-captcha-invalid',
				],
				__( 'Please complete the captcha.', 'hcaptcha-for-forms-and-more' )
			);
		} else {
			$captcha_result = hcaptcha_request_verify( $data['h-captcha-response'] );
			if ( 'fail' === $captcha_result ) {
				$result->invalidate(
					[
						'type' => 'captcha',
						'name' => 'hcap_cf7-h-captcha-invalid',
					],
					__( 'The Captcha is invalid.', 'hcaptcha-for-forms-and-more' )
				);
			}
		}

		return $result;
	}

	/**
	 * Enqueue CF7 scripts.
	 */
	public function enqueue_scrips() {
		if ( ! $this->has_shortcode ) {
			return;
		}

		wp_enqueue_script(
			'cf7-hcaptcha',
			HCAPTCHA_URL . '/assets/js/cf7.js',
			[],
			HCAPTCHA_VERSION,
			true
		);
	}
}
