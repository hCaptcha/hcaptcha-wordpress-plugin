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
 * Version:              1.10.1
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
 * WC tested up to:      5.2
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
define( 'HCAPTCHA_VERSION', '1.10.1' );

/**
 * Path to the plugin dir.
 */
define( 'HCAPTCHA_PATH', __DIR__ );

/**
 * Plugin dir url.
 */
define( 'HCAPTCHA_URL', untrailingslashit( plugin_dir_url( __FILE__ ) ) );

/**
 * Main plugin file.
 */
define( 'HCAPTCHA_FILE', __FILE__ );

require_once HCAPTCHA_PATH . '/vendor/autoload.php';

require 'common/request.php';
require 'common/functions.php';

// Add admin page.
if ( is_admin() ) {
	require 'backend/nav.php';
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
		$hcaptcha_content = sprintf( '<p id="hcap_error" class="error hcap_error">%s</p>', __( 'The Captcha is invalid.', 'hcaptcha-for-forms-and-more' ) ) . $hcaptcha_content;

		return $hcaptcha_content;
	}
}

global $hcaptcha_wordpress_plugin;

$hcaptcha_wordpress_plugin = new Main();
$hcaptcha_wordpress_plugin->init();
