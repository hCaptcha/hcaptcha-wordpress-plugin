<?php
/**
 * HCaptchaPluginWPTestCase class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpLanguageLevelInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Integration;

/**
 * Class HCaptchaPluginWPTestCase
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
		$plugins_requiring_php = [
			'7.4' => [
				'contact-form-7/wp-contact-form-7.php',
				'ninja-forms/ninja-forms.php',
				'woocommerce/woocommerce.php',
			],
		];

		foreach ( $plugins_requiring_php as $php_version => $plugins_requiring_php_version ) {
			if (
				in_array( static::$plugin, $plugins_requiring_php_version, true ) &&
				version_compare( PHP_VERSION, $php_version, '<' )
			) {
				self::markTestSkipped(
					'This test requires PHP ' . $php_version . ' at least.'
				);
			}
		}

		parent::setUp();

		if ( static::$plugin && ! isset( static::$plugin_active[ static::$plugin ] ) ) {
			activate_plugin( static::$plugin );
			static::$plugin_active[ static::$plugin ] = true;
		}
	}
}
