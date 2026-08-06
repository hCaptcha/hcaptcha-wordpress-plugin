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
use WP_Mock;

/**
 * Class CommandPaletteTest.
 *
 * @group admin
 * @group command-palette
 */
class CommandPaletteTest extends HCaptchaTestCase {

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
