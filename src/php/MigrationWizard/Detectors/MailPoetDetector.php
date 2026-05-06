<?php
/**
 * MailPoetDetector class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\MigrationWizard\Detectors;

use HCaptcha\MigrationWizard\DetectionResult;

/**
 * Class MailPoetDetector.
 *
 * Detects reCAPTCHA usage configured in MailPoet.
 * MailPoet stores captcha settings in its own '{prefix}mailpoet_settings' table
 * (not in wp_options). The 'captcha' row contains a serialized array with tokens
 * for reCAPTCHA v2 and invisible reCAPTCHA.
 */
class MailPoetDetector extends AbstractDetector {

	/**
	 * MailPoet plugin slug.
	 */
	private const PLUGIN_SLUG = 'mailpoet/mailpoet.php';

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
		return 'MailPoet';
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
		$captcha = $this->get_captcha_settings();

		if ( ! is_array( $captcha ) ) {
			return [];
		}

		// Check reCAPTCHA v2 keys.
		$site_key   = trim( (string) ( $captcha['recaptcha_site_token'] ?? '' ) );
		$secret_key = trim( (string) ( $captcha['recaptcha_secret_token'] ?? '' ) );

		if ( '' !== $site_key && '' !== $secret_key ) {
			return [
				$this->build_result(
					'recaptcha',
					'mailpoet_form',
					DetectionResult::CONFIDENCE_HIGH,
					'MailPoet has reCAPTCHA v2 keys configured.'
				),
			];
		}

		// Check reCAPTCHA invisible keys.
		$inv_site_key   = trim( (string) ( $captcha['recaptcha_invisible_site_token'] ?? '' ) );
		$inv_secret_key = trim( (string) ( $captcha['recaptcha_invisible_secret_token'] ?? '' ) );

		if ( '' !== $inv_site_key && '' !== $inv_secret_key ) {
			return [
				$this->build_result(
					'recaptcha',
					'mailpoet_form',
					DetectionResult::CONFIDENCE_HIGH,
					'MailPoet has invisible reCAPTCHA keys configured.'
				),
			];
		}

		return [];
	}

	/**
	 * Get captcha settings from MailPoet's own settings table.
	 *
	 * @return array|null
	 */
	private function get_captcha_settings(): ?array {
		global $wpdb;

		$table = $wpdb->prefix . 'mailpoet_settings';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$value = $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT value FROM $table WHERE name = %s",
				'captcha'
			)
		);

		if ( null === $value ) {
			return null;
		}

		$captcha = maybe_unserialize( $value );

		return is_array( $captcha ) ? $captcha : null;
	}
}
