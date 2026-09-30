<?php
/**
 * CommandPaletteTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Unit\Admin;

use HCaptcha\Admin\CommandPalette;
use HCaptcha\Settings\PluginSettingsBase;
use HCaptcha\Tests\Unit\HCaptchaTestCase;
use Mockery;
use ReflectionClass;
use ReflectionException;
use stdClass;
use tad\FunctionMocker\FunctionMocker;
use WP_Mock;

/**
 * Class CommandPaletteTest.
 *
 * @group admin
 * @group command-palette
 */
class CommandPaletteTest extends HCaptchaTestCase {
	/**
	 * Test the constructor registers its admin hook.
	 */
	public function test_constructor_registers_hook(): void {
		$subject = $this->create_subject();
		WP_Mock::expectActionAdded( 'admin_enqueue_scripts', [ $subject, 'enqueue_assets' ] );

		( new ReflectionClass( CommandPalette::class ) )->getConstructor()->invoke( $subject );
	}

	/**
	 * Test command creation from field types and options.
	 *
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_get_field_commands(): void {
		$subject  = $this->create_subject();
		$method   = $this->set_method_accessibility( $subject, 'get_field_commands' );
		$settings = Mockery::mock();
		$settings->shouldReceive( 'get_plugin_name' )->andReturn( 'hCaptcha' );
		$main = Mockery::mock();
		$main->shouldReceive( 'settings' )->andReturn( $settings );

		WP_Mock::userFunction( 'hcaptcha' )->andReturn( $main );
		WP_Mock::userFunction( 'wp_strip_all_tags' )->andReturnUsing( 'strip_tags' );
		WP_Mock::userFunction( 'sanitize_key' )->andReturnUsing( 'strtolower' );
		WP_Mock::userFunction( 'esc_url_raw' )->andReturnUsing(
			static function ( $url ) {
				return $url;
			}
		);

		$args = [ 'field', [ 'type' => 'hcaptcha' ], 'https://example.test/options', 'General', 'general' ];
		self::assertSame( [], $method->invokeArgs( $subject, $args ) );

		$args[1] = [ 'type' => 'checkbox' ];
		self::assertSame( [], $method->invokeArgs( $subject, $args ) );

		$args[1] = [
			'type'  => 'text',
			'label' => '<b> </b>',
		];
		self::assertSame( [], $method->invokeArgs( $subject, $args ) );

		$args[1]      = [
			'type'  => 'text',
			'label' => '<b>Site key</b>',
		];
		$text_command = $method->invokeArgs( $subject, $args );
		self::assertSame( 'hCaptcha: Site key', $text_command[0]['label'] );
		self::assertSame( 'https://example.test/options#field', $text_command[0]['url'] );

		$args[1]         = [
			'type'    => 'radio',
			'label'   => 'Mode',
			'options' => [
				'live'  => 'Live',
				'test'  => '<em>Test</em>',
				'empty' => '',
			],
		];
		$option_commands = $method->invokeArgs( $subject, $args );
		self::assertCount( 2, $option_commands );
		self::assertSame( 'hCaptcha: Mode: Live', $option_commands[0]['label'] );
		self::assertSame( 'https://example.test/options#field_2', $option_commands[1]['url'] );
	}

	/**
	 * Test settings tabs are converted into commands.
	 *
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_get_commands(): void {
		$tab = Mockery::mock( PluginSettingsBase::class );
		$tab->shouldReceive( 'command_palette_page_title' )->once()->andReturn( 'General' );
		$tab->shouldReceive( 'tab_name' )->once()->andReturn( 'general' );
		$tab->shouldReceive( 'command_palette_form_fields' )->once()->andReturn(
			[
				'site_key' => [
					'type'  => 'text',
					'label' => 'Site key',
				],
			]
		);

		$settings = Mockery::mock();
		$settings->shouldReceive( 'get_tabs' )->once()->andReturn( [ new stdClass(), $tab ] );
		$settings->shouldReceive( 'tab_url' )->with( get_class( $tab ) )->once()->andReturn( 'https://example.test/options' );
		$settings->shouldReceive( 'get_plugin_name' )->andReturn( 'hCaptcha' );
		$main = Mockery::mock();
		$main->shouldReceive( 'settings' )->andReturn( $settings );

		WP_Mock::userFunction( 'hcaptcha' )->andReturn( $main );
		WP_Mock::userFunction( 'wp_strip_all_tags' )->andReturnUsing( 'strip_tags' );
		WP_Mock::userFunction( 'sanitize_key' )->andReturnUsing( 'strtolower' );
		WP_Mock::userFunction( 'esc_url_raw' )->andReturnUsing(
			static function ( $url ) {
				return $url;
			}
		);
		WP_Mock::userFunction( 'apply_filters' )
			->andReturnUsing(
				static function ( $hook, $commands ) {
					return $commands;
				}
			);

		$method   = $this->set_method_accessibility( $this->create_subject(), 'get_commands' );
		$commands = $method->invoke( $this->create_subject() );

		self::assertCount( 1, $commands );
		self::assertSame( 'hCaptcha: Site key', $commands[0]['label'] );
	}

	/**
	 * Test missing settings on both command lookup paths.
	 *
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_missing_settings(): void {
		$main = Mockery::mock();
		$main->shouldReceive( 'settings' )->twice()->andReturn( null );
		WP_Mock::userFunction( 'hcaptcha' )->twice()->andReturn( $main );

		$subject = $this->create_subject();
		self::assertFalse( $this->set_method_accessibility( $subject, 'is_options_screen' )->invoke( $subject ) );
		self::assertSame( [], $this->set_method_accessibility( $subject, 'get_commands' )->invoke( $subject ) );
	}

	/**
	 * Test command palette asset registration for a settings field.
	 *
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_enqueue_assets_with_commands(): void {
		$tab = Mockery::mock( PluginSettingsBase::class );
		$tab->shouldReceive( 'is_options_screen' )->with( [] )->once()->andReturn( true );
		$tab->shouldReceive( 'command_palette_page_title' )->once()->andReturn( 'General' );
		$tab->shouldReceive( 'tab_name' )->once()->andReturn( 'general' );
		$tab->shouldReceive( 'command_palette_form_fields' )->once()->andReturn(
			[
				'site_key' => [
					'type'  => 'text',
					'label' => 'Site key',
				],
			]
		);
		$settings = Mockery::mock();
		$settings->shouldReceive( 'get_tabs' )->twice()->andReturn( [ $tab ] );
		$settings->shouldReceive( 'tab_url' )->andReturn( 'https://example.test/options' );
		$settings->shouldReceive( 'get_plugin_name' )->andReturn( 'hCaptcha' );
		$main = Mockery::mock();
		$main->shouldReceive( 'settings' )->andReturn( $settings );

		WP_Mock::userFunction( 'hcaptcha' )->andReturn( $main );
		WP_Mock::userFunction( 'current_user_can' )->with( 'manage_options' )->once()->andReturn( true );
		WP_Mock::userFunction( 'wp_script_is' )->with( 'wp-commands', 'registered' )->once()->andReturn( true );
		WP_Mock::userFunction( 'wp_strip_all_tags' )->andReturnUsing( 'strip_tags' );
		WP_Mock::userFunction( 'sanitize_key' )->andReturnUsing( 'strtolower' );
		WP_Mock::userFunction( 'esc_url_raw' )->andReturnUsing(
			static function ( $url ) {
				return $url;
			}
		);
		WP_Mock::userFunction( 'apply_filters' )
			->andReturnUsing(
				static function ( $hook, $commands ) {
					return $commands;
				}
			);
		WP_Mock::userFunction( 'hcap_min_suffix' )->once()->andReturn( '.min' );
		FunctionMocker::replace(
			'constant',
			static function ( $name ) {
				return 'HCAPTCHA_URL' === $name ? 'https://example.test/plugin' : '1.0.0';
			}
		);
		WP_Mock::userFunction( 'wp_enqueue_script' )
			->with(
				'hcaptcha-command-palette',
				'https://example.test/plugin/assets/js/command-palette.min.js',
				[ 'wp-commands', 'wp-data' ],
				'1.0.0',
				true
			)->once();
		WP_Mock::userFunction( 'wp_localize_script' )
			->with( 'hcaptcha-command-palette', 'HCaptchaCommandPaletteObject', Mockery::type( 'array' ) )->once();

		$this->create_subject()->enqueue_assets();
	}

	/**
	 * Test asset enqueueing stops when no fields yield commands.
	 *
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_enqueue_assets_without_commands(): void {
		$tab = Mockery::mock( PluginSettingsBase::class );
		$tab->shouldReceive( 'is_options_screen' )->with( [] )->once()->andReturn( true );
		$tab->shouldReceive( 'command_palette_page_title' )->once()->andReturn( 'General' );
		$tab->shouldReceive( 'tab_name' )->once()->andReturn( 'general' );
		$tab->shouldReceive( 'command_palette_form_fields' )->once()->andReturn( [] );
		$settings = Mockery::mock();
		$settings->shouldReceive( 'get_tabs' )->twice()->andReturn( [ $tab ] );
		$settings->shouldReceive( 'tab_url' )->once()->andReturn( 'https://example.test/options' );
		$main = Mockery::mock();
		$main->shouldReceive( 'settings' )->andReturn( $settings );

		WP_Mock::userFunction( 'hcaptcha' )->andReturn( $main );
		WP_Mock::userFunction( 'current_user_can' )->with( 'manage_options' )->once()->andReturn( true );
		WP_Mock::userFunction( 'wp_script_is' )->with( 'wp-commands', 'registered' )->once()->andReturn( true );
		WP_Mock::userFunction( 'apply_filters' )->andReturn( [] );
		WP_Mock::userFunction( 'wp_enqueue_script' )->never();

		$this->create_subject()->enqueue_assets();
	}

	/**
	 * Create a subject without constructor side effects.
	 *
	 * @return CommandPalette
	 * @throws ReflectionException Reflection exception.
	 */
	private function create_subject(): CommandPalette {
		return ( new ReflectionClass( CommandPalette::class ) )->newInstanceWithoutConstructor();
	}

