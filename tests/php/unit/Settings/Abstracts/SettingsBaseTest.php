<?php
/**
 * SettingsBaseTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedMethodInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

// phpcs:disable WordPress.WP.AlternativeFunctions.json_encode_json_encode

namespace HCaptcha\Tests\Unit\Settings\Abstracts;

use KAGG\Settings\Abstracts\SettingsBase;
use HCaptcha\Tests\Unit\HCaptchaTestCase;
use Mockery;
use PHPUnit\Runner\Version;
use ReflectionClass;
use ReflectionException;
use tad\FunctionMocker\FunctionMocker;
use WP_Mock;

/**
 * Class SettingsBaseTest
 *
 * @group settings
 * @group settings-base
 */
class SettingsBaseTest extends HCaptchaTestCase {

	/**
	 * Test constructor.
	 *
	 * @param bool   $is_tab          Whether it is a tab.
	 * @param string $admin_mode      Admin mode.
	 * @param bool   $is_network_wide Whether it is network wide.
	 *
	 * @dataProvider dp_test_constructor
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_constructor( bool $is_tab, string $admin_mode, bool $is_network_wide ): void {
		$tabs = [ 'some class instance 1', 'some class instance 2' ];
		$args = [ 'some' => 'args' ];

		$classname = SettingsBase::class;

		$subject = Mockery::mock( $classname )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'process_args' )->with( $args );
		$subject->shouldReceive( 'is_tab' )->andReturn( $is_tab );
		$subject->shouldReceive( 'is_network_wide' )->andReturn( $is_network_wide );

		$fields = [
			'text'     => [ $subject, 'print_text_field' ],
			'password' => [ $subject, 'print_text_field' ],
			'hidden'   => [ $subject, 'print_text_field' ],
			'number'   => [ $subject, 'print_number_field' ],
			'textarea' => [ $subject, 'print_textarea_field' ],
			'checkbox' => [ $subject, 'print_checkbox_field' ],
			'radio'    => [ $subject, 'print_radio_field' ],
			'select'   => [ $subject, 'print_select_field' ],
			'multiple' => [ $subject, 'print_multiple_select_field' ],
			'file'     => [ $subject, 'print_file_field' ],
			'table'    => [ $subject, 'print_table_field' ],
			'button'   => [ $subject, 'print_button_field' ],
		];

		if ( SettingsBase::MODE_PAGES === $admin_mode || ! $is_tab ) {
			WP_Mock::expectActionAdded( 'current_screen', [ $subject, 'setup_tabs_section' ], 9 );

			if ( $is_network_wide ) {
				WP_Mock::expectActionAdded( 'network_admin_menu', [ $subject, 'add_settings_page' ] );
			} else {
				WP_Mock::expectActionAdded( 'admin_menu', [ $subject, 'add_settings_page' ] );
			}
		} else {
			WP_Mock::expectActionNotAdded( 'current_screen', [ $subject, 'setup_tabs_section' ] );
			WP_Mock::expectActionNotAdded( 'admin_menu', [ $subject, 'add_settings_page' ] );
		}

		$subject->shouldReceive( 'init' )->once()->with();

		$this->set_protected_property( $subject, 'admin_mode', $admin_mode );

		$constructor = ( new ReflectionClass( $classname ) )->getConstructor();

		self::assertNotNull( $constructor );

		$constructor->invoke( $subject, $tabs, $args );

		self::assertSame( $tabs, $this->get_protected_property( $subject, 'tabs' ) );
		self::assertSame( $fields, $this->get_protected_property( $subject, 'fields' ) );
	}

	/**
	 * Data provider for test_constructor().
	 *
	 * @return array
	 */
	public function dp_test_constructor(): array {
		return [
			'Not a tab, pages mode, not network wide' => [ false, SettingsBase::MODE_PAGES, false ],
			'Not a tab, pages mode, network wide'     => [ false, SettingsBase::MODE_PAGES, true ],
			'Not a tab, tabs mode, not network wide'  => [ false, SettingsBase::MODE_TABS, false ],
			'Not a tab, tabs mode, network wide'      => [ false, SettingsBase::MODE_TABS, true ],
			'Tab, pages mode, not network wide'       => [ true, SettingsBase::MODE_PAGES, false ],
			'Tab, pages mode, network wide'           => [ true, SettingsBase::MODE_PAGES, true ],
			'Tab, tabs mode, not network wide'        => [ true, SettingsBase::MODE_TABS, false ],
			'Tab, tabs mode, network wide'            => [ true, SettingsBase::MODE_TABS, true ],
			'Not a tab, some mode, not network wide'  => [ false, 'some', false ],
			'Not a tab, some mode, network wide'      => [ false, 'some', true ],
			'Tab, some mode, not network wide'        => [ true, 'some', false ],
			'Tab, some mode, network wide'            => [ true, 'some', true ],
		];
	}

	/**
	 * Test init().
	 *
	 * @param bool $script_debug      Whether script debug is active.
	 * @param bool $is_main_menu_page Whether it is the main menu page.
	 * @param bool $is_tab_active     Whether the tab is active.
	 *
	 * @dataProvider dp_test_init
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_init( bool $script_debug, bool $is_main_menu_page, bool $is_tab_active ): void {
		$subject = Mockery::mock( SettingsBase::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'form_fields' )->once();
		$subject->shouldReceive( 'init_settings' )->once();
		$subject->shouldReceive( 'is_main_menu_page' )->andReturn( $is_main_menu_page );
		$subject->shouldReceive( 'is_tab_active' )->with( $subject )->andReturn( $is_tab_active );

		if ( $is_main_menu_page || $is_tab_active ) {
			$subject->shouldReceive( 'init_hooks' )->once();
		}

		FunctionMocker::replace(
			'defined',
			static function ( $constant_name ) use ( $script_debug ) {
				if ( 'SCRIPT_DEBUG' === $constant_name ) {
					return $script_debug;
				}

				return false;
			}
		);

		FunctionMocker::replace(
			'constant',
			static function ( $name ) use ( $script_debug ) {
				if ( 'SCRIPT_DEBUG' === $name ) {
					return $script_debug;
				}

				return false;
			}
		);

		WP_Mock::userFunction( 'is_admin' )->once()->andReturn( true );

		$subject->init();

		$min_suffix = $script_debug ? '' : '.min';
		self::assertSame( $min_suffix, $this->get_protected_property( $subject, 'min_suffix' ) );
	}

	/**
	 * Test init() when not in admin.
	 */
	public function test_init_when_not_in_admin(): void {
		$subject = Mockery::mock( SettingsBase::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'form_fields' )->once();
		$subject->shouldReceive( 'init_settings' )->once();
		$subject->shouldReceive( 'is_main_menu_page' )->never();
		$subject->shouldReceive( 'is_tab_active' )->never();
		$subject->shouldReceive( 'init_hooks' )->never();

		WP_Mock::userFunction( 'is_admin' )->once()->andReturn( false );

		$subject->init();
	}

	/**
	 * Data provider for test_init().
	 *
	 * @return array
	 */
	public function dp_test_init(): array {
		return [
			'Script_debug, main, tab'            => [ true, true, true ],
			'Script_debug, main, not tab'        => [ true, true, false ],
			'Script_debug, not main, tab'        => [ true, false, true ],
			'Script_debug, not main, not tab'    => [ true, false, false ],
			'No script debug, main, tab'         => [ false, true, true ],
			'No script debug, main, not tab'     => [ false, true, false ],
			'No script debug, not main, tab'     => [ false, false, true ],
			'No script debug, not main, not tab' => [ false, false, false ],
		];
	}

	/**
	 * Test init_hooks().
	 *
	 * @param bool $is_active         Whether it is an active tab.
	 * @param bool $is_main_menu_page Whether it is the main menu page.
	 *
	 * @dataProvider dp_test_init_hooks
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_init_hooks( bool $is_active, bool $is_main_menu_page ): void {
		$plugin_base_name = 'hcaptcha-wordpress-plugin/hcaptcha.php';
		$option_name      = 'hcaptcha_settings';

		$subject = Mockery::mock( SettingsBase::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'is_main_menu_page' )->once()->with()->andReturn( $is_main_menu_page );
		$subject->shouldReceive( 'is_tab_active' )->once()->with( $subject )->andReturn( $is_active );
		$subject->shouldReceive( 'plugin_basename' )->andReturn( $plugin_base_name );
		$subject->shouldReceive( 'option_name' )->andReturn( $option_name );

		WP_Mock::expectActionAdded( 'admin_enqueue_scripts', [ $subject, 'base_admin_enqueue_scripts' ] );

		if ( $is_main_menu_page ) {
			WP_Mock::expectActionAdded( 'plugins_loaded', [ $subject, 'load_plugin_textdomain' ] );
			WP_Mock::expectFilterAdded(
				'plugin_action_links_' . $plugin_base_name,
				[ $subject, 'add_settings_link' ]
			);
			WP_Mock::expectFilterAdded(
				'network_admin_plugin_action_links_' . $plugin_base_name,
				[ $subject, 'add_settings_link' ]
			);
		}

		if ( $is_active ) {
			WP_Mock::expectFilterAdded(
				'pre_update_option_' . $option_name,
				[ $subject, 'pre_update_option_filter' ],
				10,
				2
			);
			WP_Mock::expectFilterAdded(
				'pre_update_site_option_option_' . $option_name,
				[ $subject, 'pre_update_option_filter' ],
				10,
				2
			);

			WP_Mock::expectActionAdded( 'current_screen', [ $subject, 'setup_fields' ] );
			WP_Mock::expectActionAdded( 'current_screen', [ $subject, 'setup_sections' ], 11 );
		}

		$method = 'init_hooks';

		$this->set_method_accessibility( $subject, $method );
		$subject->$method();
	}

	/**
	 * Data provider for test_init_hooks().
	 *
	 * @return array
	 */
	public function dp_test_init_hooks(): array {
		return [
			'Not active tab, not main menu page' => [ false, false ],
			'Not active tab, main menu page'     => [ false, true ],
			'Active tab, not main menu page'     => [ true, false ],
			'Active tab, main menu page'         => [ true, true ],
		];
	}

	/**
	 * Test init_form_fields().
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_init_form_fields(): void {
		$subject = Mockery::mock( SettingsBase::class )->makePartial();
		$method  = 'init_form_fields';

		$this->set_protected_property( $subject, 'form_fields', null );

		$subject->$method();

		self::assertSame( [], $this->get_protected_property( $subject, 'form_fields' ) );
	}

	/**
	 * Test process_args().
	 *
	 * @param array $args     Arguments.
	 * @param array $expected Expected.
	 *
	 * @return void
	 * @dataProvider dp_test_process_args
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_process_args( array $args, array $expected ): void {
		$subject = Mockery::mock( SettingsBase::class )->makePartial();
		$method  = 'process_args';

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'get_menu_position' )->andReturn( SettingsBase::MODE_PAGES );
		$subject->shouldReceive( 'is_network_wide' )->andReturn( false );

		WP_Mock::userFunction( 'wp_parse_args' )->andReturnUsing(
			function ( $args, $defaults ) {
				return array_merge( $defaults, $args );
			}
		);
		WP_Mock::userFunction( 'is_multisite' )->andReturn( true );

		$subject->$method( $args );

		$actual = [
			'mode'     => $this->get_protected_property( $subject, 'admin_mode' ),
			'parent'   => $this->get_protected_property( $subject, 'parent_slug' ),
			'position' => $this->get_protected_property( $subject, 'position' ),
		];

		self::assertSame( $expected, $actual );
	}

	/**
	 * Data provider for test_process_args().
	 *
	 * @return array
	 */
	public function dp_test_process_args(): array {
		return [
			'Tabs mode'     => [
				[ 'mode' => SettingsBase::MODE_TABS ],
				[
					'mode'     => SettingsBase::MODE_TABS,
					'parent'   => 'options-general.php',
					'position' => 58.990225,
				],
			],
			'Pages mode'    => [
				[ 'mode' => SettingsBase::MODE_PAGES ],
				[
					'mode'     => SettingsBase::MODE_PAGES,
					'parent'   => '',
					'position' => 58.990225,
				],
			],
			'No mode'       => [
				[],
				[
					'mode'     => SettingsBase::MODE_PAGES,
					'parent'   => '',
					'position' => 58.990225,
				],
			],
			'Wrong mode'    => [
				[ 'mode' => 'some' ],
				[
					'mode'     => SettingsBase::MODE_PAGES,
					'parent'   => '',
					'position' => 58.990225,
				],
			],
			'Some parent'   => [
				[ 'parent' => 'some.php' ],
				[
					'mode'     => SettingsBase::MODE_PAGES,
					'parent'   => 'some.php',
					'position' => 58.990225,
				],
			],
			'Some position' => [
				[ 'position' => 99 ],
				[
					'mode'     => SettingsBase::MODE_PAGES,
					'parent'   => '',
					'position' => 99.0,
				],
			],
		];
	}

