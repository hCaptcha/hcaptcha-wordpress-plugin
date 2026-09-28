<?php
/**
 * Scoper class file.
 *
 * @package hcaptcha-wp
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedNamespaceInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Scoper;

use Composer\EventDispatcher\Event as BaseEvent;
use Composer\Script\Event;
use Isolated\Symfony\Component\Finder\Finder;
use JsonException;
use RuntimeException;

/**
 * Class Scoper.
 */
class Scoper {

	/**
	 * Vendor dir.
	 */
	private const VENDOR = '/vendor';

	/**
	 * Vendor prefixed dir.
	 */
	private const VENDOR_PREFIXED = '/vendor_prefixed';

	/**
	 * Create the classmap directory before Composer scans it.
	 *
	 * @param Event $event Composer event.
	 *
	 * @return void
	 * @noinspection PhpUnused
	 */
	public static function pre_autoload_dump( Event $event ): void {
		self::ensure_vendor_prefixed_dir();
	}

	/**
	 * Post-install and post-update Composer command.
	 *
	 * @param Event $event Composer event.
	 *
	 * @return void
	 * @noinspection PhpUnused
	 * @noinspection PhpParamsInspection
	 */
	public static function post_cmd( Event $event ): void {
		$scope_packages = $event->getComposer()->getPackage()->getExtra()['scope-packages'] ?? [];

		$packages_to_scope = self::get_unscoped_packages( $scope_packages );

		if ( $packages_to_scope ) {
			self::prepare_scope( $event );
			self::scope( $packages_to_scope );
		}

		$lock_data       = $event->getComposer()->getLocker()->getLockData();
		$locked_packages = array_unique(
			array_map(
				static function ( $package ) {
					return $package['name'] ?? '';
				},
				array_merge( $lock_data['packages'], $lock_data['packages-dev'] )
			)
		);

		$removed_packages = array_diff( $scope_packages, $locked_packages );
		$vendor_prefixed  = self::get_vendor_prefixed_dir();

		foreach ( $removed_packages as $removed_package ) {
			self::delete_package( $vendor_prefixed, $removed_package );
		}

		// Always delete scoped packages from vendor.
		self::cleanup_scope( $event );

		// Always do dump.
		self::dump( $event );
	}

	/**
	 * Check whether Composer installed or updated a package to scope.
	 *
	 * Composer leaves changed package sources in the vendor until this script runs.
	 *
	 * @param array<string> $scope_packages Packages to scope.
	 *
	 * @return array<string>
	 */
	private static function get_unscoped_packages( array $scope_packages ): array {
		$packages_to_scope = [];

		foreach ( $scope_packages as $package ) {
			if ( self::is_not_empty_dir( self::get_vendor_dir( $package ) ) ) {
				$packages_to_scope[] = $package;
			}
		}

		return $packages_to_scope;
	}

