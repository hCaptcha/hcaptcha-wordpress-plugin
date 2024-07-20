<?php
/**
 * Scoper configuration file.
 *
 * @package hcaptcha-wp
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */
/** @noinspection PhpUndefinedNamespaceInspection */
/** @noinspection PhpUndefinedMethodInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

declare(strict_types=1);

use HCaptcha\Scoper\Scoper;

require_once __DIR__ . '/src/Scoper.php';

$finders = Scoper::get_finders();

$finders[0]->name( [ 'keywords*.txt', 'operators*.txt' ] );

$config = [
	'prefix'   => 'HCaptcha\Vendors',
	'finders'  => $finders,
	'patchers' => [
		static function ( string $file_path, string $prefix, string $content ): string {
			$file_path = str_replace( '\\', '/', $file_path );

			if ( false !== strpos( $file_path, 'matthiasmullie/minify/src/CSS.php' ) ) {
				return str_replace(
					[
						"'HCaptcha\\\\Vendors\\\\1\\\\2\\\\3'",
						"'HCaptcha\\\\Vendors\\\\1\\\\2'",
						"'HCaptcha\\\\Vendors\\\\1'",
					],
					[
						"'\\\\1\\\\2\\\\3'",
						"'\\\\1\\\\2'",
						"'0\\\\1'",
					],
					$content
				);
			}

			if ( false !== strpos( $file_path, 'matthiasmullie/minify/src/JS.php' ) ) {
				return str_replace(
					'\\\MatthiasMullie\\\Minify\\\Minify',
					'\\' . $prefix . '\MatthiasMullie\Minify\Minify',
					$content
				);
			}

			return $content;
		},
	],
];

return $config;