	/**
	 * Test process_args() when network_wide.
	 *
	 * @param array $args     Arguments.
	 * @param array $expected Expected.
	 *
	 * @return void
	 * @dataProvider dp_test_process_args_when_network_wide
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_process_args_when_network_wide( array $args, array $expected ): void {
		$subject = Mockery::mock( SettingsBase::class )->makePartial();
		$method  = 'process_args';

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'get_menu_position' )->andReturn( SettingsBase::MODE_PAGES );
		$subject->shouldReceive( 'is_network_wide' )->andReturn( true );

		WP_Mock::userFunction( 'wp_parse_args' )->andReturnUsing(
			function ( $args, $defaults ) {
				return array_merge( $defaults, $args );
			}
		);
		WP_Mock::userFunction( 'is_multisite' )->andReturn( true );

		$subject->$method( $args );

		$actual = [
			'mode'     => $this->get_protected_property( $subject, 'admin_mode' ),
			'parent'   => $this->get_protected_property( $subject, 'parent_slug' ),
			'position' => $this->get_protected_property( $subject, 'position' ),
		];

		self::assertSame( $expected, $actual );
	}

	/**
	 * Data provider for test_process_args_when_network_wide().
	 *
	 * @return array
	 */
	public function dp_test_process_args_when_network_wide(): array {
		return [
			'Tabs mode'     => [
				[ 'mode' => SettingsBase::MODE_TABS ],
				[
					'mode'     => SettingsBase::MODE_TABS,
					'parent'   => 'settings.php',
					'position' => 24.990225,
				],
			],
			'Pages mode'    => [
				[ 'mode' => SettingsBase::MODE_PAGES ],
				[
					'mode'     => SettingsBase::MODE_PAGES,
					'parent'   => '',
					'position' => 24.990225,
				],
			],
			'No mode'       => [
				[],
				[
					'mode'     => SettingsBase::MODE_PAGES,
					'parent'   => '',
					'position' => 24.990225,
				],
			],
			'Wrong mode'    => [
				[ 'mode' => 'some' ],
				[
					'mode'     => SettingsBase::MODE_PAGES,
					'parent'   => '',
					'position' => 24.990225,
				],
			],
			'Some parent'   => [
				[ 'parent' => 'some.php' ],
				[
					'mode'     => SettingsBase::MODE_PAGES,
					'parent'   => 'some.php',
					'position' => 24.990225,
				],
			],
			'Some position' => [
				[ 'position' => 99 ],
				[
					'mode'     => SettingsBase::MODE_PAGES,
					'parent'   => '',
					'position' => 99.0,
				],
			],
		];
	}

