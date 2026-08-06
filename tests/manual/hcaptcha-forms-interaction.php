<?php
/**
 * Plugin Name: hCaptcha Forms Interaction
 * Description: Delays API loading until a supported protected form is used.
 * Version: 1.0.0
 *
 * @package hcaptcha-wp
 */

// WordPress Core forms handled here:
// - Login form.
// - Lost Password form.
// - Registration form.
// - Comment form.
// - Post/Page Password form.

// Contact Form 7 forms handled here:
// - Contact form.

// Elementor Pro forms handled here:
// - Form widget containing an hCaptcha field.

// WooCommerce forms handled here:
// - Add a Payment Method form.
// - Checkout form (classic and block).
// - Login form.
// - Lost Password form.
// - Order Tracking form.
// - Registration form.

// WPForms forms handled here:
// - Form using automatic or embedded hCaptcha mode.

// Divi theme, Divi Builder, and Extra theme forms handled here:
// - Comment form.
// - Contact form.
// - Email Optin form.
// - Login form.

// Essential Addons for Elementor forms handled here:
// - Login form.
// - Registration form.

// Mailchimp for WordPress forms handled here:
// - Subscription form.

// Ultimate Addons for Elementor forms handled here:
// - Login form.
// - Registration form.

// Avada forms handled here:
// - Avada Form.

// Maintenance forms handled here:
// - Login form.

// Spectra forms handled here:
// - Form block without a reCAPTCHA field.

/**
 * Mark the request when WordPress renders a comment form.
 *
 * @return void
 */
function hcap_forms_mark_wp_comment_form(): void {
	$GLOBALS['hcap_forms_has_wp_comment_form'] = true;
}

add_action( 'comment_form_top', 'hcap_forms_mark_wp_comment_form', 0 );

/**
 * Mark the request when WordPress renders a post/page password form.
 *
 * @param string|mixed $output Password form HTML output.
 *
 * @return string
 */
function hcap_forms_mark_wp_password_form( $output ): string {
	$GLOBALS['hcap_forms_has_wp_password_form'] = true;

	return $output;
}

add_filter( 'the_password_form', 'hcap_forms_mark_wp_password_form', 0 );

/**
 * Mark the request when Contact Form 7 renders a form.
 *
 * @param string|mixed $form Form HTML output.
 *
 * @return string
 */
function hcap_forms_mark_cf7_form( $form ): string {
	$GLOBALS['hcap_forms_has_cf7_form'] = true;

	return $form;
}

add_filter( 'wpcf7_form_elements', 'hcap_forms_mark_cf7_form', 0 );

/**
 * Mark the request when Elementor Pro renders an hCaptcha form field.
 *
 * @return void
 */
function hcap_forms_mark_elementor_pro_form(): void {
	$GLOBALS['hcap_forms_has_elementor_pro_form'] = true;
}

add_action( 'elementor_pro/forms/render_field/hcaptcha', 'hcap_forms_mark_elementor_pro_form', 0 );

/**
 * Mark the request when WooCommerce renders an action-based protected form.
 *
 * @return void
 */
function hcap_forms_mark_woocommerce_form(): void {
	$GLOBALS['hcap_forms_has_woocommerce_form'] = true;
}

// Classic Checkout form.
add_action( 'woocommerce_review_order_before_submit', 'hcap_forms_mark_woocommerce_form', 0 );

// Login form.
add_action( 'woocommerce_login_form', 'hcap_forms_mark_woocommerce_form', 0 );

// Lost Password form.
add_action( 'woocommerce_lostpassword_form', 'hcap_forms_mark_woocommerce_form', 0 );

// Registration form.
add_action( 'woocommerce_register_form', 'hcap_forms_mark_woocommerce_form', 0 );

/**
 * Mark the request when WooCommerce renders the Add Payment Method template.
 *
 * @param string $template_name Template name.
 *
 * @return void
 */
