<?php
/**
 * Plugin hCaptcha
 *
 * @package              hcaptcha-wp
 * @author               hCaptcha
 * @license              GPL-2.0-or-later
 * @wordpress-plugin
 *
 * Plugin Name:          hCaptcha for Forms and More
 * Plugin URI:           https://hcaptcha.com/
 * Description:          hCaptcha is a new way to monetize your site traffic while keeping out bots and spam. It is a drop-in replacement for reCAPTCHA.
 * Version:              1.13.2
 * Requires at least:    4.4
 * Requires PHP:         5.6
 * Author:               hCaptcha
 * Author URI:           https://hcaptcha.com/
 * License:              GPL v2 or later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:          hcaptcha-for-forms-and-more
 * Domain Path:          /languages/
 *
 * WC requires at least: 3.0
 * WC tested up to:      5.8
 */

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
const HCAPTCHA_VERSION = '1.13.2';

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

require HCAPTCHA_INC . '/common/request.php';
require HCAPTCHA_INC . '/common/functions.php';

// Add admin page.
if ( is_admin() ) {
	require HCAPTCHA_INC . '/backend/nav.php';
}

if ( ! function_exists( 'hcap_hcaptcha_error_message' ) ) {
	/**
	 * Print error message.
	 *
	 * @param string $hcaptcha_content Content of hCaptcha.
	 *
	 * @return string
	 */
	function hcap_hcaptcha_error_message( $hcaptcha_content = '' ) {
		$message = sprintf(
			'<p id="hcap_error" class="error hcap_error">%s</p>',
			__( 'The Captcha is invalid.', 'hcaptcha-for-forms-and-more' )
		);

		return $message . $hcaptcha_content;
	}
}

global $hcaptcha_wordpress_plugin;

$hcaptcha_wordpress_plugin = new Main();
$hcaptcha_wordpress_plugin->init();