	/**
	 * Test is_main_menu_page().
	 *
	 * @param array|null $tabs     Tabs.
	 * @param bool       $expected Expected.
	 *
	 * @dataProvider dp_test_is_main_menu_page
	 * @throws ReflectionException ReflectionException.
	 * @noinspection PhpMissingParamTypeInspection PhpMissingParamTypeInspection.
	 */
	public function test_is_main_menu_page( $tabs, bool $expected ): void {
		$subject = Mockery::mock( SettingsBase::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$method = 'is_main_menu_page';

		$this->set_protected_property( $subject, 'tabs', $tabs );
		$this->set_method_accessibility( $subject, $method );
		self::assertSame( $expected, $subject->$method() );
	}

	/**
	 * Data provider for test_is_main_menu_page().
	 *
	 * @return array
	 */
	public function dp_test_is_main_menu_page(): array {
		return [
			'Null tabs'  => [ null, false ],
			'Empty tabs' => [ [], true ],
			'Some tabs'  => [ [ 'some_tab_class_instance' ], true ],
		];
	}

	/**
	 * Test tab_name().
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_tab_name(): void {
		$class_name = 'SomeClassName';

		$subject = Mockery::mock( SettingsBase::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'get_class_name' )->with()->once()->andReturn( $class_name );
		$method = 'tab_name';

		$this->set_method_accessibility( $subject, $method );

		self::assertSame( $class_name, $subject->$method() );
	}

	/**
	 * Test get_class_name().
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_get_class_name(): void {
		$subject = Mockery::mock( SettingsBase::class )->makePartial();

		$method = 'get_class_name';
		$this->set_method_accessibility( $subject, $method );

		if (
			class_exists( Version::class ) &&
			version_compare( substr( Version::id(), 0, 1 ), '7', '>=' )
		) {
			self::assertStringContainsString(
				'KAGG_Settings_Abstracts_SettingsBase',
				$subject->$method()
			);
		} else {
			self::assertContains(
				'KAGG_Settings_Abstracts_SettingsBase',
				$subject->$method()
			);
		}
	}

	/**
	 * Test is_tab().
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_is_tab(): void {
		$subject = Mockery::mock( SettingsBase::class )->makePartial();

		$method = 'is_tab';
		$this->set_method_accessibility( $subject, $method );
		self::assertTrue( $subject->$method() );

		$this->set_protected_property( $subject, 'tabs', [ 'some_array' ] );
		self::assertFalse( $subject->$method() );
	}

	/**
	 * Test add_settings_link().
	 *
	 * @param string $parent_slug Parent slug.
	 *
	 * @dataProvider dp_test_add_settings_link
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_add_settings_link( string $parent_slug ): void {
		$option_page         = 'hcaptcha';
		$settings_link_label = 'hCaptcha Settings';
		$settings_link_text  = 'Settings';

		$subject = Mockery::mock( SettingsBase::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'option_page' )->andReturn( $option_page );
		$subject->shouldReceive( 'settings_link_label' )->andReturn( $settings_link_label );
		$subject->shouldReceive( 'settings_link_text' )->andReturn( $settings_link_text );

		$this->set_protected_property( $subject, 'parent_slug', $parent_slug );

		WP_Mock::passthruFunction( 'admin_url' );

		$expected = [
			'settings' =>
				'<a href="' . $parent_slug . '?page=' . $option_page .
				'" aria-label="' . $settings_link_label . '">' . $settings_link_text . '</a>',
		];

		self::assertSame( $expected, $subject->add_settings_link( [] ) );
	}

	/**
	 * Data provider for test_add_settings_link().
	 *
	 * @return array
	 */
	public function dp_test_add_settings_link(): array {
		return [
			'No parent slug' => [ '' ],
			'Parent slug'    => [ 'options-general.php' ],
		];
	}

	/**
	 * Test init_settings().
	 *
	 * @param mixed $settings     Settings.
	 * @param bool  $network_wide Settings.
	 *
	 * @dataProvider dp_test_init_settings
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_init_settings( $settings, bool $network_wide ): void {
		$option_name          = 'hcaptcha_settings';
		$network_wide_setting = $network_wide ? [ 'on' ] : [];

		$subject = Mockery::mock( SettingsBase::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'is_network_wide' )->andReturn( $network_wide );
		$subject->shouldReceive( 'get_network_wide' )->andReturn( $network_wide_setting );
		$subject->shouldReceive( 'option_name' )->andReturn( $option_name );
		$method = 'init_settings';
		$this->set_method_accessibility( $subject, $method );

		if ( $settings ) {
			$settings['_network_wide'] = $network_wide;
		}

		if ( empty( $network_wide ) ) {
			WP_Mock::userFunction( 'get_option' )->with( $option_name, null )->once()->andReturn( $settings );
		} else {
			WP_Mock::userFunction( 'get_site_option' )->with( $option_name, null )->once()->andReturn( $settings );
		}

		$form_fields = $this->get_test_form_fields();
		$subject->shouldReceive( 'form_fields' )->andReturn( $form_fields );

		$form_fields_pluck = $this->wp_list_pluck( $form_fields, 'default' );

		WP_Mock::userFunction( 'wp_list_pluck' )->with( $form_fields, 'default' )->once()
			->andReturn( $form_fields_pluck );

		$this->set_protected_property( $subject, 'settings', null );
		$subject->$method();

		$expected = array_merge(
			array_fill_keys( array_keys( $form_fields ), '' ),
			$form_fields_pluck
		);

		if ( is_array( $settings ) ) {
			$expected = array_merge( $form_fields_pluck, $settings );
		}

		self::assertSame( $expected, $this->get_protected_property( $subject, 'settings' ) );
	}

	/**
	 * Data provider for test_init_settings().
	 *
	 * @return array
	 */
	public function dp_test_init_settings(): array {
		return [
			'No settings in option, no network-wide'   => [ false, false ],
			'No settings in option, network-wide'      => [ false, true ],
			'Some settings in option, no network-wide' => [ $this->get_test_settings(), false ],
			'Some settings in option, network-wide'    => [ $this->get_test_settings(), true ],
		];
	}

	/**
	 * Test form_fields().
	 *
	 * @param mixed $form_fields Form fields.
	 * @param array $expected    Expected result.
	 *
	 * @dataProvider dp_test_form_fields
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_form_fields( $form_fields, array $expected ): void {
		$subject = Mockery::mock( SettingsBase::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$method = 'form_fields';
		$this->set_method_accessibility( $subject, $method );
		$this->set_protected_property( $subject, 'form_fields', $form_fields );

		if ( null === $form_fields ) {
			$subject->shouldReceive( 'init_form_fields' )->andReturnUsing(
				function () use ( $subject ) {
					$this->set_protected_property( $subject, 'form_fields', $this->get_test_form_fields() );
				}
			)->once();
		}

		WP_Mock::userFunction( 'wp_parse_args' )->andReturnUsing(
			static function ( $args, $defaults ) {
				return array_merge( $defaults, $args );
			}
		);

		self::assertSame( $expected, $subject->$method() );
	}

	/**
	 * Data provider for test_form_fields().
	 *
	 * @return array
	 */
	public function dp_test_form_fields(): array {
		return [
			[ null, $this->get_test_form_fields() ],
			[ [], [] ],
			[ $this->get_test_form_fields(), $this->get_test_form_fields() ],
		];
	}

	/**
	 * Test add_settings_page().
	 *
	 * @param bool $is_main_menu_page Whether it is the main menu page.
	 *
	 * @dataProvider dp_test_add_settings_page
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_add_settings_page( bool $is_main_menu_page ): void {
		$page_title     = 'General';
		$tab_page_title = 'Integrations';
		$menu_title     = 'hCaptcha';
		$capability     = 'manage_options';
		$slug           = 'hcaptcha';
		$tab_slug       = 'hcaptcha-integrations';
		$icon_url       = HCAPTCHA_TEST_URL . '/assets/images/hcaptcha-icon.svg';
		$position       = 99;

		$subject = Mockery::mock( SettingsBase::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'is_main_menu_page' )->andReturn( $is_main_menu_page );
		$subject->shouldReceive( 'page_title' )->andReturn( $page_title );
		$subject->shouldReceive( 'menu_title' )->andReturn( $menu_title );
		$subject->shouldReceive( 'option_page' )->andReturn( $slug );
		$subject->shouldReceive( 'icon_url' )->andReturn( $icon_url );

		$this->set_protected_property( $subject, 'position', $position );

		$callback = [ $subject, 'settings_base_page' ];

		if ( $is_main_menu_page ) {
			$tab = Mockery::mock( SettingsBase::class )->makePartial();

			$tab->shouldAllowMockingProtectedMethods();
			$tab->shouldReceive( 'page_title' )->andReturn( $tab_page_title );
			$tab->shouldReceive( 'option_page' )->andReturn( $tab_slug );

			$tab_callback = [ $tab, 'settings_base_page' ];

			$this->set_protected_property( $subject, 'tabs', [ $tab ] );

			WP_Mock::userFunction( 'add_menu_page' )
				->with( $page_title, $menu_title, $capability, $slug, $callback, $icon_url, $position + 1e-6 );
			WP_Mock::userFunction( 'add_submenu_page' )
				->with( $slug, $page_title, $page_title, $capability, $slug, $callback );
			WP_Mock::userFunction( 'add_submenu_page' )
				->with( $slug, $tab_page_title, $tab_page_title, $capability, $tab_slug, $tab_callback );
		}

		$subject->add_settings_page();
	}

	/**
	 * Data provider for test_add_settings_page().
	 *
	 * @return array
	 */
	public function dp_test_add_settings_page(): array {
		return [
			'Main menu page' => [ true ],
			'Submenu page'   => [ false ],
		];
	}

	/**
	 * Test add_settings_page() with the parent slug.
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_add_settings_page_with_parent_slug(): void {
		$parent_slug = 'options-general.php';
		$page_title  = 'General';
		$menu_title  = 'hCaptcha';
		$capability  = 'manage_options';
		$slug        = 'hcaptcha';

		$subject = Mockery::mock( SettingsBase::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'get_active_tab' )->andReturn( $subject );
		$subject->shouldReceive( 'page_title' )->andReturn( $page_title );
		$subject->shouldReceive( 'menu_title' )->andReturn( $menu_title );
		$subject->shouldReceive( 'option_page' )->andReturn( $slug );
		$this->set_protected_property( $subject, 'parent_slug', $parent_slug );

		$callback = [ $subject, 'settings_base_page' ];

		WP_Mock::userFunction( 'add_submenu_page' )
			->with( $parent_slug, $page_title, $menu_title, $capability, $slug, $callback );

		$subject->add_settings_page();
	}

	/**
	 * Test settings_base_page().
	 */
	public function test_settings_base_page(): void {
		$page = Mockery::mock( SettingsBase::class );
		$page->shouldReceive( 'settings_page' )->with()->once();

		$subject = Mockery::mock( SettingsBase::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'get_active_tab' )->once()->andReturn( $page );

		$expected = '<div class="wrap"></div>';

		ob_start();
		$subject->settings_base_page();
		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test admin_enqueue_scripts().
	 *
	 * @return void
	 */
	public function test_admin_enqueue_scripts(): void {
		$subject = Mockery::mock( SettingsBase::class )->makePartial();
		$method  = 'admin_enqueue_scripts';

		$subject->$method();
	}

	/**
	 * Test admin_enqueue_base_scripts().
	 */
	public function test_base_admin_enqueue_scripts(): void {
		$plugin_url     = 'http://test.test/wp-content/plugins/hcaptcha-for-forms-and-more';
		$plugin_version = '1.0.0';

		$page = Mockery::mock( SettingsBase::class );
		$page->shouldReceive( 'admin_enqueue_scripts' )->with()->once();

		$subject = Mockery::mock( SettingsBase::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'get_active_tab' )->once()->andReturn( $page );
		$subject->shouldReceive( 'plugin_url' )->times( 3 )->andReturn( $plugin_url );
		$subject->shouldReceive( 'plugin_version' )->times( 3 )->andReturn( $plugin_version );
		$subject->shouldReceive( 'is_options_screen' )->andReturn( true );

		WP_Mock::userFunction( 'wp_enqueue_style' )
			->with(
				SettingsBase::PREFIX . '-settings-admin',
				$plugin_url . '/assets/css/settings-admin.css',
				[],
				$plugin_version
			)
			->once();
		WP_Mock::userFunction( 'wp_enqueue_script' )
			->with(
				SettingsBase::PREFIX . '-' . SettingsBase::HANDLE,
				$plugin_url . '/assets/js/settings-base.js',
				[],
				$plugin_version,
				true
			)
			->once();
		WP_Mock::userFunction( 'wp_enqueue_style' )
			->with(
				SettingsBase::PREFIX . '-' . SettingsBase::HANDLE,
				$plugin_url . '/assets/css/settings-base.css',
				[],
				$plugin_version
			)
			->once();

		$subject->base_admin_enqueue_scripts();
	}

	/**
	 * Test admin_enqueue_base_scripts() when not options screen.
	 */
	public function test_base_admin_enqueue_scripts_when_not_options_screen(): void {
		$plugin_url     = 'http://test.test/wp-content/plugins/hcaptcha-for-forms-and-more';
		$plugin_version = '1.0.0';

		$subject = Mockery::mock( SettingsBase::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'plugin_url' )->once()->andReturn( $plugin_url );
		$subject->shouldReceive( 'plugin_version' )->once()->andReturn( $plugin_version );
		$subject->shouldReceive( 'is_options_screen' )->andReturn( false );

		WP_Mock::userFunction( 'wp_enqueue_style' )
			->with(
				SettingsBase::PREFIX . '-settings-admin',
				$plugin_url . '/assets/css/settings-admin.css',
				[],
				$plugin_version
			)
			->once();

		$subject->base_admin_enqueue_scripts();
	}

	/**
	 * Test base_admin_page_access_denied().
	 *
	 * @return void
	 */
	public function test_base_admin_page_access_denied(): void {
		$is_network_wide = false;
		$option_page     = SettingsBase::PREFIX . '-integrations';

		$subject = Mockery::mock( SettingsBase::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'option_page' )->andReturn( $option_page );
		$subject->shouldReceive( 'is_network_wide' )
			->with()
			->andReturnUsing(
				static function () use ( &$is_network_wide ) {
					return $is_network_wide;
				}
			);
		WP_Mock::passthruFunction( 'wp_unslash' );
		WP_Mock::passthruFunction( 'sanitize_text_field' );

		// No page in $_GET.
		$subject->base_admin_page_access_denied();

		// Some page in $_GET. Not network-wide.
		$_GET['page'] = 'some';
		$subject->base_admin_page_access_denied();

		// The hCaptcha option page in $_GET.
		$_GET['page'] = $option_page;
		$url          = 'admin.php?page=' . $option_page;
		$referer      = $url;
		WP_Mock::userFunction( 'is_multisite' )->with()->andReturn( true );
		WP_Mock::passthruFunction( 'admin_url' );
		WP_Mock::userFunction( 'wp_get_raw_referer' )
			->with()
			->andReturnUsing(
				static function () use ( &$referer ) {
					return $referer;
				}
			);
		$subject->base_admin_page_access_denied();

		// Network-wide.
		$is_network_wide = true;
		WP_Mock::passthruFunction( 'network_admin_url' );
		$subject->base_admin_page_access_denied();

		// Different referer. Do redirect.
		$referer = 'some';
		WP_Mock::userFunction( 'wp_safe_redirect' )->with( $url )->once();
		$subject->shouldReceive( 'exit' )->once();
		$subject->base_admin_page_access_denied();
	}

	/**
	 * Test setup_sections().
	 *
	 * @param array $tabs Tabs.
	 *
	 * @dataProvider dp_test_setup_sections
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_setup_sections( array $tabs ): void {
		$tab_option_page = 'hcaptcha';

		$tab = Mockery::mock( SettingsBase::class )->makePartial();
		$tab->shouldAllowMockingProtectedMethods();
		$tab->shouldReceive( 'option_page' )->andReturn( $tab_option_page );

		$subject = Mockery::mock( SettingsBase::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'is_options_screen' )->andReturn( true );
		$subject->shouldReceive( 'get_active_tab' )->once()->andReturn( $tab );

		$form_fields = $this->get_test_form_fields();

		$form_fields['wp_status']['title'] = 'Some Section Title';

		$this->set_protected_property( $subject, 'form_fields', $form_fields );
		$this->set_protected_property( $subject, 'tabs', $tabs );

		foreach ( $form_fields as $form_field ) {
			$title = $form_field['title'] ?? '';

			WP_Mock::userFunction( 'add_settings_section' )
				->with(
					$form_field['section'],
					$title,
					[ $tab, 'section_callback' ],
					$tab_option_page
				)
				->once();
		}

		$subject->setup_sections();
	}

	/**
	 * Data provider for test_setup_sections().
	 *
	 * @return array
	 */
	public function dp_test_setup_sections(): array {
		return [
			'No tabs'   => [ [] ],
			'Some tabs' => [ [ 'some tab' ] ],
		];
	}

	/**
	 * Test setup_sections() not on the option screen.
	 */
	public function test_setup_sections_not_on_options_screen(): void {
		$subject = Mockery::mock( SettingsBase::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'is_options_screen' )->andReturn( false );
		$subject->setup_sections();
	}

	/**
	 * Test setup_sections() when empty form_fields.
	 *
	 * @param array $tabs Tabs.
	 *
	 * @dataProvider dp_test_setup_sections
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_setup_sections_when_empty_form_fields( array $tabs ): void {
		$tab_option_page = 'hcaptcha';
		$title           = 'some title';

		$tab = Mockery::mock( SettingsBase::class )->makePartial();
		$tab->shouldAllowMockingProtectedMethods();
		$tab->shouldReceive( 'option_page' )->andReturn( $tab_option_page );

		$subject = Mockery::mock( SettingsBase::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'is_options_screen' )->andReturn( true );
		$subject->shouldReceive( 'get_active_tab' )->once()->andReturn( $tab );
		$subject->shouldReceive( 'section_title' )->once()->andReturn( $title );

		$this->set_protected_property( $subject, 'tabs', $tabs );

		WP_Mock::userFunction( 'add_settings_section' )
			->with(
				$title,
				'',
				[ $tab, 'section_callback' ],
				$tab_option_page
			)
			->once();

		$subject->setup_sections();
	}

	/**
	 * Test setup_tabs_section().
	 *
	 * @param bool $is_main_page      Whether it is the main page.
	 * @param bool $is_options_screen Whether it is the options' screen.
	 *
	 * @dataProvider dp_test_setup_tabs_section
	 * @return void
	 */
	public function test_setup_tabs_section( bool $is_main_page, bool $is_options_screen ): void {
		$tab_option_page = 'hcaptcha';

		$tab = Mockery::mock( SettingsBase::class )->makePartial();
		$tab->shouldAllowMockingProtectedMethods();
		$tab->shouldReceive( 'option_page' )->andReturn( $tab_option_page );

		$subject = Mockery::mock( SettingsBase::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'is_main_menu_page' )->once()->andReturn( $is_main_page );
		$subject->shouldReceive( 'get_active_tab' )->andReturn( $tab );
		$subject->shouldReceive( 'is_options_screen' )
			->with( [ 'options', $tab_option_page ] )->andReturn( $is_options_screen );

		$times = ( $is_main_page && $is_options_screen ) ? 1 : 0;

		WP_Mock::userFunction( 'add_settings_section' )
			->with(
				'tabs_section',
				'',
				[ $subject, 'tabs_callback' ],
				$tab_option_page
			)
			->times( $times );

		$subject->setup_tabs_section();
	}

	/**
	 * Data provider for test_setup_tabs_section().
	 *
	 * @return array
	 */
	public function dp_test_setup_tabs_section(): array {
		return [
			'Not main page, not options screen' => [ false, false ],
			'Not main page, options screen'     => [ false, true ],
			'Main page, not options screen'     => [ true, false ],
			'Main page, options screen'         => [ true, true ],
		];
	}

	/**
	 * Test setup_tabs_section() not on the main menu page.
	 */
	public function test_setup_tabs_section_not_on_options_screen(): void {
		$subject = Mockery::mock( SettingsBase::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'is_main_menu_page' )->andReturn( false );

		$subject->setup_tabs_section();
	}

	/**
	 * Test tabs_callback().
	 *
	 * @param bool $is_network_wide It is network wide.
	 *
	 * @dataProvider dp_test_tabs_callback
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_tabs_callback( bool $is_network_wide ): void {
		$option_page         = 'hcaptcha';
		$tab_option_page     = 'hcaptcha';
		$subject_class_name  = 'General';
		$subject_page_title  = 'General';
		$tab_class_name      = 'Integrations';
		$tab_page_title      = 'Integrations';
		$subject_url         = 'http://test.test/wp-admin/admin.php?page=hcaptcha';
		$tab_url             = 'http://test.test/wp-admin/admin.php?page=hcaptcha';
		$network_url         = 'https://test.test/wp-admin/network/admin.php?page=hcaptcha';
		$subject_url_arg     = 'http://test.test/wp-admin/admin.php?page=hcaptcha';
		$tab_url_arg         = 'http://test.test/wp-admin/admin.php?page=hcaptcha&tab=integrations';
		$network_url_arg     = 'http://test.test/wp-admin/network/admin.php?page=hcaptcha';
		$network_tab_url_arg = 'http://test.test/wp-admin/network/admin.php?page=hcaptcha&tab=integrations';

		$tab = Mockery::mock( SettingsBase::class )->makePartial();
		$tab->shouldAllowMockingProtectedMethods();
		$tab->shouldReceive( 'option_page' )->with()->andReturn( $tab_option_page );
		$tab->shouldReceive( 'get_class_name' )->with()->andReturn( $tab_class_name );
		$tab->shouldReceive( 'page_title' )->with()->andReturn( $tab_page_title );
		$tab->shouldReceive( 'is_tab_active' )->with( $tab )->once()->andReturn( false );

		$subject = Mockery::mock( SettingsBase::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'is_network_wide' )->with()->andReturn( $is_network_wide );
		$subject->shouldReceive( 'option_page' )->with()->andReturn( $option_page );
		$subject->shouldReceive( 'get_class_name' )->with()->andReturn( $subject_class_name );
		$subject->shouldReceive( 'page_title' )->with()->andReturn( $subject_page_title );
		$subject->shouldReceive( 'is_tab_active' )->with( $subject )->once()->andReturn( true );

		$this->set_protected_property( $subject, 'tabs', [ $tab ] );
		$this->set_protected_property( $subject, 'admin_mode', SettingsBase::MODE_TABS );

		WP_Mock::userFunction( 'is_multisite' )->with()->andReturn( true );
		WP_Mock::userFunction( 'menu_page_url' )
			->with( $option_page, false )->andReturn( $subject_url );
		WP_Mock::userFunction( 'menu_page_url' )
			->with( $tab_option_page, false )->andReturn( $tab_url );
		WP_Mock::userFunction( 'network_admin_url' )
			->with( 'admin.php?page=' . $tab_option_page )->andReturn( $network_url );
		WP_Mock::userFunction( 'add_query_arg' )
			->with( 'tab', strtolower( $subject_class_name ), $subject_url )->andReturn( $subject_url_arg );
		WP_Mock::userFunction( 'add_query_arg' )
			->with( 'tab', strtolower( $tab_class_name ), $subject_url )->andReturn( $tab_url_arg );
		WP_Mock::userFunction( 'add_query_arg' )
			->with( 'tab', strtolower( $subject_class_name ), $network_url )->andReturn( $network_url_arg );
		WP_Mock::userFunction( 'add_query_arg' )
			->with( 'tab', strtolower( $tab_class_name ), $network_url )->andReturn( $network_tab_url_arg );

		$expected = '		<div class="kagg-settings-tabs">
			<span class="kagg-settings-links">
					<a class="kagg-settings-tab active" href="http://test.test/wp-admin/admin.php?page=hcaptcha">
			General		</a>
				<a class="kagg-settings-tab" href="http://test.test/wp-admin/admin.php?page=hcaptcha&tab=integrations">
			Integrations		</a>
					</span>
					</div>
		';

		if ( $is_network_wide ) {
			$expected = str_replace( 'admin.php', 'network/admin.php', $expected );
		}

		ob_start();
		$subject->tabs_callback();
		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test tabs_callback() when no tabs.
	 */
	public function test_tabs_callback_when_no_tabs(): void {
		$subject = Mockery::mock( SettingsBase::class )->makePartial();

		ob_start();
		$subject->tabs_callback();
		self::assertSame( '', ob_get_clean() );
	}

	/**
	 * Data provider for test_tabs_callback().
	 *
	 * @return array
	 */
	public function dp_test_tabs_callback(): array {
		return [
			'Not network wide' => [ false ],
			'Network wide'     => [ true ],
		];
	}

	/**
	 * Test is_tab_active() in tabs mode.
	 *
	 * @param string|null   $on_page       $_GET['page'] === own option page.
	 * @param string|null   $input_tab     $_GET['tab'].
	 * @param string[]|null $referer_names Names from referer.
	 * @param bool          $is_tab        Is tab.
	 * @param string        $class_name    Class name.
	 * @param bool          $expected      Expected.
	 *
	 * @dataProvider dp_test_is_tab_active_in_tabs_mode
	 * @noinspection PhpMissingParamTypeInspection
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_is_tab_active_in_tabs_mode( $on_page, $input_tab, $referer_names, bool $is_tab, string $class_name, bool $expected ): void {
		$option_page = 'own-option-page';
		$input_page  = $on_page ? $option_page : 'some-page';

		$tab = Mockery::mock( SettingsBase::class )->makePartial();
		$tab->shouldAllowMockingProtectedMethods();
		$tab->shouldReceive( 'is_tab' )->with()->andReturn( $is_tab );
		$tab->shouldReceive( 'get_class_name' )->with()->andReturn( $class_name );

		$subject = Mockery::mock( SettingsBase::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'get_names_from_referer' )->andReturn( $referer_names );
		$subject->shouldReceive( 'option_page' )->andReturn( $option_page );

		$this->set_protected_property( $subject, 'admin_mode', SettingsBase::MODE_TABS );

		FunctionMocker::replace(
			'filter_input',
			static function ( $type, $name, $filter ) use ( $input_page, $input_tab ) {
				if (
					INPUT_GET === $type &&
					'page' === $name &&
					FILTER_SANITIZE_FULL_SPECIAL_CHARS === $filter
				) {
					return $input_page;
				}

				if (
					INPUT_GET === $type &&
					'tab' === $name &&
					FILTER_SANITIZE_FULL_SPECIAL_CHARS === $filter
				) {
					return $input_tab;
				}

				return null;
			}
		);

		$method = 'is_tab_active';

		self::assertSame( $expected, $subject->$method( $tab ) );
	}

	/**
	 * Data provider for test_is_tab_active_in_tabs_mode().
	 *
	 * @return array
	 */
	public function dp_test_is_tab_active_in_tabs_mode(): array {
		return [
			'No input, not on page'   => [
				false,
				null,
				[
					'page' => null,
					'tab'  => null,
				],
				false,
				'any_class_name',
				true,
			],
			'No input, not a tab'     => [
				true,
				null,
				[
					'page' => null,
					'tab'  => null,
				],
				false,
				'any_class_name',
				true,
			],
			'No input, tab'           => [
				true,
				null,
				[
					'page' => 'hcaptcha',
					'tab'  => 'integrations',
				],
				true,
				'any_class_name',
				false,
			],
			'Wrong input, not a tab'  => [
				true,
				'wrong',
				[
					'page' => null,
					'tab'  => null,
				],
				false,
				'General',
				false,
			],
			'Wrong input, tab'        => [
				true,
				'wrong',
				[
					'page' => 'hcaptcha',
					'tab'  => 'integrations',
				],
				true,
				'General',
				false,
			],
			'Proper input, not a tab' => [
				true,
				'general',
				[
					'page' => null,
					'tab'  => null,
				],
				false,
				'General',
				true,
			],
			'Proper input, tab'       => [
				true,
				'general',
				[
					'page' => 'hcaptcha',
					'tab'  => 'integrations',
				],
				true,
				'General',
				true,
			],
		];
	}

	/**
	 * Test is_tab_active() in pages mode.
	 *
	 * @param string|null   $on_page       $_GET['page'] === own option page.
	 * @param string[]|null $referer_names Names from referer.
	 * @param bool          $expected      Expected.
	 *
	 * @dataProvider dp_test_is_tab_active_in_pages_mode
	 * @noinspection PhpMissingParamTypeInspection
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_is_tab_active_in_pages_mode( $on_page, $referer_names, bool $expected ): void {
		$option_page = 'hcaptcha';
		$input_page  = $on_page ? $option_page : null;

		$tab = Mockery::mock( SettingsBase::class )->makePartial();
		$tab->shouldAllowMockingProtectedMethods();
		$tab->shouldReceive( 'option_page' )->andReturn( $option_page );

		$subject = Mockery::mock( SettingsBase::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'get_names_from_referer' )->andReturn( $referer_names );

		$this->set_protected_property( $subject, 'admin_mode', SettingsBase::MODE_PAGES );

		FunctionMocker::replace(
			'filter_input',
			static function ( $type, $name, $filter ) use ( $input_page ) {
				if (
					INPUT_GET === $type &&
					'page' === $name &&
					FILTER_SANITIZE_FULL_SPECIAL_CHARS === $filter
				) {
					return $input_page;
				}

				return null;
			}
		);

		$method = 'is_tab_active';

		self::assertSame( $expected, $subject->$method( $tab ) );
	}

	/**
	 * Data provider for test_is_tab_active_in_pages_mode().
	 *
	 * @return array
	 */
	public function dp_test_is_tab_active_in_pages_mode(): array {
		return [
			'Not on page, no referer' => [
				false,
				[ 'page' => null ],
				false,
			],
			'On page, no referer'     => [
				true,
				[ 'page' => null ],
				true,
			],
			'Not on page, referer'    => [
				false,
				[ 'page' => 'hcaptcha' ],
				true,
			],
			'On page, referer'        => [
				true,
				[ 'page' => 'hcaptcha' ],
				true,
			],
		];
	}

	/**
	 * Test is_tab_active() in the wrong mode.
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_is_tab_active_in_wrong_mode(): void {
		$tab     = Mockery::mock( SettingsBase::class )->makePartial();
		$subject = Mockery::mock( SettingsBase::class )->makePartial();

		$this->set_protected_property( $subject, 'admin_mode', 'wrong' );

		$method = 'is_tab_active';

		self::assertFalse( $subject->$method( $tab ) );
	}

	/**
	 * Test get_tab_name_from_referer().
	 *
	 * @param bool        $doing_ajax Whether we are in the ajax request.
	 * @param string|null $referer    Referer.
	 * @param string|null $expected   Expected result.
	 *
	 * @return void
	 * @dataProvider dp_test_get_tab_name_from_referer
	 * @noinspection PhpMissingParamTypeInspection
	 */
	public function test_get_tab_name_from_referer( bool $doing_ajax, $referer, $expected ): void {
		$subject = Mockery::mock( SettingsBase::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();

		$subject->shouldReceive( 'wp_parse_str' )->andReturnUsing(
			static function ( $input_string ) {
				parse_str( (string) $input_string, $result );

				return $result;
			}
		);

		WP_Mock::userFunction( 'wp_doing_ajax' )->with()->once()->andReturn( $doing_ajax );

		if ( $doing_ajax ) {
			WP_Mock::userFunction( 'wp_get_referer' )->with()->once()->andReturn( $referer );
		} else {
			WP_Mock::userFunction( 'wp_get_referer' )->never();
		}

		FunctionMocker::replace(
			'filter_input',
			static function ( $type, $name, $filter ) use ( $referer ) {
				if (
					INPUT_POST === $type &&
					'_wp_http_referer' === $name &&
					FILTER_SANITIZE_URL === $filter
				) {
					return $referer;
				}

				return null;
			}
		);

		WP_Mock::userFunction( 'wp_parse_url' )->andReturnUsing(
			static function ( $url, $component ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
				return parse_url( $url, $component );
			}
		);

		self::assertSame( $expected, $subject->get_names_from_referer() );
	}

	/**
	 * Data provider for test_get_tab_name_from_referer().
	 *
	 * @return array
	 */
	public function dp_test_get_tab_name_from_referer(): array {
		return [
			'Not ajax, not a tab'  => [
				false,
				'/wp-admin/options-general.php?page=hcaptcha',
				[
					'page' => 'hcaptcha',
					'tab'  => null,
				],
			],
			'Not ajax, tab'        => [
				false,
				'/wp-admin/options-general.php?page=hcaptcha&tab=integrations',
				[
					'page' => 'hcaptcha',
					'tab'  => 'integrations',
				],
			],
			'Not ajax, no referer' => [
				false,
				null,
				[
					'page' => null,
					'tab'  => null,
				],
			],
			'Ajax, not a tab'      => [
				true,
				'/wp-admin/options-general.php?page=hcaptcha',
				[
					'page' => 'hcaptcha',
					'tab'  => null,
				],
			],
			'Ajax, tab'            => [
				true,
				'/wp-admin/options-general.php?page=hcaptcha&tab=integrations',
				[
					'page' => 'hcaptcha',
					'tab'  => 'integrations',
				],
			],
			'Ajax, no referer'     => [
				true,
				null,
				[
					'page' => null,
					'tab'  => null,
				],
			],
		];
	}

	/**
	 * Test get_tabs().
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_get_tabs(): void {
		$tab = Mockery::mock( SettingsBase::class )->makePartial();
		$tab->shouldAllowMockingProtectedMethods();

		$tabs = [ $tab ];

		$subject = Mockery::mock( SettingsBase::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$this->set_protected_property( $subject, 'tabs', $tabs );
		self::assertSame( $tabs, $subject->get_tabs() );
	}

	/**
	 * Test get_active_tab().
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_get_active_tab(): void {
		$tab = Mockery::mock( SettingsBase::class )->makePartial();
		$tab->shouldAllowMockingProtectedMethods();

		$subject = Mockery::mock( SettingsBase::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'is_tab_active' )->with( $tab )->andReturn( true );
		$method = 'get_active_tab';
		$this->set_method_accessibility( $subject, $method );

		$this->set_protected_property( $subject, 'tabs', [] );
		self::assertSame( $subject, $subject->$method() );

		$this->set_protected_property( $subject, 'tabs', [ $tab ] );
		self::assertSame(
			json_encode( $tab ),
			json_encode( $subject->$method() )
		);
	}

	/**
	 * Test setup_fields().
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_setup_fields(): void {
		$option_group = 'hcaptcha_group';
		$option_name  = 'hcaptcha_settings';
		$option_page  = 'hcaptcha';

		$subject = Mockery::mock( SettingsBase::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'is_options_screen' )->andReturn( true );
		$subject->shouldReceive( 'option_group' )->andReturn( $option_group );
		$subject->shouldReceive( 'option_name' )->andReturn( $option_name );
		$subject->shouldReceive( 'option_page' )->andReturn( $option_page );

		$form_fields_test_data = $this->get_test_form_fields();
		$args                  = [
			'sanitize_callback' => [ $subject, 'sanitize_option_callback' ],
		];

		$this->set_protected_property( $subject, 'form_fields', $form_fields_test_data );

		WP_Mock::userFunction( 'register_setting' )
			->with( $option_group, $option_name, $args )
			->once();

		foreach ( $form_fields_test_data as $key => $field ) {
			$field['field_id'] = $key;

			WP_Mock::userFunction( 'add_settings_field' )
				->with(
					$key,
					$field['label'],
					[ $subject, 'field_callback' ],
					$option_page,
					$field['section'],
					$field
				)
				->once();
		}

		$subject->setup_fields();
	}

	/**
	 * Test setup_fields() with empty form_fields.
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_setup_fields_with_empty_form_fields(): void {
		$option_group = 'hcaptcha_group';
		$option_name  = 'hcaptcha_settings';

		$subject = Mockery::mock( SettingsBase::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'is_options_screen' )->andReturn( true );
		$subject->shouldReceive( 'option_group' )->andReturn( $option_group );
		$subject->shouldReceive( 'option_name' )->andReturn( $option_name );

		$this->set_protected_property( $subject, 'form_fields', [] );

		$args = [
			'sanitize_callback' => [ $subject, 'sanitize_option_callback' ],
		];

		WP_Mock::userFunction( 'register_setting' )
			->with( $option_group, $option_name, $args )
			->once();

		WP_Mock::userFunction( 'add_settings_field' )->never();

		$subject->setup_fields();
	}

	/**
	 * Test setup_fields() not on the option screen.
	 */
	public function test_setup_fields_not_on_options_screen(): void {
		$subject = Mockery::mock( SettingsBase::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'is_options_screen' )->andReturn( false );

		WP_Mock::userFunction( 'register_setting' )->never();

		WP_Mock::userFunction( 'add_settings_field' )->never();

		$subject->setup_fields();
	}

	/**
	 * Test sanitize_option_callback().
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_sanitize_option_callback(): void {
		$form_fields = [
			'collect_ip'      => [
				'default' => '',
				'type'    => 'checkbox',
			],
			'whitelisted_ips' => [
				'default' => '',
				'type'    => 'textarea',
			],
			'size'            => [
				'default' => '',
				'type'    => 'select',
			],
		];
		$value       = [
			'collect_ip'      => [ 'on' ],
			'whitelisted_ips' => "some ips\nline1\nline2",
			'size'            => 'some size',
			'foo'             => 'bar',
		];
		$expected    = [
			'collect_ip'      => [ 'on' ],
			'whitelisted_ips' => "some ips\nline1\nline2",
			'size'            => 'some size',
			'foo'             => 'bar',
		];

		WP_Mock::passthruFunction( 'sanitize_text_field' );
		WP_Mock::passthruFunction( 'wp_kses_post' );

		$subject = Mockery::mock( SettingsBase::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'form_fields' )->andReturn( $form_fields );
		$this->set_protected_property( $subject, 'form_fields', $form_fields );

		self::assertSame( $expected, $subject->sanitize_option_callback( $value ) );
	}

	/**
	 * Test field_callback().
	 *
	 * @param array  $arguments Arguments.
	 * @param string $expected  Expected result.
	 *
	 * @dataProvider dp_test_field_callback
	 * @noinspection PhpUnusedParameterInspection
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_field_callback( array $arguments, string $expected ): void {
		$option_name = 'hcaptcha_settings';

		$subject = Mockery::mock( SettingsBase::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'option_name' )->andReturn( $option_name );
		$subject->shouldReceive( 'get' )->with( $arguments['field_id'] )->andReturn( $arguments['default'] );

		$fields = [
			'text'     => [ $subject, 'print_text_field' ],
			'password' => [ $subject, 'print_text_field' ],
			'number'   => [ $subject, 'print_number_field' ],
			'textarea' => [ $subject, 'print_textarea_field' ],
			'checkbox' => [ $subject, 'print_checkbox_field' ],
			'radio'    => [ $subject, 'print_radio_field' ],
			'select'   => [ $subject, 'print_select_field' ],
			'multiple' => [ $subject, 'print_multiple_select_field' ],
			'file'     => [ $subject, 'print_file_field' ],
			'table'    => [ $subject, 'print_table_field' ],
			'button'   => [ $subject, 'print_button_field' ],
		];

		$this->set_protected_property( $subject, 'fields', $fields );

		WP_Mock::passthruFunction( 'wp_kses_post' );
		WP_Mock::passthruFunction( 'wp_kses' );

		WP_Mock::userFunction( 'checked' )->andReturnUsing(
			function ( $checked, $current, $do_echo ) {
				$result = '';
				if ( (string) $checked === (string) $current ) {
					$result = 'checked="checked"';
				}

				return $result;
			}
		);

		WP_Mock::userFunction( 'selected' )->andReturnUsing(
			function ( $checked, $current, $do_echo ) {
				$result = '';

				if ( (string) $checked === (string) $current ) {
					$result = 'selected="selected"';
				}

				return $result;
			}
		);

		WP_Mock::userFunction( 'disabled' )->andReturnUsing(
			function ( $disabled, $current, $display ) {
				$result = '';

				if ( (string) $disabled === (string) $current ) {
					$result = 'disabled="disabled"';
				}

				return $result;
			}
		);

		WP_Mock::userFunction( 'wp_parse_args' )->andReturnUsing(
			static function ( $args, $defaults ) {
				return array_merge( $defaults, $args );
			}
		);

		ob_start();
		$subject->field_callback( $arguments );
		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Data provider for test_field_callback().
	 *
	 * @return array
	 */
	public function dp_test_field_callback(): array {
		return array_merge(
			$this->dp_wrong_field_callback(),
			$this->dp_text_field_callback(),
			$this->dp_password_field_callback(),
			$this->dp_number_field_callback(),
			$this->dp_text_area_field_callback(),
			$this->dp_checkbox_field_callback(),
			$this->dp_radio_field_callback(),
			$this->dp_select_field_callback(),
			$this->dp_multiple_field_callback(),
			$this->dp_file_field_callback(),
			$this->dp_table_field_callback(),
			$this->dp_button_field_callback()
		);
	}

	/**
	 * Data provider for the wrong field.
	 *
	 * @return array
	 */
	private function dp_wrong_field_callback(): array {
		return [
			'Wrong type' => [
				[
					'label'        => 'some label',
					'section'      => 'some_section',
					'type'         => 'unknown',
					'placeholder'  => '',
					'helper'       => '',
					'supplemental' => '',
					'default'      => 'some_value',
					'field_id'     => 'some_id',
				],
				'',
			],
		];
	}

	/**
	 * Data provider for text field.
	 *
	 * @return array
	 */
	private function dp_text_field_callback(): array {
		return [
			'Text'                   => [
				[
					'label'        => 'some label',
					'section'      => 'some_section',
					'type'         => 'text',
					'placeholder'  => '',
					'helper'       => '',
					'supplemental' => '',
					'default'      => 'some text',
					'field_id'     => 'some_id',
					'disabled'     => false,
				],
				'<input  name="hcaptcha_settings[some_id]"' .
				' id="some_id" type="text" placeholder="" value="some text" autocomplete="" data-lpignore="" class="regular-text" />',
			],
			'Text with helper'       => [
				[
					'label'        => 'some label',
					'section'      => 'some_section',
					'type'         => 'text',
					'placeholder'  => '',
					'helper'       => 'This is a helper',
					'supplemental' => '',
					'default'      => 'some text',
					'field_id'     => 'some_id',
					'disabled'     => false,
				],
				'<input  name="hcaptcha_settings[some_id]"' .
				' id="some_id" type="text" placeholder="" value="some text" autocomplete="" data-lpignore="" class="regular-text" />' .
				'<span class="helper"><span class="helper-content">This is a helper</span></span>',
			],
			'Text with supplemental' => [
				[
					'label'        => 'some label',
					'section'      => 'some_section',
					'type'         => 'text',
					'placeholder'  => '',
					'helper'       => '',
					'supplemental' => 'This is supplemental',
					'default'      => 'some text',
					'field_id'     => 'some_id',
					'disabled'     => false,
				],
				'<input  name="hcaptcha_settings[some_id]"' .
				' id="some_id" type="text" placeholder="" value="some text" autocomplete="" data-lpignore="" class="regular-text" />' .
				'<p class="description">This is supplemental</p>',
			],
		];
	}

	/**
	 * Data provider for password field.
	 *
	 * @return array
	 */
	private function dp_password_field_callback(): array {
		return [
			'Password' => [
				[
					'label'        => 'some label',
					'section'      => 'some_section',
					'type'         => 'password',
					'placeholder'  => '',
					'helper'       => '',
					'supplemental' => '',
					'default'      => 'some password',
					'field_id'     => 'some_id',
					'disabled'     => false,
				],
				'<input  name="hcaptcha_settings[some_id]"' .
				' id="some_id" type="password" placeholder="" value="some password" autocomplete="new-password" data-lpignore="true" class="regular-text" />',
			],
		];
	}

	/**
	 * Data provider for number field.
	 *
	 * @return array
	 */
	private function dp_number_field_callback(): array {
		return [
			'Number' => [
				[
					'label'        => 'some label',
					'section'      => 'some_section',
					'type'         => 'number',
					'placeholder'  => '',
					'helper'       => '',
					'supplemental' => '',
					'default'      => 15,
					'field_id'     => 'some_id',
					'disabled'     => false,
				],
				'<input  name="hcaptcha_settings[some_id]"' .
				' id="some_id" type="number" placeholder="" value="15" class="regular-text" min="" max="" step="" />',
			],
		];
	}

	/**
	 * Data provider for area field.
	 *
	 * @return array
	 */
	private function dp_text_area_field_callback(): array {
		return [
			'Textarea' => [
				[
					'label'        => 'some label',
					'section'      => 'some_section',
					'type'         => 'textarea',
					'placeholder'  => '',
					'helper'       => '',
					'supplemental' => '',
					'default'      => '<p>This is some<br>textarea</p>',
					'field_id'     => 'some_id',
					'disabled'     => false,
				],
				'<textarea  name="hcaptcha_settings[some_id]"' .
				' id="some_id" placeholder="" rows="5" cols="50"><p>This is some<br>textarea</p></textarea>',
			],
		];
	}

	/**
	 * Data provider for the checkbox field.
	 *
	 * @return array
	 */
	private function dp_checkbox_field_callback(): array {
		return [
			'Checkbox with empty value'  => [
				[
					'label'        => 'checkbox with empty value',
					'section'      => 'some_section',
					'type'         => 'checkbox',
					'placeholder'  => '',
					'helper'       => '',
					'supplemental' => '',
					'default'      => '',
					'field_id'     => 'some_id',
					'disabled'     => false,
				],
				'<fieldset ><label for="some_id_1" ><input id="some_id_1"' .
				' name="hcaptcha_settings[some_id][]" type="checkbox" value="on"   />' .
				'</label><br/></fieldset>',
			],
			'Checkbox not checked'       => [
				[
					'label'        => 'checkbox not checked',
					'section'      => 'some_section',
					'type'         => 'checkbox',
					'placeholder'  => '',
					'helper'       => '',
					'supplemental' => '',
					'default'      => 'no',
					'field_id'     => 'some_id',
					'disabled'     => false,
				],
				'<fieldset ><label for="some_id_1" ><input id="some_id_1"' .
				' name="hcaptcha_settings[some_id][]" type="checkbox" value="on"   />' .
				'</label><br/></fieldset>',
			],
			'Checkbox checked'           => [
				[
					'label'        => 'checkbox checked',
					'section'      => 'some_section',
					'type'         => 'checkbox',
					'placeholder'  => '',
					'helper'       => '',
					'supplemental' => '',
					'default'      => 'yes',
					'field_id'     => 'some_id',
					'disabled'     => false,
				],
				'<fieldset ><label for="some_id_1" ><input id="some_id_1"' .
				' name="hcaptcha_settings[some_id][]" type="checkbox" value="on"   />' .
				'</label><br/></fieldset>',
			],
			'Checkbox checked with data' => [
				[
					'label'        => 'checkbox checked',
					'section'      => 'some_section',
					'type'         => 'checkbox',
					'placeholder'  => '',
					'helper'       => '',
					'supplemental' => '',
					'default'      => 'yes',
					'field_id'     => 'some_id',
					'disabled'     => false,
					'data'         => [
						'on' => [ 'antispam' => 'hcaptcha' ],
					],
				],
				'<fieldset ><label for="some_id_1" data-antispam="hcaptcha"><input id="some_id_1"' .
				' name="hcaptcha_settings[some_id][]" type="checkbox" value="on"   />' .
				'</label><br/></fieldset>',
			],
		];
	}

	/**
	 * Data provider for the radio field.
	 *
	 * @return array
	 */
	private function dp_radio_field_callback(): array {
		return array_merge(
			$this->dp_empty_radio_field_callback(),
			$this->dp_not_empty_radio_field_callback()
		);
	}

	/**
	 * Data provider for empty radio field.
	 *
	 * @return array
	 */
	private function dp_empty_radio_field_callback(): array {
		return [
			'Radio buttons empty options' => [
				[
					'label'        => 'radio buttons empty options',
					'section'      => 'some_section',
					'type'         => 'radio',
					'placeholder'  => '',
					'helper'       => '',
					'supplemental' => '',
					'default'      => 1,
					'field_id'     => 'some_id',
					'disabled'     => false,
				],
				'',
			],
			'Radio buttons not an array'  => [
				[
					'label'        => 'radio buttons not an array',
					'section'      => 'some_section',
					'type'         => 'radio',
					'placeholder'  => '',
					'helper'       => '',
					'supplemental' => '',
					'default'      => 1,
					'options'      => 'green, yellow, red',
					'field_id'     => 'some_id',
					'disabled'     => false,
				],
				'',
			],
		];
	}

	/**
	 * Data provider for not empty radio field.
	 *
	 * @return array
	 */
	private function dp_not_empty_radio_field_callback(): array {
		return [
			'Radio buttons' => [
				[
					'label'        => 'radio buttons',
					'section'      => 'some_section',
					'type'         => 'radio',
					'placeholder'  => '',
					'helper'       => '',
					'supplemental' => '',
					'default'      => 1,
					'options'      => [ 'green', 'yellow', 'red' ],
					'field_id'     => 'some_id',
					'disabled'     => false,
				],
				'<fieldset ><label for="some_id_1"><input id="some_id_1"' .
				' name="hcaptcha_settings[some_id]" type="radio" value="0"   />' .
				'green</label><br/>' .
				'<label for="some_id_2"><input id="some_id_2"' .
				' name="hcaptcha_settings[some_id]" type="radio" value="1" checked="checked"  />' .
				'yellow</label><br/>' .
				'<label for="some_id_3"><input id="some_id_3"' .
				' name="hcaptcha_settings[some_id]" type="radio" value="2"   />' .
				'red</label><br/></fieldset>',
			],
		];
	}

	/**
	 * Data provider for select field.
	 *
	 * @return array
	 */
	private function dp_select_field_callback(): array {
		return [
			'Select with empty options'        => [
				[
					'label'        => 'select with empty options',
					'section'      => 'some_section',
					'type'         => 'select',
					'placeholder'  => '',
					'helper'       => '',
					'supplemental' => '',
					'default'      => 1,
					'field_id'     => 'some_id',
					'disabled'     => false,
				],
				'',
			],
			'Select with options not an array' => [
				[
					'label'        => 'select with options not an array',
					'section'      => 'some_section',
					'type'         => 'select',
					'placeholder'  => '',
					'helper'       => '',
					'supplemental' => '',
					'default'      => 1,
					'options'      => 'green, yellow, red',
					'field_id'     => 'some_id',
					'disabled'     => false,
				],
				'',
			],
			'Select'                           => [
				[
					'label'        => 'select',
					'section'      => 'some_section',
					'type'         => 'select',
					'placeholder'  => '',
					'helper'       => '',
					'supplemental' => '',
					'default'      => 1,
					'options'      => [ 'green', 'yellow', 'red' ],
					'field_id'     => 'some_id',
					'disabled'     => false,
				],
				'<select  name="hcaptcha_settings[some_id]">' .
				'<option value="0"  >green</option>' .
				'<option value="1" selected="selected" >yellow</option>' .
				'<option value="2"  >red</option>' .
				'</select>',
			],
		];
	}

	/**
	 * Data provider for multiple field.
	 *
	 * @return array
	 */
	private function dp_multiple_field_callback(): array {
		return array_merge(
			$this->dp_empty_multiple_field_callback(),
			$this->dp_not_empty_multiple_field_callback()
		);
	}

	/**
	 * Data provider for empty multiple field.
	 *
	 * @return array
	 */
	private function dp_empty_multiple_field_callback(): array {
		return [
			'Multiple with empty options'        => [
				[
					'label'        => 'multiple with empty options',
					'section'      => 'some_section',
					'type'         => 'multiple',
					'placeholder'  => '',
					'helper'       => '',
					'supplemental' => '',
					'default'      => 1,
					'field_id'     => 'some_id',
					'disabled'     => false,
				],
				'',
			],
			'Multiple with options not an array' => [
				[
					'label'        => 'multiple with options not an array',
					'section'      => 'some_section',
					'type'         => 'multiple',
					'placeholder'  => '',
					'helper'       => '',
					'supplemental' => '',
					'default'      => 1,
					'options'      => 'green, yellow, red',
					'field_id'     => 'some_id',
					'disabled'     => false,
				],
				'',
			],
		];
	}

	/**
	 * Data provider for not empty multiple field.
	 *
	 * @return array
	 */
	private function dp_not_empty_multiple_field_callback(): array {
		return [
			'Multiple'                         => [
				[
					'label'        => 'multiple',
					'section'      => 'some_section',
					'type'         => 'multiple',
					'placeholder'  => '',
					'helper'       => '',
					'supplemental' => '',
					'default'      => 1,
					'options'      => [ 'green', 'yellow', 'red' ],
					'field_id'     => 'some_id',
					'disabled'     => false,
				],
				'<select  multiple="multiple" name="hcaptcha_settings[some_id][]">' .
				'<option value="0"  >green</option>' .
				'<option value="1"  >yellow</option>' .
				'<option value="2"  >red</option>' .
				'</select>',
			],
			'Multiple with multiple selection' => [
				[
					'label'        => 'multiple with multiple selection',
					'section'      => 'some_section',
					'type'         => 'multiple',
					'placeholder'  => '',
					'helper'       => '',
					'supplemental' => '',
					'default'      => [ 1, 2 ],
					'options'      => [ 'green', 'yellow', 'red' ],
					'field_id'     => 'some_id',
					'disabled'     => false,
				],
				'<select  multiple="multiple" name="hcaptcha_settings[some_id][]">' .
				'<option value="0"  >green</option>' .
				'<option value="1" selected="selected" >yellow</option>' .
				'<option value="2" selected="selected" >red</option>' .
				'</select>',
			],
		];
	}

	/**
	 * Data provider for the file field.
	 *
	 * @return array
	 */
	private function dp_file_field_callback(): array {
		return [
			'File'                     => [
				[
					'label'        => 'file',
					'section'      => 'some_section',
					'type'         => 'file',
					'placeholder'  => '',
					'helper'       => '',
					'supplemental' => '',
					'default'      => 1,
					'field_id'     => 'some_id',
					'disabled'     => false,
					'multiple'     => false,
					'accept'       => '',
				],
				'<input  name="hcaptcha_settings[some_id]" id="some_id" type="file"  />',
			],
			'File disabled'            => [
				[
					'label'        => 'file',
					'section'      => 'some_section',
					'type'         => 'file',
					'placeholder'  => '',
					'helper'       => '',
					'supplemental' => '',
					'default'      => 1,
					'field_id'     => 'some_id',
					'disabled'     => true,
					'multiple'     => false,
					'accept'       => '',
				],
				'<input disabled="disabled" name="hcaptcha_settings[some_id]" id="some_id" type="file"  />',
			],
			'File multiple accept xml' => [
				[
					'label'        => 'file',
					'section'      => 'some_section',
					'type'         => 'file',
					'placeholder'  => '',
					'helper'       => '',
					'supplemental' => '',
					'default'      => 1,
					'field_id'     => 'some_id',
					'disabled'     => false,
					'multiple'     => true,
					'accept'       => '.xml',
				],
				'<input  name="hcaptcha_settings[some_id][]" id="some_id" type="file" multiple accept=".xml"/>',
			],
		];
	}

	/**
	 * Data provider for the table field.
	 *
	 * @return array
	 */
	private function dp_table_field_callback(): array {
		return [
			'Table with non-array value' => [
				[
					'label'        => 'Some Table',
					'section'      => 'some_section',
					'type'         => 'table',
					'placeholder'  => '',
					'helper'       => '',
					'supplemental' => '',
					'default'      => 'some string',
					'field_id'     => 'some_id',
					'disabled'     => false,
				],
				'',
			],
			'Table'                      => [
				[
					'label'        => 'Some Table',
					'section'      => 'some_section',
					'type'         => 'table',
					'placeholder'  => '',
					'helper'       => '',
					'supplemental' => '',
					'default'      => [
						'ю' => 'yu',
						'я' => 'ya',
					],
					'field_id'     => 'some_id',
					'disabled'     => false,
				],
				'<fieldset >' .
				'<div class="kagg-table-cell">' .
				'<label for="some_id-0">ю</label>' .
				'<input name="hcaptcha_settings[some_id][ю]"' .
				' id="some_id-0" type="text" placeholder="" value="yu" class="regular-text" />' .
				'</div>' .
				'<div class="kagg-table-cell">' .
				'<label for="some_id-1">я</label>' .
				'<input name="hcaptcha_settings[some_id][я]"' .
				' id="some_id-1" type="text" placeholder="" value="ya" class="regular-text" />' .
				'</div>' .
				'</fieldset>',
			],
		];
	}

	/**
	 * Data provider for button field.
	 *
	 * @return array
	 */
	private function dp_button_field_callback(): array {
		return [
			'Button' => [
				[
					'label'        => 'Some Button',
					'section'      => 'some_section',
					'type'         => 'button',
					'text'         => 'Some Text',
					'placeholder'  => '',
					'helper'       => '',
					'supplemental' => '',
					'default'      => [
						'ю' => 'yu',
						'я' => 'ya',
					],
					'field_id'     => 'some_id',
					'disabled'     => false,
				],
				'<button  id="some_id" class="button button-secondary" type="button"/>' .
				'Some Text' .
				'</button>',
			],
		];
	}

	/**
	 * Test field_callback() without a field id.
	 */
	public function test_field_callback_without_field_id(): void {
		$subject = Mockery::mock( SettingsBase::class )->makePartial();

		$arguments = [];

		ob_start();
		$subject->field_callback( $arguments );
		self::assertSame( '', ob_get_clean() );
	}

	/**
	 * Test field_callback() without a callable method.
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_field_callback_without_callable(): void {
		$subject = Mockery::mock( SettingsBase::class )->makePartial();

		$arguments = [
			'field_id' => 'some_id',
			'type'     => 'text',
		];

		$fields = [
			'text' => null,
		];

		$this->set_protected_property( $subject, 'fields', $fields );

		ob_start();
		$subject->field_callback( $arguments );
		self::assertSame( '', ob_get_clean() );
	}

	/**
	 * Test get().
	 *
	 * @param array  $settings    Plugin options.
	 * @param string $key         Setting name.
	 * @param mixed  $empty_value Empty value for this setting.
	 * @param mixed  $expected    Expected result.
	 *
	 * @dataProvider dp_test_get
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_get( array $settings, string $key, $empty_value, $expected ): void {
		$tab = Mockery::mock( SettingsBase::class )->makePartial();
		$tab->shouldAllowMockingProtectedMethods();
		$tab->shouldReceive( 'form_fields' )->andReturn( [] );

		$subject = Mockery::mock( SettingsBase::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'init_settings' )->never();
		$this->set_protected_property( $subject, 'settings', $settings );
		$this->set_protected_property( $subject, 'tabs', [ $tab ] );

		if ( ! isset( $settings[ $key ] ) ) {
			$subject->shouldReceive( 'form_fields' )->andReturn( $this->get_test_settings() )->once();
		}

		self::assertSame( $expected, $subject->get( $key, $empty_value ) );
	}

	/**
	 * Data provider for test_get().
	 *
	 * @return array
	 */
	public function dp_test_get(): array {
		$test_data = $this->get_test_settings();

		return [
			'Empty key'        => [ $this->get_test_settings(), '', null, '' ],
			'Some key'         => [ $this->get_test_settings(), 'wp_status', null, $test_data['wp_status'] ],
			'Non-existent key' => [
				$this->get_test_settings(),
				'non-existent-key',
				[ 'default-value' ],
				[ 'default-value' ],
			],
		];
	}

	/**
	 * Test get() with no settings.
	 */
	public function test_get_with_no_settings(): void {
		$settings = $this->get_test_settings();
		$key      = 'wp_status';
		$expected = $settings[ $key ];

		$subject = Mockery::mock( SettingsBase::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'init_settings' )->once()->andReturnUsing(
			function () use ( $subject, $settings ) {
				$this->set_protected_property( $subject, 'settings', $settings );
			}
		);

		self::assertSame( $expected, $subject->get( $key ) );
	}

	/**
	 * Test set().
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_set(): void {
		$settings         = $this->get_test_settings();
		$key              = 'wp_status';
		$value            = 'new_value';
		$expected         = $settings;
		$expected[ $key ] = $value;

		$subject = Mockery::mock( SettingsBase::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$this->set_protected_property( $subject, 'settings', $settings );

		self::assertFalse( $subject->set( 'unknown', $value ) );
		self::assertTrue( $subject->set( $key, $value ) );
		self::assertSame( $expected, $this->get_protected_property( $subject, 'settings' ) );
	}

	/**
	 * Test field_default().
	 *
	 * @param array  $field    Field.
	 * @param string $expected Expected result.
	 *
	 * @dataProvider dp_test_field_default
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_field_default( array $field, string $expected ): void {
		$subject = Mockery::mock( SettingsBase::class )->makePartial();
		$method  = 'field_default';
		$this->set_method_accessibility( $subject, $method );

		self::assertSame( $expected, $subject->$method( $field ) );
	}

	/**
	 * Data provider for test_field_default().
	 *
	 * @return array
	 */
	public function dp_test_field_default(): array {
		return [
			'Empty field'        => [ [], '' ],
			'With default value' => [ [ 'default' => 'default_value' ], 'default_value' ],
		];
	}

	/**
	 * Test set_field().
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_set_field(): void {
		$subject = Mockery::mock( SettingsBase::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$this->set_protected_property( $subject, 'form_fields', $this->get_test_form_fields() );

		$field_key = 'title';
		$value     = 'Some title';

		self::assertFalse( $subject->set_field( 'non_existent_field', $field_key, $value ) );
		self::assertTrue( $subject->set_field( 'size', $field_key, $value ) );

		$form_fields = $this->get_protected_property( $subject, 'form_fields' );
		self::assertSame( $value, $form_fields['size'][ $field_key ] );
	}

	/**
	 * Test update_option().
	 *
	 * @param array  $settings Plugin options.
	 * @param string $key      Setting name.
	 * @param mixed  $value    Value for this setting.
	 * @param mixed  $expected Expected result.
	 *
	 * @dataProvider dp_test_update_option
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_update_option( array $settings, string $key, $value, $expected ): void {
		$option_name = 'hcaptcha_settings';

		$subject = Mockery::mock( SettingsBase::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'init_settings' )->never();
		$subject->shouldReceive( 'option_name' )->once()->andReturn( $option_name );
		$this->set_protected_property( $subject, 'settings', $settings );

		WP_Mock::userFunction( 'update_option' )->with( $option_name, $expected )->once();

		$subject->update_option( $key, $value );

		self::assertSame( $expected, $this->get_protected_property( $subject, 'settings' ) );
	}

	/**
	 * Data provider for test_update_option().
	 *
	 * @return array
	 */
	public function dp_test_update_option(): array {
		return [
			'Empty key'         => [
				$this->get_test_settings(),
				'',
				null,
				array_merge( $this->get_test_settings(), [ '' => null ] ),
			],
			'Key without value' => [
				$this->get_test_settings(),
				'pageTitle',
				null,
				array_merge( $this->get_test_settings(), [ 'pageTitle' => null ] ),
			],
			'Key with value'    => [
				$this->get_test_settings(),
				'pageTitle',
				[ 'New Page Title' ],
				array_merge( $this->get_test_settings(), [ 'pageTitle' => [ 'New Page Title' ] ] ),
			],
			'Non-existent key'  => [
				$this->get_test_settings(),
				'non-existent-key',
				[ 'some value' ],
				array_merge( $this->get_test_settings(), [ 'non-existent-key' => [ 'some value' ] ] ),
			],
		];
	}

	/**
	 * Test update_option with no settings.
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_update_option_with_no_settings(): void {
		$option_name = 'hcaptcha_settings';
		$settings    = $this->get_test_settings();
		$key         = 'page_title';
		$value       = [
			'label'        => 'New page title',
			'section'      => 'first_section',
			'type'         => 'text',
			'placeholder'  => '',
			'helper'       => '',
			'supplemental' => '',
			'default'      => 'Table Viewer',
		];

		$expected         = $settings;
		$expected[ $key ] = $value;

		$subject = Mockery::mock( SettingsBase::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'init_settings' )->once()->andReturnUsing(
			function () use ( $subject, $settings ) {
				$this->set_protected_property( $subject, 'settings', $settings );
			}
		);
		$subject->shouldReceive( 'option_name' )->andReturn( $option_name );

		WP_Mock::userFunction( 'update_option' )->with( $option_name, $expected )->once();

		$subject->update_option( $key, $value );

		self::assertSame( $expected, $this->get_protected_property( $subject, 'settings' ) );
	}

	/**
	 * Test pre_update_option_filter().
	 *
	 * @param array $form_fields Form fields.
	 * @param mixed $value       New option value.
	 * @param mixed $old_value   Old option value.
	 * @param mixed $expected    Expected result.
	 *
	 * @dataProvider dp_test_pre_update_option_filter
	 */
	public function test_pre_update_option_filter( array $form_fields, $value, $old_value, $expected ): void {
		$option_name                   = 'hcaptcha_settings';
		$netwide_name                  = '_network_wide';
		$merged_value                  = array_merge( $old_value, $value );
		$merged_value[ $netwide_name ] = array_key_exists( $netwide_name, $merged_value ) ? $merged_value[ $netwide_name ] : [];

		$subject = Mockery::mock( SettingsBase::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'form_fields' )->andReturn( $form_fields );
		$subject->shouldReceive( 'option_name' )->andReturn( $option_name );

		WP_Mock::userFunction( 'is_multisite' )->with()->andReturn( false );

		$general_tab     = isset( $value['site_key'] );
		$network_wide    = $general_tab
			? $value['_network_wide']
			: [];
		$get_site_option = $network_wide ? $old_value : [];

		WP_Mock::userFunction( 'get_site_option' )
			->with( $option_name, [] )
			->andReturn( $get_site_option );

		self::assertSame( $expected, $subject->pre_update_option_filter( $value, $old_value ) );
	}

	/**
	 * Data provider for test_pre_update_option_filter().
	 *
	 * @return array
	 */
	public function dp_test_pre_update_option_filter(): array {
		// phpcs:disable WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned
		return [
			'value not changed' => [
				'form_fields' => [],
				'value'       => [ 'value' ],
				'old_value'   => [ 'value' ],
				'expected'    => [ 'value' ],
			],
			'value changed' => [
				'form_fields' => [],
				'value'       => [ 'a' => 'value' ],
				'old_value'   => [ 'b' => 'old_value' ],
				'expected'    => [
					'b'             => 'old_value',
					'a'             => 'value',
					'_network_wide' => [],
				],
			],
			'text field changed' => [
				'form_fields' => [
					'no_checkbox' => [
						'label'        => 'some field',
						'section'      => 'some_section',
						'type'         => 'text',
						'placeholder'  => '',
						'helper'       => '',
						'supplemental' => '',
						'default'      => [ '' ],
					],
				],
				'value'       => [ 'no_checkbox' => '0' ],
				'old_value'   => [ 'no_checkbox' => '1' ],
				'expected'    => [
					'no_checkbox'   => '0',
					'_network_wide' => [],
				],
			],
			'checkbox set off' => [
				'form_fields' => [
					'some_checkbox' => [
						'label'        => 'some field',
						'section'      => 'some_section',
						'type'         => 'checkbox',
						'placeholder'  => '',
						'helper'       => '',
						'supplemental' => '',
						'default'      => [ '' ],
					],
				],
				'value'       => [ 'some_checkbox' => '0' ],
				'old_value'   => [ 'some_checkbox' => '1' ],
				'expected'    => [
					'some_checkbox' => '0',
					'_network_wide' => [],
				],
			],
			'checkbox set on' => [
				'form_fields' => [
					'some_checkbox' => [
						'label'        => 'some field',
						'section'      => 'some_section',
						'type'         => 'checkbox',
						'placeholder'  => '',
						'helper'       => '',
						'supplemental' => '',
						'default'      => [ '' ],
					],
				],
				'value'       => [ 'some_checkbox' => '1' ],
				'old_value'   => [ 'some_checkbox' => '0' ],
				'expected'    => [
					'some_checkbox' => '1',
					'_network_wide' => [],
				],
			],
			'disabled checkbox changed' => [
				'form_fields' => [
					'some_checkbox' => [
						'label'        => 'some field',
						'section'      => 'some_section',
						'type'         => 'checkbox',
						'placeholder'  => '',
						'helper'       => '',
						'supplemental' => '',
						'default'      => [ '' ],
						'disabled'     => true,
					],
				],
				'value'       => [ 'another_value' => '1' ],
				'old_value'   => [ 'some_checkbox' => '0' ],
				'expected'    => [
					'some_checkbox' => '0',
					'another_value' => '1',
					'_network_wide' => [],
				],
			],
			'another value added' => [
				'form_fields' => [
					'some_checkbox' => [
						'label'        => 'some field',
						'section'      => 'some_section',
						'type'         => 'checkbox',
						'placeholder'  => '',
						'helper'       => '',
						'supplemental' => '',
						'default'      => [ '' ],
						'disabled'     => false,
					],
				],
				'value'       => [ 'another_value' => '1' ],
				'old_value'   => [ 'some_checkbox' => '0' ],
				'expected'    => [
					'some_checkbox' => [],
					'another_value' => '1',
					'_network_wide' => [],
				],
			],
			'file field changed' => [
				'form_fields' => [
					'some_file' => [
						'label'        => 'some field',
						'section'      => 'some_section',
						'type'         => 'file',
						'placeholder'  => '',
						'helper'       => '',
						'supplemental' => '',
						'default'      => [ '' ],
						'disabled'     => false,
					],
				],
				'value'       => [ 'some_file' => 'a.xml' ],
				'old_value'   => [ 'some_file' => 'b.xml' ],
				'expected'    => [ '_network_wide' => [] ],
			],
			'table changed' => [
				'form_fields' => [],
				'value'       => [
					'bel' => [ 'Б' => 'B1' ],
				],
				'old_value'   => [
					'iso9' => [ 'Б' => 'B' ],
					'bel'  => [ 'Б' => 'B' ],
				],
				'expected'    => [
					'iso9'          => [ 'Б' => 'B' ],
					'bel'           => [ 'Б' => 'B1' ],
					'_network_wide' => [],
				],
			],
			'table and network wide changed not on general tab' => [
				'form_fields' => [],
				'value'       => [
					'bel'           => [ 'Б' => 'B1' ],
					'_network_wide' => [ 'on' ],
				],
				'old_value'   => [
					'iso9' => [ 'Б' => 'B' ],
					'bel'  => [ 'Б' => 'B' ],
				],
				'expected'    => [
					'iso9'          => [ 'Б' => 'B' ],
					'bel'           => [ 'Б' => 'B1' ],
					'_network_wide' => [],
				],
			],
			'table and network wide changed on general tab' => [
				'form_fields' => [],
				'value'       => [
					'bel'           => [ 'Б' => 'B1' ],
					'_network_wide' => [ 'on' ],
					'site_key'      => 'some_site_key',
				],
				'old_value'   => [
					'iso9' => [ 'Б' => 'B' ],
					'bel'  => [ 'Б' => 'B' ],
				],
				'expected'    => [
					'iso9'          => [ 'Б' => 'B' ],
					'bel'           => [ 'Б' => 'B1' ],
					'_network_wide' => [ 'on' ],
					'site_key'      => 'some_site_key',
				],
			],
		];
		// phpcs:enable WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned
	}

	/**
	 * Test pre_update_option_filter() on multisite.
	 *
	 * @param array $form_fields Form fields.
	 * @param mixed $value       New option value.
	 * @param mixed $old_value   Old option value.
	 * @param mixed $expected    Expected result.
	 *
	 * @dataProvider dp_test_pre_update_option_filter
	 */
	public function test_pre_update_option_filter_on_multisite( array $form_fields, $value, $old_value, $expected ): void {
		$option_name  = 'hcaptcha_settings';
		$netwide_name = '_network_wide';

		$get_network_wide = [];

		$subject = Mockery::mock( SettingsBase::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'form_fields' )->andReturn( $form_fields );
		$subject->shouldReceive( 'option_name' )->andReturn( $option_name );
		$subject->shouldReceive( 'get_network_wide' )->andReturn( $get_network_wide );

		WP_Mock::userFunction( 'is_multisite' )->with()->andReturn( true );

		$general_tab  = isset( $value['site_key'] );
		$network_wide = $general_tab
			? $value[ $netwide_name ] ?? []
			: $get_network_wide;

		if ( $network_wide ) {
			$old_value = [];
		}

		$merged_value                  = array_merge( $old_value, $value );
		$merged_value[ $netwide_name ] = $network_wide;

		WP_Mock::userFunction( 'update_site_option' )
			->with( $option_name . $netwide_name, $merged_value[ $netwide_name ] );
		WP_Mock::userFunction( 'update_site_option' )->with( $option_name, $merged_value );

		if ( $network_wide ) {
			self::assertSame( $old_value, $subject->pre_update_option_filter( $value, $old_value ) );
		} else {
			self::assertSame( $expected, $subject->pre_update_option_filter( $value, $old_value ) );
		}
	}

	/**
	 * Test load_plugin_textdomain().
	 */
	public function test_load_plugin_text_domain(): void {
		$text_domain      = 'hcaptcha-for-forms-and-more';
		$plugin_base_name = 'hcaptcha-for-forms-and-more/hcaptcha.php';

		$subject = Mockery::mock( SettingsBase::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'text_domain' )->andReturn( $text_domain );
		$subject->shouldReceive( 'plugin_basename' )->andReturn( $plugin_base_name );

		WP_Mock::userFunction( 'load_plugin_textdomain' )
			->with( $text_domain, false, dirname( $plugin_base_name ) . '/languages/' )->once();

		$subject->load_plugin_textdomain();
	}

	/**
	 * Test is_options_screen().
	 *
	 * @param mixed   $current_screen Current admin screen.
	 * @param boolean $expected       Expected result.
	 *
	 * @dataProvider dp_test_is_options_screen
	 */
	public function test_is_options_screen( $current_screen, bool $expected ): void {
		$option_page = 'hcaptcha';

		$subject = Mockery::mock( SettingsBase::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();

		$subject->shouldReceive( 'is_network_wide' )->andReturn( false );
		$subject->shouldReceive( 'option_page' )->andReturn( $option_page );

		WP_Mock::userFunction( 'get_current_screen' )->with()->andReturn( $current_screen );
		WP_Mock::userFunction( 'is_multisite' )->with()->andReturn( true );

		self::assertSame( $expected, $subject->is_options_screen() );
	}

	/**
	 * Data provider for test_is_options_screen().
	 *
	 * @return array
	 */
	public function dp_test_is_options_screen(): array {
		return [
			'Current screen not set'        => [ null, false ],
			'Wrong screen'                  => [ (object) [ 'id' => 'something' ], false ],
			'Options screen'                => [ (object) [ 'id' => 'options' ], true ],
			'Plugin screen'                 => [ (object) [ 'id' => 'settings_page_hcaptcha' ], true ],
			'Plugin screen, main menu page' => [ (object) [ 'id' => 'toplevel_page_hcaptcha' ], true ],
		];
	}

	/**
	 * Test is_options_screen() when network_wide.
	 *
	 * @param mixed   $current_screen Current admin screen.
	 * @param boolean $expected       Expected result.
	 *
	 * @dataProvider dp_test_is_options_screen_when_network_wide
	 */
	public function test_is_options_screen_when_network_wide( $current_screen, bool $expected ): void {
		$option_page = 'hcaptcha';

		$subject = Mockery::mock( SettingsBase::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();

		$subject->shouldReceive( 'is_network_wide' )->andReturn( true );
		$subject->shouldReceive( 'option_page' )->andReturn( $option_page );

		WP_Mock::userFunction( 'get_current_screen' )->with()->andReturn( $current_screen );
		WP_Mock::userFunction( 'is_multisite' )->with()->andReturn( true );

		self::assertSame( $expected, $subject->is_options_screen() );
	}

	/**
	 * Data provider for test_is_options_screen_when_network_wide().
	 *
	 * @return array
	 */
	public function dp_test_is_options_screen_when_network_wide(): array {
		return [
			'Current screen not set'        => [ null, false ],
			'Wrong screen'                  => [ (object) [ 'id' => 'something-network' ], false ],
			'Options screen'                => [ (object) [ 'id' => 'options-network' ], true ],
			'Plugin screen'                 => [ (object) [ 'id' => 'settings_page_hcaptcha-network' ], true ],
			'Plugin screen, main menu page' => [ (object) [ 'id' => 'toplevel_page_hcaptcha-network' ], true ],
		];
	}

	/**
	 * Test is_options_screen() when get_current_screen() does not exist.
	 */
	public function test_is_options_screen_when_get_current_screen_does_not_exist(): void {
		FunctionMocker::replace(
			'function_exists',
			static function ( $function_name ) {
				return 'get_current_screen' !== $function_name;
			}
		);

		$subject = Mockery::mock( SettingsBase::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();

		self::assertFalse( $subject->is_options_screen() );
	}

	/**
	 * Test get_network_wide().
	 *
	 * @return void
	 */
	public function test_get_network_wide(): void {
		$option_name  = 'hcaptcha_settings';
		$network_wide = [];

		WP_Mock::userFunction( 'is_multisite' )->andReturn( true );

		$subject = Mockery::mock( SettingsBase::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'option_name' )->andReturn( $option_name );

		WP_Mock::userFunction( 'get_site_option' )
			->with( $option_name . SettingsBase::NETWORK_WIDE, [] )
			->twice()
			->andReturn( $network_wide );

		self::assertSame( $network_wide, $subject->get_network_wide() );
		self::assertSame( $network_wide, $subject->get_network_wide() );
	}

	/**
	 * Test is_network_wide().
	 *
	 * @return void
	 */
	public function test_is_network_wide(): void {
		$network_wide = [ 'on' ];

		$subject = Mockery::mock( SettingsBase::class )->makePartial();
		$method  = 'is_network_wide';

		$subject->shouldAllowMockingProtectedMethods();

		$subject->shouldReceive( 'get_network_wide' )->andReturnUsing(
			function () use ( &$network_wide ) {
				return $network_wide;
			}
		);

		self::assertTrue( $subject->$method() );

		$network_wide = [];

		self::assertFalse( $subject->$method() );
	}

	/**
	 * Test get_menu_position().
	 *
	 * @return void
	 */
	public function test_get_menu_position(): void {
		$menu_position = [ 'on' ];

		$subject = Mockery::mock( SettingsBase::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();

		$subject->shouldReceive( 'get' )->with( 'menu_position' )
			->andReturnUsing(
				function () use ( &$menu_position ) {
					return $menu_position;
				}
			);

		self::assertSame( SettingsBase::MODE_TABS, $subject->get_menu_position() );

		$menu_position = [];

		self::assertSame( SettingsBase::MODE_PAGES, $subject->get_menu_position() );
	}

	/**
	 * Tests print_header().
	 *
	 * @return void
	 */
	public function test_print_header(): void {
		$page_title = 'General';

		$subject = Mockery::mock( SettingsBase::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'page_title' )->andReturn( $page_title );

		WP_Mock::expectAction( 'kagg_settings_header' );

		$expected = <<<HTML
		<div class="kagg-header-bar">
			<div class="kagg-header">
				<h2>
					$page_title				</h2>
			</div>
					</div>
		
HTML;

		ob_start();

		$subject->print_header();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test get_savable_form_fields().
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_get_savable_form_fields(): void {
		$subject = Mockery::mock( SettingsBase::class )->makePartial();
		$method  = 'get_savable_form_fields';

		$fields = [
			[
				'id'   => 'field_1',
				'type' => 'text',
			],
			[
				'id'   => 'field_2',
				'type' => 'password',
			],
			[
				'id'   => 'field_3',
				'type' => 'file',
			],
			[
				'id'   => 'field_4',
				'type' => 'button',
			],
		];

		$expected = [
			[
				'id'   => 'field_1',
				'type' => 'text',
			],
			[
				'id'   => 'field_2',
				'type' => 'password',
			],
		];

		$this->set_protected_property( $subject, 'form_fields', $fields );

		self::assertSame( $expected, $subject->$method() );
	}
}
