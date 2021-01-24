<?php
/**
 * HCaptchaPluginWPTestCase class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration;

/**
 * Class HCaptchaPluginWPTestCase
 *
 * @group current
 */
class HCaptchaPluginWPTestCase extends HCaptchaWPTestCase {

	/**
	 * Plugin relative path.
	 *
	 * @var string
	 */
	protected static $plugin;

	/**
	 * Plugin active status.
	 *
	 * @var array
	 */
	protected static $plugin_active = [];

	/**
	 * Status of do_action after activation of ninja-forms plugin.
	 *
	 * @var boolean
	 */
	protected static $did_plugins_loaded_for_nf = false;

	/**
	 * Teardown after class.
	 */
	public static function tearDownAfterClass(): void {
		deactivate_plugins( static::$plugin );

		parent::tearDownAfterClass();
	}

	/**
	 * Setup test.
	 */
	public function setUp(): void {
		$plugins_requiring_php_7 = [
			'ninja-forms/ninja-forms.php',
			'woocommerce/woocommerce.php',
		];

		if (
			in_array( static::$plugin, $plugins_requiring_php_7, true ) &&
			version_compare( PHP_VERSION, '7.0', '<' )
		) {
			self::markTestSkipped(
				'This tests requires PHP 7.0 at least.'
			);
		}

		parent::setUp();

		if ( ! isset( static::$plugin_active[ static::$plugin ] ) ) {
			activate_plugin( static::$plugin );
			static::$plugin_active[ static::$plugin ] = true;
		}

		if (
			'ninja-forms/ninja-forms.php' === static::$plugin &&
			! static::$did_plugins_loaded_for_nf
		) {
			do_action( 'plugins_loaded' );
			static::$did_plugins_loaded_for_nf = true;
		}
	}
}
