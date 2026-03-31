<?php
/**
 * Scanner class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\MigrationWizard;

use HCaptcha\MigrationWizard\Detectors\AdvancedGoogleRecaptchaDetector;
use HCaptcha\MigrationWizard\Detectors\AdvancedNoCaptchaDetector;
use HCaptcha\MigrationWizard\Detectors\CF7RecaptchaDetector;
use HCaptcha\MigrationWizard\Detectors\ElementorProDetector;
use HCaptcha\MigrationWizard\Detectors\ContactForm7CaptchaDetector;
use HCaptcha\MigrationWizard\Detectors\GoogleCaptchaDetector;
use HCaptcha\MigrationWizard\Detectors\GravityFormsRecaptchaDetector;
use HCaptcha\MigrationWizard\Detectors\SpectraDetector;
use HCaptcha\MigrationWizard\Detectors\SimpleTurnstileDetector;
use HCaptcha\MigrationWizard\Detectors\WPFormsRecaptchaDetector;
use HCaptcha\MigrationWizard\Detectors\WordfenceDetector;

/**
 * Class Scanner.
 *
 * Orchestrates detection across all registered source detectors.
 */
class Scanner {

	/**
	 * Registered detectors.
	 *
	 * @var SourceDetectorInterface[]
	 */
	private array $detectors = [];

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->register_default_detectors();
	}

	/**
	 * Register the default set of detectors.
	 *
	 * @return void
	 */
	private function register_default_detectors(): void {
		$default_detectors = [
			new AdvancedGoogleRecaptchaDetector(),
			new ElementorProDetector(),
			new GoogleCaptchaDetector(),
			new AdvancedNoCaptchaDetector(),
			new SimpleTurnstileDetector(),
			new CF7RecaptchaDetector(),
			new ContactForm7CaptchaDetector(),
			new WPFormsRecaptchaDetector(),
			new GravityFormsRecaptchaDetector(),
			new SpectraDetector(),
			new WordfenceDetector(),
		];

		/**
		 * Filters the list of migration wizard detectors.
		 *
		 * @param SourceDetectorInterface[] $detectors Detector instances.
		 */
		$this->detectors = apply_filters( 'hcap_migration_wizard_detectors', $default_detectors );
	}

	/**
	 * Add a detector.
	 *
	 * @param SourceDetectorInterface $detector Detector instance.
	 *
	 * @return void
	 */
	public function add_detector( SourceDetectorInterface $detector ): void {
		$this->detectors[] = $detector;
	}

	/**
	 * Get all registered detectors.
	 *
	 * @return SourceDetectorInterface[]
	 */
	public function get_detectors(): array {
		return $this->detectors;
	}

	/**
	 * Run a full scan.
	 *
	 * @return ScanResult
	 */
	public function scan(): ScanResult {
		$all_results     = [];
		$scanned_sources = [];
		$skipped_sources = [];

		foreach ( $this->detectors as $detector ) {
			$source_name = $detector->get_source_name();

			if ( ! $detector->is_applicable() ) {
				$skipped_sources[] = $source_name;

				continue;
			}

			$scanned_sources[] = $source_name;

			$results = $detector->detect();

			foreach ( $results as $result ) {
				if ( $result instanceof DetectionResult ) {
					$all_results[] = $result;
				}
			}
		}

		return new ScanResult( $all_results, $scanned_sources, $skipped_sources );
	}
}
