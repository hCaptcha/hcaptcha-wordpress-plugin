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
	 * Plugins whose PHP entry files have been loaded in this process.
	 *
	 * @var array<string, bool>
	 */
	protected static array $plugin_loaded = [];

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

		$hook_callbacks = [];

		foreach ( static::$plugin_load_hooks as $hook_name ) {
			$hook_callbacks[ $hook_name ] = $this->get_hook_callbacks( $hook_name );
		}

		foreach ( (array) static::$plugin as $plugin ) {
			$this->activate_test_plugin( $plugin );
			$this->replay_elementor_loaded_action( $plugin );
		}

		foreach ( $hook_callbacks as $hook_name => $previous_callbacks ) {
			$this->run_late_hook_callbacks( $hook_name, $previous_callbacks );
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
	 * Activate a plugin for the current test.
	 *
	 * @param string $plugin Plugin relative path.
	 *
	 * @return void
	 */
	private function activate_test_plugin( string $plugin ): void {
		if ( ! $plugin || is_plugin_active( $plugin ) ) {
			return;
		}

		$silent = isset( static::$plugin_loaded[ $plugin ] );
		$result = activate_plugin( $plugin, '', false, $silent );

		if ( is_wp_error( $result ) ) {
			self::fail( $result->get_error_message() );
		}

		static::$plugin_loaded[ $plugin ] = true;
	}

	/**
	 * Replay the Elementor loaded action when the test runner reset its state.
	 *
	 * @param string $plugin Plugin relative path.
	 *
	 * @return void
	 */
	private function replay_elementor_loaded_action( string $plugin ): void {
		if (
			'elementor/elementor.php' !== $plugin ||
			! class_exists( '\\Elementor\\Plugin', false ) ||
			did_action( 'elementor/loaded' )
		) {
			return;
		}

		// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
		do_action( 'elementor/loaded' );
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
