<?php
/**
 * Bootstrap FunctionMocker for integration tests.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration;

use RuntimeException;
use tad\FunctionMocker\FunctionMocker;

/**
 * Initialize FunctionMocker.
 *
 * @param string $hcaptcha_path   hCaptcha plugin path.
 * @param array  $extra_whitelist Extra paths that Patchwork can transform.
 *
 * @return void
 * @throws RuntimeException RuntimeException.
 * @noinspection PhpUnused
 */
function hcaptcha_init_function_mocker( string $hcaptcha_path, array $extra_whitelist = [] ): void {
	$parallel_cache_path = getenv( 'HCAPTCHA_FUNCTION_MOCKER_CACHE' );
	$cache_path          = false !== $parallel_cache_path && '' !== $parallel_cache_path
		? $parallel_cache_path
		: $hcaptcha_path . '/.codeception/_output/function-mocker-cache';
	$lock_path           = getenv( 'HCAPTCHA_FUNCTION_MOCKER_LOCK' );
	$lock_handle         = false;

	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
	if ( ! is_dir( $cache_path ) && ! mkdir( $cache_path, 0777, true ) && ! is_dir( $cache_path ) ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		throw new RuntimeException( sprintf( 'Directory "%s" was not created', $cache_path ) );
	}

	if ( false !== $lock_path && '' !== $lock_path ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Parallel test lock.
		$lock_handle = fopen( $lock_path, 'c' );

		if ( false === $lock_handle || ! flock( $lock_handle, LOCK_EX ) ) {
			throw new RuntimeException( 'Could not acquire the FunctionMocker initialization lock.' );
		}
	}

	try {
		FunctionMocker::init(
			[
				'blacklist'             => [
					hcaptcha_get_filesystem_root( $hcaptcha_path ),
				],
				'cache-path'            => $cache_path,
				'whitelist'             => hcaptcha_get_function_mocker_whitelist( $hcaptcha_path, $extra_whitelist ),
				'redefinable-internals' => hcaptcha_get_redefinable_internals(),
			]
		);
	} finally {
		if ( false !== $lock_handle ) {
			flock( $lock_handle, LOCK_UN );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Parallel test lock.
			fclose( $lock_handle );
		}
	}
}

/**
 * Get third-party plugin paths that Patchwork can transform.
 *
 * @param string $wp_root_path WordPress root path.
 *
 * @return array
 * @noinspection PhpUnused
 */
function hcaptcha_get_function_mocker_external_plugin_whitelist( string $wp_root_path ): array {
	if ( '' === $wp_root_path ) {
		return [];
	}

	$paths = [];

	foreach ( [ 'contact-form-7', 'wpforo' ] as $plugin_slug ) {
		$paths[] = $wp_root_path . '/wp-content/plugins/' . $plugin_slug;
	}

	return $paths;
}

/**
 * Get the filesystem root for a path.
 *
 * @param string $path Path.
 *
 * @return string
 */
function hcaptcha_get_filesystem_root( string $path ): string {
	$root = $path;

	while ( dirname( $root ) !== $root ) {
		$root = dirname( $root );
	}

	return realpath( $root ) ?: $root;
}

/**
 * Get paths that Patchwork can transform.
 *
 * @param string $hcaptcha_path   hCaptcha plugin path.
 * @param array  $extra_whitelist Extra paths that Patchwork can transform.
 *
 * @return array
 */
function hcaptcha_get_function_mocker_whitelist( string $hcaptcha_path, array $extra_whitelist ): array {
	$paths = array_merge(
		[
			$hcaptcha_path . '/src/php',
			$hcaptcha_path . '/tests/php/integration/Stubs',
		],
		$extra_whitelist
	);

	return array_values(
		array_unique(
			array_filter(
				array_map( 'realpath', $paths )
			)
		)
	);
}

/**
 * Get PHP internals that can be replaced by FunctionMocker.
 *
 * @return array
 */
function hcaptcha_get_redefinable_internals(): array {
	return [
		'class_exists',
		'constant',
		'date_create_immutable',
		'defined',
		'filter_input',
		'function_exists',
		'is_readable',
		'setcookie',
		'time',
		'uniqid',
	];
}
