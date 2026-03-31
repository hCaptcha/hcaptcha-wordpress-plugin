<?php
/**
 * ContactForm7CaptchaDetector class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\MigrationWizard\Detectors;

use HCaptcha\MigrationWizard\DetectionResult;

/**
 * Class ContactForm7CaptchaDetector.
 *
 * Detects CAPTCHA usage from the "Contact Form 7 Captcha" plugin (by 247wd).
 * Plugin slug: contact-form-7-simple-recaptcha/contact-form-7-simple-recaptcha.php.
 * Options stored in individual 'cf7sr_*' options per provider.
 */
class ContactForm7CaptchaDetector extends AbstractDetector {

	/**
	 * CF7 surface IDs to detect.
	 */
	private const SURFACES = [
		'cf7_form',
		'cf7_embed',
	];

	/**
	 * Provider configurations: key option, secret option, and provider name.
	 */
	private const PROVIDERS = [
		[ 'cf7sr_key', 'cf7sr_secret', 'recaptcha' ],
		[ 'cf7sr_key_v3', 'cf7sr_secret_v3', 'recaptcha' ],
		[ 'cf7sr_ts_key', 'cf7sr_ts_secret', 'turnstile' ],
	];

	/**
	 * Get the source plugin slug.
	 *
	 * @return string
	 */
	public function get_source_plugin(): string {
		return 'contact-form-7-simple-recaptcha/contact-form-7-simple-recaptcha.php';
	}

	/**
	 * Get the source plugin display name.
	 *
	 * @return string
	 */
	public function get_source_name(): string {
		return 'Contact Form 7 Captcha';
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

		foreach ( self::PROVIDERS as $provider ) {
			[ $key_option, $secret_option, $provider_name ] = $provider;

			$key    = get_option( $key_option, '' );
			$secret = get_option( $secret_option, '' );

			if ( empty( $key ) || empty( $secret ) ) {
				continue;
			}

			foreach ( self::SURFACES as $surface_id ) {
				$results[] = $this->build_result(
					$provider_name,
					$surface_id,
					DetectionResult::CONFIDENCE_HIGH
				);
			}

			// Only report the first configured provider.
			break;
		}

		return $results;
	}
}
