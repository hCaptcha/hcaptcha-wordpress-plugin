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

// ACF Extended forms handled here:
// - Form containing a reCAPTCHA field replaced by hCaptcha.

// Blocksy forms handled here:
// - Newsletter Subscribe form.
// - Product Review form.
// - Waitlist form.

// Jetpack forms handled here:
// - Contact form (classic and block).

// Contact Form 7 forms handled here:
// - Contact form.

// CoBlocks forms handled here:
// - Form block.

// Elementor Pro forms handled here:
// - Form widget containing an hCaptcha field.

// Fluent Forms forms handled here:
// - Login form.
// - Multi-Step form.
// - Regular form.

// Formidable Forms forms handled here:
// - Form containing an hCaptcha field.

// Forminator forms handled here:
// - Multi-Step form.
// - Regular form.

// Gravity Forms forms handled here:
// - Form using automatic or embedded hCaptcha mode.
// - Gravity Perks Nested Form.

// Kadence forms handled here:
// - Advanced Form.
// - Form.

// MetForm forms handled here:
// - Form.

// Ninja Forms forms handled here:
// - Form containing an hCaptcha field.

// Otter Blocks forms handled here:
// - Form block.

// Password Protected forms handled here:
// - Site Password form.

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

// MailPoet forms handled here:
// - Subscription form.

// Ultimate Addons for Elementor forms handled here:
// - Login form.
// - Registration form.

// Ultimate Member forms handled here:
// - Login form.
// - Lost Password form.
// - Member Register form.

// Avada forms handled here:
// - Avada Form.

// Maintenance forms handled here:
// - Login form.

// Spectra forms handled here:
// - Form block without a reCAPTCHA field.

/**
 * Mark the request when ACF Extended renders a reCAPTCHA field replaced by hCaptcha.
 *
 * @return void
 */
function hcap_forms_mark_acfe_form(): void {
	$GLOBALS['hcap_forms_has_acfe_form'] = true;
}

add_action( 'acf/render_field/type=acfe_recaptcha', 'hcap_forms_mark_acfe_form', 0 );

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
 * Mark the request when WordPress renders a Blocksy newsletter block.
 *
 * @param string|mixed $block_content Block content.
 * @param array        $block         Block data.
 *
 * @return string|mixed
 */
function hcap_forms_mark_blocksy_newsletter_form( $block_content, array $block ) {
	if ( 'blocksy/newsletter' === ( $block['blockName'] ?? '' ) ) {
		$GLOBALS['hcap_forms_has_blocksy_form'] = true;
	}

	return $block_content;
}

add_filter( 'render_block', 'hcap_forms_mark_blocksy_newsletter_form', 0, 2 );

/**
 * Mark the request when Blocksy renders a product waitlist layer.
 *
 * @param array|mixed $layer Layer data.
 *
 * @return void
 */
function hcap_forms_mark_blocksy_waitlist_form( $layer ): void {
	if ( 'product_waitlist' === ( $layer['id'] ?? '' ) ) {
		$GLOBALS['hcap_forms_has_blocksy_form'] = true;
	}
}

add_action( 'blocksy:woocommerce:product:custom:layer', 'hcap_forms_mark_blocksy_waitlist_form', 0 );

/**
 * Mark the request when Blocksy renders a product review form.
 *
 * @param string|mixed $submit_field Submit field markup.
 *
 * @return string|mixed
 */
function hcap_forms_mark_blocksy_product_review_form( $submit_field ) {
	if ( ! function_exists( 'blocksy_manager' ) ) {
		return $submit_field;
	}

	$manager = blocksy_manager();
	$screen  = is_object( $manager ) ? ( $manager->screen ?? null ) : null;

	if ( is_object( $screen ) && method_exists( $screen, 'is_product' ) && $screen->is_product() ) {
		$GLOBALS['hcap_forms_has_blocksy_form'] = true;
	}

	return $submit_field;
}

add_filter( 'comment_form_submit_field', 'hcap_forms_mark_blocksy_product_review_form', 0 );

/**
 * Mark the request when Jetpack renders a contact form.
 *
 * @param string $html Jetpack contact form HTML.
 *
 * @return string
 */
function hcap_forms_mark_jetpack_form( string $html ): string {
	$GLOBALS['hcap_forms_has_jetpack_form'] = true;

	return $html;
}

add_filter( 'jetpack_contact_form_html', 'hcap_forms_mark_jetpack_form', 1 );

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
 * Mark the request when WordPress renders a CoBlocks form block.
 *
 * @param string|mixed $block_content Block content.
 * @param array        $block         Block data.
 *
 * @return string|mixed
 */
function hcap_forms_mark_coblocks_form( $block_content, array $block ) {
	if ( 'coblocks/form' === ( $block['blockName'] ?? '' ) ) {
		$GLOBALS['hcap_forms_has_coblocks_form'] = true;
	}

	return $block_content;
}

