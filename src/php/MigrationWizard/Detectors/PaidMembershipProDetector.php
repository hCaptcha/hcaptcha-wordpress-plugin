<?php
/**
 * PaidMembershipProDetector class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\MigrationWizard\Detectors;

use HCaptcha\MigrationWizard\DetectionResult;

/**
 * Class PaidMembershipProDetector.
 *
 * Detects reCAPTCHA and Cloudflare Turnstile usage configured in Paid Memberships Pro plugin settings.
 */
class PaidMembershipProDetector extends AbstractDetector {

	/**
	 * Paid Memberships Pro plugin slug.
	 */
	private const PLUGIN_SLUG = 'paid-memberships-pro/paid-memberships-pro.php';

	/**
	 * Surfaces to report for Paid Memberships Pro.
	 */
	private const SURFACES = [ 'pmp_checkout', 'pmp_login' ];

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
		return 'Paid Memberships Pro';
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
		return array_merge(
			$this->detect_recaptcha(),
			$this->detect_turnstile()
		);
	}

	/**
	 * Detect reCAPTCHA keys.
	 *
	 * @return DetectionResult[]
	 */
	private function detect_recaptcha(): array {
		$site_key   = (string) get_option( 'pmpro_recaptcha_publickey', '' );
		$secret_key = (string) get_option( 'pmpro_recaptcha_privatekey', '' );

		if ( '' === trim( $site_key ) || '' === trim( $secret_key ) ) {
			return [];
		}

		$results = [];

		foreach ( self::SURFACES as $surface ) {
			$results[] = $this->build_result(
				'recaptcha',
				$surface,
				DetectionResult::CONFIDENCE_HIGH,
				'Paid Memberships Pro has reCAPTCHA keys configured.'
			);
		}

		return $results;
	}

	/**
	 * Detect Cloudflare Turnstile keys.
	 *
	 * @return DetectionResult[]
	 */
	private function detect_turnstile(): array {
		$site_key   = (string) get_option( 'pmpro_cloudflare_turnstile_site_key', '' );
		$secret_key = (string) get_option( 'pmpro_cloudflare_turnstile_secret_key', '' );

		if ( '' === trim( $site_key ) || '' === trim( $secret_key ) ) {
			return [];
		}

		$results = [];

		foreach ( self::SURFACES as $surface ) {
			$results[] = $this->build_result(
				'turnstile',
				$surface,
				DetectionResult::CONFIDENCE_HIGH,
				'Paid Memberships Pro has Cloudflare Turnstile keys configured.'
			);
		}

		return $results;
	}
}
