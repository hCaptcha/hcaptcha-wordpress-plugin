<?php
/**
 * Honeypot class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\AntiSpam;

/**
 * Class Honeypot.
 */
class Honeypot {
	private const PROTECTED_FORMS = [
		'wp_status'                      => [ 'comment', 'login', 'lost_pass', 'password_protected', 'register', 'signup' ],
		'acfe_status'                    => [ 'form' ],
		'affiliates_status'              => [ 'login', 'register' ],
		'asgaros_status'                 => [ 'form' ],
		'avada_status'                   => [ 'form' ],
		'back_in_stock_notifier_status'  => [ 'form' ],
		'beaver_builder_status'          => [ 'contact', 'login' ],
		'bbp_status'                     => [ 'login', 'lost_pass', 'new_topic', 'register', 'reply' ],
		'blocksy_status'                 => [ 'newsletter_subscribe', 'product_review', 'waitlist' ],
		'bp_status'                      => [ 'create_group', 'registration' ],
		'brizy_status'                   => [ 'form' ],
		'cf7_status'                     => [ 'form', 'embed' ],
		'classified_listing_status'      => [ 'contact', 'login', 'lost_pass', 'register' ],
		'coblocks_status'                => [ 'form' ],
		'colorlib_customizer_status'     => [ 'login', 'lost_pass', 'register' ],
		'customer_reviews_status'        => [ 'q&a', 'review' ],
		'divi_status'                    => [ 'comment', 'contact', 'email_optin', 'login' ],
		'divi_builder_status'            => [ 'comment', 'contact', 'email_optin', 'login' ],
		'download_manager_status'        => [ 'button' ],
		'easy_digital_downloads_status'  => [ 'checkout', 'login', 'lost_pass', 'register' ],
		'essential_addons_status'        => [ 'login', 'register' ],
		'essential_blocks_status'        => [ 'form' ],
		'extra_status'                   => [ 'comment', 'contact', 'email_optin', 'login' ],
		'elementor_pro_status'           => [ 'form', 'login' ],
		'events_manager_status'          => [ 'booking' ],
		'fluent_status'                  => [ 'form' ],
		'formidable_forms_status'        => [ 'form' ],
		'forminator_status'              => [ 'form' ],
		'give_wp_status'                 => [ 'form' ],
		'gravity_status'                 => [ 'form', 'embed' ],
		'html_forms_status'              => [ 'form' ],
		'icegram_express_status'         => [ 'form' ],
		'jetpack_status'                 => [ 'contact' ],
		'kadence_status'                 => [ 'form', 'advanced_form' ],
		'learn_dash_status'              => [ 'login', 'lost_pass', 'register' ],
		'learn_press_status'             => [ 'checkout', 'login', 'register' ],
		'login_signup_popup_status'      => [ 'login', 'register' ],
		'mailchimp_status'               => [ 'form' ],
		'mailpoet_status'                => [ 'form' ],
		'maintenance_status'             => [ 'login' ],
		'memberpress_status'             => [ 'login', 'register' ],
		'metform_status'                 => [ 'form' ],
		'ninja_status'                   => [ 'form' ],
		'otter_status'                   => [ 'form' ],
		'passster_status'                => [ 'protect' ],
		'password_protected_status'      => [ 'protect' ],
		'profile_builder_status'         => [ 'login', 'lost_pass', 'register' ],
		'quform_status'                  => [ 'form' ],
		'sendinblue_status'              => [ 'form' ],
		'simple_download_monitor_status' => [ 'form' ],
		'simple_membership_status'       => [ 'login', 'lost_pass', 'register' ],
		'spectra_status'                 => [ 'form' ],
		'subscriber_status'              => [ 'form' ],
		'supportcandy_status'            => [ 'form' ],
		'theme_my_login_status'          => [ 'login', 'lost_pass', 'register', 'signup' ],
		'tutor_status'                   => [ 'checkout', 'login', 'lost_pass', 'register' ],
		'ultimate_addons_status'         => [ 'login', 'register' ],
		'ultimate_member_status'         => [ 'login', 'lost_pass', 'register' ],
		'users_wp_status'                => [ 'forgot', 'login', 'register' ],
		'woocommerce_status'             => [ 'add_payment_method', 'checkout', 'login', 'lost_pass', 'order_tracking', 'order_withdrawal', 'register' ],
		'woocommerce_germanized_status'  => [ 'return_request' ],
		'woocommerce_wishlists_status'   => [ 'create_list' ],
		'paypal_payments_status'         => [ 'button' ],
		'wordfence_status'               => [ 'login' ],
		'wpdiscuz_status'                => [ 'comment_form', 'subscribe_form' ],
		'wpforo_status'                  => [ 'new_topic', 'reply' ],
		'wpforms_status'                 => [ 'form', 'embed' ],
		'wp_job_openings_status'         => [ 'form' ],
	];

	/**
	 * Retrieves the protected forms list.
	 *
	 * @return array
	 */
	public static function get_protected_forms(): array {
		$settings = hcaptcha()->settings();

		$honeypot                 = $settings && $settings->is_on( 'honeypot' );
		$honeypot_protected_forms = $honeypot ? self::PROTECTED_FORMS : [];

		$fst                 = $settings && $settings->is_on( 'set_min_submit_time' );
		$fst_protected_forms = $fst ? self::PROTECTED_FORMS : [];

		return [
			'honeypot' => $honeypot_protected_forms,
			'fst'      => $fst_protected_forms,
		];
	}
}
