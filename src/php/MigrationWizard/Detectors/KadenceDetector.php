<?php
/**
 * KadenceDetector class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\MigrationWizard\Detectors;

use HCaptcha\MigrationWizard\DetectionResult;

/**
 * Class KadenceDetector.
 *
 * Detects reCAPTCHA and Turnstile usage configured in Kadence Blocks.
 * Kadence stores reCAPTCHA keys in 'kadence_blocks_recaptcha_site_key' and 'kadence_blocks_recaptcha_secret_key' options.
 * Kadence stores Turnstile keys in 'kadence_blocks_turnstile_site_key' and 'kadence_blocks_turnstile_secret_key' options.
 */
class KadenceDetector extends AbstractDetector {

	/**
	 * Kadence Blocks plugin slug.
	 */
	private const PLUGIN_SLUG = 'kadence-blocks/kadence-blocks.php';

	/**
	 * Kadence surface IDs to detect.
	 */
	private const SURFACES = [
		'kadence_form',
		'kadence_advanced',
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
		return 'Kadence Blocks';
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

		$this->detect_recaptcha( $results );
		$this->detect_turnstile( $results );

		return $results;
	}

	/**
	 * Detect reCAPTCHA keys.
	 *
	 * @param DetectionResult[] $results Detection results.
	 *
	 * @return void
	 */
	private function detect_recaptcha( array &$results ): void {
		$site_key   = trim( (string) get_option( 'kadence_blocks_recaptcha_site_key', '' ) );
		$secret_key = trim( (string) get_option( 'kadence_blocks_recaptcha_secret_key', '' ) );

		if ( '' === $site_key || '' === $secret_key ) {
			return;
		}

		foreach ( self::SURFACES as $surface_id ) {
			$results[] = $this->build_result(
				'recaptcha',
				$surface_id,
				DetectionResult::CONFIDENCE_HIGH,
				'Kadence Blocks has reCAPTCHA keys configured. Kadence forms may use reCAPTCHA.'
			);
		}
	}

	/**
	 * Detect Turnstile keys.
	 *
	 * @param DetectionResult[] $results Detection results.
	 *
	 * @return void
	 */
	private function detect_turnstile( array &$results ): void {
		$site_key   = trim( (string) get_option( 'kadence_blocks_turnstile_site_key', '' ) );
		$secret_key = trim( (string) get_option( 'kadence_blocks_turnstile_secret_key', '' ) );

		if ( '' === $site_key || '' === $secret_key ) {
			return;
		}

		foreach ( self::SURFACES as $surface_id ) {
			$results[] = $this->build_result(
				'turnstile',
				$surface_id,
				DetectionResult::CONFIDENCE_HIGH,
				'Kadence Blocks has Turnstile keys configured. Kadence forms may use Turnstile.'
			);
		}
	}
}
