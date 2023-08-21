<?php
/**
 * Bootstrap file for hCaptcha phpunit tests.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpParamsInspection */
/** @noinspection PhpUnused */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

use tad\FunctionMocker\FunctionMocker;

/**
 * Plugin test dir.
 */
const PLUGIN_TESTS_DIR = __DIR__;

/**
 * Plugin main file.
 */
define( 'PLUGIN_MAIN_FILE', dirname( __DIR__, 3 ) . '/hcaptcha.php' );

/**
 * Plugin path.
 */
define( 'PLUGIN_PATH', realpath( dirname( PLUGIN_MAIN_FILE ) ) );

require_once PLUGIN_PATH . '/vendor/autoload.php';

if ( ! defined( 'ABSPATH' ) ) {
	/**
	 * WordPress ABSPATH.
	 */
	define( 'ABSPATH', PLUGIN_PATH . '/../../../' );
}

/**
 * Path to the plugin dir.
 */
const HCAPTCHA_TEST_PATH = PLUGIN_PATH;

/**
 * Plugin dir url.
 */
const HCAPTCHA_TEST_URL = 'https://site.org/wp-content/plugins/hcaptcha-wordpress-plugin';

/**
 * Main plugin file.
 */
const HCAPTCHA_TEST_FILE = PLUGIN_MAIN_FILE;

FunctionMocker::init(
	[
		'blacklist'             => [
			realpath( PLUGIN_PATH ),
		],
		'whitelist'             => [
			realpath( PLUGIN_PATH . '/hcaptcha.php' ),
			realpath( PLUGIN_PATH . '/src/php' ),
		],
		'redefinable-internals' => [
			'constant',
			'defined',
			'filter_input',
			'function_exists',
			'uniqid',
		],
	]
);

WP_Mock::bootstrap();
