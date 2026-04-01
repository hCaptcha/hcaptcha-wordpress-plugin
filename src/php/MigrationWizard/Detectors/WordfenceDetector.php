<?php
/**
 * WordfenceDetector class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\MigrationWizard\Detectors;

use HCaptcha\MigrationWizard\DetectionResult;

/**
 * Class WordfenceDetector.
 *
 * Detects reCAPTCHA usage from the "Wordfence" or "Wordfence Login Security" plugins.
 * Plugin slugs: wordfence/wordfence.php, wordfence-login-security/wordfence-login-security.php.
 */
class WordfenceDetector extends AbstractDetector {

	/**
	 * Get the source plugin slug.
	 *
	 * Note: Wordfence Login Security is also common, but Wordfence is the primary one.
	 *
	 * @return string
	 */
	public function get_source_plugin(): string {
		return 'wordfence/wordfence.php';
	}

	/**
	 * Get the source plugin display name.
	 *
	 * @return string
	 */
	public function get_source_name(): string {
		return 'Wordfence';
	}

	/**
	 * Check if this detector is applicable.
	 *
	 * @return bool
	 */
	public function is_applicable(): bool {
		return (
			$this->is_plugin_active( 'wordfence/wordfence.php' ) ||
			$this->is_plugin_active( 'wordfence-login-security/wordfence-login-security.php' )
		);
	}

	/**
	 * Run detection.
	 *
	 * Wordfence stores settings in a custom DB table `{prefix}wfls_settings`,
	 * not in wp_options. Keys: 'enable-auth-captcha', 'recaptcha-site-key', 'recaptcha-secret'.
	 *
	 * @return DetectionResult[]
	 */
	public function detect(): array {
		global $wpdb;

		$results = [];
		$table   = $wpdb->base_prefix . 'wfls_settings';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT `name`, `value` FROM `{$table}` WHERE `name` IN (%s, %s, %s)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				'enable-auth-captcha',
				'recaptcha-site-key',
				'recaptcha-secret'
			)
		);

		if ( ! is_array( $rows ) ) {
			return $results;
		}

		$settings = [];

		foreach ( $rows as $row ) {
			$settings[ $row->name ] = $row->value;
		}

		$enabled  = ! empty( $settings['enable-auth-captcha'] );
		$site_key = $settings['recaptcha-site-key'] ?? '';
		$secret   = $settings['recaptcha-secret'] ?? '';

		if ( $enabled && ! empty( $site_key ) && ! empty( $secret ) ) {
			$results[] = $this->build_result(
				'recaptcha',
				'wordfence_login',
				DetectionResult::CONFIDENCE_HIGH,
				'Wordfence reCAPTCHA is enabled with keys configured.'
			);
		} elseif ( ! empty( $site_key ) && ! empty( $secret ) ) {
			$results[] = $this->build_result(
				'recaptcha',
				'wordfence_login',
				DetectionResult::CONFIDENCE_MEDIUM,
				'Wordfence reCAPTCHA keys are configured but CAPTCHA may not be enabled.'
			);
		}

		return $results;
	}
}