function hcap_forms_mark_woocommerce_add_payment_method( string $template_name ): void {
	if ( 'myaccount/form-add-payment-method.php' === $template_name ) {
		$GLOBALS['hcap_forms_has_woocommerce_form'] = true;
	}
}

add_action( 'woocommerce_before_template_part', 'hcap_forms_mark_woocommerce_add_payment_method', 0 );

/**
 * Mark the request when WordPress renders the WooCommerce Checkout block.
 *
 * @param string|mixed $block_content Block content.
 * @param array        $block         Block data.
 *
 * @return string
 */
function hcap_forms_mark_woocommerce_checkout_block( $block_content, array $block ): string {
	if ( 'woocommerce/checkout' === ( $block['blockName'] ?? '' ) ) {
		$GLOBALS['hcap_forms_has_woocommerce_form'] = true;
	}

	return (string) $block_content;
}

add_filter( 'render_block', 'hcap_forms_mark_woocommerce_checkout_block', 0, 2 );

/**
 * Mark the request when WordPress renders the WooCommerce Order Tracking shortcode.
 *
 * @param string|mixed $output Shortcode output.
 * @param string       $tag    Shortcode tag.
 *
 * @return string|mixed
 */
function hcap_forms_mark_woocommerce_order_tracking( $output, string $tag ) {
	if ( 'woocommerce_order_tracking' === $tag ) {
		$GLOBALS['hcap_forms_has_woocommerce_form'] = true;
	}

	return $output;
}

add_filter( 'do_shortcode_tag', 'hcap_forms_mark_woocommerce_order_tracking', 0, 2 );

/**
 * Mark the request when WPForms renders a form.
 *
 * @return void
 */
function hcap_forms_mark_wpforms_form(): void {
	$GLOBALS['hcap_forms_has_wpforms_form'] = true;
}

add_action( 'wpforms_frontend_output', 'hcap_forms_mark_wpforms_form', 0 );

/**
 * Mark the request when Divi renders a protected module form.
 *
 * @param mixed $output Module output.
 *
 * @return mixed
 */
function hcap_forms_mark_divi_module_form( $output ) {
	$GLOBALS['hcap_forms_has_divi_form'] = true;

	return $output;
}

// Comment form.
add_filter( 'et_pb_comments_shortcode_output', 'hcap_forms_mark_divi_module_form', 0 );

// Contact form.
add_filter( 'et_pb_contact_form_shortcode_output', 'hcap_forms_mark_divi_module_form', 0 );

// Email Optin form.
add_filter( 'et_pb_signup_form_field_html_submit_button', 'hcap_forms_mark_divi_module_form', 0 );

// Login form.
add_filter( 'et_pb_login_shortcode_output', 'hcap_forms_mark_divi_module_form', 0 );

/**
 * Mark the request when WordPress renders a protected Divi 5 form block.
 *
 * @param string|mixed $block_content Block content.
 * @param array        $block         Block data.
 *
 * @return string
 */
function hcap_forms_mark_divi_block_form( $block_content, array $block ): string {
	if ( in_array( $block['blockName'] ?? '', [ 'divi/contact-form', 'divi/login' ], true ) ) {
		$GLOBALS['hcap_forms_has_divi_form'] = true;
	}

	return (string) $block_content;
}

add_filter( 'render_block', 'hcap_forms_mark_divi_block_form', 0, 2 );

/**
 * Mark the request when Essential Addons renders a protected form.
 *
 * @return void
 */
function hcap_forms_mark_essential_addons_form(): void {
	$GLOBALS['hcap_forms_has_essential_addons_form'] = true;
}

// Login form.
add_action( 'eael/login-register/before-login-footer', 'hcap_forms_mark_essential_addons_form', 0 );

// Registration form.
add_action( 'eael/login-register/after-password-field', 'hcap_forms_mark_essential_addons_form', 0 );

/**
 * Mark the request when Mailchimp for WordPress renders a protected form.
 *
 * @param mixed $content Form HTML output.
 *
 * @return mixed
 */