add_filter( 'render_block', 'hcap_forms_mark_coblocks_form', 0, 2 );

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
 * Mark the request when Fluent Forms renders a form.
 *
 * @param mixed $form Form data.
 *
 * @return mixed
 */
function hcap_forms_mark_fluent_form( $form ) {
	$GLOBALS['hcap_forms_has_fluent_form'] = true;

	return $form;
}

add_filter( 'fluentform/rendering_form', 'hcap_forms_mark_fluent_form', 0 );

/**
 * Mark the request when Fluent Forms renders a conversational form.
 *
 * Conversational forms load their interactive markup only after hCaptcha loads,
 * so they cannot use the built-in form interaction trigger.
 *
 * @return void
 */
function hcap_forms_mark_fluent_conversational_form(): void {
	$GLOBALS['hcap_forms_has_fluent_conversational_form'] = true;
}

add_action( 'fluentform/conversational_enqueue_assets', 'hcap_forms_mark_fluent_conversational_form', 0 );

/**
 * Mark the request when Formidable Forms renders an hCaptcha field.
 *
 * @param string|mixed $html  Field HTML.
 * @param array        $field Field data.
 *
 * @return string|mixed
 */
function hcap_forms_mark_formidable_form( $html, array $field ) {
	if ( 'captcha' !== ( $field['type'] ?? '' ) || false === strpos( (string) $html, 'h-captcha' ) ) {
		return $html;
	}

	$GLOBALS['hcap_forms_has_formidable_form'] = true;

	return $html;
}

add_filter( 'frm_replace_shortcodes', 'hcap_forms_mark_formidable_form', 0, 2 );

/**
 * Mark the request when Forminator renders a custom form.
 *
 * @param int|mixed $id        Form id.
 * @param string    $form_type Form type.
 *
 * @return void
 */
function hcap_forms_mark_forminator_form( $id, string $form_type ): void {
	if ( 'custom-form' !== $form_type ) {
		return;
	}

	$GLOBALS['hcap_forms_has_forminator_form'] = true;
}

add_action( 'forminator_before_form_render', 'hcap_forms_mark_forminator_form', 0, 2 );

/**
 * Mark the request when Gravity Forms renders a protected form.
 *
 * @param string|mixed $markup Form opening markup.
 * @param array        $form   Form data and settings.
 *
 * @return string
 */
function hcap_forms_mark_gravity_form( $markup, array $form ): string {
	$settings = function_exists( 'hcaptcha' ) ? hcaptcha()->settings() : null;

	if ( $settings && $settings->is( 'gravity_status', 'form' ) ) {
		$GLOBALS['hcap_forms_has_gravity_form'] = true;

		return (string) $markup;
	}

	$embed = $settings && $settings->is( 'gravity_status', 'embed' );

	foreach ( (array) ( $form['fields'] ?? [] ) as $field ) {
		$type    = is_object( $field ) ? ( $field->type ?? '' ) : '';
		$content = is_object( $field ) ? ( $field->content ?? '' ) : '';

		if ( ( $embed && 'hcaptcha' === $type ) || has_shortcode( $content, 'hcaptcha' ) ) {
			$GLOBALS['hcap_forms_has_gravity_form'] = true;

			break;
		}
	}

	return (string) $markup;
}

add_filter( 'gform_form_after_open', 'hcap_forms_mark_gravity_form', 0, 2 );

/**
 * Mark the request when WordPress renders a protected Kadence form block.
 *
 * @param string|mixed $block_content Block content.
 * @param array        $block         Block data.
 *
 * @return string|mixed
 */
function hcap_forms_mark_kadence_form( $block_content, array $block ) {
	$block_names = [
		'kadence/form',
		'kadence/advanced-form-captcha',
		'kadence/advanced-form-submit',
	];

	if ( in_array( $block['blockName'] ?? '', $block_names, true ) ) {
		$GLOBALS['hcap_forms_has_kadence_form'] = true;
	}

	return $block_content;
}

add_filter( 'render_block', 'hcap_forms_mark_kadence_form', 0, 2 );

/**
 * Mark the request when MetForm renders a submit button widget.
 *
 * @param mixed $content Widget content.
 * @param mixed $widget  Elementor widget.
 *
 * @return mixed
 */
function hcap_forms_mark_metform_form( $content, $widget ) {
	if ( ! is_object( $widget ) || ! method_exists( $widget, 'get_name' ) ) {
		return $content;
	}

	if ( 'mf-button' !== $widget->get_name() ) {
		return $content;
	}

	$GLOBALS['hcap_forms_has_metform_form'] = true;

	return $content;
}

add_filter( 'elementor/widget/render_content', 'hcap_forms_mark_metform_form', 0, 2 );

/**
 * Mark the request when Ninja Forms localizes an hCaptcha field.
 *
 * @param mixed $field Field data.
 *
 * @return mixed
 */
function hcap_forms_mark_ninja_form( $field ) {
	$GLOBALS['hcap_forms_has_ninja_form'] = true;

	return $field;
}

