<?php

/**
 * List of hcap options.
 */
function hcap_options() {
	return [
		'hcaptcha_api_key'                  => [
			'label' => __( 'hCaptcha Site Key', 'hcaptcha-wp' ),
			'type'  => 'text',
		],
		'hcaptcha_secret_key'               => [
			'label' => __( 'hCaptcha Secret Key', 'hcaptcha-wp' ),
			'type'  => 'text',
		],
		'hcaptcha_theme'                    => [
			'label'   => __( 'hCaptcha Theme', 'hcaptcha-wp' ),
			'type'    => 'select',
			'options' => [
				'light' => __( 'Light', 'hcaptcha-wp' ),
				'dark'  => __( 'Dark', 'hcaptcha-wp' ),
			],
		],
		'hcaptcha_size'                     => [
			'label'   => __( 'hCaptcha Size', 'hcaptcha-wp' ),
			'type'    => 'select',
			'options' => [
				'normal'  => __( 'Normal', 'hcaptcha-wp' ),
				'compact' => __( 'Compact', 'hcaptcha-wp' ),
			],
		],
		'hcaptcha_language'                 => [
			'label'       => __( 'Override Language Detection (optional)', 'hcaptcha-wp' ),
			'type'        => 'text',
			'description' => __(
				'Info on <a href="https://hcaptcha.com/docs/languages" target="_blank">language codes</a>.',
				'hcaptcha-wp'
			),
		],
		'hcaptcha_nf_status'                => [
			'label' => __( 'Enable Ninja Forms Addon', 'hcaptcha-wp' ),
			'type'  => 'checkbox',
		],
		'hcaptcha_cf7_status'               => [
			'label' => __( 'Enable Contact Form 7 Addon', 'hcaptcha-wp' ),
			'type'  => 'checkbox',
		],
		'hcaptcha_lf_status'                => [
			'label' => __( 'Enable hCaptcha on Login Form', 'hcaptcha-wp' ),
			'type'  => 'checkbox',
		],
		'hcaptcha_rf_status'                => [
			'label' => __( 'Enable hCaptcha on Register Form', 'hcaptcha-wp' ),
			'type'  => 'checkbox',
		],
		'hcaptcha_cmf_status'               => [
			'label' => __( 'Enable hCaptcha on Comment Form', 'hcaptcha-wp' ),
			'type'  => 'checkbox',
		],
		'hcaptcha_lpf_status'               => [
			'label' => __( 'Enable hCaptcha on Lost Password Form', 'hcaptcha-wp' ),
			'type'  => 'checkbox',
		],
		'hcaptcha_wc_login_status'          => [
			'label' => __( 'Enable hCaptcha on WooCommerce Login Form', 'hcaptcha-wp' ),
			'type'  => 'checkbox',
		],
		'hcaptcha_wc_reg_status'            => [
			'label' => __( 'Enable hCaptcha on WooCommerce Registration Form', 'hcaptcha-wp' ),
			'type'  => 'checkbox',
		],
		'hcaptcha_wc_lost_pass_status'      => [
			'label' => __( 'Enable hCaptcha on WooCommerce Lost Password Form', 'hcaptcha-wp' ),
			'type'  => 'checkbox',
		],
		'hcaptcha_wc_checkout_status'       => [
			'label' => __( 'Enable hCaptcha on WooCommerce Checkout Form', 'hcaptcha-wp' ),
			'type'  => 'checkbox',
		],
		'hcaptcha_bp_reg_status'            => [
			'label' => __( 'Enable hCaptcha on Buddypress Registration Form', 'hcaptcha-wp' ),
			'type'  => 'checkbox',
		],
		'hcaptcha_bp_create_group_status'   => [
			'label' => __( 'Enable hCaptcha on BuddyPress Create Group Form', 'hcaptcha-wp' ),
			'type'  => 'checkbox',
		],
		'hcaptcha_bbp_new_topic_status'     => [
			'label' => __( 'Enable hCaptcha on bbpress new topic Form', 'hcaptcha-wp' ),
			'type'  => 'checkbox',
		],
		'hcaptcha_bbp_reply_status'         => [
			'label' => __( 'Enable hCaptcha on bbpress reply Form', 'hcaptcha-wp' ),
			'type'  => 'checkbox',
		],
		'hcaptcha_wpforo_new_topic_status'  => [
			'label' => __( 'Enable hCaptcha on WPForo new topic Form', 'hcaptcha-wp' ),
			'type'  => 'checkbox',
		],
		'hcaptcha_wpforo_reply_status'      => [
			'label' => __( 'Enable hCaptcha on WPForo Reply Form', 'hcaptcha-wp' ),
			'type'  => 'checkbox',
		],
		'hcaptcha_mc4wp_status'             => [
			'label' => __( 'Enable hCaptcha on Mailchimp for WP Form', 'hcaptcha-wp' ),
			'type'  => 'checkbox',
		],
		'hcaptcha_jetpack_cf_status'        => [
			'label' => __( 'Enable hCaptcha on Jetpack Contact Form', 'hcaptcha-wp' ),
			'type'  => 'checkbox',
		],
		'hcaptcha_subscribers_status'       => [
			'label' => __( 'Enable hCaptcha on Subscribers Form', 'hcaptcha-wp' ),
			'type'  => 'checkbox',
		],
		'hcaptcha_wc_wl_create_list_status' => [
			'label' => __( 'Enable hCaptcha on WooCommerce Wishlists Create List Form', 'hcaptcha-wp' ),
			'type'  => 'checkbox',
		],
	];
}
