<?php
/**
 * BeaverBuilderDetectorTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Unit\MigrationWizard\Detectors;

use HCaptcha\MigrationWizard\DetectionResult;
use HCaptcha\MigrationWizard\Detectors\BeaverBuilderDetector;
use HCaptcha\Tests\Unit\HCaptchaTestCase;
use WP_Mock;

/**
 * Test BeaverBuilderDetector class.
 *
 * @group migration-wizard
 */
class BeaverBuilderDetectorTest extends HCaptchaTestCase {

	/**
	 * Test get_source_plugin.
	 *
	 * @return void
	 */
	public function test_get_source_plugin(): void {
		$detector = new BeaverBuilderDetector();

		self::assertSame( 'bb-plugin/fl-builder.php', $detector->get_source_plugin() );
	}

	/**
	 * Test get_source_name.
	 *
	 * @return void
	 */
	public function test_get_source_name(): void {
		$detector = new BeaverBuilderDetector();

		self::assertSame( 'Beaver Builder', $detector->get_source_name() );
	}

	/**
	 * Test is_applicable when the plugin is active.
	 *
	 * @return void
	 */
	public function test_is_applicable_true(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'active_plugins', [] )
			->andReturn( [ 'bb-plugin/fl-builder.php' ] );

		$detector = new BeaverBuilderDetector();

		self::assertTrue( $detector->is_applicable() );
	}

	/**
	 * Test is_applicable when the plugin is not active.
	 *
	 * @return void
	 */
	public function test_is_applicable_false(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'active_plugins', [] )
			->andReturn( [] );

		$detector = new BeaverBuilderDetector();

		self::assertFalse( $detector->is_applicable() );
	}

	/**
	 * Build serialized '_fl_builder_data' with given recaptcha key values.
	 *
	 * @param string $site_key   reCAPTCHA site key.
	 * @param string $secret_key reCAPTCHA secret key.
	 *
	 * @return string
	 */
	private function build_meta_value( string $site_key, string $secret_key ): string {
		$settings                       = new \stdClass();
		$settings->recaptcha_site_key   = $site_key;
		$settings->recaptcha_secret_key = $secret_key;

		$node           = new \stdClass();
		$node->node     = 'abc123';
		$node->type     = 'module';
		$node->settings = $settings;

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		return serialize( [ 'abc123' => $node ] );
	}

	/**
	 * Set up wpdb mock.
	 *
	 * @param string|null $meta_value Value to return from get_var, or null for no row.
	 *
	 * @return void
	 */
	private function setup_wpdb( ?string $meta_value ): void {
		global $wpdb;

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$wpdb           = \Mockery::mock( 'wpdb' );
		$wpdb->postmeta = 'wp_postmeta';

		$wpdb->shouldReceive( 'esc_like' )->andReturnUsing(
			static function ( string $text ): string {
				return $text;
			}
		);

		$wpdb->shouldReceive( 'prepare' )->once()->andReturn( 'prepared_query' );

		$wpdb->shouldReceive( 'get_var' )
			->once()
			->with( 'prepared_query' )
			->andReturn( $meta_value );

		WP_Mock::userFunction( 'maybe_unserialize' )
			->andReturnUsing(
				static function ( $data ) {
					// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize, WordPress.PHP.NoSilencedErrors.Discouraged
					$result = @unserialize( $data );

					return false !== $result ? $result : $data;
				}
			);
	}

	/**
	 * Test detect returns result when reCAPTCHA keys are configured.
	 *
	 * @return void
	 */
	public function test_detect_with_keys(): void {
		$meta_value = $this->build_meta_value( 'site_key_value', 'secret_key_value' );

		$this->setup_wpdb( $meta_value );

		$detector = new BeaverBuilderDetector();
		$results  = $detector->detect();

		self::assertCount( 1, $results );
		self::assertSame( 'recaptcha', $results[0]->get_provider() );
		self::assertSame( 'beaver_builder_contact', $results[0]->get_surface() );
		self::assertSame( DetectionResult::CONFIDENCE_HIGH, $results[0]->get_confidence() );
	}

	/**
	 * Test detect returns empty array when no postmeta row is found.
	 *
	 * @return void
	 */
	public function test_detect_no_rows(): void {
		$this->setup_wpdb( null );

		$detector = new BeaverBuilderDetector();
		$results  = $detector->detect();

		self::assertSame( [], $results );
	}

	/**
	 * Test detect returns empty array when serialized data is not an array.
	 *
	 * @return void
	 */
	public function test_detect_invalid_data(): void {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		$this->setup_wpdb( serialize( 'not_an_array' ) );

		$detector = new BeaverBuilderDetector();
		$results  = $detector->detect();

		self::assertSame( [], $results );
	}

	/**
	 * Test detect returns empty array when site key is empty.
	 *
	 * @return void
	 */
	public function test_detect_empty_site_key(): void {
		$this->setup_wpdb( $this->build_meta_value( '', 'secret_key_value' ) );

		$detector = new BeaverBuilderDetector();
		$results  = $detector->detect();

		self::assertSame( [], $results );
	}

	/**
	 * Test detect returns empty array when secret key is empty.
	 *
	 * @return void
	 */
	public function test_detect_empty_secret_key(): void {
		$this->setup_wpdb( $this->build_meta_value( 'site_key_value', '' ) );

		$detector = new BeaverBuilderDetector();
		$results  = $detector->detect();

		self::assertSame( [], $results );
	}

	/**
	 * Test detect returns empty array when keys contain only whitespace.
	 *
	 * @return void
	 */
	public function test_detect_whitespace_keys(): void {
		$this->setup_wpdb( $this->build_meta_value( '   ', '   ' ) );

		$detector = new BeaverBuilderDetector();
		$results  = $detector->detect();

		self::assertSame( [], $results );
	}

	/**
	 * Test detect finds keys among multiple nodes when first node lacks recaptcha settings.
	 *
	 * @return void
	 */
	public function test_detect_finds_keys_in_second_node(): void {
		$settings_no_captcha = new \stdClass();

		$node_row           = new \stdClass();
		$node_row->node     = 'row1';
		$node_row->type     = 'row';
		$node_row->settings = $settings_no_captcha;

		$settings_captcha                       = new \stdClass();
		$settings_captcha->recaptcha_site_key   = 'site_abc';
		$settings_captcha->recaptcha_secret_key = 'secret_abc';

		$node_form           = new \stdClass();
		$node_form->node     = 'mod1';
		$node_form->type     = 'module';
		$node_form->settings = $settings_captcha;

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		$meta_value = serialize(
			[
				'row1' => $node_row,
				'mod1' => $node_form,
			]
		);

		$this->setup_wpdb( $meta_value );

		$detector = new BeaverBuilderDetector();
		$results  = $detector->detect();

		self::assertCount( 1, $results );
		self::assertSame( 'beaver_builder_contact', $results[0]->get_surface() );
	}
}
