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
		'honeypot' => [
			'wp_status'               => [ 'comment', 'login', 'lost_pass', 'password_protected', 'register' ],
			'avada_status'            => [ 'form' ],
			'cf7_status'              => [ 'form', 'embed' ],
			'coblocks_status'         => [ 'form' ],
			'divi_status'             => [ 'comment', 'contact', 'email_optin', 'login' ],
			'divi_builder_status'     => [ 'comment', 'contact', 'email_optin', 'login' ],
			'essential_addons_status' => [ 'login', 'register' ],
			'extra_status'            => [ 'comment', 'contact', 'email_optin', 'login' ],
			'elementor_pro_status'    => [ 'form', 'login' ],
			'fluent_status'           => [ 'form' ],
			'formidable_forms_status' => [ 'form' ],
			'forminator_status'       => [ 'form' ],
			'gravity_status'          => [ 'form', 'embed' ],
			'jetpack_status'          => [ 'contact' ],
			'mailchimp_status'        => [ 'form' ],
			'mailpoet_status'         => [ 'form' ],
			'ninja_status'            => [ 'form' ],
			'spectra_status'          => [ 'form' ],
			'woocommerce_status'      => [ 'checkout', 'login', 'lost_pass', 'order_tracking', 'register' ],
			'wordfence_status'        => [ 'login' ],
			'wpforms_status'          => [ 'form', 'embed' ],
		],
	];

	/**
	 * Retrieves the protected forms list.
	 *
	 * @return array
	 */
	public static function get_protected_forms(): array {
		$honeypot = hcaptcha()->settings()->get( 'honeypot' );

		return $honeypot ? self::PROTECTED_FORMS : [];
	}
}
