<?php
/**
 * CommandsTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpLanguageLevelInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Integration\CLI;

use HCaptcha\CLI\Commands;
use HCaptcha\Settings\PluginSettingsBase;
use HCaptcha\Settings\SettingsTransfer;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use Throwable;
use WP_CLI;

require_once __DIR__ . '/WPCLIExitException.php';
require_once __DIR__ . '/WPCLI.php';
require_once __DIR__ . '/functions.php';

/**
 * Test WP-CLI commands.
 *
 * @group cli
 */
class CommandsTest extends HCaptchaWPTestCase {
	/**
	 * Temporary files.
	 *
	 * @var string[]
	 */
	private array $files = [];

	/**
	 * Temporary directories.
	 *
	 * @var string[]
	 */
	private array $dirs = [];

	/**
	 * Setup test.
	 */
	public function setUp(): void {
		parent::setUp();

		WP_CLI::reset();
	}

	/**
	 * Tear down test.
	 */
	public function tearDown(): void {
		$this->cleanup_temp_paths();

		WP_CLI::reset();

		parent::tearDown();
	}

	/**
	 * Test export() prints compact JSON without keys.
	 *
	 * @return void
	 */
	public function test_export_prints_json_without_keys(): void {
		update_option(
			PluginSettingsBase::OPTION_NAME,
			[
				'site_key'   => 'site-key',
				'secret_key' => 'secret-key',
				'theme'      => 'dark',
				'size'       => 'compact',
			]
		);

		$output = $this->capture_output(
			static function (): void {
				( new Commands() )->export( [], [] );
			}
		);
		$data   = json_decode( $output, true );

		self::assertSame( JSON_ERROR_NONE, json_last_error() );
		self::assertArrayHasKey( 'meta', $data );
		self::assertArrayHasKey( 'settings', $data );
		self::assertArrayNotHasKey( 'keys', $data );
		self::assertSame( 'dark', $data['settings']['theme'] );
		self::assertSame( 'compact', $data['settings']['size'] );
		self::assertArrayNotHasKey( 'site_key', $data['settings'] );
		self::assertArrayNotHasKey( 'secret_key', $data['settings'] );
		self::assertSame( [], WP_CLI::$success_messages );
	}

	/**
	 * Test export() writes pretty JSON with keys.
	 *
	 * @return void
	 */
	public function test_export_writes_file_with_keys(): void {
		update_option(
			PluginSettingsBase::OPTION_NAME,
			[
				'site_key'   => 'file-site-key',
				'secret_key' => 'file-secret-key',
				'theme'      => 'light',
				'size'       => 'normal',
			]
		);

		$file          = $this->new_file_path_in_new_dir( 'settings.json' );
		$this->files[] = $file;

		( new Commands() )->export(
			[],
			[
				'include-keys' => true,
				'pretty'       => true,
				'file'         => $file,
			]
		);

		self::assertFileExists( $file );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$contents = file_get_contents( $file );
		$data     = json_decode( (string) $contents, true );

		self::assertStringContainsString( "\n    ", (string) $contents );
		self::assertSame( JSON_ERROR_NONE, json_last_error() );
		self::assertSame( 'file-site-key', $data['keys']['site_key'] );
		self::assertSame( 'file-secret-key', $data['keys']['secret_key'] );
		self::assertSame( [ sprintf( 'Exported settings to %s', $file ) ], WP_CLI::$success_messages );
	}

	/**
	 * Test export() reports a JSON encode error.
	 *
	 * @return void
	 */
	public function test_export_reports_json_encode_error(): void {
		$settings              = [];
		$settings['recursive'] = &$settings;

		update_option( PluginSettingsBase::OPTION_NAME, $settings );

		$this->expectException( WPCLIExitException::class );
		$this->expectExceptionMessage( 'Failed to encode JSON.' );

		( new Commands() )->export( [], [] );
	}

	/**
	 * Test export() reports directory creation failure.
	 *
	 * @return void
	 */
	public function test_export_reports_directory_creation_failure(): void {
		$directory = $this->create_temp_file( 'not a directory' );
		$file      = $directory . '/settings.json';

		$this->expectException( WPCLIExitException::class );
		$this->expectExceptionMessage( sprintf( 'Cannot create directory: %s', $directory ) );

		( new Commands() )->export( [], [ 'file' => $file ] );
	}

