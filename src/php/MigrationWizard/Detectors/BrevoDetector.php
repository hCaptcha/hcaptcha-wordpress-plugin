<?php
/**
 * BrevoDetector class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\MigrationWizard\Detectors;

use HCaptcha\MigrationWizard\DetectionResult;

/**
 * Class BrevoDetector.
 *
 * Detects reCAPTCHA and Cloudflare Turnstile usage configured in Brevo (Sendinblue) plugin forms.
 */
class BrevoDetector extends AbstractDetector {

	/**
	 * Brevo plugin slug.
	 */
	private const PLUGIN_SLUG = 'mailin/sendinblue.php';

	/**
	 * Brevo forms table name (without prefix).
	 */
	private const TABLE_NAME = 'sib_model_forms';

	/**
	 * Turnstile captcha type value.
	 */
	private const CAPTCHA_TYPE_TURNSTILE = 3;

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
		return 'Brevo';
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
		$results = [];

		if ( $this->has_recaptcha() ) {
			$results[] = $this->build_result(
				'recaptcha',
				'sendinblue_form',
				DetectionResult::CONFIDENCE_HIGH,
				'Brevo has forms with reCAPTCHA keys configured.'
			);
		}

		if ( $this->has_turnstile() ) {
			$results[] = $this->build_result(
				'turnstile',
				'sendinblue_form',
				DetectionResult::CONFIDENCE_HIGH,
				'Brevo has forms with Cloudflare Turnstile keys configured.'
			);
		}

		return $results;
	}

	/**
	 * Check if any Brevo form has reCAPTCHA configured.
	 *
	 * @return bool
	 * @noinspection SqlResolve
	 */
	private function has_recaptcha(): bool {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE gCaptcha != 0 AND selectCaptchaType != %d AND gCaptcha_site != %s AND gCaptcha_secret != %s',
				$wpdb->prefix . self::TABLE_NAME,
				self::CAPTCHA_TYPE_TURNSTILE,
				'',
				''
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return (int) $count > 0;
	}

	/**
	 * Check if any Brevo form has Cloudflare Turnstile configured.
	 *
	 * @return bool
	 * @noinspection SqlResolve
	 */
	private function has_turnstile(): bool {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE gCaptcha != 0 AND selectCaptchaType = %d AND cCaptcha_site != %s AND cCaptcha_secret != %s',
				$wpdb->prefix . self::TABLE_NAME,
				self::CAPTCHA_TYPE_TURNSTILE,
				'',
				''
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return (int) $count > 0;
	}
}
