<?php
/**
 * ElementorProDetector class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\MigrationWizard\Detectors;

use HCaptcha\MigrationWizard\DetectionResult;

/**
 * Class ElementorProDetector.
 *
 * Detects reCAPTCHA usage from the Elementor Pro plugin.
 * Plugin slug: elementor-pro/elementor-pro.php.
 * Options: elementor_pro_recaptcha_site_key / secret_key (v2),
 *          elementor_pro_recaptcha_v3_site_key / secret_key (v3).
 */
class ElementorProDetector extends AbstractDetector {

	/**
	 * Surface IDs to detect.
	 */
	private const SURFACES = [
		'elementor_form',
		'elementor_login',
	];

	/**
	 * Provider key pairs: [ site_key_option, secret_key_option ].
	 */
	private const KEY_PAIRS = [
		[ 'elementor_pro_recaptcha_site_key', 'elementor_pro_recaptcha_secret_key' ],
		[ 'elementor_pro_recaptcha_v3_site_key', 'elementor_pro_recaptcha_v3_secret_key' ],
	];

	/**
	 * Get the source plugin slug.
	 *
	 * @return string
	 */
	public function get_source_plugin(): string {
		return 'elementor-pro/elementor-pro.php';
	}

	/**
	 * Get the source plugin display name.
	 *
	 * @return string
	 */
	public function get_source_name(): string {
		return 'Elementor Pro';
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
		foreach ( self::KEY_PAIRS as $pair ) {
			[ $site_key_option, $secret_key_option ] = $pair;

			$site_key   = get_option( $site_key_option, '' );
			$secret_key = get_option( $secret_key_option, '' );

			if ( '' === $site_key || '' === $secret_key ) {
				continue;
			}

			$results = [];

			foreach ( self::SURFACES as $surface_id ) {
				$results[] = $this->build_result(
					'recaptcha',
					$surface_id,
					DetectionResult::CONFIDENCE_HIGH
				);
			}

			return $results;
		}

		return [];
	}
}
