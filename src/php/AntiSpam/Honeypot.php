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
		'wp_status'                   => [ 'comment', 'login', 'lost_pass', 'password_protected', 'register' ],
		'acfe_status'                 => [ 'form' ],
		'avada_status'                => [ 'form' ],
		'bbp_status'                  => [ 'login', 'lost_pass', 'new_topic', 'register', 'reply' ],
		'blocksy_status'              => [ 'newsletter_subscribe', 'product_review', 'waitlist' ],
		'bp_status'                   => [ 'create_group', 'registration' ],
		'cf7_status'                  => [ 'form', 'embed' ],
		'coblocks_status'             => [ 'form' ],
		'divi_status'                 => [ 'comment', 'contact', 'email_optin', 'login' ],
		'divi_builder_status'         => [ 'comment', 'contact', 'email_optin', 'login' ],
		'download_manager_status'     => [ 'button' ],
		'essential_addons_status'     => [ 'login', 'register' ],
		'essential_blocks_status'     => [ 'form' ],
		'extra_status'                => [ 'comment', 'contact', 'email_optin', 'login' ],
		'elementor_pro_status'        => [ 'form', 'login' ],
		'fluent_status'               => [ 'form' ],
		'formidable_forms_status'     => [ 'form' ],
		'forminator_status'           => [ 'form' ],
		'give_wp_status'              => [ 'form' ],
		'gravity_status'              => [ 'form', 'embed' ],
		'jetpack_status'              => [ 'contact' ],
		'kadence_status'              => [ 'form', 'advanced_form' ],
		'mailchimp_status'            => [ 'form' ],
		'mailpoet_status'             => [ 'form' ],
		'maintenance_status'          => [ 'login' ],
		'ninja_status'                => [ 'form' ],
		'otter_status'                => [ 'form' ],
		'paid_memberships_pro_status' => [ 'checkout', 'login' ],
		'password_protected_status'   => [ 'protect' ],
		'sendinblue_status'           => [ 'form' ],
		'spectra_status'              => [ 'form' ],
		'ultimate_addons_status'      => [ 'login', 'register' ],
		'ultimate_member_status'      => [ 'login', 'lost_pass', 'register' ],
		'woocommerce_status'          => [ 'checkout', 'login', 'lost_pass', 'order_tracking', 'register' ],
		'wordfence_status'            => [ 'login' ],
		'wpforms_status'              => [ 'form', 'embed' ],
	];

	/**
	 * Retrieves the protected forms list.
	 *
	 * @return array
	 */
	public static function get_protected_forms(): array {
		$honeypot                 = hcaptcha()->settings()->is_on( 'honeypot' );
		$honeypot_protected_forms = $honeypot ? self::PROTECTED_FORMS : [];

		$fst                 = hcaptcha()->settings()->is_on( 'set_min_submit_time' );
		$fst_protected_forms = $fst ? self::PROTECTED_FORMS : [];

		return [
			'honeypot' => $honeypot_protected_forms,
			'fst'      => $fst_protected_forms,
		];
	}
}
