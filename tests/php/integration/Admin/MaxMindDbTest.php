<?php
/**
 * MaxMindDbTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\Admin;

use FilesystemIterator;
use HCaptcha\Admin\MaxMindDb;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use Mockery;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionException;
use RuntimeException;
use tad\FunctionMocker\FunctionMocker;
use WP_Error;
use function is_file;

/**
 * Test MaxMindDb class.
 *
 * @group admin
 * @group maxmind-db
 */
class MaxMindDbTest extends HCaptchaWPTestCase {

	/**
	 * Set up test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		if ( ! defined( 'HCaptcha\\Admin\\FS_CHMOD_DIR' ) ) {
			define( 'HCaptcha\\Admin\\FS_CHMOD_DIR', defined( 'FS_CHMOD_DIR' ) ? FS_CHMOD_DIR : 0755 );
		}
	}

	/**
	 * Temporary directories.
	 *
	 * @var string[]
	 */
	private array $temp_dirs = [];

	/**
	 * Temporary files.
	 *
	 * @var string[]
	 */
	private array $temp_files = [];

	/**
	 * Tear down test.
	 *
	 * @return void
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function tearDown(): void {
		global $wp_filesystem;

		remove_all_filters( 'hcap_maxmind_db_path' );
		remove_all_filters( 'pre_http_request' );

		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( MaxMindDb::UPDATE_ACTION, [], 'hcaptcha' );
		}

		foreach ( $this->temp_files as $file ) {
			if ( is_file( $file ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				unlink( $file );
			}
		}

		foreach ( array_reverse( $this->temp_dirs ) as $dir ) {
			$this->remove_dir( $dir );
		}

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$wp_filesystem = null;

		parent::tearDown();
	}

	/**
	 * Test activate() and deactivate() without Action Scheduler.
	 *
	 * @return void
	 */
	public function test_activate_and_deactivate_without_action_scheduler(): void {
		FunctionMocker::replace( 'function_exists', false );

		$subject = new MaxMindDb();

		$subject->activate( '' );
		$subject->deactivate();

		self::assertTrue( true );
	}

	/**
	 * Test schedule_update() and unschedule_update() with Action Scheduler available.
	 *
	 * @return void
	 * @throws ReflectionException Reflection exception.
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function test_schedule_and_unschedule_with_action_scheduler(): void {
		require_once HCAPTCHA_PATH . '/vendor/woocommerce/action-scheduler/action-scheduler.php';

		if (
			! function_exists( 'as_schedule_recurring_action' ) ||
			! function_exists( 'as_unschedule_all_actions' ) ||
			! function_exists( 'as_next_scheduled_action' )
		) {
			self::markTestSkipped( 'Action Scheduler is not available.' );
		}

		as_unschedule_all_actions( MaxMindDb::UPDATE_ACTION, [], 'hcaptcha' );

		$subject           = new MaxMindDb();
		$schedule_method   = $this->set_method_accessibility( $subject, 'schedule_update' );
		$unschedule_method = $this->set_method_accessibility( $subject, 'unschedule_update' );

		self::assertFalse( as_next_scheduled_action( MaxMindDb::UPDATE_ACTION, [], 'hcaptcha' ) );

		$schedule_method->invoke( $subject );

		$scheduled = as_next_scheduled_action( MaxMindDb::UPDATE_ACTION, [], 'hcaptcha' );

		self::assertIsInt( $scheduled );
		self::assertGreaterThan( time(), $scheduled );

		$unschedule_method->invoke( $subject );

		self::assertFalse( as_next_scheduled_action( MaxMindDb::UPDATE_ACTION, [], 'hcaptcha' ) );
	}

	/**
	 * Test load_db() when a filtered DB path already exists.
	 *
	 * @return void
	 */
	public function test_load_db_uses_existing_filtered_path(): void {
		$called = false;
		$db     = $this->create_temp_file( 'GeoLite2-Country.mmdb', 'db' );

		add_filter(
			'hcap_maxmind_db_path',
			static function () use ( $db ) {
				return $db;
			}
		);
		add_filter(
			'pre_http_request',
			static function () use ( &$called ) {
				$called = true;

				return new WP_Error( 'unexpected_download' );
			}
		);

		( new MaxMindDb() )->load_db( 'license-key' );

		self::assertFalse( $called );
	}

