<?php
/**
 * CoBlocksDetector class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\MigrationWizard\Detectors;

use HCaptcha\MigrationWizard\DetectionResult;

/**
 * Class CoBlocksDetector.
 *
 * Detects reCAPTCHA usage configured in CoBlocks forms.
 */
class CoBlocksDetector extends AbstractDetector {

	/**
	 * CoBlocks plugin slug.
	 */
	private const PLUGIN_SLUG = 'coblocks/class-coblocks.php';

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
		return 'CoBlocks';
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
		$site_key   = (string) get_option( 'coblocks_google_recaptcha_site_key', '' );
		$secret_key = (string) get_option( 'coblocks_google_recaptcha_secret_key', '' );

		if ( '' !== trim( $site_key ) && '' !== trim( $secret_key ) ) {
			return [
				$this->build_result(
					'recaptcha',
					'coblocks_form',
					DetectionResult::CONFIDENCE_HIGH,
					'CoBlocks has reCAPTCHA v3 keys configured. CoBlocks forms may use reCAPTCHA.'
				),
			];
		}

		return [];
	}
}
