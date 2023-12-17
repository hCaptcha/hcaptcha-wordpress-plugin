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
use Composer\Script\Event;
use Composer\Installer\PackageEvent;
use Isolated\Symfony\Component\Finder\Finder;

/**
 * Class Scoper.
 */
class Scoper {

	/**
	 * Do scope, as some scope-packages were updated.
	 *
	 * @var bool
	 */
	private static $do_scope = false;

	/**
	 * Pre-update composer command.
	 * It checks the PHP version, clears recursively the `vendor_prefixed` dir and re-creates it.
	 *
	 * @param Event $event Composer event.
	 *
	 * @return void
	 * @noinspection MkdirRaceConditionInspection
	 * @noinspection PhpUnused
	 */
	public static function pre_cmd( Event $event ) {
		$packages = $event->getComposer()->getPackage()->getExtra()['scope-packages'] ?? [];

		if ( ! $packages ) {
			return;
		}

		// When .php-scoper/vendor dir exists and not empty, it means that this script was already executed.
		if ( self::is_not_empty_dir( __DIR__ . '/vendor' ) ) {
			return;
		}

		self::check_php_version();

		$vendor_prefixed = getcwd() . '/lib';

		self::delete_all( $vendor_prefixed );

		// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_mkdir, Generic.Commenting.DocComment.MissingShort
		mkdir( $vendor_prefixed );
		// phpcs:enable WordPress.WP.AlternativeFunctions.file_system_operations_mkdir, Generic.Commenting.DocComment.MissingShort

		$composer_cmd = 'composer --working-dir="' . __DIR__ . '" --no-plugins --no-scripts install';

		// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec, WordPress.Security.EscapeOutput.OutputNotEscaped
		echo shell_exec( $composer_cmd );
		// phpcs:enable WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec, WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Post-update composer command.
	 *
	 * @param Event $event Composer event.
	 *
	 * @return void
	 * @noinspection PhpUnused
	 */
	public static function post_cmd( Event $event ) {
		echo "============================== Post cmd\n";

//		xdebug_break();

		self::scope( $event );
	}

	/**
	 * Post-package-install composer command.
	 *
	 * @param PackageEvent $package_event Composer event.
	 *
	 * @return void
	 * @noinspection PhpUnused
	 */
	public static function post_package_install( PackageEvent $package_event ) {
		$packages  = $package_event->getComposer()->getPackage()->getExtra()['scope-packages'] ?? [];
		$operation = $package_event->getOperation();

		/**
		 * Current operation.
		 *
		 * @var InstallOperation $operation
		 */
		$package = $operation->getPackage()->getName();
//		xdebug_break();

		if ( ! in_array( $package, $packages, true ) ) {
			return;
		}

		echo "============================== Post package install cmd - $package\n";
	}

	/**
	 * Post-package-update composer command.
	 *
	 * @param PackageEvent $event Composer event.
	 *
	 * @return void
	 * @noinspection PhpUnused
	 */
	public static function post_package_update( PackageEvent $event ) {
		$packages  = $event->getComposer()->getPackage()->getExtra()['scope-packages'] ?? [];
		$operation = $event->getOperation();

		/**
		 * Current operation.
		 *
		 * @var UpdateOperation $operation
		 */
		$package = $operation->getInitialPackage()->getName();

		if ( ! in_array( $package, $packages, true ) ) {
			return;
		}

		echo "============================== Post package update cmd - $package\n";
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
	public static function post_package_uninstall( PackageEvent $event ) {
		$packages  = $event->getComposer()->getPackage()->getExtra()['scope-packages'] ?? [];
		$operation = $event->getOperation();

		/**
		 * Current operation.
		 *
		 * @var UninstallOperation $operation
		 */
//		xdebug_break();
		$package = $operation->getPackage()->getName();

		if ( ! in_array( $package, $packages, true ) ) {
			return;
		}

		echo "============================== Post package uninstall cmd - $package\n";
		self::$do_scope = true;
	}

	/**
	 * Scope libraries.
	 *
	 * @param BaseEvent $event Composer event.
	 *
	 * @return void
	 */
	private static function scope( BaseEvent $event ) {
		if ( ! self::$do_scope ) {
			return;
		}

		$scope_packages = $event->getComposer()->getPackage()->getExtra()['scope-packages'] ?? [];

		if ( ! $scope_packages ) {
			return;
		}

		$vendor          = getcwd() . '/vendor';
		$vendor_prefixed = getcwd() . '/lib';
		$slug            = basename( getcwd() );

		// When vendor_prefixed dir exists and not empty, it means that this script was already executed.
		if ( self::is_not_empty_dir( $vendor_prefixed ) ) {
			return;
		}

		self::fix_logo_on_windows();

		$scoper_file  = __DIR__ . '/vendor/humbug/php-scoper/bin/php-scoper';
		$scoper_args  =
			'" add-prefix' .
			' --config=.php-scoper/' . $slug . '-scoper.php' .
			' --output-dir=' . $vendor_prefixed . '/' .
			' --force';
		$scoper_cmd   = 'php "' . $scoper_file . $scoper_args;
		$composer_cmd = 'composer --no-plugins --no-scripts dump-autoload';

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec, WordPress.Security.EscapeOutput.OutputNotEscaped
		echo shell_exec( $scoper_cmd );

		// Loop through the list of  packages and delete relevant dirs in vendor.
		foreach ( $scope_packages as $package ) {
			self::delete_all( $vendor . '/' . $package );
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec, WordPress.Security.EscapeOutput.OutputNotEscaped
		echo shell_exec( $composer_cmd );
	}

	/**
	 * Get finders for the scoper.
	 *
	 * @return Finder[]
	 */
	public static function get_finders(): array {
		/**
		 * PHP Scoper has a bug.
		 * When only one Finder is used, it creates in the `vendor_prefixed` only sub-dirs,
		 * without the main package dir like `guzzlehttp`.
		 * So, we need to scope two libs at least.
		 * We should add also all dependent packages.
		 */

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$composer_json = json_decode( file_get_contents( getcwd() . '/composer.json' ), true );
		$packages      = $composer_json['extra']['scope-packages'] ?? [];
		$vendor_dir    = getcwd() . '/vendor/';
		$filenames     = [ '*.php', 'composer.json', 'LICENSE', 'CHANGELOG.md', 'README.md' ];
		$finders       = [];

		foreach ( $packages as $package ) {
			if ( ! is_dir( $vendor_dir . $package ) ) {
				// Some packages may not exist in the lite version, skip them.
				continue;
			}

			$finders[] = Finder::create()
				->files()
				->in( $vendor_dir . $package )
				->name( $filenames );
		}

		return $finders;
	}

	/**
	 * Show a message with color formatting.
	 *
	 * @param string $message Message.
	 *
	 * @return void
	 */
	private static function show_message( string $message ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo "\033[31m$message\033[0m" . PHP_EOL;
	}

	/**
	 * Check PHP version.
	 *
	 * @return void
	 */
	private static function check_php_version() {
		if ( PHP_VERSION_ID < 70400 ) {
			self::show_message( 'Your PHP version is not correct(' . PHP_VERSION . ')! Please use PHP 7.4 for executing this composer script.' );
			exit( 1 );
		}
	}

	/**
	 * Fix scoper logo on Windows.
	 * On Windows, we have to replace EOLs to output Scoper logo properly.
	 */
	private static function fix_logo_on_windows() {
		if ( stripos( PHP_OS, 'WIN' ) !== 0 ) {
			return;
		}

		$file = __DIR__ . '/vendor/humbug/php-scoper/src/Console/Application.php';

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$contents = file_get_contents( $file );
		$contents = str_replace( "\n", "\r\n", $contents );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $file, $contents, LOCK_EX );
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
}
