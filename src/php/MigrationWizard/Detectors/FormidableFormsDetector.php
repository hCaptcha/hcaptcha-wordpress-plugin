<?php
/**
 * FormidableFormsDetector class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\MigrationWizard\Detectors;

use HCaptcha\MigrationWizard\DetectionResult;

/**
 * Class FormidableFormsDetector.
 *
 * Detects reCAPTCHA and Turnstile settings in Formidable Forms.
 */
class FormidableFormsDetector extends AbstractDetector {

	/**
	 * Formidable Forms plugin slug.
	 */
	private const PLUGIN_SLUG = 'formidable/formidable.php';

	/**
	 * Get the source plugin name.
	 *
	 * @return string
	 */
	public function get_source_name(): string {
		return 'Formidable Forms';
	}

	/**
	 * Get the source plugin identifier.
	 *
	 * @return string
	 */
	public function get_source_plugin(): string {
		return self::PLUGIN_SLUG;
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
	 * Detect reCAPTCHA and Turnstile settings.
	 *
	 * @return DetectionResult[]
	 * @noinspection PhpUnnecessaryLocalVariableInspection
	 */
	public function detect(): array {
		$results = [];

		$settings = get_option( 'frm_options' );

		// Formidable Forms uses a serialized object for its settings.
		if ( ! is_object( $settings ) ) {
			return $results;
		}

		$results = array_merge( $results, $this->detect_recaptcha( $settings ) );
		$results = array_merge( $results, $this->detect_turnstile( $settings ) );

		return $results;
	}

	/**
	 * Detect reCAPTCHA settings.
	 *
	 * @param object $settings Formidable settings object.
	 *
	 * @return DetectionResult[]
	 */
	private function detect_recaptcha( object $settings ): array {
		$results    = [];
		$site_key   = $settings->pubkey ?? '';
		$secret_key = $settings->privkey ?? '';

		if ( ! empty( trim( (string) $site_key ) ) && ! empty( trim( (string) $secret_key ) ) ) {
			$results[] = $this->build_result(
				'recaptcha',
				'formidable_form',
				DetectionResult::CONFIDENCE_HIGH
			);
		}

		return $results;
	}

	/**
	 * Detect Turnstile settings.
	 *
	 * @param object $settings Formidable settings object.
	 *
	 * @return DetectionResult[]
	 */
	private function detect_turnstile( object $settings ): array {
		$results    = [];
		$site_key   = $settings->turnstile_pubkey ?? '';
		$secret_key = $settings->turnstile_privkey ?? '';

		if ( ! empty( trim( (string) $site_key ) ) && ! empty( trim( (string) $secret_key ) ) ) {
			$results[] = $this->build_result(
				'turnstile',
				'formidable_form',
				DetectionResult::CONFIDENCE_HIGH
			);
		}

		return $results;
	}
}
