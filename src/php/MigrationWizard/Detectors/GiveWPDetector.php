<?php
/**
 * GiveWPDetector class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\MigrationWizard\Detectors;

use HCaptcha\MigrationWizard\DetectionResult;

/**
 * Class GiveWPDetector.
 *
 * Detects reCAPTCHA usage configured in GiveWP plugin settings.
 */
class GiveWPDetector extends AbstractDetector {

	/**
	 * GiveWP plugin slug.
	 */
	private const PLUGIN_SLUG = 'give/give.php';

	/**
	 * Get the source plugin slug.
	 *
	 * @return string
	 */
	public function get_source_plugin(): string {
		return self::PLUGIN_SLUG;
	}

	/**
	 * Get the source plugin display name.
	 *
	 * @return string
	 */
	public function get_source_name(): string {
		return 'GiveWP';
	}

	/**
	 * Check if this detector is applicable.
	 *
	 * @return bool
	 */
	public function is_applicable(): bool {
		return $this->is_plugin_active( self::PLUGIN_SLUG );
	}

	/**
	 * Run detection.
	 *
	 * @return DetectionResult[]
	 */
	public function detect(): array {
		$settings   = get_option( 'give_settings', [] );
		$settings   = is_array( $settings ) ? $settings : [];
		$site_key   = (string) ( $settings['recaptcha_key'] ?? '' );
		$secret_key = (string) ( $settings['recaptcha_secret'] ?? '' );

		if ( '' !== trim( $site_key ) && '' !== trim( $secret_key ) ) {
			return [
				$this->build_result(
					'recaptcha',
					'give_wp_form',
					DetectionResult::CONFIDENCE_HIGH,
					'GiveWP has reCAPTCHA keys configured.'
				),
			];
		}

		return [];
	}
}
