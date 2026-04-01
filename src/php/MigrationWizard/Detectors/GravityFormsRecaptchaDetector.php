<?php
/**
 * GravityFormsRecaptchaDetector class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\MigrationWizard\Detectors;

use HCaptcha\MigrationWizard\DetectionResult;

/**
 * Class GravityFormsRecaptchaDetector.
 *
 * Detects reCAPTCHA usage from Gravity Forms.
 * GF stores reCAPTCHA keys in 'rg_gforms_captcha_public_key' and 'rg_gforms_captcha_private_key' options.
 */
class GravityFormsRecaptchaDetector extends AbstractDetector {

	/**
	 * GF surface IDs to detect.
	 */
	private const SURFACES = [
		'gravity_form',
		'gravity_embed',
	];

	/**
	 * Get the source plugin slug.
	 *
	 * @return string
	 */
	public function get_source_plugin(): string {
		return 'gravityforms/gravityforms.php';
	}

	/**
	 * Get the source plugin display name.
	 *
	 * @return string
	 */
	public function get_source_name(): string {
		return 'Gravity Forms';
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

		$public_key  = get_option( 'rg_gforms_captcha_public_key', '' );
		$private_key = get_option( 'rg_gforms_captcha_private_key', '' );

		if ( $public_key && $private_key ) {
			foreach ( self::SURFACES as $surface_id ) {
				$results[] = $this->build_result(
					'recaptcha',
					$surface_id,
					DetectionResult::CONFIDENCE_MEDIUM,
					'Gravity Forms has reCAPTCHA keys configured. Individual forms may use reCAPTCHA fields.'
				);
			}
		}

		return $results;
	}
}
