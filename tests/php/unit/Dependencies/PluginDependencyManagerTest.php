<?php
/**
 * PluginDependencyManagerTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Unit\Dependencies;

use HCaptcha\Dependencies\PluginDependencyManager;
use HCaptcha\Tests\Unit\HCaptchaTestCase;

/**
 * Test plugin dependency management.
 *
 * @group dependencies
 */
class PluginDependencyManagerTest extends HCaptchaTestCase {
	/**
	 * Test an array header containing an installed plugin file directly.
	 */
	public function test_get_dependencies_accepts_array_of_plugin_files(): void {
		$manager = new PluginDependencyManager(
			[
				'pro/pro.php'   => [ 'RequiresPlugins' => [ 'base/base.php' ] ],
				'base/base.php' => [ 'Name' => 'Base' ],
			]
		);

		self::assertSame( [ 'base/base.php' ], $manager->get_dependencies( 'pro/pro.php' ) );
	}


	/**
	 * Test dependencies from the plugin header and injected configuration.
	 */
	public function test_get_dependencies_combines_header_and_injected_configuration(): void {
		$plugins = [
			'pro/pro.php'               => [
				'Name'            => 'Pro',
				'RequiresPlugins' => 'base, shared',
			],
			'base/base.php'             => [ 'Name' => 'Base' ],
			'shared/plugin.php'         => [ 'Name' => 'Shared' ],
			'configured/configured.php' => [ 'Name' => 'Configured' ],
		];
		$manager = new PluginDependencyManager(
			$plugins,
			[
				'pro/pro.php' => 'configured/configured.php',
			]
		);

		self::assertSame(
			[
				'base/base.php',
				'shared/plugin.php',
				'configured/configured.php',
			],
			$manager->get_dependencies( 'pro/pro.php' )
		);
	}

	/**
	 * Test activation plan contains only inactive dependencies.
	 */
	public function test_get_activation_plan_contains_only_inactive_dependencies(): void {
		$plugins = $this->get_plugins();
		$manager = new PluginDependencyManager( $plugins, $this->get_dependencies() );
		$plan    = $manager->get_activation_plan(
			[ 'pro/pro.php' ],
			[ 'foundation/foundation.php' ]
		);

		self::assertSame( [ 'base/base.php' ], array_column( $plan['items'], 'plugin' ) );
		self::assertSame( [ 'Base' ], array_column( $plan['items'], 'name' ) );
	}

	/**
	 * Test dependencies required outside the deactivation tree are blocked.
	 */
	public function test_get_deactivation_plan_blocks_dependencies_used_outside_tree(): void {
		$plugins = $this->get_plugins();
		$manager = new PluginDependencyManager( $plugins, $this->get_dependencies() );
		$plan    = $manager->get_deactivation_plan(
			[ 'pro/pro.php' ],
			array_keys( $plugins )
		);

		self::assertSame( [ 'pro/pro.php' ], $plan['roots'] );
		self::assertSame( 'base/base.php', $plan['items'][0]['plugin'] );
		self::assertTrue( $plan['items'][0]['disabled'] );
		self::assertSame( [ 'Outside Add-on' ], $plan['items'][0]['requiredBy'] );
		self::assertSame( 'foundation/foundation.php', $plan['items'][1]['plugin'] );
		self::assertTrue( $plan['items'][1]['disabled'] );
		self::assertSame( [ 'Outside Add-on' ], $plan['items'][1]['requiredBy'] );
	}

	/**
	 * Test all theme dependency roots are optional plan items.
	 */
	public function test_get_deactivation_plan_can_include_roots_as_optional_items(): void {
		$plugins = $this->get_plugins();
		$manager = new PluginDependencyManager( $plugins, $this->get_dependencies() );
		$plan    = $manager->get_deactivation_plan(
			[ 'base/base.php' ],
			[ 'base/base.php', 'foundation/foundation.php' ],
			true
		);

		self::assertSame( [], $plan['roots'] );
		self::assertSame( [ 'base/base.php', 'foundation/foundation.php' ], array_column( $plan['items'], 'plugin' ) );
		self::assertSame( [ 0, 1 ], array_column( $plan['items'], 'depth' ) );
	}

	/**
	 * Test an external theme can block deactivation of a root plugin.
	 */
	public function test_get_deactivation_plan_reports_blocked_root(): void {
		$plugins = $this->get_plugins();
		$manager = new PluginDependencyManager( $plugins, $this->get_dependencies() );
		$plan    = $manager->get_deactivation_plan(
			[ 'pro/pro.php' ],
			[ 'pro/pro.php', 'base/base.php', 'foundation/foundation.php' ],
			false,
			[ 'Active Theme' => [ 'pro/pro.php' ] ]
		);

		self::assertSame( [ 'Active Theme' ], $plan['rootBlockedBy'] );
	}

	/**
	 * Test unsafe partial selections are removed while the root remains selected.
	 */
	public function test_get_safe_deactivation_plugins_rejects_dependency_with_active_parent(): void {
		$plugins = $this->get_plugins();
		$manager = new PluginDependencyManager( $plugins, $this->get_dependencies() );
		$active  = [ 'pro/pro.php', 'base/base.php', 'foundation/foundation.php' ];
		$plan    = $manager->get_deactivation_plan( [ 'pro/pro.php' ], $active );

		self::assertSame(
			[ 'pro/pro.php' ],
			$manager->get_safe_deactivation_plugins(
				$plan,
				[ 'foundation/foundation.php' ],
				$active
			)
		);

		self::assertSame(
			[ 'pro/pro.php', 'base/base.php', 'foundation/foundation.php' ],
			$manager->get_safe_deactivation_plugins(
				$plan,
				[ 'base/base.php', 'foundation/foundation.php' ],
				$active
			)
		);
	}

	/**
	 * Get test plugins.
	 *
	 * @return array
	 */
	private function get_plugins(): array {
		return [
			'pro/pro.php'                       => [ 'Name' => 'Pro' ],
			'base/base.php'                     => [ 'Name' => 'Base' ],
			'foundation/foundation.php'         => [ 'Name' => 'Foundation' ],
			'outside-add-on/outside-add-on.php' => [ 'Name' => 'Outside Add-on' ],
		];
	}

	/**
	 * Get test additional dependencies.
	 *
	 * @return array
	 */
	private function get_dependencies(): array {
		return [
			'pro/pro.php'                       => 'base/base.php',
			'base/base.php'                     => 'foundation/foundation.php',
			'outside-add-on/outside-add-on.php' => 'base/base.php',
		];
	}
}
