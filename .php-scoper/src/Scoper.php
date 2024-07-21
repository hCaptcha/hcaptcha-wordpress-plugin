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

use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UninstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\EventDispatcher\Event as BaseEvent;
use Composer\Installer\PackageEvent;
use Composer\Script\Event;
use Isolated\Symfony\Component\Finder\Finder;

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
	private const VENDOR_PREFIXED = '/vendors';

	/**
	 * Do scope, as some scope-packages were updated.
	 *
	 * @var bool
	 */
	private static $do_scope = false;

	/**
	 * Post-update composer command.
	 *
	 * @param Event $event Composer event.
	 *
	 * @return void
	 * @noinspection PhpUnused
	 */
	public static function post_cmd( Event $event ): void {
		$scope_packages = $event->getComposer()->getPackage()->getExtra()['scope-packages'] ?? [];

		if ( self::$do_scope ) {
			self::prepare_scope( $event );
			self::scope( $event );
		} else {
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
		}

		// Always delete scoped packages from vendor.
		self::cleanup_scope( $event );

		// Always do dump.
		self::dump( $event );
	}

	/**
	 * Post-package-install composer command.
	 *
	 * @param PackageEvent $package_event Composer event.
	 *
	 * @return void
	 * @noinspection PhpUnused
	 */
	public static function post_package_install( PackageEvent $package_event ): void {
		$scope_packages = $package_event->getComposer()->getPackage()->getExtra()['scope-packages'] ?? [];
		$operation      = $package_event->getOperation();

		/**
		 * Current operation.
		 *
		 * @var InstallOperation $operation
		 */
		$package = $operation->getPackage()->getName();

		if ( ! in_array( $package, $scope_packages, true ) ) {
			return;
		}

		// Do not run scoper after installation if we already have package scoped.
		self::$do_scope = self::$do_scope || ! self::is_not_empty_dir( self::get_vendor_prefixed_dir( $package ) );
	}

	/**
	 * Post-package-update composer command.
	 *
	 * @param PackageEvent $event Composer event.
	 *
	 * @return void
	 * @noinspection PhpUnused
	 */
	public static function post_package_update( PackageEvent $event ): void {
		$scope_packages = $event->getComposer()->getPackage()->getExtra()['scope-packages'] ?? [];
		$operation      = $event->getOperation();

		/**
		 * Current operation.
		 *
		 * @var UpdateOperation $operation
		 */
		$package = $operation->getInitialPackage()->getName();

		if ( ! in_array( $package, $scope_packages, true ) ) {
			return;
		}

		self::$do_scope = true;
	}

	/**
	 * Post-package-uninstall composer command.
	 *
	 * @param PackageEvent $event Composer event.
	 *
	 * @return void
	 * @noinspection PhpUnused
	 */
	public static function post_package_uninstall( PackageEvent $event ): void {
		$scope_packages = $event->getComposer()->getPackage()->getExtra()['scope-packages'] ?? [];
		$operation      = $event->getOperation();

		/**
		 * Current operation.
		 *
		 * @var UninstallOperation $operation
		 */
		$package = $operation->getPackage()->getName();

		if ( ! in_array( $package, $scope_packages, true ) ) {
			return;
		}

		self::delete_package( self::get_vendor_prefixed_dir(), $package );
	}

	/**
	 * Prepare scoper to work.
	 * It checks the PHP version, creates the `vendor_prefixed` dir and runs composer for scoper package.
	 *
	 * @param Event $event Composer event.
	 *
	 * @return void
	 */
	private static function prepare_scope( Event $event ): void {
		$scope_packages = $event->getComposer()->getPackage()->getExtra()['scope-packages'] ?? [];

		if ( ! $scope_packages ) {
			return;
		}

		// Bail if .php-scoper/vendor dir already exists and not empty.
		if ( self::is_not_empty_dir( self::get_scoper_dir( self::VENDOR ) ) ) {
			return;
		}

		$vendor_prefixed = self::get_vendor_prefixed_dir();

		if ( ! is_dir( $vendor_prefixed ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
			mkdir( $vendor_prefixed );
		}

		$composer_cmd = 'composer --working-dir="' . self::get_scoper_dir() . '" --no-plugins --no-scripts --no-dev install';

		// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec, WordPress.Security.EscapeOutput.OutputNotEscaped
		echo shell_exec( $composer_cmd );
		// phpcs:enable WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec, WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Scope libraries.
	 *
	 * @param Event $event Composer event.
	 *
	 * @return void
	 */
	private static function scope( Event $event ): void {
		$scope_packages = $event->getComposer()->getPackage()->getExtra()['scope-packages'] ?? [];

		if ( ! $scope_packages ) {
			return;
		}

		$slug       = basename( getcwd() );
		$output_dir = self::get_vendor_prefixed_dir();

		$vendors = array_unique(
			array_map(
				static function ( $scope_package ) {
					return explode( '/', $scope_package )[0];
				},
				$scope_packages
			)
		);

		/**
		 * PHP Scoper has a bug.
		 * Packages to scope have directory structure like vendor-name/package-name.
		 * When all packages have the same vendor-name,
		 * PHP Scoper creates only package-name-dirs, without the common vendor-name dir.
		 * If it is the case, we should add the vendor-name dir to the output dir.
		 */
		if ( 1 === count( $vendors ) ) {
			$output_dir .= '/' . $vendors[0];
		}

		self::fix_logo_on_windows();

		$scoper_file = self::get_scoper_dir( self::VENDOR . '/humbug/php-scoper/bin/php-scoper' );
		$scoper_args =
			'" add-prefix' .
			' --config=.php-scoper/' . $slug . '-scoper.php' .
			' --output-dir=' . $output_dir .
			' --force';
		$scoper_cmd  = 'php "' . $scoper_file . $scoper_args;

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec, WordPress.Security.EscapeOutput.OutputNotEscaped
		echo shell_exec( $scoper_cmd );
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

		// Loop through the list of  packages and delete relevant dirs in vendor.
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
	 * @return Finder[]
	 * @noinspection PhpUndefinedMethodInspection
	 */
	public static function get_finders(): array {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$composer_json = json_decode( file_get_contents( getcwd() . '/composer.json' ), true );
		$packages      = $composer_json['extra']['scope-packages'] ?? [];
		$vendor_dir    = self::get_vendor_dir();
		$filenames     = [ '*.php', 'LICENSE', 'CHANGELOG.md', 'README.md' ];
		$finders       = [];

		foreach ( $packages as $package ) {
			$package_dir = $vendor_dir . '/' . $package;

			if ( ! is_dir( $package_dir ) ) {
				continue;
			}

			$finders[] = Finder::create()
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
	 * Fix scoper logo on Windows.
	 * On Windows, we have to replace EOLs to output Scoper logo properly.
	 */
	private static function fix_logo_on_windows(): void {
		if ( PHP_OS_FAMILY !== 'Windows' ) {
			return;
		}

		$file = self::get_scoper_dir( self::VENDOR . '/humbug/php-scoper/src/Console/Application.php' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$contents = file_get_contents( $file );
		$contents = str_replace( "\n", "\r\n", $contents );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $file, $contents, LOCK_EX );
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
	 * Detect if filename is dir and is empty.
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
	 * Detect if filename is dir and is not empty.
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
	 * @noinspection PhpSameParameterValueInspection
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