	/**
	 * Test load_db() when download_url() returns WP_Error.
	 *
	 * @return void
	 */
	public function test_load_db_returns_on_download_error(): void {
		$restore_default_db = $this->move_default_db_aside();

		add_filter(
			'pre_http_request',
			static function () {
				return new WP_Error( 'download_error' );
			}
		);

		try {
			( new MaxMindDb() )->load_db( 'license key' );
		} finally {
			$restore_default_db();
		}

		self::assertTrue( true );
	}

	/**
	 * Test load_db() downloads an archive when no DB exists.
	 *
	 * @return void
	 */
	public function test_load_db_downloads_archive_when_default_db_is_absent(): void {
		$archive_path       = $this->create_maxmind_archive();
		$target_path        = $this->create_temp_dir( 'download-target' ) . '/GeoLite2-Country.mmdb';
		$restore_default_db = $this->move_default_db_aside();
		$downloaded         = false;

		$this->mock_successful_wp_filesystem();

		add_filter(
			'hcap_maxmind_db_path',
			static function () use ( $target_path ): string {
				return $target_path;
			}
		);
		add_filter(
			'pre_http_request',
			static function ( $preempt, array $args ) use ( $archive_path, &$downloaded ) {
				$downloaded = true;

				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy
				copy( $archive_path, $args['filename'] );

				return [
					'headers'  => [
						'content-disposition' => 'attachment; filename=country.tar',
					],
					'body'     => '',
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
					'cookies'  => [],
				];
			},
			10,
			2
		);

		try {
			( new MaxMindDb() )->load_db( 'license key' );
		} finally {
			$restore_default_db();
		}

		self::assertTrue( $downloaded );
	}

	/**
	 * Test update_db() without a stored key.
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_update_db_returns_without_key(): void {
		$main     = hcaptcha();
		$settings = $this->get_protected_property( $main, 'settings' );

		$this->set_protected_property( $main, 'settings', null );

		try {
			( new MaxMindDb() )->update_db();
		} finally {
			$this->set_protected_property( $main, 'settings', $settings );
		}

		self::assertTrue( true );
	}

	/**
	 * Test update_db() with a stored key.
	 *
	 * @return void
	 */
	public function test_update_db_downloads_with_key(): void {
		update_option( 'hcaptcha_settings', [ 'maxmind_key' => 'license-key' ] );
		hcaptcha()->init_hooks();

		add_filter(
			'pre_http_request',
			static function () {
				return new WP_Error( 'download_error' );
			}
		);

		( new MaxMindDb() )->update_db();

		self::assertTrue( true );
	}

