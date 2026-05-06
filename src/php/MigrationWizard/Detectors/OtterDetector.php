<?php
/**
 * OtterDetector class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\MigrationWizard\Detectors;

use HCaptcha\MigrationWizard\DetectionResult;

/**
 * Class OtterDetector.
 *
 * Detects reCAPTCHA keys in Otter Blocks plugin.
 */
class OtterDetector extends AbstractDetector {

	/**
	 * Otter plugin slug.
	 */
	private const PLUGIN_SLUG = 'otter-blocks/otter-blocks.php';

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
		return 'Otter Blocks';
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
	 * Detect reCAPTCHA keys.
	 *
	 * @return DetectionResult[]
	 */
	public function detect(): array {
		$site_key   = (string) get_option( 'themeisle_google_captcha_api_site_key', '' );
		$secret_key = (string) get_option( 'themeisle_google_captcha_api_secret_key', '' );

		if ( '' !== trim( $site_key ) && '' !== trim( $secret_key ) ) {
			return [
				$this->build_result(
					'recaptcha',
					'otter_form',
					DetectionResult::CONFIDENCE_HIGH,
					'Otter Blocks has reCAPTCHA keys configured. Otter Blocks forms may use reCAPTCHA.'
				),
			];
		}

		return [];
	}
}
