<?php
/**
 * AbilitiesTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpLanguageLevelInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Integration\Abilities;

use HCaptcha\Abilities\Abilities;
use HCaptcha\Admin\Events\Events;
use HCaptcha\Settings\General;
use HCaptcha\Settings\PluginSettingsBase;
use HCaptcha\Settings\SettingsTransfer;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use Mockery;
use ReflectionException;
use tad\FunctionMocker\FunctionMocker;
use WP_Error;

/**
 * Test Abilities API integration.
 *
 * @group abilities
 */
class AbilitiesTest extends HCaptchaWPTestCase {

	/**
	 * Test init() and init_hooks().
	 *
	 * @param string $version WordPress version.
	 *
	 * @dataProvider dp_test_init_and_init_hooks
	 */
	public function test_init_and_init_hooks( string $version ): void {
		global $wp_version;

		// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited
		$saved_version = $wp_version;
		$wp_version    = $version;

		$subject = new Abilities();

		$wp_version = $saved_version;
		// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited

		if ( version_compare( $version, '6.9', '<' ) ) {
			self::assertFalse( has_action( 'wp_abilities_api_categories_init', [ $subject, 'register_categories' ] ) );
			self::assertFalse( has_action( 'wp_abilities_api_init', [ $subject, 'register_abilities' ] ) );
			self::assertFalse( has_filter( 'hcap_blacklist_ip', [ $subject, 'block_offender' ] ) );
		} else {
			self::assertSame(
				10,
				has_action(
					'wp_abilities_api_categories_init',
					[
						$subject,
						'register_categories',
					]
				)
			);
			self::assertSame( 10, has_action( 'wp_abilities_api_init', [ $subject, 'register_abilities' ] ) );
			self::assertSame( -PHP_INT_MAX + 1, has_filter( 'hcap_blacklist_ip', [ $subject, 'block_offender' ] ) );
		}
	}

	/**
	 * Data provider for test_init_and_init_hooks().
	 *
	 * @return array
	 */
	public function dp_test_init_and_init_hooks(): array {
		return [
			[ '6.8' ],
			[ '6.9' ],
		];
	}

	/**
	 * Test that the plugin registers its ability category and the first ability.
	 */
	public function test_registers_abilities_api_category_and_ability(): void {
		self::assertTrue( wp_has_ability_category( 'hcaptcha' ) );
		self::assertTrue( wp_has_ability( 'hcaptcha/get-threat-snapshot' ) );
		self::assertTrue( wp_has_ability( 'hcaptcha/block-offenders' ) );
		self::assertTrue( wp_has_ability( 'hcaptcha/export-settings' ) );
		self::assertTrue( wp_has_ability( 'hcaptcha/import-settings' ) );

		$ability = wp_get_ability( 'hcaptcha/get-threat-snapshot' );

		self::assertNotNull( $ability );
		self::assertSame( 'hcaptcha/get-threat-snapshot', $ability->get_name() );
	}

	/**
	 * Test export_settings() without keys.
	 */
	public function test_export_settings_without_keys(): void {
		$subject = new Abilities();

		update_option(
			PluginSettingsBase::OPTION_NAME,
			[
				'site_key'   => 'site-key',
				'secret_key' => 'secret-key',
				'theme'      => 'dark',
				'size'       => 'compact',
			]
		);

		$payload = $subject->export_settings( [ 'include_keys' => false ] );

		self::assertArrayHasKey( 'meta', $payload );
		self::assertArrayHasKey( 'settings', $payload );
		self::assertArrayNotHasKey( 'keys', $payload );
		self::assertSame( 'dark', $payload['settings']['theme'] );
		self::assertSame( 'compact', $payload['settings']['size'] );
		self::assertArrayNotHasKey( 'site_key', $payload['settings'] );
		self::assertArrayNotHasKey( 'secret_key', $payload['settings'] );
	}

