<?php
/**
 * TutorLMSDetector class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\MigrationWizard\Detectors;

use HCaptcha\MigrationWizard\DetectionResult;

/**
 * Detects Tutor LMS Pro fraud protection on login and registration forms.
 */
class TutorLMSDetector extends AbstractDetector {

	/**
	 * Tutor LMS Pro plugin slug.
	 */
	private const PLUGIN_SLUG = 'tutor-pro/tutor-pro.php';

	/**
	 * Tutor location to hCaptcha surface mapping.
	 */
	private const LOCATIONS = [
		'tutor_login'        => [ 'tutor_login', 'tutor_lost_password' ],
		'tutor_registration' => [ 'tutor_register' ],
		'wp_login'           => [ 'wp_login' ],
		'wp_registration'    => [ 'wp_register' ],
	];

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
		return 'Tutor LMS Pro';
	}

	/**
	 * Check if Tutor LMS Pro is active.
	 *
	 * @return bool
	 */
	public function is_applicable(): bool {
		return $this->is_plugin_active( self::PLUGIN_SLUG );
	}

	/**
	 * Detect enabled reCAPTCHA locations in Tutor LMS Pro settings.
	 *
	 * @return DetectionResult[]
	 */
	public function detect(): array {
		$options = get_option( 'tutor_option', [] );

		if ( ! is_array( $options ) ) {
			return [];
		}

		if ( ! $this->has_recaptcha( $options ) ) {
			return [];
		}

		$locations = $options['spam_protection_location'] ?? [];

		if ( ! is_array( $locations ) ) {
			return [];
		}

		$results = [];

		foreach ( self::LOCATIONS as $location => $surfaces ) {
			if ( ! $this->location_enabled( $locations, $location ) ) {
				continue;
			}

			foreach ( $surfaces as $surface ) {
				$results[] = $this->build_result(
					'recaptcha',
					$surface,
					DetectionResult::CONFIDENCE_HIGH,
					'Disable Tutor LMS Pro reCAPTCHA for this form after verifying hCaptcha.'
				);
			}
		}

		return $results;
	}

	/**
	 * Check that reCAPTCHA is enabled and has credentials.
	 *
	 * @param array $options Tutor options.
	 *
	 * @return bool
	 */
	private function has_recaptcha( array $options ): bool {
		$enabled = $options['enable_spam_protection'] ?? '';
		$method  = $options['spam_protection_method'] ?? '';

		if ( ! $this->is_enabled( $enabled ) || ! in_array( $method, [ 'recaptcha_v2', 'recaptcha_v3' ], true ) ) {
			return false;
		}

		return $this->has_recaptcha_keys( $options, $method );
	}

	/**
	 * Check that both reCAPTCHA keys have values.
	 *
	 * @param array  $options Tutor options.
	 * @param string $method  Selected reCAPTCHA method.
	 *
	 * @return bool
	 */
	private function has_recaptcha_keys( array $options, string $method ): bool {
		$site   = $options[ $method . '_site_key' ] ?? '';
		$secret = $options[ $method . '_secret_key' ] ?? '';

		return is_string( $site ) && is_string( $secret ) && '' !== trim( $site ) && '' !== trim( $secret );
	}

	/**
	 * Check a Tutor LMS switch value.
	 *
	 * @param mixed $value Switch value.
	 *
	 * @return bool
	 */
	private function is_enabled( $value ): bool {
		return true === $value || 1 === $value || '1' === $value || 'on' === $value;
	}

	/**
	 * Check a location in an associative or list setting.
	 *
	 * @param array  $locations Location setting.
	 * @param string $location  Location name.
	 *
	 * @return bool
	 */
	private function location_enabled( array $locations, string $location ): bool {
		return isset( $locations[ $location ] ) || in_array( $location, $locations, true );
	}
}
