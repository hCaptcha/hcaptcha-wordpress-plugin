<?php
/**
 * ScannerTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Unit\MigrationWizard;

use HCaptcha\MigrationWizard\DetectionResult;
use HCaptcha\MigrationWizard\Scanner;
use HCaptcha\MigrationWizard\SourceDetectorInterface;
use HCaptcha\Tests\Unit\HCaptchaTestCase;
use Mockery;
use ReflectionException;
use WP_Mock;

/**
 * Test Scanner class.
 *
 * @group migration-wizard
 */
class ScannerTest extends HCaptchaTestCase {

	/**
	 * Test scan with no applicable detectors.
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_scan_no_applicable(): void {
		WP_Mock::userFunction( 'apply_filters' )->andReturnUsing(
			static function ( $tag, $value ) {
				return $value;
			}
		);

		WP_Mock::userFunction( 'get_option' )->andReturn( [] );

		$scanner = new Scanner();

		// Override detectors with a mock that is not applicable.
		$detector = Mockery::mock( SourceDetectorInterface::class );
		$detector->shouldReceive( 'is_applicable' )->andReturn( false );
		$detector->shouldReceive( 'get_source_name' )->andReturn( 'Mock Plugin' );

		$this->set_protected_property( $scanner, 'detectors', [ $detector ] );

		$result = $scanner->scan();

		self::assertFalse( $result->has_results() );
		self::assertSame( [ 'Mock Plugin' ], $result->get_skipped_sources() );
		self::assertSame( [], $result->get_scanned_sources() );
	}

	/**
	 * Test scan with an applicable detector returning results.
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_scan_with_results(): void {
		WP_Mock::userFunction( 'apply_filters' )->andReturnUsing(
			static function ( $tag, $value ) {
				return $value;
			}
		);

		WP_Mock::userFunction( 'get_option' )->andReturn( [] );

		$detection = new DetectionResult(
			[
				'provider' => 'recaptcha',
				'surface'  => 'wp_login',
			]
		);

		$detector = Mockery::mock( SourceDetectorInterface::class );
		$detector->shouldReceive( 'is_applicable' )->andReturn( true );
		$detector->shouldReceive( 'get_source_name' )->andReturn( 'Test Plugin' );
		$detector->shouldReceive( 'detect' )->andReturn( [ $detection ] );

		$scanner = new Scanner();

		$this->set_protected_property( $scanner, 'detectors', [ $detector ] );

		$result = $scanner->scan();

		self::assertTrue( $result->has_results() );
		self::assertCount( 1, $result->get_results() );
		self::assertSame( [ 'Test Plugin' ], $result->get_scanned_sources() );
	}

	/**
	 * Test add_detector.
	 *
	 * @return void
	 */
	public function test_add_detector(): void {
		WP_Mock::userFunction( 'apply_filters' )->andReturnUsing(
			static function ( $tag, $value ) {
				return $value;
			}
		);

		WP_Mock::userFunction( 'get_option' )->andReturn( [] );

		$scanner = new Scanner();

		$detector = Mockery::mock( SourceDetectorInterface::class );
		$scanner->add_detector( $detector );

		$detectors = $scanner->get_detectors();

		self::assertContains( $detector, $detectors );
	}
}