	/**
	 * Ensure Composer's classmap directory exists.
	 *
	 * @return void
	 * @throws RuntimeException RuntimeException.
	 */
	private static function ensure_vendor_prefixed_dir(): void {
		$vendor_prefixed = self::get_vendor_prefixed_dir();

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
		if ( ! is_dir( $vendor_prefixed ) && ! mkdir( $vendor_prefixed ) && ! is_dir( $vendor_prefixed ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new RuntimeException( sprintf( 'Directory "%s" was not created', $vendor_prefixed ) );
		}
	}

	/**
	 * Prepare scoper to work.
	 * It checks the PHP version, creates the `vendor_prefixed` dir, and runs composer for scoper package.
	 *
	 * @param Event $event Composer event.
	 *
	 * @return void
	 * @throws RuntimeException RuntimeException.
	 */
	private static function prepare_scope( Event $event ): void {
		$scope_packages = $event->getComposer()->getPackage()->getExtra()['scope-packages'] ?? [];

		if ( ! $scope_packages ) {
			return;
		}

		self::ensure_vendor_prefixed_dir();

		// Bail if .php-scoper/vendor dir already exists and not empty.
		if ( self::is_not_empty_dir( self::get_scoper_dir( self::VENDOR ) ) ) {
			return;
		}

		$composer_cmd = 'composer --working-dir="' . self::get_scoper_dir() . '" --no-plugins --no-scripts --no-dev install';

		// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec, WordPress.Security.EscapeOutput.OutputNotEscaped
		echo shell_exec( $composer_cmd );
		// phpcs:enable WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec, WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Scope libraries.
	 *
	 * @param array<string> $packages Packages with source files in vendor.
	 *
	 * @return void
	 * @throws RuntimeException RuntimeException.
	 */
	private static function scope( array $packages ): void {
		$slug        = basename( getcwd() );
		$staging_dir = self::get_vendor_dir( '.hcaptcha-scoper-output-' . bin2hex( random_bytes( 8 ) ) );
		$output_dir  = $staging_dir;

		$vendors = array_unique(
			array_map(
				static function ( $package ) {
					return explode( '/', $package )[0];
				},
				$packages
			)
		);

		/**
		 * PHP-Scoper removes the common source path from output files.
		 * Restore that path in the staging directory before publishing packages.
		 */
		if ( 1 === count( $packages ) ) {
			$output_dir .= '/' . $packages[0];
		} elseif ( 1 === count( $vendors ) ) {
			$output_dir .= '/' . reset( $vendors );
		}

		$scoper_file = self::get_scoper_dir( self::VENDOR . '/humbug/php-scoper/bin/php-scoper' );
		$scoper_cmd  = 'php "' . $scoper_file . '" add-prefix' .
			' --config=.php-scoper/' . $slug . '-scoper.php' .
			' --output-dir="' . $output_dir . '" --force 2>&1';
		$exit_code   = 0;

		// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.system_calls_passthru, WordPress.Security.EscapeOutput.OutputNotEscaped
		passthru( $scoper_cmd, $exit_code );
		// phpcs:enable WordPress.PHP.DiscouragedPHPFunctions.system_calls_passthru, WordPress.Security.EscapeOutput.OutputNotEscaped

		if ( 0 !== $exit_code ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new RuntimeException( 'PHP-Scoper failed with exit code ' . $exit_code );
		}

		foreach ( $packages as $package ) {
			if ( ! self::is_not_empty_dir( $staging_dir . '/' . $package ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
				throw new RuntimeException( 'PHP-Scoper did not create ' . $package );
			}
		}

		foreach ( $packages as $package ) {
			self::publish_scoped_package( $staging_dir, $package );
		}

		self::delete_all( $staging_dir );
	}

	/**
	 * Cleanup scoped libraries.
	 *
	 * @param Event $event Composer event.
	 *
	 * @return void
	 */
	private static function cleanup_scope( Event $event ): void {
		$scope_packages = $event->getComposer()->getPackage()->getExtra()['scope-packages'] ?? [];

		if ( ! $scope_packages ) {
			return;
		}

		$vendor = self::get_vendor_dir();

		// Loop through the list of packages and delete relevant dirs in the vendor.
		foreach ( $scope_packages as $scope_package ) {
			self::delete_package( $vendor, $scope_package );
		}
	}

	/**
	 * Dump autoload.
	 *
	 * @param BaseEvent $event Composer event.
	 *
	 * @return void
	 */
	private static function dump( BaseEvent $event ): void {
		global $argv;

		/**
		 * Current event.
		 *
		 * @var Event $event
		 */
		$composer = $event->getComposer();

		$installation_manager = $composer->getInstallationManager();
		$local_repo           = $composer->getRepositoryManager()->getLocalRepository();
		$package              = $composer->getPackage();
		$config               = $composer->getConfig();

		$scope_packages = $package->getExtra()['scope-packages'] ?? [];
		$local_packages = $local_repo->getPackages();

		foreach ( $local_packages as $local_package ) {
			$package_name = $local_package->getName();

			if ( in_array( $package_name, $scope_packages, true ) ) {
				$local_repo->removePackage( $local_package );
			}
		}

		$optimize      = in_array( '--optimize-autoloader', $argv, true );
		$authoritative = in_array( '--classmap-authoritative', $argv, true );
		$apcu          = in_array( '--apcu-autoloader', $argv, true );

		if ( $authoritative ) {
			$event->getIO()->write( '<info>Generating optimized autoload files (authoritative)</info>' );
		} elseif ( $optimize ) {
			$event->getIO()->write( '<info>Generating optimized autoload files</info>' );
		} else {
			$event->getIO()->write( '<info>Generating autoload files</info>' );
		}

		$generator = $composer->getAutoloadGenerator();

		$generator->setClassMapAuthoritative( $authoritative );
		$generator->setRunScripts( false );
		$generator->setApcu( $apcu );

		$class_map = $generator->dump(
			$config,
			$local_repo,
			$package,
			$installation_manager,
			'composer',
			$optimize,
			null,
			$composer->getLocker()
		);

		$number_of_classes = $class_map->count();

		if ( $authoritative ) {
			$event->getIO()
				->write( '<info>Generated optimized autoload files (authoritative) containing ' . $number_of_classes . ' classes</info>' );
		} elseif ( $optimize ) {
			$event->getIO()
				->write( '<info>Generated optimized autoload files containing ' . $number_of_classes . ' classes</info>' );
		} else {
			$event->getIO()->write( '<info>Generated autoload files</info>' );
		}
	}

	/**
	 * Get finders for the scoper.
	 *
	 * @return array<string, Finder>
	 */
	public static function get_finders(): array {
		try {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$composer_json = json_decode( file_get_contents( getcwd() . '/composer.json' ), true, 512, JSON_THROW_ON_ERROR );
		} catch ( JsonException $e ) {
			$composer_json = [];
		}

		$packages   = $composer_json['extra']['scope-packages'] ?? [];
		$vendor_dir = self::get_vendor_dir();
		$filenames  = [ '*.php', 'LICENSE', 'CHANGELOG.md', 'README.md' ];
		$finders    = [];

		foreach ( $packages as $package ) {
			$package_dir = $vendor_dir . '/' . $package;

			if ( ! is_dir( $package_dir ) ) {
				continue;
			}

			$finders[ $package ] = Finder::create()
				->files()
				->in( $package_dir )
				->name( $filenames )
				->notName( '/.*\\.dist|Makefile|composer\\.json|composer\\.lock/' );
		}

		return $finders;
	}

	/**
	 * Show a message with color formatting.
	 *
	 * @param string $message Message.
	 *
	 * @return void
	 * @noinspection PhpUnusedPrivateMethodInspection
	 */
	private static function show_message( string $message ): void {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo "\033[31m$message\033[0m" . PHP_EOL;
	}

	/**
	 * Replace one scoped package after PHP-Scoper has completed successfully.
	 *
	 * @param string $staging_dir Staging directory.
	 * @param string $package     Package name.
	 *
	 * @return void
	 * @throws RuntimeException RuntimeException.
	 */
	private static function publish_scoped_package( string $staging_dir, string $package ): void {
		$source      = $staging_dir . '/' . $package;
		$destination = self::get_vendor_prefixed_dir( $package );
		$parent      = dirname( $destination );
		$backup      = $staging_dir . '/.backup-' . str_replace( '/', '-', $package );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
		if ( ! is_dir( $parent ) && ! mkdir( $parent, 0777, true ) && ! is_dir( $parent ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new RuntimeException( 'Could not create directory for ' . $package );
		}

		if ( file_exists( $destination ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
			if ( ! is_dir( $destination ) || ! rename( $destination, $backup ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
				throw new RuntimeException( 'Could not back up scoped package ' . $package );
			}
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
		if ( ! rename( $source, $destination ) ) {
			if ( is_dir( $backup ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
				rename( $backup, $destination );
			}

			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new RuntimeException( 'Could not publish scoped package ' . $package );
		}

		if ( is_dir( $backup ) ) {
			self::delete_all( $backup );
		}
	}

	/**
	 * Delete package.
	 *
	 * @param string $dir     Home package dir like /vendor.
	 * @param string $package Package name like vendor/package.
	 *
	 * @return void
	 */
	private static function delete_package( string $dir, string $package ): void {
		self::delete_all( $dir . '/' . $package );

		$vendor_name     = explode( '/', $package )[0];
		$vendor_name_dir = $dir . '/' . $vendor_name;

		if ( self::is_empty_dir( $vendor_name_dir ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
			rmdir( $vendor_name_dir );
		}
	}

	/**
	 * Delete all in the directory recursively.
	 *
	 * @param string $str Directory name.
	 *
	 * @return bool
	 * @noinspection PhpReturnValueOfMethodIsNeverUsedInspection
	 */
	private static function delete_all( string $str ): bool {
		if ( is_file( $str ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			return unlink( $str );
		}

		if ( is_dir( $str ) ) {
			// Loop through the list of files. Get all files/dirs, including hidden. Do not get `.` and `..` dirs.
			foreach ( glob( rtrim( $str, '/' ) . '/{,.}[!.,!..]*', GLOB_NOSORT | GLOB_BRACE ) as $path ) {
				self::delete_all( $path );
			}

			// Remove the directory itself.
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
			return rmdir( $str );
		}

		return false;
	}

	/**
	 * Detect if the filename is dir and is empty.
	 *
	 * @param string $filename Filename.
	 *
	 * @return bool
	 */
	private static function is_empty_dir( string $filename ): bool {
		return (
			is_dir( $filename ) &&
			empty( glob( rtrim( $filename, '/' ) . '/{,.}[!.,!..]*', GLOB_NOSORT | GLOB_BRACE ) )
		);
	}

	/**
	 * Detect if the filename is dir and is not empty.
	 *
	 * @param string $filename Filename.
	 *
	 * @return bool
	 */
	private static function is_not_empty_dir( string $filename ): bool {
		return (
			is_dir( $filename ) &&
			! empty( glob( rtrim( $filename, '/' ) . '/{,.}[!.,!..]*', GLOB_NOSORT | GLOB_BRACE ) )
		);
	}

	/**
	 * Get vendor dir.
	 *
	 * @param string $path Path relative to the vendor prefixed dir.
	 *
	 * @return string
	 */
	private static function get_vendor_dir( string $path = '' ): string {
		return self::add_path_to_dir( getcwd() . self::VENDOR, $path );
	}

	/**
	 * Get vendor prefixed dir.
	 *
	 * @param string $path Path relative to the vendor prefixed dir.
	 *
	 * @return string
	 * @noinspection PhpSameParameterValueInspection
	 */
	private static function get_vendor_prefixed_dir( string $path = '' ): string {
		return self::add_path_to_dir( getcwd() . self::VENDOR_PREFIXED, $path );
	}

	/**
	 * Get scoper dir.
	 *
	 * @param string $path Path relative to the scoper dir.
	 *
	 * @return string
	 */
	private static function get_scoper_dir( string $path = '' ): string {
		return self::add_path_to_dir( dirname( __DIR__ ), $path );
	}

	/**
	 * Add a path to dir.
	 *
	 * @param string $dir  Dir.
	 * @param string $path Path.
	 *
	 * @return string
	 */
	private static function add_path_to_dir( string $dir, string $path ): string {
		$dir  = rtrim( $dir, '/' );
		$path = ltrim( $path, '/' );

		return rtrim( $dir . '/' . $path, '/' );
	}
}
