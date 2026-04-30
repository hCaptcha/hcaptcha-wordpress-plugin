<?php
/**
 * NinjaFormsDetector class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\MigrationWizard\Detectors;

use HCaptcha\MigrationWizard\DetectionResult;

/**
 * Class NinjaFormsDetector.
 *
 * Detects reCAPTCHA usage configured in Ninja Forms.
 * Ninja Forms stores reCAPTCHA keys in the 'ninja_forms_settings' option.
 */
class NinjaFormsDetector extends AbstractDetector {

	/**
	 * Ninja Forms plugin slug.
	 */
	private const PLUGIN_SLUG = 'ninja-forms/ninja-forms.php';

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
		return 'Ninja Forms';
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
		$settings = get_option( 'ninja_forms_settings', [] );

		if ( ! is_array( $settings ) ) {
			return [];
		}

		$key_pairs = [
			[ 'recaptcha_site_key', 'recaptcha_secret_key' ],
			[ 'recaptcha_site_key_3', 'recaptcha_secret_key_3' ],
		];

		foreach ( $key_pairs as $pair ) {
			$site_key   = trim( (string) ( $settings[ $pair[0] ] ?? '' ) );
			$secret_key = trim( (string) ( $settings[ $pair[1] ] ?? '' ) );

			if ( '' !== $site_key && '' !== $secret_key ) {
				return [
					$this->build_result(
						'recaptcha',
						'ninja_form',
						DetectionResult::CONFIDENCE_HIGH,
						'Ninja Forms has reCAPTCHA keys configured. Forms may use reCAPTCHA.'
					),
				];
			}
		}

		return [];
	}
}