	/**
	 * Test enqueue_assets() when not on an hCaptcha options screen.
	 *
	 * @return void
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_enqueue_assets_when_not_options_screen(): void {
		$tab = Mockery::mock( PluginSettingsBase::class );
		$tab->shouldReceive( 'is_options_screen' )->with( [] )->once()->andReturn( false );

		$settings = Mockery::mock();
		$settings->shouldReceive( 'get_tabs' )->with()->once()->andReturn( [ $tab ] );

		$hcaptcha = Mockery::mock();
		$hcaptcha->shouldReceive( 'settings' )->with()->once()->andReturn( $settings );

		WP_Mock::userFunction( 'hcaptcha' )->with()->once()->andReturn( $hcaptcha );
		WP_Mock::userFunction( 'current_user_can' )->never();
		WP_Mock::userFunction( 'wp_script_is' )->never();
		WP_Mock::userFunction( 'wp_enqueue_script' )->never();
		WP_Mock::userFunction( 'wp_localize_script' )->never();

		$this->create_subject()->enqueue_assets();
	}

	/**
	 * Test is_options_screen().
	 *
	 * @return void
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_is_options_screen(): void {
		$inactive_tab = Mockery::mock( PluginSettingsBase::class );
		$inactive_tab->shouldReceive( 'is_options_screen' )->with( [] )->once()->andReturn( false );

		$active_tab = Mockery::mock( PluginSettingsBase::class );
		$active_tab->shouldReceive( 'is_options_screen' )->with( [] )->once()->andReturn( true );

		$settings = Mockery::mock();
		$settings->shouldReceive( 'get_tabs' )->with()->once()->andReturn( [ $inactive_tab, $active_tab ] );

		$hcaptcha = Mockery::mock();
		$hcaptcha->shouldReceive( 'settings' )->with()->once()->andReturn( $settings );

		WP_Mock::userFunction( 'hcaptcha' )->with()->once()->andReturn( $hcaptcha );

		$subject = $this->create_subject();
		$method  = $this->set_method_accessibility( $subject, 'is_options_screen' );

		self::assertTrue( $method->invoke( $subject ) );
	}
}