	/**
	 * Test export_settings() with keys.
	 */
	public function test_export_settings_with_keys(): void {
		$subject = new Abilities();

		update_option(
			PluginSettingsBase::OPTION_NAME,
			[
				'site_key'   => 'site-key-2',
				'secret_key' => 'secret-key-2',
				'theme'      => 'light',
				'size'       => 'normal',
			]
		);

		$payload = $subject->export_settings( [ 'include_keys' => true ] );

		self::assertArrayHasKey( 'keys', $payload );
		self::assertSame( 'site-key-2', $payload['keys']['site_key'] );
		self::assertSame( 'secret-key-2', $payload['keys']['secret_key'] );
		self::assertArrayNotHasKey( 'site_key', $payload['settings'] );
		self::assertArrayNotHasKey( 'secret_key', $payload['settings'] );
	}

	/**
	 * Test import_settings() in dry-run mode.
	 */
	public function test_import_settings_dry_run(): void {
		$subject = new Abilities();

		$original_settings = [
			'site_key'   => 'old-site',
			'secret_key' => 'old-secret',
			'theme'      => 'light',
			'size'       => 'normal',
		];
		$payload_settings  = [
			'site_key'   => 'new-site',
			'secret_key' => 'new-secret',
			'theme'      => 'dark',
			'size'       => 'compact',
		];

		update_option( PluginSettingsBase::OPTION_NAME, $payload_settings );

		$transfer = new SettingsTransfer();
		$payload  = $transfer->build_export_payload( true );

		update_option( PluginSettingsBase::OPTION_NAME, $original_settings );

		$result = $subject->import_settings(
			array_merge(
				$payload,
				[
					'dry_run'    => true,
					'allow_keys' => false,
				]
			)
		);

		self::assertIsArray( $result );
		self::assertTrue( $result['success'] );
		self::assertTrue( $result['dry_run'] );
		self::assertFalse( $result['applied']['settings'] );
		self::assertFalse( $result['applied']['keys'] );
		self::assertContains( 'Keys were present but were not applied.', $result['messages']['warnings'] );

		$saved = get_option( PluginSettingsBase::OPTION_NAME, [] );
		self::assertSame( 'light', $saved['theme'] ?? '' );
		self::assertSame( 'normal', $saved['size'] ?? '' );
	}

	/**
	 * Test import_settings() applying settings and keys.
	 */
	public function test_import_settings_applies_with_keys(): void {
		$subject = new Abilities();

		$payload_settings  = [
			'site_key'   => 'apply-site',
			'secret_key' => 'apply-secret',
			'theme'      => 'dark',
			'size'       => 'compact',
		];
		$existing_settings = [
			'site_key'   => 'old-site-2',
			'secret_key' => 'old-secret-2',
			'theme'      => 'light',
			'size'       => 'normal',
		];

		update_option( PluginSettingsBase::OPTION_NAME, $payload_settings );

		$transfer = new SettingsTransfer();
		$payload  = $transfer->build_export_payload( true );

		update_option( PluginSettingsBase::OPTION_NAME, $existing_settings );

		$result = $subject->import_settings(
			array_merge(
				$payload,
				[ 'allow_keys' => true ]
			)
		);

		self::assertIsArray( $result );
		self::assertTrue( $result['success'] );
		self::assertFalse( $result['dry_run'] );
		self::assertTrue( $result['applied']['settings'] );
		self::assertTrue( $result['applied']['keys'] );

		$saved = get_option( PluginSettingsBase::OPTION_NAME, [] );
		self::assertSame( 'dark', $saved['theme'] ?? '' );
		self::assertSame( 'compact', $saved['size'] ?? '' );
		self::assertSame( 'apply-site', $saved['site_key'] ?? '' );
		self::assertSame( 'apply-secret', $saved['secret_key'] ?? '' );
	}

