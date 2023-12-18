<?php
/**
 * Scoper configuration file.
 *
 * @package hcaptcha-wp
 */

declare(strict_types=1);

use HCaptcha\Scoper\Scoper;

require_once __DIR__ . '/Scoper.php';

$config = [
	'prefix'    => 'HCaptcha\Vendor',

	/**
	 * By default, when running php-scoper add-prefix,
	 * it will prefix all relevant code found in the current working directory.
	 * You can, however, define which files should be scoped by defining a collection of Finders
	 * in the following configuration key.
	 * For more see: https://github.com/humbug/php-scoper#finders-and-paths.
	 */
	'finders'   => Scoper::get_finders(),

	/**
	 * When scoping PHP files, there will be scenarios where some of the code being scoped indirectly
	 * references the original namespace.
	 * These will include, for example, strings or string manipulations.
	 * PHP-Scoper has limited support for prefixing such strings.
	 * To circumvent that, you can define patchers to manipulate the file to your heart contents.
	 * For more see: https://github.com/humbug/php-scoper#patchers.
	 */
	'patchers'  => [
		function ( string $file_path, string $prefix, string $content ): string {
			$file_path = str_replace( '\\', '/', $file_path );

			if ( strpos( $file_path, 'matthiasmullie/minify/src/JS.php' ) !== false ) {
				return str_replace(
					'\\\MatthiasMullie\\\Minify\\\Minify',
					'\\' . $prefix . '\MatthiasMullie\Minify\Minify',
					$content
				);
			}

			return $content;
		},
	],

	/*
	 * Whitelists Classes that don't need to be scoped.
	 */
	'whitelist' => [],
];

return $config;