function hcap_forms_mark_mailchimp_form( $content ) {
	$GLOBALS['hcap_forms_has_mailchimp_form'] = true;

	return $content;
}

add_filter( 'mc4wp_form_content', 'hcap_forms_mark_mailchimp_form', 0 );

/**
 * Mark the request when Ultimate Addons renders a protected form widget.
 *
 * @param mixed $element Elementor element.
 *
 * @return void
 */
function hcap_forms_mark_ultimate_addons_form( $element ): void {
	if ( ! is_object( $element ) || ! method_exists( $element, 'get_name' ) ) {
		return;
	}

	if ( ! in_array( $element->get_name(), [ 'uael-login-form', 'uael-registration-form' ], true ) ) {
		return;
	}

	$GLOBALS['hcap_forms_has_ultimate_addons_form'] = true;
}

add_action( 'elementor/frontend/widget/before_render', 'hcap_forms_mark_ultimate_addons_form', 0 );

/**
 * Mark the request when Avada renders a protected form.
 *
 * @return void
 */
function hcap_forms_mark_avada_form(): void {
	$GLOBALS['hcap_forms_has_avada_form'] = true;
}

add_action( 'fusion_form_after_open', 'hcap_forms_mark_avada_form', 0 );

/**
 * Mark the request when Maintenance renders its login form.
 *
 * @return void
 */
function hcap_forms_mark_maintenance_form(): void {
	$GLOBALS['hcap_forms_has_maintenance_form'] = true;
}

add_action( 'mtnc_after_main_container', 'hcap_forms_mark_maintenance_form', 0 );

/**
 * Mark the request when WordPress renders a protected Spectra Form block.
 *
 * @param string|mixed $block_content Block content.
 * @param array        $block         Block data.
 *
 * @return string
 */
function hcap_forms_mark_spectra_form( $block_content, array $block ): string {
	$block_content = (string) $block_content;

	if (
		'uagb/forms' === ( $block['blockName'] ?? '' ) &&
		false === strpos( $block_content, 'uagb-forms-recaptcha' )
	) {
		$GLOBALS['hcap_forms_has_spectra_form'] = true;
	}

	return $block_content;
}

add_filter( 'render_block', 'hcap_forms_mark_spectra_form', 0, 2 );

/**
 * Enable built-in form interaction on selected forms.
 *
 * @param bool|string $delay_api_event Current delay API event value.
 *
 * @return bool|string
 */
function hcap_forms_delay_api_event( $delay_api_event ) {
	if (
		! empty( $GLOBALS['hcap_forms_has_wp_comment_form'] ) ||
		! empty( $GLOBALS['hcap_forms_has_wp_password_form'] ) ||
		! empty( $GLOBALS['hcap_forms_has_cf7_form'] ) ||
		! empty( $GLOBALS['hcap_forms_has_elementor_pro_form'] ) ||
		! empty( $GLOBALS['hcap_forms_has_woocommerce_form'] ) ||
		! empty( $GLOBALS['hcap_forms_has_wpforms_form'] ) ||
		! empty( $GLOBALS['hcap_forms_has_divi_form'] ) ||
		! empty( $GLOBALS['hcap_forms_has_essential_addons_form'] ) ||
		! empty( $GLOBALS['hcap_forms_has_mailchimp_form'] ) ||
		! empty( $GLOBALS['hcap_forms_has_ultimate_addons_form'] ) ||
		! empty( $GLOBALS['hcap_forms_has_avada_form'] ) ||
		! empty( $GLOBALS['hcap_forms_has_maintenance_form'] ) ||
		! empty( $GLOBALS['hcap_forms_has_spectra_form'] )
	) {
		return true;
	}

	if ( 'wp-login.php' !== ( $GLOBALS['pagenow'] ?? '' ) ) {
		return $delay_api_event;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : 'login';

	if ( ! in_array( $action, [ 'login', 'lostpassword', 'register' ], true ) ) {
		return $delay_api_event;
	}

	return true;
}

add_filter( 'hcap_delay_api_event', 'hcap_forms_delay_api_event' );
