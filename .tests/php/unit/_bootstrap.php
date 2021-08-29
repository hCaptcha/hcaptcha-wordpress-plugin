<?php
/**
 * Bootstrap file for hCaptcha phpunit tests.
 *
 * @package HCaptcha\Tests
 */

use tad\FunctionMocker\FunctionMocker;

/**
 * Plugin test dir.
 */
const PLUGIN_TESTS_DIR = __DIR__;

/**
 * Plugin main file.
 */
define( 'PLUGIN_MAIN_FILE', dirname( dirname( dirname( __DIR__ ) ) ) . '/hcaptcha.php' );

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
const CYR_TO_LAT_TEST_FILE = PLUGIN_MAIN_FILE;

FunctionMocker::init(
	[
		'blacklist'             => [
			realpath( PLUGIN_PATH ),
		],
		'whitelist'             => [
			realpath( PLUGIN_PATH . '/hcaptcha.php' ),
			realpath( PLUGIN_PATH . '/backend' ),
			realpath( PLUGIN_PATH . '/bbp' ),
			realpath( PLUGIN_PATH . '/bp' ),
			realpath( PLUGIN_PATH . '/common' ),
			realpath( PLUGIN_PATH . '/default' ),
			realpath( PLUGIN_PATH . '/jetpack' ),
			realpath( PLUGIN_PATH . '/mailchimp' ),
			realpath( PLUGIN_PATH . '/nf' ),
			realpath( PLUGIN_PATH . '/src/php' ),
			realpath( PLUGIN_PATH . '/subscriber' ),
			realpath( PLUGIN_PATH . '/wc' ),
			realpath( PLUGIN_PATH . '/wc_wl' ),
			realpath( PLUGIN_PATH . '/wpforms' ),
			realpath( PLUGIN_PATH . '/wpforo' ),
		],
		'redefinable-internals' => [
			'constant',
			'defined',
			'filter_input',
			'uniqid',
		],
	]
);

WP_Mock::bootstrap();
