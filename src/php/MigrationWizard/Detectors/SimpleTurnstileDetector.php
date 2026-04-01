<?php
/**
 * SimpleTurnstileDetector class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\MigrationWizard\Detectors;

use HCaptcha\MigrationWizard\DetectionResult;

/**
 * Class SimpleTurnstileDetector.
 *
 * Detects Turnstile usage from the "Simple Cloudflare Turnstile" plugin.
 * Plugin slug: simple-cloudflare-turnstile/simple-cloudflare-turnstile.php.
 * Options stored in: 'cfturnstile_*' individual options.
 */
class SimpleTurnstileDetector extends AbstractDetector {

	/**
	 * Get the source plugin slug.
	 *
	 * @return string
	 */
	public function get_source_plugin(): string {
		return 'simple-cloudflare-turnstile/simple-cloudflare-turnstile.php';
	}

	/**
	 * Get the source plugin display name.
	 *
	 * @return string
	 */
	public function get_source_name(): string {
		return 'Simple Cloudflare Turnstile';
	}

	/**
	 * Check if this detector is applicable.
	 *
	 * @return bool
	 */
	public function is_applicable(): bool {
		return $this->is_plugin_active( $this->get_source_plugin() );
	}

	/**
	 * Run detection.
	 *
	 * @return DetectionResult[]
	 */
	public function detect(): array {
		$results = [];

		// Check individual toggle options.
		$toggle_map = [
			'cfturnstile_login'            => 'wp_login',
			'cfturnstile_register'         => 'wp_register',
			'cfturnstile_reset'            => 'wp_lost_password',
			'cfturnstile_comment'          => 'wp_comment',
			'cfturnstile_bbpress_create'   => 'bbpress_new_topic',
			'cfturnstile_bbpress_reply'    => 'bbpress_reply',
			'cfturnstile_bp_register'      => 'buddypress_registration',
			'cfturnstile_cf7_all'          => [ 'cf7_form', 'cf7_embed' ],
			'cfturnstile_edd_checkout'     => 'edd_checkout',
			'cfturnstile_edd_login'        => 'edd_login',
			'cfturnstile_edd_register'     => 'edd_register',
			'cfturnstile_elementor'        => 'elementor_form',
			'cfturnstile_fluent'           => 'fluent_form',
			'cfturnstile_formidable'       => 'formidable_form',
			'cfturnstile_forminator'       => 'forminator_form',
			'cfturnstile_gravity'          => [ 'gravity_form', 'gravity_embed' ],
			'cfturnstile_jetpack'          => 'jetpack_form',
			'cfturnstile_kadence'          => [ 'kadence_form', 'kadence_advanced' ],
			'cfturnstile_mailpoet'         => 'mailpoet_form',
			'cfturnstile_mepr_login'       => 'memberpress_login',  // Always off. Turnstile maps it to the cfturnstile_login.
			'cfturnstile_mepr_register'    => 'memberpress_register',
			'cfturnstile_pmp_checkout'     => 'pmp_checkout',
			'cfturnstile_pmp_login'        => 'pmp_login', // Always off. Turnstile maps it to the cfturnstile_login.
			'cfturnstile_um_login'         => 'ultimate_member_login',
			'cfturnstile_um_password'      => 'ultimate_member_password',
			'cfturnstile_um_register'      => 'ultimate_member_register',
			'cfturnstile_woo_login'        => 'wc_login',
			'cfturnstile_woo_register'     => 'wc_register',
			'cfturnstile_woo_checkout'     => 'wc_checkout',
			'cfturnstile_woo_checkout_pay' => 'wc_checkout',
			'cfturnstile_woo_reset'        => 'wc_lost_password',
			'cfturnstile_wpforms'          => [ 'wpforms_form', 'wpforms_embed' ],
		];

		foreach ( $toggle_map as $option_key => $surface_ids ) {
			foreach ( (array) $surface_ids as $surface_id ) {
				$value = get_option( $option_key, '' );

				if ( '1' === (string) $value || 'on' === (string) $value ) {
					// Avoid duplicates from the array-based detection above.
					$already_found = false;

					foreach ( $results as $existing ) {
						if ( $existing->get_surface() === $surface_id ) {
							$already_found = true;

							break;
						}
					}

					if ( ! $already_found ) {
						$results[] = $this->build_result(
							'turnstile',
							$surface_id,
							DetectionResult::CONFIDENCE_MEDIUM,
							'Detected via individual toggle option.'
						);
					}
				}
			}
		}

		return $results;
	}
}
