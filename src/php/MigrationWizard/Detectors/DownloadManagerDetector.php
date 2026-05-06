<?php
/**
 * DownloadManagerDetector class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\MigrationWizard\Detectors;

use HCaptcha\MigrationWizard\DetectionResult;

/**
 * Class DownloadManagerDetector.
 *
 * Detects reCAPTCHA Enterprise usage configured in Download Manager.
 */
class DownloadManagerDetector extends AbstractDetector {

	/**
	 * Download Manager plugin slug.
	 */
	private const PLUGIN_SLUG = 'download-manager/download-manager.php';

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
		return 'Download Manager';
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
		$site_key   = (string) get_option( '_wpdm_recaptcha_site_key', '' );
		$secret_key = (string) get_option( '_wpdm_recaptcha_secret_key', '' );
		$project_id = (string) get_option( '_wpdm_recaptcha_project_id', '' );

		if (
			'' !== trim( $site_key ) &&
			'' !== trim( $secret_key ) &&
			'' !== trim( $project_id )
		) {
			return [
				$this->build_result(
					'recaptcha',
					'download_manager_button',
					DetectionResult::CONFIDENCE_HIGH,
					'Download Manager has reCAPTCHA Enterprise keys configured.'
				),
			];
		}

		return [];
	}
}
