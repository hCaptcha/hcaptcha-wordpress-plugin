<?php
/**
 * Scoper configuration file.
 *
 * @package hcaptcha-wp
 */

declare(strict_types=1);

use HCaptcha\Scoper\Scoper;

require_once __DIR__ . '/Scoper.php';

$finders = Scoper::get_finders();

$finders[0]->name( [ 'keywords*.txt', 'operators*.txt' ] );

$config = [
	'prefix'    => 'HCaptcha\Vendor',
	'finders'   => $finders,
	'patchers'  => [
		static function ( string $file_path, string $prefix, string $content ): string {
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
	'whitelist' => [],
];

return $config;
