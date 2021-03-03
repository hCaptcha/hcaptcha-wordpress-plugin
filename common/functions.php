<?php
/**
 * Functions file.
 *
 * @package hcaptcha-wp
 */

/**
 * Get hCaptcha form.
 *
 * @return false|string
 */
function hcap_form() {
	ob_start();
	hcap_form_display();
	return ob_get_clean();
}

/**
 * Display hCaptcha form.
 */
function hcap_form_display() {
	$hcaptcha_api_key = get_option( 'hcaptcha_api_key' );
	$hcaptcha_theme   = get_option( 'hcaptcha_theme' );
	$hcaptcha_size    = get_option( 'hcaptcha_size' );
	?>
	<div
		class="h-captcha"
		data-sitekey="<?php echo esc_html( $hcaptcha_api_key ); ?>"
		data-theme="<?php echo esc_html( $hcaptcha_theme ); ?>"
		data-size="<?php echo esc_html( $hcaptcha_size ); ?>">
	</div>
	<?php
}

/**
 * Display hCaptcha shortcode.
 *
 * @param string $content hcaptcha shortcode content.
 *
 * @return string
 */
function hcap_shortcode( $content = '' ) {
	$hcaptcha = apply_filters( 'hcap_hcaptcha_content', hcap_form() );

	return $content . $hcaptcha;
}

add_shortcode( 'hcaptcha', 'hcap_shortcode' );

/**
 * List of hcap options.
 */