	/**
	 * Test export() reports file write failure.
	 *
	 * @return void
	 */
	public function test_export_reports_file_write_failure(): void {
		$directory = $this->create_temp_dir();

		$this->expectException( WPCLIExitException::class );
		$this->expectExceptionMessage( sprintf( 'Cannot write file: %s', $directory ) );

		( new Commands() )->export( [], [ 'file' => $directory ] );
	}

	/**
	 * Test import() reports missing file argument.
	 *
	 * @return void
	 */
	public function test_import_reports_missing_file_argument(): void {
		$this->expectException( WPCLIExitException::class );
		$this->expectExceptionMessage( 'Missing <file> argument.' );

		( new Commands() )->import( [], [] );
	}

	/**
	 * Test import() reports missing or unreadable file.
	 *
	 * @return void
	 */
	public function test_import_reports_missing_file(): void {
		$file = wp_normalize_path( sys_get_temp_dir() ) . '/' . uniqid( 'missing-hcap-cli-', true ) . '.json';

		$this->expectException( WPCLIExitException::class );
		$this->expectExceptionMessage( sprintf( 'File not found or unreadable: %s', $file ) );

		( new Commands() )->import( [ $file ], [] );
	}

	/**
	 * Test import() reports invalid JSON.
	 *
	 * @return void
	 */
	public function test_import_reports_invalid_json(): void {
		$file = $this->create_temp_file( '{invalid json' );

		json_decode( '{invalid json' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_decode_json_decode -- Set decoder error state for CLI validation.

		$this->expectException( WPCLIExitException::class );
		$this->expectExceptionMessage( 'Invalid JSON.' );

		( new Commands() )->import( [ $file ], [] );
	}

	/**
	 * Test import() validates a payload in dry-run mode.
	 *
	 * @return void
	 */
	public function test_import_dry_run_reports_payload_summary(): void {
		$payload = $this->build_export_payload(
			[
				'site_key'   => 'dry-site',
				'secret_key' => 'dry-secret',
				'theme'      => 'dark',
				'size'       => 'compact',
			],
			true
		);

		update_option(
			PluginSettingsBase::OPTION_NAME,
			[
				'site_key'   => 'old-site',
				'secret_key' => 'old-secret',
				'theme'      => 'light',
				'size'       => 'normal',
			]
		);

		$file = $this->create_json_file( $payload );

		( new Commands() )->import(
			[ $file ],
			[
				'dry-run'    => true,
				'allow-keys' => true,
			]
		);

		self::assertSame(
			[
				'Dry run completed. Detected 2 settings fields. Keys present: yes. Keys would be applied: yes.',
			],
			WP_CLI::$success_messages
		);

		$saved = get_option( PluginSettingsBase::OPTION_NAME, [] );

		self::assertSame( 'light', $saved['theme'] ?? '' );
		self::assertSame( 'normal', $saved['size'] ?? '' );
		self::assertSame( 'old-site', $saved['site_key'] ?? '' );
		self::assertSame( 'old-secret', $saved['secret_key'] ?? '' );
	}

	/**
	 * Test import() reports dry-run validation errors.
	 *
	 * @return void
	 */
	public function test_import_dry_run_reports_validation_error(): void {
		$file = $this->create_json_file( $this->invalid_payload() );

		$this->expectException( WPCLIExitException::class );
		$this->expectExceptionMessage( 'Unsupported settings format.' );

		( new Commands() )->import( [ $file ], [ 'dry-run' => true ] );
	}

	/**
	 * Test import() reports apply validation errors.
	 *
	 * @return void
	 */
	public function test_import_reports_apply_validation_error(): void {
		$file = $this->create_json_file( $this->invalid_payload() );

		$this->expectException( WPCLIExitException::class );
		$this->expectExceptionMessage( 'Unsupported settings format.' );

		( new Commands() )->import( [ $file ], [] );
	}

	/**
	 * Test import() applies settings and skips keys by default.
	 *
	 * @return void
	 */
	public function test_import_applies_settings_and_warns_when_keys_are_skipped(): void {
		$payload = $this->build_export_payload(
			[
				'site_key'   => 'skipped-site',
				'secret_key' => 'skipped-secret',
				'theme'      => 'dark',
				'size'       => 'compact',
			],
			true
		);

		update_option(
			PluginSettingsBase::OPTION_NAME,
			[
				'site_key'   => 'kept-site',
				'secret_key' => 'kept-secret',
				'theme'      => 'light',
				'size'       => 'normal',
			]
		);

		$file = $this->create_json_file( $payload );

		( new Commands() )->import( [ $file ], [] );

		$saved = get_option( PluginSettingsBase::OPTION_NAME, [] );

		self::assertSame( 'dark', $saved['theme'] ?? '' );
		self::assertSame( 'compact', $saved['size'] ?? '' );
		self::assertNotSame( 'skipped-site', $saved['site_key'] ?? '' );
		self::assertNotSame( 'skipped-secret', $saved['secret_key'] ?? '' );
		self::assertSame(
			[ 'Keys present in JSON were skipped. Use --allow-keys to import.' ],
			WP_CLI::$warning_messages
		);
		self::assertSame(
			[ 'hCaptcha settings were successfully imported.' ],
			WP_CLI::$success_messages
		);
	}

	/**
	 * Build an export payload from settings.
	 *
	 * @param array $settings     Settings.
	 * @param bool  $include_keys Whether to include keys.
	 *
	 * @return array
	 */
	private function build_export_payload( array $settings, bool $include_keys ): array {
		update_option( PluginSettingsBase::OPTION_NAME, $settings );

		return ( new SettingsTransfer() )->build_export_payload( $include_keys );
	}

	/**
	 * Get an invalid import payload.
	 *
	 * @return array
	 */
	private function invalid_payload(): array {
		return [
			'meta'     => [
				'plugin'         => 'Wrong Plugin',
				'schema_version' => '0.0',
			],
			'settings' => [],
		];
	}

	/**
	 * Create a temporary JSON file.
	 *
	 * @param array $payload Payload.
	 *
	 * @return string
	 */
	private function create_json_file( array $payload ): string {
		return $this->create_temp_file( (string) wp_json_encode( $payload ) );
	}

	/**
	 * Create a temporary file.
	 *
	 * @param string $contents File contents.
	 *
	 * @return string
	 */
	private function create_temp_file( string $contents ): string {
		$file = tempnam( sys_get_temp_dir(), 'hcap-cli-' );

		self::assertIsString( $file );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $file, $contents );

		$this->files[] = $file;

		return $file;
	}

