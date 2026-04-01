<?php
/**
 * SourceDetectorInterface file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\MigrationWizard;

/**
 * Interface SourceDetectorInterface.
 *
 * Detects CAPTCHA usage from a specific source plugin.
 */
interface SourceDetectorInterface {

	/**
	 * Get the source plugin slug (e.g., 'google-captcha/google-captcha.php').
	 *
	 * @return string
	 */
	public function get_source_plugin(): string;

	/**
	 * Get the source plugin display name.
	 *
	 * @return string
	 */
	public function get_source_name(): string;

	/**
	 * Check if this detector is applicable (the plugin is active).
	 *
	 * @return bool
	 */
	public function is_applicable(): bool;

	/**
	 * Run detection and return results.
	 *
	 * @return DetectionResult[]
	 */
	public function detect(): array;
}
