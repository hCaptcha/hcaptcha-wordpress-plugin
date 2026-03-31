<?php
/**
 * SpectraDetector class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\MigrationWizard\Detectors;

use HCaptcha\MigrationWizard\DetectionResult;

/**
 * Class SpectraDetector.
 *
 * Detects reCAPTCHA usage configured in Spectra forms.
 */
class SpectraDetector extends AbstractDetector {

	/**
	 * Spectra plugin slug.
	 */
	private const PLUGIN_SLUG = 'ultimate-addons-for-gutenberg/ultimate-addons-for-gutenberg.php';

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
		return 'Spectra';
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
		$site_keys   = [
			(string) get_option( 'uag_recaptcha_site_key_v2', '' ),
			(string) get_option( 'uag_recaptcha_site_key_v3', '' ),
		];
		$secret_keys = [
			(string) get_option( 'uag_recaptcha_secret_key_v2', '' ),
			(string) get_option( 'uag_recaptcha_secret_key_v3', '' ),
		];

		foreach ( $site_keys as $index => $site_key ) {
			if ( '' !== trim( $site_key ) && '' !== trim( $secret_keys[ $index ] ?? '' ) ) {
				return [
					$this->build_result(
						'recaptcha',
						'spectra_form',
						DetectionResult::CONFIDENCE_HIGH,
						'Spectra has reCAPTCHA keys configured. Spectra forms may use reCAPTCHA.'
					),
				];
			}
		}

		return [];
	}
}
