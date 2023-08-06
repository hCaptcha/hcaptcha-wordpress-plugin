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
	 * Status of do_action after activation of ninja-forms plugin.
	 *
	 * @var boolean
	 */
	protected static $did_plugins_loaded_for_nf = false;

	/**
	 * Teardown after class.
	 */
	public static function tearDownAfterClass(): void {  // phpcs:ignore PHPCompatibility.FunctionDeclarations.NewReturnTypeDeclarations.voidFound
		deactivate_plugins( static::$plugin );

		parent::tearDownAfterClass();
	}

	/**
	 * Setup test.
	 */
	public function setUp(): void { // phpcs:ignore PHPCompatibility.FunctionDeclarations.NewReturnTypeDeclarations.voidFound
		$plugins_requiring_php = [
			'7.1' => [ 'ninja-forms/ninja-forms.php' ],
			'7.3' => [ 'woocommerce/woocommerce.php' ],
			'7.4' => [ 'contact-form-7/wp-contact-form-7.php' ],
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