add_filter( 'ninja_forms_localize_field_hcaptcha-for-ninja-forms', 'hcap_forms_mark_ninja_form', 0 );

/**
 * Mark the request when WordPress renders an Otter form block.
 *
 * @param string|mixed $block_content Block content.
 * @param array        $block         Block data.
 *
 * @return string|mixed
 */
function hcap_forms_mark_otter_form( $block_content, array $block ) {
	if ( 'themeisle-blocks/form' === ( $block['blockName'] ?? '' ) ) {
		$GLOBALS['hcap_forms_has_otter_form'] = true;
	}

	return $block_content;
}

add_filter( 'render_block', 'hcap_forms_mark_otter_form', 0, 2 );

/**
 * Mark the request when Password Protected renders its site password form.
 *
 * @return void
 */
function hcap_forms_mark_password_protected_form(): void {
	$GLOBALS['hcap_forms_has_password_protected_form'] = true;
}

add_action( 'password_protected_below_password_field', 'hcap_forms_mark_password_protected_form', 0 );

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
 * Mark the request when WordPress renders a MailPoet subscription form.
 *
 * @param string|mixed $content Post content.
 *
 * @return string
 */
function hcap_forms_mark_mailpoet_form( $content ): string {
	$content = (string) $content;

	if ( preg_match( '~<form[\s\S]+?"data\[form_id]" value="\d+?"[\s\S]+?<input type="submit"[\s\S]+?</form>~', $content ) ) {
		$GLOBALS['hcap_forms_has_mailpoet_form'] = true;
	}

	return $content;
}

add_filter( 'the_content', 'hcap_forms_mark_mailpoet_form', 19 );

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
 * Mark the request when Ultimate Member renders an hCaptcha form field.
 *
 * @param mixed  $output Field HTML output.
 * @param string $mode   Form mode.
 *
 * @return mixed
 */
function hcap_forms_mark_ultimate_member_form( $output, string $mode ) {
	if ( ! in_array( $mode, [ 'login', 'password', 'register' ], true ) ) {
		return $output;
	}

	$GLOBALS['hcap_forms_has_ultimate_member_form'] = true;

	return $output;
}

add_filter( 'um_hcaptcha_form_edit_field', 'hcap_forms_mark_ultimate_member_form', 0, 2 );

/**
 * Mark the request when Ultimate Member renders its lost password form.
 *
 * @return void
 */
function hcap_forms_mark_ultimate_member_lost_password_form(): void {
	$GLOBALS['hcap_forms_has_ultimate_member_form'] = true;
}

add_action( 'um_after_password_reset_fields', 'hcap_forms_mark_ultimate_member_lost_password_form', 0 );

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
		! empty( $GLOBALS['hcap_forms_has_acfe_form'] ) ||
		! empty( $GLOBALS['hcap_forms_has_wp_comment_form'] ) ||
		! empty( $GLOBALS['hcap_forms_has_wp_password_form'] ) ||
		! empty( $GLOBALS['hcap_forms_has_blocksy_form'] ) ||
		! empty( $GLOBALS['hcap_forms_has_jetpack_form'] ) ||
		! empty( $GLOBALS['hcap_forms_has_cf7_form'] ) ||
		! empty( $GLOBALS['hcap_forms_has_coblocks_form'] ) ||
		! empty( $GLOBALS['hcap_forms_has_elementor_pro_form'] ) ||
		(
			! empty( $GLOBALS['hcap_forms_has_fluent_form'] ) &&
			empty( $GLOBALS['hcap_forms_has_fluent_conversational_form'] )
		) ||
		! empty( $GLOBALS['hcap_forms_has_formidable_form'] ) ||
		! empty( $GLOBALS['hcap_forms_has_forminator_form'] ) ||
		! empty( $GLOBALS['hcap_forms_has_gravity_form'] ) ||
		! empty( $GLOBALS['hcap_forms_has_kadence_form'] ) ||
		! empty( $GLOBALS['hcap_forms_has_metform_form'] ) ||
		! empty( $GLOBALS['hcap_forms_has_ninja_form'] ) ||
		! empty( $GLOBALS['hcap_forms_has_otter_form'] ) ||
		! empty( $GLOBALS['hcap_forms_has_password_protected_form'] ) ||
		! empty( $GLOBALS['hcap_forms_has_woocommerce_form'] ) ||
		! empty( $GLOBALS['hcap_forms_has_wpforms_form'] ) ||
		! empty( $GLOBALS['hcap_forms_has_divi_form'] ) ||
		! empty( $GLOBALS['hcap_forms_has_essential_addons_form'] ) ||
		! empty( $GLOBALS['hcap_forms_has_mailchimp_form'] ) ||
		! empty( $GLOBALS['hcap_forms_has_mailpoet_form'] ) ||
		! empty( $GLOBALS['hcap_forms_has_ultimate_addons_form'] ) ||
		! empty( $GLOBALS['hcap_forms_has_ultimate_member_form'] ) ||
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