	/**
	 * Create a file path in a missing temporary directory.
	 *
	 * @param string $filename File name.
	 *
	 * @return string
	 */
	private function new_file_path_in_new_dir( string $filename ): string {
		$directory    = wp_normalize_path( sys_get_temp_dir() ) . '/' . uniqid( 'hcap-cli-', true );
		$this->dirs[] = $directory;

		return $directory . '/' . $filename;
	}

	/**
	 * Create a temporary directory.
	 *
	 * @return string
	 */
	private function create_temp_dir(): string {
		$directory = wp_normalize_path( sys_get_temp_dir() ) . '/' . uniqid( 'hcap-cli-', true );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
		self::assertTrue( mkdir( $directory, 0777, true ) );

		$this->dirs[] = $directory;

		return $directory;
	}

	/**
	 * Capture echoed output.
	 *
	 * @param callable $callback Callback.
	 *
	 * @return string
	 * @throws Throwable Throwable.
	 */
	private function capture_output( callable $callback ): string {
		ob_start();

		try {
			$callback();

			return (string) ob_get_clean();
		} catch ( Throwable $throwable ) {
			ob_end_clean();

			throw $throwable;
		}
	}

	/**
	 * Remove temporary files and directories.
	 *
	 * @return void
	 */
	private function cleanup_temp_paths(): void {
		foreach ( $this->files as $file ) {
			if ( is_file( $file ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				unlink( $file );
			}
		}

		foreach ( array_reverse( $this->dirs ) as $directory ) {
			if ( is_dir( $directory ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
				rmdir( $directory );
			}
		}

		$this->files = [];
		$this->dirs  = [];
	}
}
