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
	private $plugin_active = [];

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
		parent::setUp();

		if ( ! isset( $this->plugin_active[ static::$plugin ] ) ) {
			activate_plugin( static::$plugin );
			$this->plugin_active[ static::$plugin ] = true;
		}
	}
}
