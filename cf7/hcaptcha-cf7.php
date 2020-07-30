<?php
/**
 * CF7 form file.
 *
 * @package hcaptcha-wp
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( get_option( 'hcaptcha_api_key' ) ) || empty( get_option( 'hcaptcha_secret_key' ) ) ) {
	return;
}

/**
 * Enqueue CF7 script.
 */
function enqueue_hcap_cf7_script() {
	global $hcap_cf7;

	if ( ! $hcap_cf7 ) {
		return;
	}

	$hcaptcha_api_key = get_option( 'hcaptcha_api_key' );

	$script = "var widgetIds = [];
        var hcap_cf7LoadCallback = function() {
        var hcap_cf7Widgets = document.querySelectorAll('.hcap_cf7-h-captcha');
        for (var i = 0; i < hcap_cf7Widgets.length; ++i) {
            var hcap_cf7Widget = hcap_cf7Widgets[i];
            var widgetId = hcaptcha.render(hcap_cf7Widget.id, {
                'sitekey' : '" . esc_html( $hcaptcha_api_key ) . "'
                });
                widgetIds.push(widgetId);
            }
        };
        (function($) {
            $('.wpcf7').on('invalid.wpcf7 mailsent.wpcf7', function() {
                for (var i = 0; i < widgetIds.length; i++) {
                    hcaptcha.reset(widgetIds[i]);
                }
            });
        })(jQuery);";

	wp_add_inline_script( 'hcaptcha-script', $script );
}

add_action( 'wp_enqueue_scripts', 'enqueue_hcap_cf7_script' );

/**
 * Add CF7 form element.
 *
 * @param mixed $form CF7 form.
 *
 * @return string
 */
function hcap_cf7_wpcf7_form_elements( $form ) {

	/**
	 * The quickest and easiest way to add the hcaptcha shortcode if it's not added in the CF7 form fields.
	 */
	if ( strpos( $form, '[cf7-hcaptcha]' ) === false ) {
		$form = str_replace( '<input type="submit"', '[cf7-hcaptcha]<br><input type="submit"', $form );
	}
	$form = do_shortcode( $form );

	return $form;
}

add_filter( 'wpcf7_form_elements', 'hcap_cf7_wpcf7_form_elements' );

/**
 * CF7 hCaptcha shortcode.
 *
 * @param array $atts Attributes.
 *
 * @return string
 */
function hcap_cf7_shortcode( $atts ) {
	global $hcap_cf7;

	$hcap_cf7 = true;

	$hcaptcha_api_key = get_option( 'hcaptcha_api_key' );
	$hcaptcha_theme   = get_option( 'hcaptcha_theme' );
	$hcaptcha_size    = get_option( 'hcaptcha_size' );

	return (
		'<div id="hcap_cf7-' . uniqid() .
		'" class="h-captcha hcap_cf7-h-captcha" data-sitekey="' . esc_html( $hcaptcha_api_key ) .
		'" data-theme="' . esc_html( $hcaptcha_theme ) .
		'" data-size="' . esc_html( $hcaptcha_size ) . '"></div>' .
		'<span class="wpcf7-form-control-wrap hcap_cf7-h-captcha-invalid"></span>' .
		wp_nonce_field( 'hcaptcha_contact_form7', 'hcaptcha_contact_form7', true, false )
	);
}

add_shortcode( 'cf7-hcaptcha', 'hcap_cf7_shortcode' );

/**
 * Verify CF7 recaptcha.
 *
 * @param WPCF7_Validation $result Result.
 *
 * @return mixed
 */
function hcap_cf7_verify_recaptcha( $result ) {
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
	$data       = $submission->get_posted_data();
	$wpcf7_id   = filter_input( INPUT_POST, '_wpcf7', FILTER_VALIDATE_INT );
	if ( empty( $wpcf7_id ) ) {
		return $result;
	}

	$cf7_text         = do_shortcode( '[contact-form-7 id="' . $wpcf7_id . '"]' );
	$hcaptcha_api_key = get_option( 'hcaptcha_api_key' );
	if ( false === strpos( $cf7_text, $hcaptcha_api_key ) ) {
		return $result;
	}

	if ( empty( $data['h-captcha-response'] ) ) {
		$result->invalidate(
			array(
				'type' => 'captcha',
				'name' => 'hcap_cf7-h-captcha-invalid',
			),
			__( 'Please complete the captcha.', 'hcaptcha-for-forms-and-more' )
		);
	} else {
		$captcha_result = hcaptcha_request_verify( $data['h-captcha-response'] );
		if ( 'fail' === $captcha_result ) {
			$result->invalidate(
				array(
					'type' => 'captcha',
					'name' => 'hcap_cf7-h-captcha-invalid',
				),
				__( 'The Captcha is invalid.', 'hcaptcha-for-forms-and-more' )
			);
		}
	}

	return $result;
}

add_filter( 'wpcf7_validate', 'hcap_cf7_verify_recaptcha', 20, 2 );
