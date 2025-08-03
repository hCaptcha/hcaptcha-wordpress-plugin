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

/**
 * Default nonce action.
 */
const HCAPTCHA_ACTION = 'hcaptcha_action';

/**
 * Default nonce name.
 */
const HCAPTCHA_NONCE = 'hcaptcha_nonce';

$loader = require PLUGIN_PATH . '/vendor/autoload.php';

$loader->addPsr4( '', __DIR__ . '/Stubs/', true );

FunctionMocker::init(
	[
		'blacklist'             => [
			realpath( PLUGIN_PATH ),
		],
		'whitelist'             => [
			realpath( PLUGIN_PATH . '/hcaptcha.php' ),
			realpath( PLUGIN_PATH . '/src/php' ),
			realpath( PLUGIN_PATH . '/tests/php/unit/Stubs' ),
		],
		'redefinable-internals' => [
			'class_exists',
			'constant',
			'date_create_immutable',
			'defined',
			'extension_loaded',
			'filter_input',
			'function_exists',
			'header_remove',
			'http_response_code',
			'ini_get',
			'uniqid',
		],
	]
);

WP_Mock::bootstrap();
