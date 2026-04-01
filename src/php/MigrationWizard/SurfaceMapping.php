<?php
/**
 * SurfaceMapping class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\MigrationWizard;

/**
 * Class SurfaceMapping.
 *
 * Maps normalized surface identifiers to hCaptcha integration settings.
 */
class SurfaceMapping {

	/**
	 * Surface-to-hCaptcha mapping.
	 *
	 * Each entry: surface_id => [ option_key, option_value, label ].
	 *
	 * @var array
	 */
	private const MAP = [
		// WordPress core.
		'wp_comment'               => [ 'wp_status', 'comment', 'WordPress Comment' ],
		'wp_login'                 => [ 'wp_status', 'login', 'WordPress Login' ],
		'wp_lost_password'         => [ 'wp_status', 'lost_pass', 'WordPress Lost Password' ],
		'wp_password_protected'    => [ 'wp_status', 'password_protected', 'WordPress Post/Page Password' ],
		'wp_register'              => [ 'wp_status', 'register', 'WordPress Register' ],

		// bbPress.
		'bbpress_new_topic'        => [ 'bbp_status', 'new_topic', 'bbPress New Topic' ],
		'bbpress_register'         => [ 'bbp_status', 'register', 'bbPress Register' ],
		'bbpress_reply'            => [ 'bbp_status', 'reply', 'bbPress Reply' ],

		// BuddyPress.
		'buddypress_create_group'  => [ 'bp_status', 'create_group', 'BuddyPress Create Group' ],
		'buddypress_registration'  => [ 'bp_status', 'registration', 'BuddyPress Register' ],

		// Contact Form 7.
		'cf7_form'                 => [ 'cf7_status', 'form', 'Contact Form 7 Auto' ],
		'cf7_embed'                => [ 'cf7_status', 'embed', 'Contact Form 7 Embed' ],

		// Easy Digital Downloads.
		'edd_checkout'             => [ 'easy_digital_downloads_status', 'checkout', 'Easy Digital Downloads Checkout' ],
		'edd_login'                => [ 'easy_digital_downloads_status', 'login', 'Easy Digital Downloads Login' ],
		'edd_register'             => [ 'easy_digital_downloads_status', 'register', 'Easy Digital Downloads Register' ],

		// Elementor Pro.
		'elementor_form'           => [ 'elementor_pro_status', 'form', 'Elementor Pro Form' ],
		'elementor_login'          => [ 'elementor_pro_status', 'login', 'Elementor Pro Login' ],

		// Fluent Forms.
		'fluent_form'              => [ 'fluent_status', 'form', 'Fluent Forms' ],

		// Formidable Forms.
		'formidable_form'          => [ 'formidable_forms_status', 'form', 'Formidable Forms' ],

		// Forminator.
		'forminator_form'          => [ 'forminator_status', 'form', 'Forminator' ],

		// Gravity Forms.
		'gravity_form'             => [ 'gravity_status', 'form', 'Gravity Forms Auto' ],
		'gravity_embed'            => [ 'gravity_status', 'embed', 'Gravity Forms Embed' ],

		// Jetpack Forms.
		'jetpack_form'             => [ 'jetpack_status', 'contact', 'Jetpack' ],

		// Kadence Forms.
		'kadence_form'             => [ 'kadence_status', 'form', 'Kadence Form' ],
		'kadence_advanced'         => [ 'kadence_status', 'advanced_form', 'Kadence Advanced' ],

		// Mailpoet Forms.
		'mailpoet_form'            => [ 'mailpoet_status', 'form', 'Mailpoet' ],

		// MemberPress.
		'memberpress_login'        => [ 'memberpress_status', 'login', 'MemberPress Login' ],
		'memberpress_register'     => [ 'memberpress_status', 'register', 'MemberPress Register' ],

		// Paid Memberships Pro.
		'pmp_checkout'             => [ 'paid_memberships_pro_status', 'checkout', 'Paid Memberships Pro Checkout' ],
		'pmp_login'                => [ 'paid_memberships_pro_status', 'login', 'Paid Memberships Pro Login' ],

		// Spectra.
		'spectra_form'             => [ 'spectra_status', 'form', 'Spectra Form' ],

		// Ultimate Member.
		'ultimate_member_login'    => [ 'ultimate_member_status', 'login', 'Ultimate Member Login' ],
		'ultimate_member_password' => [ 'ultimate_member_status', 'lost_pass', 'Ultimate Member Lost Password' ],
		'ultimate_member_register' => [ 'ultimate_member_status', 'register', 'Ultimate Member Register' ],

		// WooCommerce.
		'wc_login'                 => [ 'woocommerce_status', 'login', 'WooCommerce Login' ],
		'wc_register'              => [ 'woocommerce_status', 'register', 'WooCommerce Register' ],
		'wc_checkout'              => [ 'woocommerce_status', 'checkout', 'WooCommerce Checkout' ],
		'wc_lost_password'         => [ 'woocommerce_status', 'lost_pass', 'WooCommerce Lost Password' ],

		// Wordfence.
		'wordfence_login'          => [ 'wordfence_status', 'login', 'Wordfence Login' ],

		// WPForms.
		'wpforms_form'             => [ 'wpforms_status', 'form', 'WPForms Auto' ],
		'wpforms_embed'            => [ 'wpforms_status', 'embed', 'WPForms Embed' ],
	];

	/**
	 * Get the mapping for a surface.
	 *
	 * @param string $surface_id Normalized surface identifier.
	 *
	 * @return array|null [ option_key, option_value, label ] or null.
	 */
	public static function get( string $surface_id ): ?array {
		return self::MAP[ $surface_id ] ?? null;
	}

	/**
	 * Check if a surface is supported.
	 *
	 * @param string $surface_id Normalized surface identifier.
	 *
	 * @return bool
	 */
	public static function is_supported( string $surface_id ): bool {
		return isset( self::MAP[ $surface_id ] );
	}

	/**
	 * Get all supported surface IDs.
	 *
	 * @return string[]
	 */
	public static function get_all_surface_ids(): array {
		return array_keys( self::MAP );
	}

	/**
	 * Get the full map.
	 *
	 * @return array
	 */
	public static function get_all(): array {
		return self::MAP;
	}
}
