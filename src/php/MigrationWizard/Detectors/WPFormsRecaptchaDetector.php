<?php
/**
 * WPFormsRecaptchaDetector class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\MigrationWizard\Detectors;

use HCaptcha\MigrationWizard\DetectionResult;

/**
 * Class WPFormsRecaptchaDetector.
 *
 * Detects reCAPTCHA or Turnstile usage from WPForms.
 * WPForms stores CAPTCHA settings in the 'wpforms_settings' option.
 */
class WPFormsRecaptchaDetector extends AbstractDetector {

	/**
	 * WPForms surface IDs to detect.
	 */
	private const SURFACES = [
		'wpforms_form',
		'wpforms_embed',
	];

	/**
	 * Get the source plugin slug.
	 *
	 * @return string
	 */
	public function get_source_plugin(): string {
		return 'wpforms-lite/wpforms.php';
	}

	/**
	 * Get the source plugin display name.
	 *
	 * @return string
	 */
	public function get_source_name(): string {
		return 'WPForms';
	}

	/**
	 * Check if this detector is applicable.
	 *
	 * @return bool
	 */
	public function is_applicable(): bool {
		return $this->is_plugin_active( 'wpforms-lite/wpforms.php' )
			|| $this->is_plugin_active( 'wpforms/wpforms.php' );
	}

	/**
	 * Run detection.
	 *
	 * @return DetectionResult[]
	 */
	public function detect(): array {
		$results  = [];
		$settings = get_option( 'wpforms_settings', [] );

		if ( ! is_array( $settings ) || empty( $settings ) ) {
			return $results;
		}

		$captcha_provider = $settings['captcha-provider'] ?? '';

		// WPForms supports recaptcha, hcaptcha, and turnstile.
		if ( ! in_array( $captcha_provider, [ 'recaptcha', 'turnstile' ], true ) ) {
			return $results;
		}

		$site_key   = $settings[ $captcha_provider . '-site-key' ] ?? '';
		$secret_key = $settings[ $captcha_provider . '-secret-key' ] ?? '';

		if ( empty( $site_key ) || empty( $secret_key ) ) {
			return $results;
		}

		foreach ( self::SURFACES as $surface_id ) {
			$results[] = $this->build_result(
				$captcha_provider,
				$surface_id,
				DetectionResult::CONFIDENCE_MEDIUM,
				'WPForms has ' . $captcha_provider . ' configured. Individual forms may or may not use it.'
			);
		}

		return $results;
	}
}