	/**
	 * Test import_settings() with keys present but not allowed.
	 */
	public function test_import_settings_skips_keys_when_not_allowed(): void {
		$subject = new Abilities();

		$payload_settings  = [
			'site_key'   => 'skip-site',
			'secret_key' => 'skip-secret',
			'theme'      => 'dark',
			'size'       => 'compact',
		];
		$existing_settings = [
			'site_key'   => 'keep-site',
			'secret_key' => 'keep-secret',
			'theme'      => 'light',
			'size'       => 'normal',
		];

		update_option( PluginSettingsBase::OPTION_NAME, $payload_settings );

		$transfer = new SettingsTransfer();
		$payload  = $transfer->build_export_payload( true );

		update_option( PluginSettingsBase::OPTION_NAME, $existing_settings );

		$result = $subject->import_settings(
			array_merge(
				$payload,
				[ 'allow_keys' => false ]
			)
		);

		self::assertIsArray( $result );
		self::assertTrue( $result['success'] );
		self::assertFalse( $result['dry_run'] );
		self::assertTrue( $result['applied']['settings'] );
		self::assertFalse( $result['applied']['keys'] );
		self::assertContains( 'Keys were present but were not applied.', $result['messages']['warnings'] );

		$saved = get_option( PluginSettingsBase::OPTION_NAME, [] );
		self::assertSame( 'dark', $saved['theme'] ?? '' );
		self::assertSame( 'compact', $saved['size'] ?? '' );
		self::assertNotSame( 'skip-site', $saved['site_key'] ?? '' );
		self::assertNotSame( 'skip-secret', $saved['secret_key'] ?? '' );
	}

	/**
	 * Test import_settings() reading payload from input_file.
	 */
	public function test_import_settings_from_input_file(): void {
		$subject = new Abilities();

		$payload_settings  = [
			'site_key'   => 'file-site',
			'secret_key' => 'file-secret',
			'theme'      => 'dark',
			'size'       => 'compact',
		];
		$existing_settings = [
			'site_key'   => 'old-file-site',
			'secret_key' => 'old-file-secret',
			'theme'      => 'light',
			'size'       => 'normal',
		];

		update_option( PluginSettingsBase::OPTION_NAME, $payload_settings );

		$transfer = new SettingsTransfer();
		$payload  = $transfer->build_export_payload( true );

		$file = tempnam( sys_get_temp_dir(), 'hcap' );
		self::assertNotFalse( $file );

		try {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $file, wp_json_encode( $payload ) );

			update_option( PluginSettingsBase::OPTION_NAME, $existing_settings );

			$result = $subject->import_settings(
				[
					'input_file' => $file,
					'allow_keys' => true,
				]
			);

			self::assertIsArray( $result );
			self::assertTrue( $result['success'] );
			self::assertFalse( $result['dry_run'] );
			self::assertTrue( $result['applied']['settings'] );
			self::assertTrue( $result['applied']['keys'] );

			$saved = get_option( PluginSettingsBase::OPTION_NAME, [] );
			self::assertSame( 'dark', $saved['theme'] ?? '' );
			self::assertSame( 'compact', $saved['size'] ?? '' );
			self::assertSame( 'file-site', $saved['site_key'] ?? '' );
			self::assertSame( 'file-secret', $saved['secret_key'] ?? '' );
		} finally {
			if ( is_string( $file ) && file_exists( $file ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				unlink( $file );
			}
		}
	}

