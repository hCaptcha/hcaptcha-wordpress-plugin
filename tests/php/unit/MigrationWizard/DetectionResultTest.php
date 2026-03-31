<?php
/**
 * DetectionResultTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Unit\MigrationWizard;

use HCaptcha\MigrationWizard\DetectionResult;
use HCaptcha\Tests\Unit\HCaptchaTestCase;

/**
 * Test DetectionResult class.
 *
 * @group migration-wizard
 */
class DetectionResultTest extends HCaptchaTestCase {

	/**
	 * Test constructor with default values.
	 *
	 * @return void
	 */
	public function test_constructor_defaults(): void {
		$result = new DetectionResult();

		self::assertSame( '', $result->get_provider() );
		self::assertSame( '', $result->get_source_plugin() );
		self::assertSame( '', $result->get_source_name() );
		self::assertSame( '', $result->get_surface() );
		self::assertSame( '', $result->get_surface_label() );
		self::assertSame( DetectionResult::CONFIDENCE_LOW, $result->get_confidence() );
		self::assertSame( DetectionResult::STATUS_UNKNOWN, $result->get_support_status() );
		self::assertSame( '', $result->get_hcaptcha_option_key() );
		self::assertSame( '', $result->get_hcaptcha_option_value() );
		self::assertSame( '', $result->get_notes() );
	}

	/**
	 * Test constructor with custom values.
	 *
	 * @return void
	 */
	public function test_constructor_custom(): void {
		$args = [
			'provider'              => 'recaptcha',
			'source_plugin'         => 'some-plugin/some-plugin.php',
			'source_name'           => 'Some Plugin',
			'surface'               => 'wp_login',
			'surface_label'         => 'WordPress Login',
			'confidence'            => DetectionResult::CONFIDENCE_HIGH,
			'support_status'        => DetectionResult::STATUS_SUPPORTED,
			'hcaptcha_option_key'   => 'wp_status',
			'hcaptcha_option_value' => 'login',
			'notes'                 => 'Test note',
		];

		$result = new DetectionResult( $args );

		self::assertSame( 'recaptcha', $result->get_provider() );
		self::assertSame( 'some-plugin/some-plugin.php', $result->get_source_plugin() );
		self::assertSame( 'Some Plugin', $result->get_source_name() );
		self::assertSame( 'wp_login', $result->get_surface() );
		self::assertSame( 'WordPress Login', $result->get_surface_label() );
		self::assertSame( DetectionResult::CONFIDENCE_HIGH, $result->get_confidence() );
		self::assertSame( DetectionResult::STATUS_SUPPORTED, $result->get_support_status() );
		self::assertSame( 'wp_status', $result->get_hcaptcha_option_key() );
		self::assertSame( 'login', $result->get_hcaptcha_option_value() );
		self::assertSame( 'Test note', $result->get_notes() );
	}

	/**
	 * Test is_migratable returns true for a supported result with keys.
	 *
	 * @return void
	 */
	public function test_is_migratable_true(): void {
		$result = new DetectionResult(
			[
				'support_status'        => DetectionResult::STATUS_SUPPORTED,
				'hcaptcha_option_key'   => 'wp_status',
				'hcaptcha_option_value' => 'login',
			]
		);

		self::assertTrue( $result->is_migratable() );
	}

	/**
	 * Test is_migratable returns false for unsupported.
	 *
	 * @return void
	 */
	public function test_is_migratable_false_unsupported(): void {
		$result = new DetectionResult(
			[
				'support_status'        => DetectionResult::STATUS_UNSUPPORTED,
				'hcaptcha_option_key'   => 'wp_status',
				'hcaptcha_option_value' => 'login',
			]
		);

		self::assertFalse( $result->is_migratable() );
	}

	/**
	 * Test is_migratable returns false when an option key is empty.
	 *
	 * @return void
	 */
	public function test_is_migratable_false_no_key(): void {
		$result = new DetectionResult(
			[
				'support_status'        => DetectionResult::STATUS_SUPPORTED,
				'hcaptcha_option_key'   => '',
				'hcaptcha_option_value' => 'login',
			]
		);

		self::assertFalse( $result->is_migratable() );
	}

	/**
	 * Test to_array returns the correct structure.
	 *
	 * @return void
	 */
	public function test_to_array(): void {
		$args = [
			'provider'              => 'turnstile',
			'source_plugin'         => 'test/test.php',
			'source_name'           => 'Test',
			'surface'               => 'wp_comment',
			'surface_label'         => 'WordPress Comment',
			'confidence'            => DetectionResult::CONFIDENCE_MEDIUM,
			'support_status'        => DetectionResult::STATUS_SUPPORTED,
			'hcaptcha_option_key'   => 'wp_status',
			'hcaptcha_option_value' => 'comment',
			'notes'                 => 'Some note',
		];

		$result = new DetectionResult( $args );
		$array  = $result->to_array();

		self::assertSame( 'turnstile', $array['provider'] );
		self::assertSame( 'wp_comment', $array['surface'] );
		self::assertTrue( $array['is_migratable'] );
	}

	/**
	 * Test from_array creates the correct instance.
	 *
	 * @return void
	 */
	public function test_from_array(): void {
		$data = [
			'provider'              => 'recaptcha',
			'surface'               => 'wp_login',
			'confidence'            => DetectionResult::CONFIDENCE_HIGH,
			'support_status'        => DetectionResult::STATUS_SUPPORTED,
			'hcaptcha_option_key'   => 'wp_status',
			'hcaptcha_option_value' => 'login',
		];

		$result = DetectionResult::from_array( $data );

		self::assertSame( 'recaptcha', $result->get_provider() );
		self::assertSame( 'wp_login', $result->get_surface() );
	}
}
