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

use WP_Theme;

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
	 * Theme stylesheet slug.
	 *
	 * @var string
	 */
	protected static string $theme = '';

	/**
	 * Plugins whose PHP entry files have been loaded in this process.
	 *
	 * @var array<string, bool>
	 */
	protected static array $plugin_loaded = [];

	/**
	 * Themes whose PHP files have been loaded in this process.
	 *
	 * @var array<string, bool>
	 */
	protected static array $theme_loaded = [];

	/**
	 * Theme shortcode tags and the classes that register them.
	 *
	 * @var array<string, string>
	 */
	protected static array $theme_shortcode_classes = [];

	/**
	 * Shortcodes registered by loaded themes.
	 *
	 * @var array<string, array<string, callable>>
	 */
	protected static array $theme_shortcodes = [];

	/**
	 * Hooks to replay when plugins are loaded after the hooks fired.
	 *
	 * @var string[]
	 */
	protected static array $plugin_load_hooks = [];

	/**
	 * Expected incorrect usage notices caused by loading a theme after WordPress bootstrap.
	 *
	 * @var string[]
	 */
	protected static array $theme_expected_incorrect_usage = [];

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

		$this->load_test_theme();

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
	 * Load and activate a theme for the current test.
	 *
	 * @return void
	 */
	private function load_test_theme(): void {
		if ( ! static::$theme ) {
			return;
		}

		$theme = wp_get_theme( static::$theme );

		if ( ! $theme->exists() || $theme->errors() ) {
			self::markTestSkipped( 'The ' . static::$theme . ' theme is not installed.' );
		}

		$this->set_active_test_theme( $theme );

		if ( isset( static::$theme_loaded[ static::$theme ] ) ) {
			$this->load_test_theme_shortcodes();
			return;
		}

		$this->initialize_test_theme( $theme );
		$this->load_test_theme_shortcodes();

		static::$theme_loaded[ static::$theme ] = true;
	}

	/**
	 * Load theme files and replay its WordPress lifecycle callbacks.
	 *
	 * @param WP_Theme $theme Theme instance.
	 *
	 * @return void
	 */
	private function initialize_test_theme( WP_Theme $theme ): void {
		$hook_callbacks      = [];
		$previous_shortcodes = $GLOBALS['shortcode_tags'] ?? [];

		foreach ( [ 'after_setup_theme', 'init', 'wp_loaded' ] as $hook_name ) {
			$hook_callbacks[ $hook_name ] = $this->get_hook_callbacks( $hook_name );
		}

		foreach ( static::$theme_expected_incorrect_usage as $incorrect_usage ) {
			$this->setExpectedIncorrectUsage( $incorrect_usage );
		}

		require_once $theme->get_template_directory() . '/functions.php';

		foreach ( $hook_callbacks as $hook_name => $previous_callbacks ) {
			for ( $pass = 0; $pass < 10; ++$pass ) {
				$current_callbacks = $this->run_late_hook_callbacks( $hook_name, $previous_callbacks, true );

				if ( $current_callbacks === $previous_callbacks ) {
					break;
				}

				$previous_callbacks = $current_callbacks;
			}
		}

		static::$theme_shortcodes[ static::$theme ] = array_diff_key(
			$GLOBALS['shortcode_tags'] ?? [],
			$previous_shortcodes
		);
	}

	/**
	 * Load lazy theme modules and restore their shortcode callbacks.
	 *
	 * @return void
	 */
	private function load_test_theme_shortcodes(): void {
		foreach ( static::$theme_shortcodes[ static::$theme ] ?? [] as $tag => $callback ) {
			add_shortcode( $tag, $callback );
		}

		foreach ( static::$theme_shortcode_classes as $class_name ) {
			if ( class_exists( $class_name ) ) {
				new $class_name();
			}
		}
	}

	/**
	 * Make a theme active for the current test.
	 *
	 * @param WP_Theme $theme Theme instance.
	 *
	 * @return void
	 */
	private function set_active_test_theme( WP_Theme $theme ): void {
		$template   = $theme->get_template();
		$stylesheet = $theme->get_stylesheet();

		add_filter(
			'pre_option_template',
			static function () use ( $template ) {
				return $template;
			},
			PHP_INT_MIN
		);
		add_filter(
			'pre_option_stylesheet',
			static function () use ( $stylesheet ) {
				return $stylesheet;
			},
			PHP_INT_MIN
		);

		$GLOBALS['wp_template_path']   = $theme->get_template_directory();
		$GLOBALS['wp_stylesheet_path'] = $theme->get_stylesheet_directory();
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
	 * @param bool   $force              Run callbacks even when the hook counter was reset by the test runner.
	 *
	 * @return array
	 */
	private function run_late_hook_callbacks( string $hook_name, array $previous_callbacks, bool $force = false ): array {
		if ( ! $force && ! did_action( $hook_name ) ) {
			return $previous_callbacks;
		}

		foreach ( $this->get_hook_callbacks( $hook_name ) as $priority => $callbacks ) {
			foreach ( $callbacks as $callback_id => $callback ) {
				if ( isset( $previous_callbacks[ $priority ][ $callback_id ] ) ) {
					continue;
				}

				$previous_callbacks[ $priority ][ $callback_id ] = $callback;

				call_user_func( $callback['function'] );
			}
		}

		return $previous_callbacks;
	}
}