	/**
	 * Test import_settings() returns an error for missing input_file.
	 */
	public function test_import_settings_missing_input_file(): void {
		$subject = new Abilities();

		$result = $subject->import_settings(
			[ 'input_file' => sys_get_temp_dir() . '/missing-hcap.json' ]
		);

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'hcaptcha_invalid_payload', $result->get_error_code() );
		self::assertSame( 'Input file not found or unreadable.', $result->get_error_message() );
	}

	/**
	 * Test import_settings() returns an error for invalid JSON in input_file.
	 */
	public function test_import_settings_invalid_json_in_input_file(): void {
		$subject = new Abilities();

		$file = tempnam( sys_get_temp_dir(), 'hcap' );
		self::assertNotFalse( $file );

		try {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $file, '{invalid json' );

			$result = $subject->import_settings(
				[ 'input_file' => $file ]
			);

			self::assertInstanceOf( WP_Error::class, $result );
			self::assertSame( 'hcaptcha_invalid_payload', $result->get_error_code() );
			self::assertSame( 'Invalid JSON in input file.', $result->get_error_message() );
		} finally {
			if ( is_string( $file ) && file_exists( $file ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				unlink( $file );
			}
		}
	}

	/**
	 * Test import_settings() returns error for empty payload.
	 */
	public function test_import_settings_invalid_payload(): void {
		$subject = new Abilities();

		$result = $subject->import_settings( [] );

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'hcaptcha_invalid_payload', $result->get_error_code() );
		self::assertSame( 'Invalid settings payload.', $result->get_error_message() );
	}

	/**
	 * Test import_settings() returns WP_Error from SettingsTransfer.
	 */
	public function test_import_settings_propagates_transfer_error(): void {
		$subject = new Abilities();

		$transfer = new SettingsTransfer();
		$payload  = $transfer->build_export_payload();

		$payload['meta']['plugin'] = 'invalid-plugin';

		$result = $subject->import_settings( $payload );

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'plugin_mismatch', $result->get_error_code() );
		self::assertSame( 'Unsupported settings format.', $result->get_error_message() );
	}

	/**
	 * Test import_settings() returns an info message when settings are unchanged.
	 */
	public function test_import_settings_returns_info_message(): void {
		$subject = new Abilities();

		$settings = [
			'site_key'   => 'same-site',
			'secret_key' => 'same-secret',
			'theme'      => 'light',
			'size'       => 'normal',
		];

		$general  = hcaptcha()->settings()->get_tab( General::class );
		$prepared = $general->pre_update_option_filter( $settings, [] );

		update_option( PluginSettingsBase::OPTION_NAME, $prepared );

		$transfer = new SettingsTransfer();
		$payload  = $transfer->build_export_payload( true );

		$result = $subject->import_settings(
			array_merge(
				$payload,
				[ 'allow_keys' => true ]
			)
		);

		self::assertIsArray( $result );
		self::assertTrue( $result['success'] );
		self::assertFalse( $result['dry_run'] );
		self::assertFalse( $result['applied']['settings'] );
		self::assertFalse( $result['applied']['keys'] );
		self::assertArrayHasKey( 'info', $result['messages'] );
		self::assertContains( 'Current and imported hCaptcha settings are the same.', $result['messages']['info'] );
	}

	/**
	 * Test block_offender().
	 */
	public function test_block_offender(): void {
		$subject = new Abilities();

		delete_option( 'hcaptcha_offender_blocks_v1' );

		$ip      = '7.7.7.7';
		$hash    = wp_hash( $ip );
		$now     = time();
		$expires = $now + 100;

		$expired_ip      = '6.6.6.6';
		$expired_hash    = wp_hash( $expired_ip );
		$expired_expires = $now - 1;

		FunctionMocker::replace( 'time', $now );

		update_option(
			'hcaptcha_offender_blocks_v1',
			[
				$hash         => [
					'expires_at' => $expires,
					'reason'     => 'test',
					'created_at' => $now,
				],
				// Expired block to ensure it gets pruned by prune_offender_blocks().
				$expired_hash => [
					'expires_at' => $expired_expires,
					'reason'     => 'expired',
					'created_at' => $now,
				],
			],
			false
		);

		// When the IP is blocked, the result must be true regardless of prior denylisted value.
		self::assertTrue( $subject->block_offender( false, $ip ) );
		self::assertTrue( $subject->block_offender( true, $ip ) );

		// When the IP is not blocked, it must preserve the prior denylisted value.
		self::assertFalse( $subject->block_offender( false, '8.8.8.8' ) );
		self::assertTrue( $subject->block_offender( true, '8.8.8.8' ) );

		// Ensure expired blocks are pruned and persisted back to the option.
		$blocks = get_option( 'hcaptcha_offender_blocks_v1', [] );
		self::assertIsArray( $blocks );
		self::assertArrayHasKey( $hash, $blocks );
		self::assertArrayNotHasKey( $expired_hash, $blocks );

		// Edge case: false client IP should not break and should preserve denylisted value.
		self::assertFalse( $subject->block_offender( false, false ) );
		self::assertTrue( $subject->block_offender( true, false ) );
	}

	/**
	 * Test can_get_threat_snapshot().
	 */
	public function test_can_get_threat_snapshot(): void {
		global $current_user;

		$subject = new Abilities();

		unset( $current_user );
		wp_set_current_user( 0 );
		self::assertFalse( $subject->can_get_threat_snapshot() );

		unset( $current_user );
		wp_set_current_user( 1 );
		self::assertTrue( $subject->can_get_threat_snapshot() );

		unset( $current_user );
		wp_set_current_user( 0 );
	}

	/**
	 * Test can_block_offenders().
	 */
	public function test_can_block_offenders(): void {
		global $current_user;

		$subject = new Abilities();

		unset( $current_user );
		wp_set_current_user( 0 );
		self::assertFalse( $subject->can_block_offenders() );

		unset( $current_user );
		wp_set_current_user( 1 );
		self::assertTrue( $subject->can_block_offenders() );

		unset( $current_user );
		wp_set_current_user( 0 );
	}

	/**
	 * Test can_export_settings().
	 */
	public function test_can_export_settings(): void {
		global $current_user;

		$subject = new Abilities();

		unset( $current_user );
		wp_set_current_user( 0 );
		self::assertFalse( $subject->can_export_settings() );

		unset( $current_user );
		wp_set_current_user( 1 );
		self::assertTrue( $subject->can_export_settings() );

		unset( $current_user );
		wp_set_current_user( 0 );
	}

	/**
	 * Test can_import_settings().
	 */
	public function test_can_import_settings(): void {
		global $current_user;

		$subject = new Abilities();

		unset( $current_user );
		wp_set_current_user( 0 );
		self::assertFalse( $subject->can_import_settings() );

		unset( $current_user );
		wp_set_current_user( 1 );
		self::assertTrue( $subject->can_import_settings() );

		unset( $current_user );
		wp_set_current_user( 0 );
	}

	/**
	 * Test get_threat_snapshot().
	 */
	public function test_get_threat_snapshot(): void {
		$subject = new Abilities();

		// Default window when no input is provided.
		$default = $subject->get_threat_snapshot( [] );
		self::assertIsArray( $default );
		self::assertSame( '1.0', $default['schema_version'] );
		self::assertSame( '5m', $default['window'] );
		self::assertSame( 300, $default['window_seconds'] );
		self::assertArrayHasKey( 'generated_at', $default );
		self::assertSame(
			[
				'total'     => 0,
				'failed'    => 0,
				'fail_rate' => '0.00',
			],
			$default['metrics']
		);
		self::assertSame( [], $default['breakdown']['offenders'] );

		$invalid = $subject->get_threat_snapshot( [ 'window' => 'invalid' ] );

		self::assertInstanceOf( WP_Error::class, $invalid );
		self::assertSame( 'hcaptcha_invalid_window', $invalid->get_error_code() );
		self::assertStringContainsString( 'Invalid window', $invalid->get_error_message() );

		$result = $subject->get_threat_snapshot(
			[
				'window' => '5m',
				'top_n'  => 10,
			]
		);

		self::assertIsArray( $result );
		self::assertSame( '1.0', $result['schema_version'] );
		self::assertSame( '5m', $result['window'] );
		self::assertSame( 300, $result['window_seconds'] );
		self::assertArrayHasKey( 'generated_at', $result );
		self::assertArrayHasKey( 'metrics', $result );
		self::assertArrayHasKey( 'signals', $result );
		self::assertArrayHasKey( 'breakdown', $result );
		self::assertArrayHasKey( 'errors', $result['breakdown'] );
		self::assertArrayHasKey( 'sources', $result['breakdown'] );
		self::assertArrayHasKey( 'offenders', $result['breakdown'] );
	}

	/**
	 * Test window_to_seconds().
	 *
	 * @return void
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_window_to_seconds(): void {
		$subject = new Abilities();

		$method = $this->set_method_accessibility( $subject, 'window_to_seconds' );

		self::assertSame( 30, $method->invoke( $subject, '30s' ) );
		self::assertSame( 5 * MINUTE_IN_SECONDS, $method->invoke( $subject, '5m' ) );
		self::assertSame( 3 * HOUR_IN_SECONDS, $method->invoke( $subject, '3h' ) );
		self::assertSame( DAY_IN_SECONDS, $method->invoke( $subject, '1d' ) );
		self::assertSame( 0, $method->invoke( $subject, '0s' ) );

		// Invalid window falls back to the default window duration.
		self::assertSame( 5 * MINUTE_IN_SECONDS, $method->invoke( $subject, 'invalid' ) );
		self::assertSame( 5 * MINUTE_IN_SECONDS, $method->invoke( $subject, '5x' ) );

		$this->set_method_accessibility( $subject, 'window_to_seconds', false );
	}

	/**
	 * Test calculate_attack_likelihood().
	 *
	 * @return void
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_calculate_attack_likelihood(): void {
		$subject = new Abilities();

		$method = $this->set_method_accessibility( $subject, 'calculate_attack_likelihood' );

		// When there is insufficient sample size, always return low.
		self::assertSame( 'low', $method->invoke( $subject, 0, 0, 0.0 ) );
		self::assertSame( 'low', $method->invoke( $subject, 19, 19, 1.0 ) );

		// High likelihood threshold (inclusive).
		self::assertSame( 'high', $method->invoke( $subject, 20, 10, 0.50 ) );

		// Medium likelihood threshold (inclusive).
		self::assertSame( 'medium', $method->invoke( $subject, 20, 5, 0.20 ) );
		self::assertSame( 'medium', $method->invoke( $subject, 20, 9, 0.60 ) );

		// Fallback to low if thresholds are not met.
		self::assertSame( 'low', $method->invoke( $subject, 20, 4, 0.90 ) );
		self::assertSame( 'low', $method->invoke( $subject, 20, 5, 0.19 ) );

		$this->set_method_accessibility( $subject, 'calculate_attack_likelihood', false );
	}

	/**
	 * Test calculate_confidence().
	 *
	 * @return void
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_calculate_confidence(): void {
		$subject = new Abilities();

		$method = $this->set_method_accessibility( $subject, 'calculate_confidence' );

		// High confidence threshold (inclusive).
		self::assertSame( 'high', $method->invoke( $subject, 1000 ) );
		self::assertSame( 'medium', $method->invoke( $subject, 999 ) );

		// Medium confidence threshold (inclusive).
		self::assertSame( 'medium', $method->invoke( $subject, 100 ) );
		self::assertSame( 'low', $method->invoke( $subject, 99 ) );

		// Low confidence fallback.
		self::assertSame( 'low', $method->invoke( $subject, 0 ) );

		$this->set_method_accessibility( $subject, 'calculate_confidence', false );
	}

	/**
	 * Test query_threats_snapshot() returns an empty snapshot when a count query fails.
	 *
	 * @return void
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_query_threats_snapshot_returns_empty_on_null_total(): void {
		global $wpdb;

		$subject = new Abilities();
		$saved   = $wpdb;

		$mock         = Mockery::mock( 'wpdb' );
		$mock->prefix = $saved->prefix ?? 'wp_';

		$mock->shouldReceive( 'prepare' )->andReturn( 'SELECT 1' );
		$mock->shouldReceive( 'get_var' )->andReturn( null );

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride
		$GLOBALS['wpdb'] = $mock;

		try {
			$method   = $this->set_method_accessibility( $subject, 'query_threats_snapshot' );
			$snapshot = $method->invoke( $subject, '2026-01-01 00:00:00', '2026-01-01 00:01:00', 10 );

			self::assertSame(
				[
					'metrics'   => [
						'total'     => 0,
						'failed'    => 0,
						'fail_rate' => 0.0,
					],
					'signals'   => [
						'attack_likelihood' => 'low',
						'confidence'        => 'low',
						'top_vectors'       => [],
					],
					'breakdown' => [
						'errors'    => [],
						'sources'   => [],
						'offenders' => [],
					],
				],
				$snapshot
			);
		} finally {
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride
			$GLOBALS['wpdb'] = $saved;
		}
	}

	/**
	 * Test query_threats_snapshot() with seeded DB rows.
	 *
	 * @return void
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_query_threats_snapshot(): void {
		global $wpdb;

		$subject = new Abilities();

		$table_name = $wpdb->prefix . Events::TABLE_NAME;
		$now        = time();
		$from_gmt   = gmdate( 'Y-m-d H:i:s', $now - 60 );
		$to_gmt     = gmdate( 'Y-m-d H:i:s', $now );

		$user_agent = 'PHPUnit';

		$source_b = wp_json_encode( [ 'b/b.php' ] );
		$source_a = wp_json_encode( [ 'a/a.php' ] );
		$source_c = wp_json_encode( [ 'c/c.php' ] );

		$form_b  = '1';
		$form_a2 = '2';
		$form_a1 = '1';
		$form_c  = '9';

		$ip_high = '9.9.9.9';

		// Ensure offender ordering checks are deterministic by computing hashes at runtime.
		$tie_ips    = [ '6.6.6.6', '7.7.7.7', '8.8.8.8' ];
		$hash_to_ip = [];
		$tie_hashes = [];

		foreach ( $tie_ips as $tie_ip ) {
			$hash                = wp_hash( $tie_ip );
			$hash_to_ip[ $hash ] = $tie_ip;
			$tie_hashes[]        = $hash;
		}

		$expected_tie_hashes = $tie_hashes;

		sort( $expected_tie_hashes, SORT_STRING );

		// Reverse the first-appearance order to force `usort()` to execute tie-break branches.
		$first_seen_hashes = array_reverse( $expected_tie_hashes );
		$ip_b              = $hash_to_ip[ $first_seen_hashes[0] ];
		$ip_a2             = $hash_to_ip[ $first_seen_hashes[1] ];
		$ip_a1             = $hash_to_ip[ $first_seen_hashes[2] ];

		$ids = [];

		// Seed enough rows so `sources` and `offenders` contain multiple elements.
		// This forces `usort()` to execute all comparator branches (count diff + tie-breaks).
		$rows = [
			// First appearances are intentionally ordered as `b:1, a:2, a:1, c:9`.
			[
				'source'      => $source_b,
				'form_id'     => $form_b,
				'ip'          => $ip_b,
				'error_codes' => wp_json_encode( [ 'e' ] ),
				'date_gmt'    => gmdate( 'Y-m-d H:i:s', $now - 59 ),
			],
			[
				'source'      => $source_b,
				'form_id'     => $form_b,
				'ip'          => $ip_b,
				'error_codes' => wp_json_encode( [ 'e' ] ),
				'date_gmt'    => gmdate( 'Y-m-d H:i:s', $now - 58 ),
			],
			[
				'source'      => $source_a,
				'form_id'     => $form_a2,
				'ip'          => $ip_a2,
				'error_codes' => wp_json_encode( [ 'e' ] ),
				'date_gmt'    => gmdate( 'Y-m-d H:i:s', $now - 57 ),
			],
			[
				'source'      => $source_a,
				'form_id'     => $form_a2,
				'ip'          => $ip_a2,
				'error_codes' => wp_json_encode( [ 'e' ] ),
				'date_gmt'    => gmdate( 'Y-m-d H:i:s', $now - 56 ),
			],
			[
				'source'      => $source_a,
				'form_id'     => $form_a1,
				'ip'          => $ip_a1,
				'error_codes' => wp_json_encode( [ 'e' ] ),
				'date_gmt'    => gmdate( 'Y-m-d H:i:s', $now - 55 ),
			],
			[
				'source'      => $source_a,
				'form_id'     => $form_a1,
				'ip'          => $ip_a1,
				'error_codes' => wp_json_encode( [ 'e' ] ),
				'date_gmt'    => gmdate( 'Y-m-d H:i:s', $now - 54 ),
			],
			[
				'source'      => $source_c,
				'form_id'     => $form_c,
				'ip'          => $ip_high,
				'error_codes' => wp_json_encode( [ 'a', 'b' ] ),
				'date_gmt'    => gmdate( 'Y-m-d H:i:s', $now - 53 ),
			],
			[
				'source'      => $source_c,
				'form_id'     => $form_c,
				'ip'          => $ip_high,
				'error_codes' => wp_json_encode( [ 'a', 'b' ] ),
				'date_gmt'    => gmdate( 'Y-m-d H:i:s', $now - 52 ),
			],
			[
				'source'      => $source_c,
				'form_id'     => $form_c,
				'ip'          => $ip_high,
				'error_codes' => wp_json_encode( [ 'a', 'b' ] ),
				'date_gmt'    => gmdate( 'Y-m-d H:i:s', $now - 51 ),
			],
			[
				'source'      => $source_c,
				'form_id'     => $form_c,
				'ip'          => '',
				'error_codes' => wp_json_encode( [ 'z' ] ),
				'date_gmt'    => gmdate( 'Y-m-d H:i:s', $now - 50 ),
			],
		];

		foreach ( $rows as $row ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$inserted = $wpdb->insert(
				$table_name,
				[
					'source'      => $row['source'],
					'form_id'     => $row['form_id'],
					'ip'          => $row['ip'],
					'user_agent'  => $user_agent,
					'uuid'        => wp_generate_uuid4(),
					'error_codes' => $row['error_codes'],
					'date_gmt'    => $row['date_gmt'],
				],
				[ '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
			);

			self::assertNotFalse( $inserted );

			$ids[] = $wpdb->insert_id;
		}

		try {
			$method   = $this->set_method_accessibility( $subject, 'query_threats_snapshot' );
			$snapshot = $method->invoke( $subject, $from_gmt, $to_gmt, 10 );

			self::assertIsArray( $snapshot );
			self::assertSame( 10, $snapshot['metrics']['total'] );
			self::assertSame( 10, $snapshot['metrics']['failed'] );
			self::assertSame( '1.00', $snapshot['metrics']['fail_rate'] );
			self::assertSame(
				[
					[
						'source'  => 'c/c.php',
						'form_id' => $form_c,
						'count'   => 4,
					],
					[
						'source'  => 'a/a.php',
						'form_id' => $form_a1,
						'count'   => 2,
					],
					[
						'source'  => 'a/a.php',
						'form_id' => $form_a2,
						'count'   => 2,
					],
					[
						'source'  => 'b/b.php',
						'form_id' => $form_b,
						'count'   => 2,
					],
				],
				$snapshot['breakdown']['sources']
			);

			self::assertCount( 4, $snapshot['breakdown']['offenders'] );

			$expected_offender_ids = array_merge( [ wp_hash( $ip_high ) ], $expected_tie_hashes );
			$actual_offender_ids   = array_map(
				static function ( array $offender ): string {
					return (string) ( $offender['offender_id'] ?? '' );
				},
				$snapshot['breakdown']['offenders']
			);

			self::assertSame( $expected_offender_ids, $actual_offender_ids );

			$actual_offender_counts = array_map(
				static function ( array $offender ): int {
					return (int) ( $offender['count'] ?? 0 );
				},
				$snapshot['breakdown']['offenders']
			);

			self::assertSame( [ 3, 2, 2, 2 ], $actual_offender_counts );

			// Cover `sort_map_by_count_desc_then_key()` tie-branch: equal counts must fall back to `strcmp()`.
			$top_offender = $snapshot['breakdown']['offenders'][0];
			self::assertSame( wp_hash( $ip_high ), $top_offender['offender_id'] );
			self::assertSame( [ 'a', 'b' ], $top_offender['top_errors'] );
		} finally {
			foreach ( $ids as $id ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->delete( $table_name, [ 'id' => $id ], [ '%d' ] );
			}
		}
	}

	/**
	 * Test block_offenders().
	 *
	 * @return void
	 */
	public function test_block_offenders(): void {
		$subject = new Abilities();

		delete_option( 'hcaptcha_offender_blocks_v1' );

		$valid       = wp_hash( '7.7.7.7' );
		$default_ttl = 3600;
		$ttl         = 100;
		$now         = time();

		FunctionMocker::replace( 'time', $now );

		$result = $subject->block_offenders(
			[
				'offender_ids' => [ $valid ],
				'reason'       => 'test',
			]
		);

		self::assertSame( [ $valid ], $result['blocked'] );
		self::assertSame( [], $result['already_blocked'] );
		self::assertSame( gmdate( 'c', $now + $default_ttl ), $result['effective_until'] );

		$result2 = $subject->block_offenders(
			[
				'offender_ids' => [ $valid ],
				'ttl_seconds'  => $ttl,
			]
		);

		$max_ttl = max( $default_ttl, $ttl );

		self::assertSame( [], $result2['blocked'] );
		self::assertSame( [ $valid ], $result2['already_blocked'] );
		self::assertSame( gmdate( 'c', $now + $max_ttl ), $result['effective_until'] );
	}
}
