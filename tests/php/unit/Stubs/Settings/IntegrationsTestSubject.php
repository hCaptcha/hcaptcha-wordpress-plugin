<?php
/**
 * Integrations test subject class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Unit\Stubs\Settings;

use HCaptcha\Settings\Integrations;
use ReflectionClass;

/**
 * Test subject for Integrations.
 */
class IntegrationsTestSubject extends Integrations {

	/**
	 * Make a test subject without running the parent constructor.
	 *
	 * @return self
	 */
	public static function make(): self {
		$reflection = new ReflectionClass( self::class );

		return $reflection->newInstanceWithoutConstructor();
	}
	/**
	 * Test settings values.
	 *
	 * @var array
	 */
	private array $test_settings = [];

	/**
	 * Plugin file path.
	 *
	 * @var string
	 */
	private string $plugin_file = '';

	/**
	 * Whether the current screen is an option screen.
	 *
	 * @param string|array $ids Screen ids.
	 *
	 * @return bool
	 */
	public function is_options_screen( $ids = 'options' ): bool {
		return true;
	}

	/**
	 * Set test settings values.
	 *
	 * @param array $test_settings Test settings values.
	 *
	 * @return void
	 */
	public function set_test_settings( array $test_settings ): void {
		$this->test_settings = $test_settings;
	}

	/**
	 * Set the plugin file path.
	 *
	 * @param string $plugin_file Plugin file path.
	 *
	 * @return void
	 */
	public function set_plugin_file( string $plugin_file ): void {
		$this->plugin_file = $plugin_file;
	}

	/**
	 * Get a plugin option.
	 *
	 * @param string $key         Setting name.
	 * @param mixed  $empty_value Empty value for this setting.
	 *
	 * @return mixed
	 */
	public function get( string $key, $empty_value = null ) {
		return $this->test_settings[ $key ] ?? $empty_value ?? '';
	}

	/**
	 * Call get_plugin_data().
	 *
	 * @param string $plugin    Plugin slug.
	 * @param bool   $markup    Optional. If the returned data should have HTML markup applied.
	 * @param bool   $translate Optional. If the returned data should be translated.
	 *
	 * @return array
	 */
	public function call_get_plugin_data( string $plugin, bool $markup = true, bool $translate = true ): array {
		return $this->get_plugin_data( $plugin, $markup, $translate );
	}

	/**
	 * Call setup_field_data().
	 *
	 * @param array $installed Installed entities.
	 *
	 * @return void
	 */
	public function call_setup_field_data( array $installed ): void {
		$this->setup_field_data( $installed );
	}

	/**
	 * Get a plugin file from the plugin slug.
	 *
	 * @param string $plugin Plugin slug.
	 *
	 * @return string
	 */
	protected function get_plugin_file( string $plugin ): string {
		return $this->plugin_file ?: parent::get_plugin_file( $plugin );
	}
}
