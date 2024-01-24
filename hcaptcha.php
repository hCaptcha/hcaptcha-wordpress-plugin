<?php
/**
 * Plugin hCaptcha
 *
 * @package              hcaptcha-wp
 * @author               hCaptcha
 * @license              GPL-2.0-or-later
 * @wordpress-plugin
 *
 * Plugin Name:          hCaptcha for WordPress
 * Plugin URI:           https://www.hcaptcha.com/
 * Description:          hCaptcha keeps out bots and spam while putting privacy first. It is a drop-in replacement for reCAPTCHA.
 * Version:              3.8.1
 * Requires at least:    5.0
 * Requires PHP:         7.0
 * Author:               hCaptcha
 * Author URI:           https://www.hcaptcha.com/
 * License:              GPL v2 or later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:          hcaptcha-for-forms-and-more
 * Domain Path:          /languages/
 *
 * WC requires at least: 3.0
 * WC tested up to:      8.5
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpParamsInspection */

use HCaptcha\Main;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	// @codeCoverageIgnoreStart
	exit;
	// @codeCoverageIgnoreEnd
}

/**
 * Plugin version.
 */
const HCAPTCHA_VERSION = '3.8.1';

/**
 * Path to the plugin dir.
 */
const HCAPTCHA_PATH = __DIR__;

/**
 * Path to the plugin dir.
 */
const HCAPTCHA_INC = HCAPTCHA_PATH . '/src/php/includes';

/**
 * Plugin dir url.
 */
define( 'HCAPTCHA_URL', untrailingslashit( plugin_dir_url( __FILE__ ) ) );

/**
 * Main plugin file.
 */
const HCAPTCHA_FILE = __FILE__;

/**
 * Default nonce action.
 */
const HCAPTCHA_ACTION = 'hcaptcha_action';

/**
 * Default nonce name.
 */
const HCAPTCHA_NONCE = 'hcaptcha_nonce';

require_once HCAPTCHA_PATH . '/vendor/autoload.php';

require HCAPTCHA_INC . '/request.php';
require HCAPTCHA_INC . '/functions.php';

/**
 * Get hCaptcha Main class instance.
 *
 * @return Main
 */
function hcaptcha(): Main {
	static $hcaptcha;

	if ( ! $hcaptcha ) {
		// @codeCoverageIgnoreStart
		$hcaptcha = new Main();
		// @codeCoverageIgnoreEnd
	}

	return $hcaptcha;
}

hcaptcha()->init();