	/**
	 * Test default and filtered DB paths.
	 *
	 * @return void
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_default_filtered_existing_and_target_paths(): void {
		$subject              = new MaxMindDb();
		$get_default_paths    = $this->set_method_accessibility( $subject, 'get_default_db_paths' );
		$get_existing_db_path = $this->set_method_accessibility( $subject, 'get_existing_db_path' );
		$get_target_db_path   = $this->set_method_accessibility( $subject, 'get_target_db_path' );
		$default_path         = WP_CONTENT_DIR . '/uploads/hcaptcha/GeoLite2-Country.mmdb';
		$filtered_path        = $this->create_temp_file( 'filtered.mmdb', 'filtered' );

		self::assertSame( [ $default_path ], $get_default_paths->invoke( $subject ) );
		self::assertSame( $default_path, $get_target_db_path->invoke( $subject ) );

		add_filter(
			'hcap_maxmind_db_path',
			static function () use ( $filtered_path ) {
				return $filtered_path;
			}
		);

		self::assertSame( $filtered_path, $get_existing_db_path->invoke( $subject ) );
		self::assertSame( $filtered_path, $get_target_db_path->invoke( $subject ) );

		remove_all_filters( 'hcap_maxmind_db_path' );
		remove_all_filters( 'pre_http_request' );

		if ( ! is_file( $default_path ) ) {
			wp_mkdir_p( dirname( $default_path ) );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $default_path, 'default' );
			$this->temp_files[] = $default_path;
		}

		self::assertSame( $default_path, $get_existing_db_path->invoke( $subject ) );
	}

	/**
	 * Test get_existing_db_path() when no path is readable.
	 *
	 * @return void
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_get_existing_db_path_returns_empty_without_readable_paths(): void {
		$subject              = new MaxMindDb();
		$get_existing_db_path = $this->set_method_accessibility( $subject, 'get_existing_db_path' );
		$restore_default_db   = $this->move_default_db_aside();

		try {
			self::assertSame( '', $get_existing_db_path->invoke( $subject ) );
		} finally {
			$restore_default_db();
		}
	}

	/**
	 * Test extract_db_from_archive().
	 *
	 * @return void
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_extract_db_from_archive_moves_database(): void {
		$subject      = new MaxMindDb();
		$method       = $this->set_method_accessibility( $subject, 'extract_db_from_archive' );
		$archive_path = $this->create_maxmind_archive();
		$target_dir   = $this->create_temp_dir( 'target' );
		$target_path  = $target_dir . '/nested/GeoLite2-Country.mmdb';

		$this->mock_successful_wp_filesystem();

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Suppress PharData warnings in tests.
		set_error_handler(
			static function (): bool {
				return true;
			}
		);

		try {
			$method->invoke( $subject, $archive_path, $target_path );
		} finally {
			restore_error_handler();
		}

		self::assertFileDoesNotExist( $archive_path );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local test fixture.
		self::assertSame( 'country-db', file_get_contents( $target_path ) );
	}

	/**
	 * Test extract_db_from_archive() when the filesystem cannot initialize.
	 *
	 * @return void
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_extract_db_from_archive_returns_when_filesystem_is_unavailable(): void {
		$subject      = new MaxMindDb();
		$method       = $this->set_method_accessibility( $subject, 'extract_db_from_archive' );
		$archive_path = $this->create_temp_file( 'archive.tar', 'not-used' );

		FunctionMocker::replace( 'HCaptcha\Admin\WP_Filesystem', false );

		$method->invoke( $subject, $archive_path, $this->create_temp_dir( 'target' ) . '/GeoLite2-Country.mmdb' );

		self::assertFileExists( $archive_path );
	}

	/**
	 * Test extract_db_from_archive() handles invalid archives.
	 *
	 * @return void
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_extract_db_from_archive_returns_on_invalid_archive(): void {
		$subject      = new MaxMindDb();
		$method       = $this->set_method_accessibility( $subject, 'extract_db_from_archive' );
		$archive_path = $this->create_temp_file( 'invalid.tar', 'invalid' );

		$this->mock_successful_wp_filesystem();

		$method->invoke( $subject, $archive_path, $this->create_temp_dir( 'target' ) . '/GeoLite2-Country.mmdb' );

		self::assertFileDoesNotExist( $archive_path );
	}

	/**
	 * Test extract_db_from_archive() returns when the extracted DB is missing.
	 *
	 * @return void
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_extract_db_from_archive_returns_when_database_file_is_missing(): void {
		$subject      = new MaxMindDb();
		$method       = $this->set_method_accessibility( $subject, 'extract_db_from_archive' );
		$archive_path = $this->create_maxmind_archive( false );
		$target_path  = $this->create_temp_dir( 'target' ) . '/GeoLite2-Country.mmdb';

		$this->mock_successful_wp_filesystem();

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Suppress PharData warnings in tests.
		set_error_handler(
			static function (): bool {
				return true;
			}
		);

		try {
			$method->invoke( $subject, $archive_path, $target_path );
		} finally {
			restore_error_handler();
		}

		self::assertFileDoesNotExist( $archive_path );
		self::assertFileDoesNotExist( $target_path );
	}

	/**
	 * Test extract_db_from_archive() returns when the target directory cannot be created.
	 *
	 * @return void
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_extract_db_from_archive_returns_when_target_directory_cannot_be_created(): void {
		$subject      = new MaxMindDb();
		$method       = $this->set_method_accessibility( $subject, 'extract_db_from_archive' );
		$archive_path = $this->create_maxmind_archive();
		$target_path  = $this->create_temp_dir( 'target' ) . '/missing/GeoLite2-Country.mmdb';

		$mkdir_called = false;

		$this->mock_wp_filesystem_with_failed_mkdir( $mkdir_called );

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Suppress PharData warnings in tests.
		set_error_handler(
			static function (): bool {
				return true;
			}
		);

		try {
			$method->invoke( $subject, $archive_path, $target_path );
		} finally {
			restore_error_handler();
		}

		self::assertTrue( $mkdir_called );

		self::assertFileDoesNotExist( $archive_path );
		self::assertFileDoesNotExist( $target_path );
	}

	/**
	 * Test mkdir_p() variants.
	 *
	 * @return void
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_mkdir_p_variants(): void {
		global $wp_filesystem;

		$subject = new MaxMindDb();
		$method  = $this->set_method_accessibility( $subject, 'mkdir_p' );

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$wp_filesystem = Mockery::mock();
		$wp_filesystem->shouldReceive( 'is_dir' )->andReturn( true );

		self::assertTrue( $method->invoke( $subject, '' ) );
		self::assertTrue( $method->invoke( $subject, 'C:/already-created' ) );

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$wp_filesystem = Mockery::mock();
		$wp_filesystem->shouldReceive( 'is_dir' )->andReturn( false );
		$wp_filesystem->shouldReceive( 'mkdir' )->andReturnUsing(
			static function () use ( &$mkdir_called ): bool {
				$mkdir_called = true;

				return false;
			}
		);

		self::assertFalse( $method->invoke( $subject, '/cannot/create' ) );

		$created = [];

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$wp_filesystem = Mockery::mock();
		$wp_filesystem->shouldReceive( 'is_dir' )->andReturnUsing(
			static function ( string $path ) use ( &$created ): bool {
				return isset( $created[ $path ] );
			}
		);
		$wp_filesystem->shouldReceive( 'mkdir' )->andReturnUsing(
			static function ( string $path ) use ( &$created ): bool {
				$created[ $path ] = true;

				return true;
			}
		);

		self::assertTrue( $method->invoke( $subject, '/root//child' ) );
	}

	/**
	 * Test mkdir_p() skips empty and existing segments.
	 *
	 * @return void
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_mkdir_p_skips_empty_and_existing_segments(): void {
		global $wp_filesystem;

		$subject = new MaxMindDb();
		$method  = $this->set_method_accessibility( $subject, 'mkdir_p' );

		FunctionMocker::replace(
			'HCaptcha\Admin\wp_normalize_path',
			static function ( string $path ): string {
				return $path;
			}
		);

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$wp_filesystem = Mockery::mock();
		$wp_filesystem->shouldReceive( 'is_dir' )->andReturnUsing(
			static function ( string $path ): bool {
				return '/root' === $path;
			}
		);
		$wp_filesystem->shouldReceive( 'mkdir' )->andReturn( true );

		self::assertTrue( $method->invoke( $subject, '/root//child' ) );
	}

	/**
	 * Create a temporary directory.
	 *
	 * @param string $suffix Directory suffix.
	 *
	 * @return string
	 */
	private function create_temp_dir( string $suffix ): string {
		$dir = trailingslashit( wp_normalize_path( sys_get_temp_dir() ) ) . uniqid( 'hcaptcha-maxmind-' . $suffix . '-', true );

		if ( ! is_dir( $dir ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
			mkdir( $dir, 0777, true );
		}

		$this->temp_dirs[] = $dir;

		return $dir;
	}

	/**
	 * Create a temporary file.
	 *
	 * @param string $filename File name.
	 * @param string $contents File contents.
	 *
	 * @return string
	 */
	private function create_temp_file( string $filename, string $contents ): string {
		$dir  = $this->create_temp_dir( 'file' );
		$file = $dir . '/' . $filename;

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $file, $contents );
		$this->temp_files[] = $file;

		return $file;
	}

