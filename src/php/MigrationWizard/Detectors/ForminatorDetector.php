<?php
/**
 * ForminatorDetector class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\MigrationWizard\Detectors;

use HCaptcha\MigrationWizard\DetectionResult;

/**
 * Class ForminatorDetector.
 *
 * Detects reCAPTCHA usage configured in Forminator.
 * Forminator stores reCAPTCHA keys in separate options for v2 Checkbox, v2 Invisible, and v3.
 */
class ForminatorDetector extends AbstractDetector {

	/**
	 * Forminator plugin slug.
	 */
	private const PLUGIN_SLUG = 'forminator/forminator.php';

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
		return 'Forminator';
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
		$key_pairs = [
			[ 'forminator_captcha_key', 'forminator_captcha_secret' ],
			[ 'forminator_v2_invisible_captcha_key', 'forminator_v2_invisible_captcha_secret' ],
			[ 'forminator_v3_captcha_key', 'forminator_v3_captcha_secret' ],
		];

		foreach ( $key_pairs as $pair ) {
			$site_key   = trim( (string) get_option( $pair[0], '' ) );
			$secret_key = trim( (string) get_option( $pair[1], '' ) );

			if ( '' !== $site_key && '' !== $secret_key ) {
				return [
					$this->build_result(
						'recaptcha',
						'forminator_form',
						DetectionResult::CONFIDENCE_HIGH,
						'Forminator has reCAPTCHA keys configured. Forms may use reCAPTCHA.'
					),
				];
			}
		}

		return [];
	}
}
