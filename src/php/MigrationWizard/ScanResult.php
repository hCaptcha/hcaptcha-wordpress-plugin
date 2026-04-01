<?php
/**
 * ScanResult class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\MigrationWizard;

/**
 * Class ScanResult.
 *
 * Holds the complete result of a migration scan.
 */
class ScanResult {

	/**
	 * Detection results.
	 *
	 * @var DetectionResult[]
	 */
	private array $results;

	/**
	 * Sources that were scanned.
	 *
	 * @var string[]
	 */
	private array $scanned_sources;

	/**
	 * Sources that were skipped (not applicable).
	 *
	 * @var string[]
	 */
	private array $skipped_sources;

	/**
	 * Constructor.
	 *
	 * @param DetectionResult[] $results         Detection results.
	 * @param string[]          $scanned_sources Sources that were scanned.
	 * @param string[]          $skipped_sources Sources that were skipped.
	 */
	public function __construct( array $results, array $scanned_sources, array $skipped_sources ) {
		$this->results         = $results;
		$this->scanned_sources = $scanned_sources;
		$this->skipped_sources = $skipped_sources;
	}

	/**
	 * Get all detection results.
	 *
	 * @return DetectionResult[]
	 */
	public function get_results(): array {
		return $this->results;
	}

	/**
	 * Get migratable results only.
	 *
	 * @return DetectionResult[]
	 */
	public function get_migratable(): array {
		return array_filter(
			$this->results,
			static function ( DetectionResult $result ) {
				return $result->is_migratable();
			}
		);
	}

	/**
	 * Get results that need review.
	 *
	 * @return DetectionResult[]
	 */
	public function get_needs_review(): array {
		return array_filter(
			$this->results,
			static function ( DetectionResult $result ) {
				return $result->is_migratable()
					&& DetectionResult::CONFIDENCE_HIGH !== $result->get_confidence();
			}
		);
	}

	/**
	 * Get unsupported results.
	 *
	 * @return DetectionResult[]
	 */
	public function get_unsupported(): array {
		return array_filter(
			$this->results,
			static function ( DetectionResult $result ) {
				return ! $result->is_migratable();
			}
		);
	}

	/**
	 * Get scanned source names.
	 *
	 * @return string[]
	 */
	public function get_scanned_sources(): array {
		return $this->scanned_sources;
	}

	/**
	 * Get skipped source names.
	 *
	 * @return string[]
	 */
	public function get_skipped_sources(): array {
		return $this->skipped_sources;
	}

	/**
	 * Check if any results were found.
	 *
	 * @return bool
	 */
	public function has_results(): bool {
		return ! empty( $this->results );
	}

	/**
	 * Convert to array for JSON serialization.
	 *
	 * @return array
	 */
	public function to_array(): array {
		return [
			'results'         => array_map(
				static function ( DetectionResult $result ) {
					return $result->to_array();
				},
				$this->results
			),
			'scanned_sources' => $this->scanned_sources,
			'skipped_sources' => $this->skipped_sources,
			'total'           => count( $this->results ),
			'migratable'      => count( $this->get_migratable() ),
			'unsupported'     => count( $this->get_unsupported() ),
		];
	}

	/**
	 * Create from an array.
	 *
	 * @param array $data Data array.
	 *
	 * @return self
	 */
	public static function from_array( array $data ): self {
		$results = [];

		foreach ( $data['results'] ?? [] as $result_data ) {
			$results[] = DetectionResult::from_array( $result_data );
		}

		return new self(
			$results,
			$data['scanned_sources'] ?? [],
			$data['skipped_sources'] ?? []
		);
	}
}