	/**
	 * Create a MaxMind-like tar archive.
	 *
	 * @param bool $include_db Whether the archive should include the database file.
	 *
	 * @return string
	 * @throws RuntimeException Cannot create archive.
	 * @noinspection PhpSameParameterValueInspection
	 */
	private function create_maxmind_archive( bool $include_db = true ): string {
		$root         = $this->create_temp_dir( 'archive-root' );
		$db_dir       = $root . '/GeoLite2-Country_20260101';
		$archive_path = $this->create_temp_dir( 'archive' ) . '/country.tar';

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
		mkdir( $db_dir, 0777, true );

		if ( $include_db ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $db_dir . '/GeoLite2-Country.mmdb', 'country-db' );
		}

		$tar = $this->get_tar_command();

		if ( '' !== $tar ) {
			$command = $tar . ' -cf ' . escapeshellarg( $archive_path ) .
				' -C ' . escapeshellarg( $root ) . ' GeoLite2-Country_20260101';
			$output  = [];
			$code    = 0;

			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec
			exec( $command, $output, $code );

			if ( 0 !== $code ) {
				throw new RuntimeException( 'Cannot create MaxMind tar fixture.' );
			}
		} else {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Suppress PharData warnings in tests.
			set_error_handler(
				static function (): bool {
					return true;
				}
			);

			try {
				$archive = new \PharData( $archive_path );
				$archive->buildFromDirectory( $root );
				unset( $archive );
			} finally {
				restore_error_handler();
			}
		}

