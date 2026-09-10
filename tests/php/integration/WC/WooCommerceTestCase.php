<?php
/**
 * WooCommerceTestCase class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\WC;

use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;

/**
 * Base test case for the live WooCommerce plugin.
 */
abstract class WooCommerceTestCase extends HCaptchaPluginWPTestCase {

	/**
	 * Plugin relative path.
	 *
	 * @var string
	 */
	protected static $plugin = 'woocommerce/woocommerce.php';

	/**
	 * Hooks to replay after loading WooCommerce.
	 *
	 * @var string[]
	 */
	protected static array $plugin_load_hooks = [
		'plugins_loaded',
		'after_setup_theme',
		'init',
	];

	/**
	 * Set up the test.
	 *
	 * @return void
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function setUp(): void {
		parent::setUp();

		$woocommerce = WC();

		if ( ! did_action( 'woocommerce_loaded' ) ) {
			$woocommerce->on_plugins_loaded();
		}

		if ( ! defined( 'WC_TEMPLATE_PATH' ) ) {
			$woocommerce->setup_environment();
		}

		if ( ! function_exists( 'woocommerce_output_all_notices' ) ) {
			$woocommerce->include_template_functions();
		}

		if ( null === $woocommerce->countries ) {
			$woocommerce->init();
		} elseif ( ! did_action( 'woocommerce_init' ) ) {
			do_action( 'woocommerce_init' );
		}
	}
}