function hcap_options() {
	return array(
		'hcaptcha_api_key'                  => array(
			'label' => __( 'hCaptcha Site Key', 'hcaptcha-for-forms-and-more' ),
			'type'  => 'text',
		),
		'hcaptcha_secret_key'               => array(
			'label' => __( 'hCaptcha Secret Key', 'hcaptcha-for-forms-and-more' ),
			'type'  => 'password',
		),
		'hcaptcha_theme'                    => array(
			'label'   => __( 'hCaptcha Theme', 'hcaptcha-for-forms-and-more' ),
			'type'    => 'select',
			'options' => array(
				'light' => __( 'Light', 'hcaptcha-for-forms-and-more' ),
				'dark'  => __( 'Dark', 'hcaptcha-for-forms-and-more' ),
			),
		),
		'hcaptcha_size'                     => array(
			'label'   => __( 'hCaptcha Size', 'hcaptcha-for-forms-and-more' ),
			'type'    => 'select',
			'options' => array(
				'normal'  => __( 'Normal', 'hcaptcha-for-forms-and-more' ),
				'compact' => __( 'Compact', 'hcaptcha-for-forms-and-more' ),
			),
		),
		'hcaptcha_language'                 => array(
			'label'       => __( 'Override Language Detection (optional)', 'hcaptcha-for-forms-and-more' ),
			'type'        => 'text',
			'description' => __(
				'Info on <a href="https://hcaptcha.com/docs/languages" target="_blank">language codes</a>.',
				'hcaptcha-for-forms-and-more'
			),
		),
		'hcaptcha_recaptchacompat'          => array(
			'label' => __( 'Disable reCAPTCHA Compatibility (use if including both hCaptcha and reCAPTCHA on the same page)', 'hcaptcha-for-forms-and-more' ),
			'type'  => 'checkbox',
		),
		'hcaptcha_nf_status'                => array(
			'label' => __( 'Enable Ninja Forms Addon', 'hcaptcha-for-forms-and-more' ),
			'type'  => 'checkbox',
		),
		'hcaptcha_cf7_status'               => array(
			'label' => __( 'Enable Contact Form 7 Addon', 'hcaptcha-for-forms-and-more' ),
			'type'  => 'checkbox',
		),
		'hcaptcha_lf_status'                => array(
			'label' => __( 'Enable hCaptcha on Login Form', 'hcaptcha-for-forms-and-more' ),
			'type'  => 'checkbox',
		),
		'hcaptcha_rf_status'                => array(
			'label' => __( 'Enable hCaptcha on Register Form', 'hcaptcha-for-forms-and-more' ),
			'type'  => 'checkbox',
		),
		'hcaptcha_cmf_status'               => array(
			'label' => __( 'Enable hCaptcha on Comment Form', 'hcaptcha-for-forms-and-more' ),
			'type'  => 'checkbox',
		),
		'hcaptcha_lpf_status'               => array(
			'label' => __( 'Enable hCaptcha on Lost Password Form', 'hcaptcha-for-forms-and-more' ),
			'type'  => 'checkbox',
		),
		'hcaptcha_wc_login_status'          => array(
			'label' => __( 'Enable hCaptcha on WooCommerce Login Form', 'hcaptcha-for-forms-and-more' ),
			'type'  => 'checkbox',
		),
		'hcaptcha_wc_reg_status'            => array(
			'label' => __( 'Enable hCaptcha on WooCommerce Registration Form', 'hcaptcha-for-forms-and-more' ),
			'type'  => 'checkbox',
		),
		'hcaptcha_wc_lost_pass_status'      => array(
			'label' => __( 'Enable hCaptcha on WooCommerce Lost Password Form', 'hcaptcha-for-forms-and-more' ),
			'type'  => 'checkbox',
		),
		'hcaptcha_wc_checkout_status'       => array(
			'label' => __( 'Enable hCaptcha on WooCommerce Checkout Form', 'hcaptcha-for-forms-and-more' ),
			'type'  => 'checkbox',
		),
		'hcaptcha_bp_reg_status'            => array(
			'label' => __( 'Enable hCaptcha on Buddypress Registration Form', 'hcaptcha-for-forms-and-more' ),
			'type'  => 'checkbox',
		),
		'hcaptcha_bp_create_group_status'   => array(
			'label' => __( 'Enable hCaptcha on BuddyPress Create Group Form', 'hcaptcha-for-forms-and-more' ),
			'type'  => 'checkbox',
		),
		'hcaptcha_bbp_new_topic_status'     => array(
			'label' => __( 'Enable hCaptcha on bbPress New Topic Form', 'hcaptcha-for-forms-and-more' ),
			'type'  => 'checkbox',
		),
		'hcaptcha_bbp_reply_status'         => array(
			'label' => __( 'Enable hCaptcha on bbPress Reply Form', 'hcaptcha-for-forms-and-more' ),
			'type'  => 'checkbox',
		),
		'hcaptcha_wpforms_status'           => array(
			'label' => __( 'Enable hCaptcha on WPForms Lite', 'hcaptcha-for-forms-and-more' ),
			'type'  => 'checkbox',
		),
		'hcaptcha_wpforms_pro_status'       => array(
			'label' => __( 'Enable hCaptcha on WPForms Pro', 'hcaptcha-for-forms-and-more' ),
			'type'  => 'checkbox',
		),
		'hcaptcha_wpforo_new_topic_status'  => array(
			'label' => __( 'Enable hCaptcha on WPForo New Topic Form', 'hcaptcha-for-forms-and-more' ),
			'type'  => 'checkbox',
		),
		'hcaptcha_wpforo_reply_status'      => array(
			'label' => __( 'Enable hCaptcha on WPForo Reply Form', 'hcaptcha-for-forms-and-more' ),
			'type'  => 'checkbox',
		),
		'hcaptcha_mc4wp_status'             => array(
			'label' => __( 'Enable hCaptcha on Mailchimp for WP Form', 'hcaptcha-for-forms-and-more' ),
			'type'  => 'checkbox',
		),
		'hcaptcha_jetpack_cf_status'        => array(
			'label' => __( 'Enable hCaptcha on Jetpack Contact Form', 'hcaptcha-for-forms-and-more' ),
			'type'  => 'checkbox',
		),
		'hcaptcha_subscribers_status'       => array(
			'label' => __( 'Enable hCaptcha on Subscribers Form', 'hcaptcha-for-forms-and-more' ),
			'type'  => 'checkbox',
		),
		'hcaptcha_wc_wl_create_list_status' => array(
			'label' => __( 'Enable hCaptcha on WooCommerce Wishlists Create List Form', 'hcaptcha-for-forms-and-more' ),
			'type'  => 'checkbox',
		),
	);
}