		$this->temp_files[] = $archive_path;

		return $archive_path;
	}

	/**
	 * Get the tar command for creating MaxMind archive fixtures.
	 *
	 * @return string
	 */
	private function get_tar_command(): string {
		if ( 'Windows' === PHP_OS_FAMILY ) {
			return is_file( 'C:/Windows/System32/tar.exe' ) ? '"C:/Windows/System32/tar.exe"' : '';
		}

		$output = [];
		$code   = 0;

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec
		exec( 'command -v tar', $output, $code );

		return 0 === $code && ! empty( $output[0] ) ? escapeshellarg( $output[0] ) : '';
	}

	/**
	 * Move the default DB aside for tests that must exercise a missing DB.
	 *
	 * @return callable
	 */
	private function move_default_db_aside(): callable {
		$default_path = WP_CONTENT_DIR . '/uploads/hcaptcha/GeoLite2-Country.mmdb';

		if ( ! is_file( $default_path ) ) {
			return static function (): void {};
		}

		$backup_path = $default_path . '.hcaptcha-test-' . uniqid( '', true );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
		rename( $default_path, $backup_path );

		return static function () use ( $default_path, $backup_path ): void {
			if ( is_file( $backup_path ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
				rename( $backup_path, $default_path );
			}
		};
	}

	/**
	 * Mock successful WP_Filesystem initialization and operations.
	 *
	 * @return void
	 * @noinspection PhpVariableIsUsedOnlyInClosureInspection
	 */
	private function mock_successful_wp_filesystem(): void {
		global $wp_filesystem;

		$test = $this;

		FunctionMocker::replace( 'HCaptcha\Admin\WP_Filesystem', true );

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$wp_filesystem = Mockery::mock();
		$wp_filesystem->shouldReceive( 'is_dir' )->andReturnUsing(
			static function ( string $path ): bool {
				return is_dir( $path );
			}
		);
		$wp_filesystem->shouldReceive( 'mkdir' )->andReturnUsing(
			static function ( string $path ): bool {
				return is_dir( $path ) || mkdir( $path, 0777, true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
			}
		);
		$wp_filesystem->shouldReceive( 'move' )->andReturnUsing(
			static function ( string $from, string $to ): bool {
				if ( ! is_dir( dirname( $to ) ) ) {
					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
					mkdir( dirname( $to ), 0777, true );
				}

				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local test fixture.
				$contents = is_file( $from ) ? file_get_contents( $from ) : 'country-db';

				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				file_put_contents( $to, $contents );

				if ( is_file( $from ) ) {
					// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
					unlink( $from );
				}

				return true;
			}
		);
		$wp_filesystem->shouldReceive( 'rmdir' )->andReturnUsing(
			static function ( string $dir ) use ( $test ): bool {
				$test->remove_dir( $dir );

				return true;
			}
		);
	}

	/**
	 * Mock WP_Filesystem initialization with mkdir() failure.
	 *
	 * @param bool $mkdir_called Whether mkdir() was called.
	 *
	 * @return void
	 */
	private function mock_wp_filesystem_with_failed_mkdir( bool &$mkdir_called ): void {
		global $wp_filesystem;

		FunctionMocker::replace( 'HCaptcha\Admin\WP_Filesystem', true );

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$wp_filesystem = Mockery::mock();
		$wp_filesystem->shouldReceive( 'is_dir' )->andReturn( false );
		$wp_filesystem->shouldReceive( 'mkdir' )->andReturnUsing(
			static function () use ( &$mkdir_called ): bool {
				$mkdir_called = true;

				return false;
			}
		);
		$wp_filesystem->shouldReceive( 'move' )->never();
		$wp_filesystem->shouldReceive( 'rmdir' )->never();
	}

	/**
	 * Remove a directory recursively.
	 *
	 * @param string $dir Directory path.
	 *
	 * @return void
	 */
	private function remove_dir( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $iterator as $item ) {
			if ( $item->isDir() ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
				rmdir( $item->getPathname() );
			} else {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				unlink( $item->getPathname() );
			}
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
		rmdir( $dir );
	}
}
