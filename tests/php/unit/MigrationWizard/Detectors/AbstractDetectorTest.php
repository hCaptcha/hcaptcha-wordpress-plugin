<?php
/**
 * Test AbstractDetector.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Tests\Unit\MigrationWizard\Detectors;

use HCaptcha\MigrationWizard\Detectors\AbstractDetector;
use HCaptcha\Tests\Unit\HCaptchaTestCase;
use WP_Mock;

/**
 * Test AbstractDetector.
 *
 * @group migration-wizard
 */
class AbstractDetectorTest extends HCaptchaTestCase {

	/**
	 * Test is_plugin_active handles the non-array active_plugins option.
	 */
	public function test_is_plugin_active_handles_non_array_active_plugins(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'active_plugins', [] )
			->andReturn( 'broken' );

		$detector = new class() extends AbstractDetector {

			/**
			 * Get a source plugin.
			 *
			 * @return string
			 */
			public function get_source_plugin(): string {
				return 'test/plugin.php';
			}

			/**
			 * Get the source name.
			 *
			 * @return string
			 */
			public function get_source_name(): string {
				return 'Test Plugin';
			}

			/**
			 * Whether a detector is applicable.
			 *
			 * @return bool
			 */
			public function is_applicable(): bool {
				return $this->is_plugin_active( 'test/plugin.php' );
			}

			/**
			 * Detect settings.
			 *
			 * @return array
			 */
			public function detect(): array {
				return [];
			}
		};

		self::assertFalse( $detector->is_applicable() );
	}
}
