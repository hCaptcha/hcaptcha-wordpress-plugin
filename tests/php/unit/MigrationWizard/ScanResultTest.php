<?php
/**
 * ScanResultTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Unit\MigrationWizard;

use HCaptcha\MigrationWizard\DetectionResult;
use HCaptcha\MigrationWizard\ScanResult;
use HCaptcha\Tests\Unit\HCaptchaTestCase;

/**
 * Test ScanResult class.
 *
 * @group migration-wizard
 */
class ScanResultTest extends HCaptchaTestCase {

	/**
	 * Test empty scan result.
	 *
	 * @return void
	 */
	public function test_empty_result(): void {
		$result = new ScanResult( [], [], [] );

		self::assertFalse( $result->has_results() );
		self::assertSame( [], $result->get_results() );
		self::assertSame( [], $result->get_migratable() );
		self::assertSame( [], $result->get_unsupported() );
	}

	/**
	 * Test get_migratable filters correctly.
	 *
	 * @return void
	 */
	public function test_get_migratable(): void {
		$migratable = new DetectionResult(
			[
				'support_status'        => DetectionResult::STATUS_SUPPORTED,
				'hcaptcha_option_key'   => 'wp_status',
				'hcaptcha_option_value' => 'login',
			]
		);

		$unsupported = new DetectionResult(
			[
				'support_status' => DetectionResult::STATUS_UNSUPPORTED,
			]
		);

		$result = new ScanResult( [ $migratable, $unsupported ], [ 'Source A' ], [] );

		self::assertTrue( $result->has_results() );
		self::assertCount( 1, $result->get_migratable() );
		self::assertCount( 1, $result->get_unsupported() );
	}

	/**
	 * Test get_needs_review filters medium/low confidence migratable results.
	 *
	 * @return void
	 */
	public function test_get_needs_review(): void {
		$high = new DetectionResult(
			[
				'confidence'            => DetectionResult::CONFIDENCE_HIGH,
				'support_status'        => DetectionResult::STATUS_SUPPORTED,
				'hcaptcha_option_key'   => 'wp_status',
				'hcaptcha_option_value' => 'login',
			]
		);

		$medium = new DetectionResult(
			[
				'confidence'            => DetectionResult::CONFIDENCE_MEDIUM,
				'support_status'        => DetectionResult::STATUS_SUPPORTED,
				'hcaptcha_option_key'   => 'wp_status',
				'hcaptcha_option_value' => 'comment',
			]
		);

		$result = new ScanResult( [ $high, $medium ], [], [] );

		self::assertCount( 1, $result->get_needs_review() );
	}

	/**
	 * Test to_array and from_array round trip.
	 *
	 * @return void
	 */
	public function test_to_array_from_array(): void {
		$detection = new DetectionResult(
			[
				'provider'              => 'recaptcha',
				'surface'               => 'wp_login',
				'support_status'        => DetectionResult::STATUS_SUPPORTED,
				'hcaptcha_option_key'   => 'wp_status',
				'hcaptcha_option_value' => 'login',
			]
		);

		$original = new ScanResult( [ $detection ], [ 'Source A' ], [ 'Source B' ] );
		$array    = $original->to_array();

		self::assertSame( 1, $array['total'] );
		self::assertSame( 1, $array['migratable'] );
		self::assertSame( 0, $array['unsupported'] );
		self::assertSame( [ 'Source A' ], $array['scanned_sources'] );
		self::assertSame( [ 'Source B' ], $array['skipped_sources'] );

		$restored = ScanResult::from_array( $array );

		self::assertCount( 1, $restored->get_results() );
		self::assertSame( 'recaptcha', $restored->get_results()[0]->get_provider() );
	}

	/**
	 * Test scanned and skipped sources.
	 *
	 * @return void
	 */
	public function test_sources(): void {
		$result = new ScanResult( [], [ 'Scanned' ], [ 'Skipped' ] );

		self::assertSame( [ 'Scanned' ], $result->get_scanned_sources() );
		self::assertSame( [ 'Skipped' ], $result->get_skipped_sources() );
	}
}
