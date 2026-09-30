<?php
/**
 * IntegrationsDependencyTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Unit\Settings;

use HCaptcha\Dependencies\PluginDependencyManager;
use HCaptcha\Settings\Integrations;
use HCaptcha\Tests\Unit\HCaptchaTestCase;
use Mockery;
use ReflectionException;
use tad\FunctionMocker\FunctionMocker;
use WP_Mock;

/**
 * Test integration dependency plan helpers.
 *
 * @group settings
 * @group settings-integrations
 */
class IntegrationsDependencyTest extends HCaptchaTestCase {
	/**
	 * Test command palette exposes only the anti-spam overview control.
	 */
	public function test_command_palette_form_fields(): void {
		$subject = Mockery::mock( Integrations::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$field = [
			'type'  => 'checkbox',
			'label' => 'Coverage',
		];
		$subject->shouldReceive( 'form_fields' )->once()->andReturn(
			[
				'show_antispam_coverage' => $field,
				'other'                  => [ 'type' => 'text' ],
			]
		);

		self::assertSame( [ 'show_antispam_coverage' => $field ], $subject->command_palette_form_fields() );
	}

	/**
	 * Test active plugin discovery from installed plugin files.
	 *
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_get_active_plugin_slugs(): void {
		$subject = Mockery::mock( Integrations::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$this->set_protected_property(
			$subject,
			'plugins',
			[
				'active/active.php'     => [],
				'inactive/inactive.php' => [],
			]
		);
		$main = Mockery::mock();
		$main->shouldReceive( 'is_plugin_active' )->with( 'active/active.php' )->once()->andReturn( true );
		$main->shouldReceive( 'is_plugin_active' )->with( 'inactive/inactive.php' )->once()->andReturn( false );
		WP_Mock::userFunction( 'hcaptcha' )->once()->andReturn( $main );
		$subject->shouldAllowMockingProtectedMethods();

		self::assertSame( [ 'active/active.php' ], $subject->get_active_plugin_slugs() );
	}

	/**
	 * Test activating a plugin with its dependencies.
	 */
	public function test_build_activation_plan_for_plugin(): void {
		$subject = Mockery::mock( Integrations::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$manager = Mockery::mock( PluginDependencyManager::class );
		$subject->shouldReceive( 'get_dependency_manager' )->once()->andReturn( $manager );
		$subject->shouldReceive( 'get_status_plugins' )->with( 'example_status' )->once()->andReturn( [ 'example/plugin.php' ] );
		$subject->shouldReceive( 'filter_activate_plugins' )->with( [ 'example/plugin.php' ] )->once()
			->andReturn( [ 'example/plugin.php' ] );
		$subject->shouldReceive( 'get_active_plugin_slugs' )->once()->andReturn( [] );
		$manager->shouldReceive( 'get_activation_plan' )->with( [ 'example/plugin.php' ], [], false )->once()
			->andReturn( [ 'items' => [] ] );

		self::assertSame( [ 'items' => [] ], $subject->build_activation_plan( 'example_status', 'Example' ) );
	}

	/**
	 * Test a theme activation plan includes its plugin roots.
	 *
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_build_activation_plan_for_theme(): void {
		$subject = Mockery::mock( Integrations::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$this->set_protected_property( $subject, 'entity', 'theme' );
		$manager = Mockery::mock( PluginDependencyManager::class );
		$subject->shouldReceive( 'get_dependency_manager' )->once()->andReturn( $manager );
		$subject->shouldReceive( 'get_active_plugin_slugs' )->once()->andReturn( [] );
		$manager->shouldReceive( 'get_additional_dependencies' )->with( 'Theme' )->once()
			->andReturn( [ 'required/required.php' ] );
		$manager->shouldReceive( 'get_activation_plan' )
			->with( [ 'required/required.php' ], [], true )->once()->andReturn( [ 'items' => [] ] );
		$subject->shouldAllowMockingProtectedMethods();

		self::assertSame( [ 'items' => [] ], $subject->build_activation_plan( 'theme_status', 'Theme' ) );
	}

	/**
	 * Test plugin deactivation plans describe dependent consumers.
	 */
	public function test_build_deactivation_plan_for_plugin(): void {
		$subject = Mockery::mock( Integrations::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$manager = Mockery::mock( PluginDependencyManager::class );
		$subject->shouldReceive( 'get_dependency_manager' )->once()->andReturn( $manager );
		$subject->shouldReceive( 'get_active_plugin_slugs' )->once()->andReturn( [ 'root/root.php' ] );
		$subject->shouldReceive( 'get_current_theme_consumer' )->once()->andReturn( [ 'Theme' => [ 'root/root.php' ] ] );
		$manager->shouldReceive( 'get_deactivation_plan' )
			->with( [ 'root/root.php' ], [ 'root/root.php' ], false, [ 'Theme' => [ 'root/root.php' ] ] )
			->once()->andReturn(
				[
					'items' => [
						[
							'plugin'     => 'root/root.php',
							'requiredBy' => [ 'Theme' ],
							'disabled'   => true,
						],
					],
				]
			);

		$plan = $subject->build_deactivation_plan( [ 'root/root.php' ], 'Root', '' );
		self::assertSame( 'Required by: Theme', $plan['items'][0]['reason'] );
	}

	/**
	 * Test a theme plan marks plugin items unavailable without a plugin capability.
	 *
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_build_deactivation_plan_for_theme_without_plugin_capability(): void {
		$subject = Mockery::mock( Integrations::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$this->set_protected_property( $subject, 'entity', 'theme' );
		$manager = Mockery::mock( PluginDependencyManager::class );
		$subject->shouldReceive( 'get_dependency_manager' )->once()->andReturn( $manager );
		$subject->shouldReceive( 'get_active_plugin_slugs' )->once()->andReturn( [ 'required/required.php' ] );
		$subject->shouldReceive( 'get_theme_consumer' )->with( '', 'Theme' )->once()->andReturn( [] );
		$subject->shouldReceive( 'get_replacement_theme' )->with( 'replacement' )->once()->andReturn( 'replacement' );
		$subject->shouldReceive( 'get_theme_consumer' )->with( 'replacement' )->once()->andReturn( [] );
		$subject->shouldReceive( 'get_plugin_activation_error' )->once()->andReturn( Mockery::mock( 'overload:WP_Error' ) );
		$manager->shouldReceive( 'get_additional_dependencies' )->with( 'Theme' )->once()
			->andReturn( [ 'required/required.php' ] );
		$manager->shouldReceive( 'get_deactivation_plan' )
			->with( [ 'required/required.php' ], [ 'required/required.php' ], true, [] )
			->once()->andReturn(
				[
					'items' => [
						[
							'plugin'     => 'required/required.php',
							'requiredBy' => [],
							'disabled'   => false,
						],
					],
				]
			);
		$subject->shouldAllowMockingProtectedMethods();

		$plan = $subject->build_deactivation_plan( [], 'Theme', 'replacement' );
		self::assertTrue( $plan['items'][0]['disabled'] );
		self::assertSame( 'You are not allowed to deactivate plugins on this site.', $plan['items'][0]['reason'] );
	}

	/**
	 * Test all safe dependencies are selected when requested.
	 */
	public function test_get_deactivation_plugins(): void {
		$subject = Mockery::mock( Integrations::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$manager = Mockery::mock( PluginDependencyManager::class );
		$plan    = [
			'items' => [
				[
					'plugin'   => 'safe/safe.php',
					'disabled' => false,
				],
				[
					'plugin'   => 'blocked/blocked.php',
					'disabled' => true,
				],
			],
		];
		$subject->shouldReceive( 'get_dependency_manager' )->once()->andReturn( $manager );
		$subject->shouldReceive( 'get_active_plugin_slugs' )->once()->andReturn( [ 'safe/safe.php' ] );
		$manager->shouldReceive( 'get_safe_deactivation_plugins' )
			->with( $plan, [ 'safe/safe.php' ], [ 'safe/safe.php' ], [] )->once()
			->andReturn( [ 'safe/safe.php' ] );

		self::assertSame( [ 'safe/safe.php' ], $subject->get_deactivation_plugins( $plan, [], true, [] ) );
	}

	/**
	 * Test dependency blocker feedback lists unique consumers.
	 */
	public function test_send_deactivation_blocked_error(): void {
		$subject = Mockery::mock( Integrations::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		WP_Mock::passthruFunction( 'esc_html' );
		WP_Mock::userFunction( 'wp_send_json_error' )
			->with( 'Cannot deactivate this plugin because it is required by: Theme, Add-on.' )->once();

		$subject->send_deactivation_blocked_error( [ 'Theme', 'Add-on', 'Theme' ] );
	}

	/**
	 * Test a theme's declared plugin dependencies are found by its identity.
	 *
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_get_theme_consumer(): void {
		$theme = Mockery::mock( 'WP_Theme' );
		$theme->shouldReceive( 'get_stylesheet' )->once()->andReturn( 'example' );
		$theme->shouldReceive( 'get_template' )->once()->andReturn( 'example' );
		$theme->shouldReceive( 'get' )->with( 'Name' )->twice()->andReturn( 'Example' );
		$subject = Mockery::mock( Integrations::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$this->set_protected_property( $subject, 'themes', [ 'example' => $theme ] );
		$manager = Mockery::mock( PluginDependencyManager::class );
		$manager->shouldReceive( 'get_additional_dependencies' )->with( 'example' )->once()->andReturn( [] );
		$manager->shouldReceive( 'get_additional_dependencies' )->with( 'Example' )->once()
			->andReturn( [ 'required/required.php' ] );
		$subject->shouldReceive( 'get_dependency_manager' )->once()->andReturn( $manager );
		$subject->shouldAllowMockingProtectedMethods();

		self::assertSame(
			[ 'Example' => [ 'required/required.php' ] ],
			$subject->get_theme_consumer( 'example' )
		);
	}

	/**
	 * Test the active theme consumer helper.
	 */
	public function test_get_current_theme_consumer(): void {
		$subject = Mockery::mock( Integrations::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'get_theme_consumer' )->with( '' )->once()->andReturn( [ 'Theme' => [ 'plugin/plugin.php' ] ] );

		self::assertSame( [ 'Theme' => [ 'plugin/plugin.php' ] ], $subject->get_current_theme_consumer() );
	}

	/**
	 * Test active theme lookup when no cached theme is available.
	 */
	public function test_get_theme_consumer_uses_wordpress_theme(): void {
		$subject = Mockery::mock( Integrations::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$manager = Mockery::mock( PluginDependencyManager::class );
		$subject->shouldReceive( 'get_dependency_manager' )->once()->andReturn( $manager );
		WP_Mock::userFunction( 'wp_get_theme' )->with( null )->once()->andReturn( null );

		self::assertSame( [], $subject->get_theme_consumer( '' ) );
	}

	/**
	 * Test WordPress metadata loading is requested when the API is unavailable.
	 */
	public function test_get_plugin_data_loads_wordpress_api(): void {
		$subject = Mockery::mock( Integrations::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'load_plugin_data_api' )->once();
		$subject->shouldReceive( 'get_plugin_file' )->with( 'example/plugin.php' )->once()->andReturn( 'missing.php' );
		FunctionMocker::replace( 'function_exists', false );
		FunctionMocker::replace( 'file_exists', false );

		self::assertSame( [], $subject->get_plugin_data( 'example/plugin.php' ) );
	}

	/**
	 * Test fallback messages for deactivated plugins without display names.
	 */
	public function test_get_plugins_deactivated_message_without_names(): void {
		$subject = Mockery::mock( Integrations::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$manager = Mockery::mock( PluginDependencyManager::class );
		$manager->shouldReceive( 'get_plugin_name' )->with( 'plugin/plugin.php' )->twice()->andReturn( '' );
		$subject->shouldReceive( 'get_dependency_manager' )->twice()->andReturn( $manager );
		WP_Mock::passthruFunction( '_n' );

		self::assertSame( '', $subject->get_plugins_deactivated_message( [ 'plugin/plugin.php' ] ) );
		self::assertSame( 'Fallback plugin is deactivated.', $subject->get_plugins_deactivated_message( [ 'plugin/plugin.php' ], 'Fallback' ) );
	}
}
