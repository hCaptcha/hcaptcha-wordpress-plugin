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
	 * Plugin relative path or paths.
	 *
	 * @var string|string[]
	 */
	protected static $plugin;

	/**
	 * Plugin active status.
	 *
	 * @var array
	 */
	protected static array $plugin_active = [];

	/**
	 * Hooks to replay when plugins are loaded after the hooks fired.
	 *
	 * @var string[]
	 */
	protected static array $plugin_load_hooks = [];

	/**
	 * Teardown after class.
	 */
	public static function tearDownAfterClass(): void {
		$plugins = array_reverse( (array) static::$plugin );

		deactivate_plugins( $plugins );

		parent::tearDownAfterClass();
	}

	/**
	 * Setup test.
	 */
	public function setUp(): void {
		$plugins_requiring_php = [
			'7.4' => [
				'contact-form-7/wp-contact-form-7.php',
				'elementor/elementor.php',
				'elementor-pro/elementor-pro.php',
				'ninja-forms/ninja-forms.php',
				'woocommerce/woocommerce.php',
			],
		];

		foreach ( (array) static::$plugin as $plugin ) {
			foreach ( $plugins_requiring_php as $php_version => $plugins_requiring_php_version ) {
				if (
					in_array( $plugin, $plugins_requiring_php_version, true ) &&
					version_compare( PHP_VERSION, $php_version, '<' )
				) {
					self::markTestSkipped(
						'This test requires PHP ' . $php_version . ' at least.'
					);
				}
			}
		}

		parent::setUp();

		$plugins        = [];
		$hook_callbacks = [];

		foreach ( static::$plugin_load_hooks as $hook_name ) {
			$hook_callbacks[ $hook_name ] = $this->get_hook_callbacks( $hook_name );
		}

		foreach ( (array) static::$plugin as $plugin ) {
			if ( $plugin && ! isset( static::$plugin_active[ $plugin ] ) ) {
				$result = activate_plugin( $plugin );

				if ( is_wp_error( $result ) ) {
					self::fail( $result->get_error_message() );
				}

				$plugins[] = $plugin;
			}
		}

		foreach ( $hook_callbacks as $hook_name => $previous_callbacks ) {
			$this->run_late_hook_callbacks( $hook_name, $previous_callbacks );
		}

		foreach ( $plugins as $plugin ) {
			static::$plugin_active[ $plugin ] = true;
		}
	}

	/**
	 * Get callbacks currently registered on a hook.
	 *
	 * @param string $hook_name Hook name.
	 *
	 * @return array
	 */
	private function get_hook_callbacks( string $hook_name ): array {
		return $GLOBALS['wp_filter'][ $hook_name ]->callbacks ?? [];
	}

	/**
	 * Run callbacks registered by a plugin after the hook fired.
	 *
	 * @param string $hook_name          Hook name.
	 * @param array  $previous_callbacks Callbacks registered before plugin activation.
	 *
	 * @return void
	 */
	private function run_late_hook_callbacks( string $hook_name, array $previous_callbacks ): void {
		if ( ! did_action( $hook_name ) ) {
			return;
		}

		foreach ( $this->get_hook_callbacks( $hook_name ) as $priority => $callbacks ) {
			foreach ( $callbacks as $callback_id => $callback ) {
				if ( isset( $previous_callbacks[ $priority ][ $callback_id ] ) ) {
					continue;
				}

				call_user_func( $callback['function'] );
			}
		}
	}
}
