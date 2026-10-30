<?php
/**
 * DetectionResultTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\MigrationWizard;

use HCaptcha\MigrationWizard\DetectionResult;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;

/**
 * Test the detection data passed from scanners to the migration wizard.
 *
 * @group migration-wizard
 */
class DetectionResultTest extends HCaptchaWPTestCase {
	/**
	 * A supported surface retains its source, target setting, and migration status.
	 */
	public function test_supported_surface_round_trip(): void {
		$data   = [
			'provider'              => 'recaptcha',
			'source_plugin'         => 'essential-blocks/essential-blocks.php',
			'source_name'           => 'Essential Blocks',
			'surface'               => 'essential_blocks_form',
			'surface_label'         => 'Essential Blocks form',
			'confidence'            => DetectionResult::CONFIDENCE_HIGH,
			'support_status'        => DetectionResult::STATUS_SUPPORTED,
			'hcaptcha_option_key'   => 'essential_blocks_status',
			'hcaptcha_option_value' => 'form',
			'notes'                 => 'Saved form detected.',
		];
		$result = DetectionResult::from_array( $data );

		self::assertSame( $data['provider'], $result->get_provider() );
		self::assertSame( $data['source_plugin'], $result->get_source_plugin() );
		self::assertSame( $data['source_name'], $result->get_source_name() );
		self::assertSame( $data['surface'], $result->get_surface() );
		self::assertSame( $data['surface_label'], $result->get_surface_label() );
		self::assertSame( $data['confidence'], $result->get_confidence() );
		self::assertSame( $data['support_status'], $result->get_support_status() );
		self::assertSame( $data['hcaptcha_option_key'], $result->get_hcaptcha_option_key() );
		self::assertSame( $data['hcaptcha_option_value'], $result->get_hcaptcha_option_value() );
		self::assertSame( $data['notes'], $result->get_notes() );
		self::assertTrue( $result->is_migratable() );
		self::assertSame( $data + [ 'is_migratable' => true ], $result->to_array() );
	}

	/**
	 * Unsupported or incomplete settings cannot be migrated.
	 *
	 * @param array $data Surface overrides.
	 *
	 * @dataProvider dp_incomplete_surface
	 */
	public function test_incomplete_surface_is_not_migratable( array $data ): void {
		$result = new DetectionResult( $data );

		self::assertFalse( $result->is_migratable() );
		self::assertFalse( $result->to_array()['is_migratable'] );
	}

	/**
	 * Each missing prerequisite prevents migration.
	 *
	 * @return array
	 */
	public function dp_incomplete_surface(): array {
		return [
			'unknown by default'    => [ [] ],
			'missing setting key'   => [
				[
					'support_status'        => DetectionResult::STATUS_SUPPORTED,
					'hcaptcha_option_value' => 'form',
				],
			],
			'missing setting value' => [
				[
					'support_status'      => DetectionResult::STATUS_SUPPORTED,
					'hcaptcha_option_key' => 'essential_blocks_status',
				],
			],
		];
	}
}
