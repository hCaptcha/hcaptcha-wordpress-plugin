<?php
/**
 * AbstractDetector class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\MigrationWizard\Detectors;

use HCaptcha\MigrationWizard\DetectionResult;
use HCaptcha\MigrationWizard\SourceDetectorInterface;
use HCaptcha\MigrationWizard\SurfaceMapping;

/**
 * Class AbstractDetector.
 *
 * Base class for source plugin detectors.
 */
abstract class AbstractDetector implements SourceDetectorInterface {

	/**
	 * Check if a plugin is active.
	 *
	 * @param string $plugin_slug Plugin slug (e.g., 'plugin-dir/plugin-file.php').
	 *
	 * @return bool
	 */
	protected function is_plugin_active( string $plugin_slug ): bool {
		return in_array( $plugin_slug, (array) get_option( 'active_plugins', [] ), true );
	}

	/**
	 * Build a DetectionResult for a given surface.
	 *
	 * @param string $provider   Provider (recaptcha/turnstile).
	 * @param string $surface_id Normalized surface identifier.
	 * @param string $confidence Confidence level.
	 * @param string $notes      Optional notes.
	 *
	 * @return DetectionResult
	 */
	protected function build_result( string $provider, string $surface_id, string $confidence, string $notes = '' ): DetectionResult {
		$mapping = SurfaceMapping::get( $surface_id );

		$support_status        = $mapping ? DetectionResult::STATUS_SUPPORTED : DetectionResult::STATUS_UNSUPPORTED;
		$hcaptcha_option_key   = $mapping[0] ?? '';
		$hcaptcha_option_value = $mapping[1] ?? '';
		$surface_label         = $mapping[2] ?? $surface_id;

		return new DetectionResult(
			[
				'provider'              => $provider,
				'source_plugin'         => $this->get_source_plugin(),
				'source_name'           => $this->get_source_name(),
				'surface'               => $surface_id,
				'surface_label'         => $surface_label,
				'confidence'            => $confidence,
				'support_status'        => $support_status,
				'hcaptcha_option_key'   => $hcaptcha_option_key,
				'hcaptcha_option_value' => $hcaptcha_option_value,
				'notes'                 => $notes,
			]
		);
	}
}
