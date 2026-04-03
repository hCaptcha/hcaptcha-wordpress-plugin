<?php
/**
 * FluentFormsDetector class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\MigrationWizard\Detectors;

use HCaptcha\MigrationWizard\DetectionResult;

/**
 * Class FluentFormsDetector.
 *
 * Detects reCAPTCHA usage configured in Fluent Forms.
 * Fluent Forms stores reCAPTCHA keys in the '_ fluentform_reCaptcha_details' option.
 */
class FluentFormsDetector extends AbstractDetector {

	/**
	 * Fluent Forms plugin slug.
	 */
	private const PLUGIN_SLUG = 'fluentform/fluentform.php';

	/**
	 * Fluent Forms Pro plugin slug.
	 */
	private const PLUGIN_SLUG_PRO = 'fluentformpro/fluentformpro.php';

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
		return 'Fluent Forms';
	}

	/**
	 * Check if this detector is applicable.
	 *
	 * @return bool
	 */
	public function is_applicable(): bool {
		return $this->is_plugin_active( self::PLUGIN_SLUG ) || $this->is_plugin_active( self::PLUGIN_SLUG_PRO );
	}

	/**
	 * Run detection.
	 *
	 * @return DetectionResult[]
	 */
	public function detect(): array {
		$details = get_option( '_fluentform_reCaptcha_details', [] );

		if ( ! is_array( $details ) ) {
			return [];
		}

		$site_key   = trim( (string) ( $details['siteKey'] ?? '' ) );
		$secret_key = trim( (string) ( $details['secretKey'] ?? '' ) );

		if ( '' !== $site_key && '' !== $secret_key ) {
			return [
				$this->build_result(
					'recaptcha',
					'fluent_form',
					DetectionResult::CONFIDENCE_HIGH,
					'Fluent Forms has reCAPTCHA keys configured. Forms may use reCAPTCHA.'
				),
			];
		}

		return [];
	}
}
