<?php
/**
 * CF7RecaptchaDetector class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\MigrationWizard\Detectors;

use HCaptcha\CF7\Admin;
use HCaptcha\CF7\Base;
use HCaptcha\Helpers\Utils;
use HCaptcha\MigrationWizard\DetectionResult;

/**
 * Class CF7RecaptchaDetector.
 *
 * Detects native reCAPTCHA usage from Contact Form 7.
 * CF7 stores reCAPTCHA integration in the 'wpcf7' option with the 'recaptcha' key.
 */
class CF7RecaptchaDetector extends AbstractDetector {

	/**
	 * CF7 surface IDs to detect.
	 */
	private const SURFACES = [
		'cf7_form',
		'cf7_embed',
	];

	/**
	 * Get the source plugin slug.
	 *
	 * @return string
	 */
	public function get_source_plugin(): string {
		return 'contact-form-7/wp-contact-form-7.php';
	}

	/**
	 * Get the source plugin display name.
	 *
	 * @return string
	 */
	public function get_source_name(): string {
		return 'Contact Form 7 (native reCAPTCHA)';
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
		$callback_pattern = '#^' . preg_quote( Base::class, '#' ) . '#';
		$option           = 'wpcf7';
		$results          = [];

		// Remove our filter.
		Utils::instance()->remove_action_regex( $callback_pattern, "option_$option" );

		// CF7 stores reCAPTCHA settings in the 'wpcf7' option under the 'recaptcha' key.
		$wpcf7 = get_option( $option, [] );

		if ( ! is_array( $wpcf7 ) ) {
			return $results;
		}

		$recaptcha = $wpcf7['recaptcha'] ?? [];

		if ( ! empty( $recaptcha ) && is_array( $recaptcha ) ) {
			// CF7 recaptcha is a key-value pair of site_key => secret_key.
			$has_keys = false;

			foreach ( $recaptcha as $site_key => $secret_key ) {
				if ( ! empty( $site_key ) && ! empty( $secret_key ) ) {
					$has_keys = true;

					break;
				}
			}

			if ( $has_keys ) {
				foreach ( self::SURFACES as $surface_id ) {
					$results[] = $this->build_result(
						'recaptcha',
						$surface_id,
						DetectionResult::CONFIDENCE_HIGH,
						'CF7 has reCAPTCHA keys configured. All CF7 forms may use reCAPTCHA.'
					);
				}
			}
		}

		return $results;
	}
}
