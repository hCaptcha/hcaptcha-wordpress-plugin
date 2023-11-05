<?php
/**
 * SettingsBaseTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedMethodInspection */
/** @noinspection PhpArrayShapeAttributeCanBeAddedInspection */
/** @noinspection TypoSafeNamingInspection */
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
	 * @param bool $is_tab Is this a tab.
	 *
	 * @dataProvider dp_test_constructor
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_constructor( bool $is_tab ) {
		$classname = SettingsBase::class;

		$subject = Mockery::mock( $classname )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'is_tab' )->once()->andReturn( $is_tab );

		if ( $is_tab ) {
			WP_Mock::expectActionNotAdded( 'current_screen', [ $subject, 'setup_tabs_section' ] );
			WP_Mock::expectActionNotAdded( 'admin_menu', [ $subject, 'add_settings_page' ] );
		} else {
			WP_Mock::expectActionAdded( 'current_screen', [ $subject, 'setup_tabs_section' ], 9 );
			WP_Mock::expectActionAdded( 'admin_menu', [ $subject, 'add_settings_page' ] );
		}

		$subject->shouldReceive( 'init' )->once()->with();

		$constructor = ( new ReflectionClass( $classname ) )->getConstructor();

		self::assertNotNull( $constructor );

		$constructor->invoke( $subject );
	}

	/**
	 * Data provider for test_constructor().
	 *
	 * @return array
	 */
	public function dp_test_constructor(): array {
		return [
			'Tab'       => [ true ],
			'Not a tab' => [ false ],
		];
	}

	/**
	 * Test init().
	 *
	 * @param bool $is_active    Is this an active tab.
	 * @param bool $script_debug Is script debug active.
	 *
	 * @dataProvider dp_test_init
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_init( bool $is_active, bool $script_debug ) {
		$subject = Mockery::mock( SettingsBase::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'form_fields' )->once();
		$subject->shouldReceive( 'init_settings' )->once();
		$subject->shouldReceive( 'is_tab_active' )->once()->with( $subject )->andReturn( $is_active );

		if ( $is_active ) {
			$subject->shouldReceive( 'init_hooks' )->once();
		} else {
			$subject->shouldReceive( 'init_hooks' )->never();
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

		$subject->init();

		$min_prefix = $script_debug ? '' : '.min';
		self::assertSame( $min_prefix, $this->get_protected_property( $subject, 'min_prefix' ) );
	}

	/**
	 * Data provider for test_init().
	 *
	 * @return array
	 */
	public function dp_test_init(): array {
		return [
			'Active tab, script_debug'        => [ true, true ],
			'Active tab, no script debug'     => [ true, false ],
			'Not active tab, script debug'    => [ false, true ],
			'Not active tab, no script debug' => [ false, false ],
		];
	}

	/**
	 * Test init_hooks().
	 */
	public function test_init_hooks() {
		$plugin_base_name = 'hcaptcha-wordpress-plugin/hcaptcha.php';
		$option_name      = 'hcaptcha_settings';

		$subject = Mockery::mock( SettingsBase::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'plugin_basename' )->andReturn( $plugin_base_name );
		$subject->shouldReceive( 'option_name' )->andReturn( $option_name );

		WP_Mock::expectActionAdded( 'plugins_loaded', [ $subject, 'load_plugin_textdomain' ] );

		WP_Mock::expectFilterAdded(
			'plugin_action_links_' . $plugin_base_name,
			[ $subject, 'add_settings_link' ]
		);

		WP_Mock::expectActionAdded( 'current_screen', [ $subject, 'setup_fields' ] );
		WP_Mock::expectActionAdded( 'current_screen', [ $subject, 'setup_sections' ], 11 );

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

		WP_Mock::expectActionAdded( 'admin_enqueue_scripts', [ $subject, 'base_admin_enqueue_scripts' ] );

		$subject->init_hooks();
	}

	/**
	 * Test parent_slug().
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_parent_slug() {
		$subject = Mockery::mock( SettingsBase::class )->makePartial();

		$method = 'parent_slug';
		$this->set_method_accessibility( $subject, $method );
		self::assertSame( 'options-general.php', $subject->$method() );
	}

	/**
	 * Test is_main_menu_page().
	 *
	 * @param string $parent_slug Parent slug.
	 * @param bool   $expected    Expected.
	 *
	 * @dataProvider dp_test_is_main_menu_page
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_is_main_menu_page( string $parent_slug, bool $expected ) {
		$subject = Mockery::mock( SettingsBase::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'parent_slug' )->once()->andReturn( $parent_slug );
		$method = 'is_main_menu_page';

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
			'Empty slug' => [ '', true ],
			'Some slug'  => [ 'options-general.php', false ],
		];
	}

	/**
	 * Test tab_name().
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_tab_name() {
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
	public function test_get_class_name() {
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
	public function test_is_tab() {
		$subject = Mockery::mock( SettingsBase::class )->makePartial();

		$method = 'is_tab';
		$this->set_method_accessibility( $subject, $method );
		self::assertTrue( $subject->$method() );

		$this->set_protected_property( $subject, 'tabs', [ 'some_array' ] );
		self::assertFalse( $subject->$method() );
	}

	/**
	 * Test add_settings_link().
	 */
	public function test_add_settings_link() {
		$option_page         = 'hcaptcha';
		$settings_link_label = 'hCaptcha Settings';
		$settings_link_text  = 'Settings';

		$subject = Mockery::mock( SettingsBase::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'option_page' )->andReturn( $option_page );
		$subject->shouldReceive( 'settings_link_label' )->andReturn( $settings_link_label );
		$subject->shouldReceive( 'settings_link_text' )->andReturn( $settings_link_text );

		WP_Mock::passthruFunction( 'admin_url' );

		$expected = [
			'settings' =>
				'<a href="options-general.php?page=' . $option_page .
				'" aria-label="' . $settings_link_label . '">' . $settings_link_text . '</a>',
		];

		self::assertSame( $expected, $subject->add_settings_link( [] ) );
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
	public function test_init_settings( $settings, bool $network_wide ) {
		$option_name = 'hcaptcha_settings';

		$subject = Mockery::mock( SettingsBase::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'option_name' )->andReturn( $option_name );
		$method = 'init_settings';
		$this->set_method_accessibility( $subject, $method );

		if ( $settings ) {
			$settings['_network_wide'] = $network_wide;
		}

		WP_Mock::userFunction( 'get_site_option' )->with( $option_name . '_network_wide', [] )->once()
			->andReturn( $network_wide );

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
	public function test_form_fields( $form_fields, array $expected ) {
		$subject = Mockery::mock( SettingsBase::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$method = 'form_fields';
		$this->set_method_accessibility( $subject, $method );
		$this->set_protected_property( $subject, 'form_fields', $form_fields );

		if ( empty( $form_fields ) ) {
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
			[ [], $this->get_test_form_fields() ],
			[ $this->get_test_form_fields(), $this->get_test_form_fields() ],
		];
	}

	/**
	 * Test add_settings_page().
	 *
	 * @param bool $is_main_menu_page Is this the main menu page.
	 *
	 * @dataProvider dp_test_add_settings_page
	 */
	public function test_add_settings_page( bool $is_main_menu_page ) {
		$parent_slug = 'options-general.php';
		$page_title  = 'General';
		$menu_title  = 'hCaptcha';
		$capability  = 'manage_options';
		$slug        = 'hcaptcha';

		$subject = Mockery::mock( SettingsBase::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'is_main_menu_page' )->andReturn( $is_main_menu_page );
		$subject->shouldReceive( 'page_title' )->andReturn( $page_title );
		$subject->shouldReceive( 'menu_title' )->andReturn( $menu_title );
		$subject->shouldReceive( 'option_page' )->andReturn( $slug );

		$callback = [ $subject, 'settings_base_page' ];

		if ( $is_main_menu_page ) {
			WP_Mock::userFunction( 'add_menu_page' )
				->with( $page_title, $menu_title, $capability, $slug, $callback );
		} else {
			WP_Mock::userFunction( 'add_submenu_page' )
				->with( $parent_slug, $page_title, $menu_title, $capability, $slug, $callback );
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
	 * Test settings_base_page().
	 */
	public function test_settings_base_page() {
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
	 * Test admin_enqueue_base_scripts().
	 */
	public function test_base_admin_enqueue_scripts() {
		$plugin_url     = 'http://test.test/wp-content/plugins/hcaptcha-for-forms-and-more';
		$plugin_version = '1.0.0';

		$page = Mockery::mock( SettingsBase::class );
		$page->shouldReceive( 'admin_enqueue_scripts' )->with()->once();

		$subject = Mockery::mock( SettingsBase::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'get_active_tab' )->once()->andReturn( $page );
		$subject->shouldReceive( 'plugin_url' )->once()->andReturn( $plugin_url );
		$subject->shouldReceive( 'plugin_version' )->once()->andReturn( $plugin_version );
		$subject->shouldReceive( 'is_options_screen' )->andReturn( true );

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
	 * Test setup_sections().
	 *
	 * @param array $tabs Tabs.
	 *
	 * @dataProvider dp_test_setup_sections
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_setup_sections( array $tabs ) {
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
	 * Test setup_sections() not on options screen.
	 */
	public function test_setup_sections_not_on_options_screen() {
		$subject = Mockery::mock( SettingsBase::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'is_options_screen' )->andReturn( false );
		$subject->setup_sections();
	}

	/**
	 * Test setup_tabs_section() not on options screen.
	 */
	public function test_setup_tabs_section_not_on_options_screen() {
		$subject = Mockery::mock( SettingsBase::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'is_options_screen' )->andReturn( false );

		$subject->setup_tabs_section();
	}

	/**
	 * Test setup_tabs_section().
	 */
	public function test_setup_tabs_section() {
		$tab_option_page = 'hcaptcha';

		$tab = Mockery::mock( SettingsBase::class )->makePartial();
		$tab->shouldAllowMockingProtectedMethods();
		$tab->shouldReceive( 'option_page' )->andReturn( $tab_option_page );

		$subject = Mockery::mock( SettingsBase::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'is_options_screen' )->once()->andReturn( true );
		$subject->shouldReceive( 'get_active_tab' )->once()->andReturn( $tab );

		WP_Mock::userFunction( 'add_settings_section' )
			->with(
				'tabs_section',
				'',
				[ $subject, 'tabs_callback' ],
				$tab_option_page
			)
			->once();

		$subject->setup_tabs_section();
	}

	/**
	 * Test tabs_callback().
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_tabs_callback() {
		$option_page        = 'hcaptcha';
		$subject_class_name = 'General';
		$subject_page_title = 'General';
		$tab_class_name     = 'Integrations';
		$tab_page_title     = 'Integrations';
		$subject_url        = 'http://test.test/wp-admin/admin.php?page=hcaptcha';
		$subject_url_arg    = 'http://test.test/wp-admin/admin.php?page=hcaptcha';
		$tab_url_arg        = 'http://test.test/wp-admin/admin.php?page=hcaptcha&tab=integrations';

		$tab = Mockery::mock( SettingsBase::class )->makePartial();
		$tab->shouldAllowMockingProtectedMethods();
		$tab->shouldReceive( 'get_class_name' )->with()->andReturn( $tab_class_name );
		$tab->shouldReceive( 'page_title' )->with()->andReturn( $tab_page_title );

		$subject = Mockery::mock( SettingsBase::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'option_page' )->with()->twice()->andReturn( $option_page );
		$subject->shouldReceive( 'get_class_name' )->with()->andReturn( $subject_class_name );
		$subject->shouldReceive( 'page_title' )->with()->andReturn( $subject_page_title );
		$subject->shouldReceive( 'is_tab_active' )->with( $subject )->once()->andReturn( true );
		$subject->shouldReceive( 'is_tab_active' )->with( $tab )->once()->andReturn( false );

		$this->set_protected_property( $subject, 'tabs', [ $tab ] );

		WP_Mock::userFunction( 'menu_page_url' )
			->with( $option_page, false )->twice()->andReturn( $subject_url );
		WP_Mock::userFunction( 'add_query_arg' )
			->with( 'tab', strtolower( $subject_class_name ), $subject_url )->andReturn( $subject_url_arg );
		WP_Mock::userFunction( 'add_query_arg' )
			->with( 'tab', strtolower( $tab_class_name ), $subject_url )->andReturn( $tab_url_arg );

		$expected = '		<div class="kagg-settings-tabs">
					<a
				class="kagg-settings-tab active"
				href="http://test.test/wp-admin/admin.php?page=hcaptcha">
			General		</a>
				<a
				class="kagg-settings-tab"
				href="http://test.test/wp-admin/admin.php?page=hcaptcha&tab=integrations">
			Integrations		</a>
				</div>
		';

		ob_start();
		$subject->tabs_callback();
		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test is_tab_active().
	 *
	 * @param string|null $on_page     $_GET['page'] === own option page.
	 * @param string|null $input_tab   $_GET['tab'].
	 * @param string|null $referer_tab Tab name from referer.
	 * @param bool        $is_tab      Is tab.
	 * @param string      $class_name  Class name.
	 * @param bool        $expected    Expected.
	 *
	 * @dataProvider dp_test_is_tab_active
	 * @noinspection PhpMissingParamTypeInspection
	 */
	public function test_is_tab_active( $on_page, $input_tab, $referer_tab, bool $is_tab, string $class_name, bool $expected ) {
		$option_page = 'own-option-page';
		$input_page  = $on_page ? $option_page : 'some-page';

		$tab = Mockery::mock( SettingsBase::class )->makePartial();
		$tab->shouldAllowMockingProtectedMethods();
		$tab->shouldReceive( 'is_tab' )->with()->andReturn( $is_tab );
		$tab->shouldReceive( 'get_class_name' )->with()->andReturn( $class_name );

		$subject = Mockery::mock( SettingsBase::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'get_names_from_referer' )->andReturn( $referer_tab );
		$subject->shouldReceive( 'option_page' )->andReturn( $option_page );

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

		self::assertSame( $expected, $subject->is_tab_active( $tab ) );
	}

	/**
	 * Data provider for test_is_tab_active().
	 *
	 * @return array
	 */
	public function dp_test_is_tab_active(): array {
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
	public function test_get_tab_name_from_referer( bool $doing_ajax, $referer, $expected ) {
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
	public function test_get_tabs() {
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
	public function test_get_active_tab() {
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
	public function test_setup_fields() {
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
		$this->set_protected_property( $subject, 'form_fields', $form_fields_test_data );

		WP_Mock::userFunction( 'register_setting' )
			->with( $option_group, $option_name )
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
	public function test_setup_fields_with_empty_form_fields() {
		$option_group = 'hcaptcha_group';
		$option_name  = 'hcaptcha_settings';

		$subject = Mockery::mock( SettingsBase::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'is_options_screen' )->andReturn( true );
		$subject->shouldReceive( 'option_group' )->andReturn( $option_group );
		$subject->shouldReceive( 'option_name' )->andReturn( $option_name );

		$this->set_protected_property( $subject, 'form_fields', [] );

		WP_Mock::userFunction( 'register_setting' )
			->with( $option_group, $option_name )
			->once();

		WP_Mock::userFunction( 'add_settings_field' )->never();

		$subject->setup_fields();
	}

	/**
	 * Test setup_fields() not on options screen.
	 */
	public function test_setup_fields_not_on_options_screen() {
		$subject = Mockery::mock( SettingsBase::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'is_options_screen' )->andReturn( false );

		WP_Mock::userFunction( 'register_setting' )->never();

		WP_Mock::userFunction( 'add_settings_field' )->never();

		$subject->setup_fields();
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
	public function test_field_callback( array $arguments, string $expected ) {
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
			$this->dp_check_box_field_callback(),
			$this->dp_radio_field_callback(),
			$this->dp_select_field_callback(),
			$this->dp_multiple_field_callback(),
			$this->dp_table_field_callback()
		);
	}

	/**
	 * Data provider for wrong field.
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
					'helper'       => 'This is helper',
					'supplemental' => '',
					'default'      => 'some text',
					'field_id'     => 'some_id',
					'disabled'     => false,
				],
				'<input  name="hcaptcha_settings[some_id]"' .
				' id="some_id" type="text" placeholder="" value="some text" autocomplete="" data-lpignore="" class="regular-text" />' .
				'<span class="helper"><span class="helper-content">This is helper</span></span>',
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
	 * Data provider for checkbox field.
	 *
	 * @return array
	 */
	private function dp_check_box_field_callback(): array {
		return [
			'Checkbox with empty value' => [
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
				'<fieldset ><label for="some_id_1"><input id="some_id_1"' .
				' name="hcaptcha_settings[some_id][]" type="checkbox" value="on"   />' .
				' </label><br/></fieldset>',
			],
			'Checkbox not checked'      => [
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
				'<fieldset ><label for="some_id_1"><input id="some_id_1"' .
				' name="hcaptcha_settings[some_id][]" type="checkbox" value="on"   />' .
				' </label><br/></fieldset>',
			],
			'Checkbox checked'          => [
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
				'<fieldset ><label for="some_id_1"><input id="some_id_1"' .
				' name="hcaptcha_settings[some_id][]" type="checkbox" value="on"   />' .
				' </label><br/></fieldset>',
			],
		];
	}

	/**
	 * Data provider for radio field.
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
				' green</label><br/>' .
				'<label for="some_id_2"><input id="some_id_2"' .
				' name="hcaptcha_settings[some_id]" type="radio" value="1" checked="checked"  />' .
				' yellow</label><br/>' .
				'<label for="some_id_3"><input id="some_id_3"' .
				' name="hcaptcha_settings[some_id]" type="radio" value="2"   />' .
				' red</label><br/></fieldset>',
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
	 * Data provider for table field.
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
	 * Test field_callback() without field id.
	 */
	public function test_field_callback_without_field_id() {
		$subject = Mockery::mock( SettingsBase::class )->makePartial();

		$arguments = [];

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
	public function test_get( array $settings, string $key, $empty_value, $expected ) {
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
	public function test_get_with_no_settings() {
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
	 * Test field_default().
	 *
	 * @param array  $field    Field.
	 * @param string $expected Expected result.
	 *
	 * @dataProvider dp_test_field_default
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_field_default( array $field, string $expected ) {
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
	public function test_set_field() {
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
	public function test_update_option( array $settings, string $key, $value, $expected ) {
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
	public function test_update_option_with_no_settings() {
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
	 * @noinspection RepetitiveMethodCallsInspection
	 */
	public function test_pre_update_option_filter( array $form_fields, $value, $old_value, $expected ) {
		$option_name                   = 'hcaptcha_settings';
		$network_wide                  = '_network_wide';
		$merged_value                  = array_merge( $old_value, $value );
		$merged_value[ $network_wide ] = array_key_exists( $network_wide, $merged_value ) ? $merged_value[ $network_wide ] : [];

		$subject = Mockery::mock( SettingsBase::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'form_fields' )->andReturn( $form_fields );
		$subject->shouldReceive( 'option_name' )->andReturn( $option_name );

		WP_Mock::userFunction( 'update_site_option' )
			->with( $option_name . $network_wide, $merged_value[ $network_wide ] );
		WP_Mock::userFunction( 'update_site_option' )->with( $option_name, $merged_value );

		self::assertSame( $expected, $subject->pre_update_option_filter( $value, $old_value ) );
	}

	/**
	 * Data provider for test_pre_update_option_filter().
	 *
	 * @return array
	 */
	public function dp_test_pre_update_option_filter(): array {
		return [
			[
				[],
				[ 'value' ],
				[ 'value' ],
				[ 'value' ],
			],
			[
				[],
				[ 'a' => 'value' ],
				[ 'b' => 'old_value' ],
				[
					'b'             => 'old_value',
					'a'             => 'value',
					'_network_wide' => [],
				],
			],
			[
				[
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
				[ 'no_checkbox' => '0' ],
				[ 'no_checkbox' => '1' ],
				[
					'no_checkbox'   => '0',
					'_network_wide' => [],
				],
			],
			[
				[
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
				[ 'some_checkbox' => '0' ],
				[ 'some_checkbox' => '1' ],
				[
					'some_checkbox' => '0',
					'_network_wide' => [],
				],
			],
			[
				[
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
				[ 'some_checkbox' => '1' ],
				[ 'some_checkbox' => '0' ],
				[
					'some_checkbox' => '1',
					'_network_wide' => [],
				],
			],
			[
				[
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
				[ 'another_value' => '1' ],
				[ 'some_checkbox' => '0' ],
				[
					'some_checkbox' => '0',
					'another_value' => '1',
					'_network_wide' => [],
				],
			],
			[
				[
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
				[ 'another_value' => '1' ],
				[ 'some_checkbox' => '0' ],
				[
					'some_checkbox' => [],
					'another_value' => '1',
					'_network_wide' => [],
				],
			],
			[
				[],
				[
					'bel' => [ 'Б' => 'B1' ],
				],
				[
					'iso9' => [ 'Б' => 'B' ],
					'bel'  => [ 'Б' => 'B' ],
				],
				[
					'iso9'          => [ 'Б' => 'B' ],
					'bel'           => [ 'Б' => 'B1' ],
					'_network_wide' => [],
				],
			],
			[
				[],
				[
					'bel'           => [ 'Б' => 'B1' ],
					'_network_wide' => [ 'on' ],
				],
				[
					'iso9' => [ 'Б' => 'B' ],
					'bel'  => [ 'Б' => 'B' ],
				],
				[
					'iso9' => [ 'Б' => 'B' ],
					'bel'  => [ 'Б' => 'B' ],
				],
			],
		];
	}

	/**
	 * Test load_plugin_textdomain().
	 */
	public function test_load_plugin_text_domain() {
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
	 * @param mixed   $current_screen    Current admin screen.
	 * @param boolean $is_main_menu_page It is the main menu page.
	 * @param boolean $expected          Expected result.
	 *
	 * @dataProvider dp_test_is_options_screen
	 */
	public function test_is_options_screen( $current_screen, bool $is_main_menu_page, bool $expected ) {
		$screen_id      = 'settings_page_hcaptcha';
		$main_screen_id = 'toplevel_page_hcaptcha';

		$subject = Mockery::mock( SettingsBase::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'is_main_menu_page' )->andReturn( $is_main_menu_page );

		if ( $is_main_menu_page ) {
			$subject->shouldReceive( 'screen_id' )->andReturn( $main_screen_id );
		} else {
			$subject->shouldReceive( 'screen_id' )->andReturn( $screen_id );
		}

		WP_Mock::userFunction( 'get_current_screen' )->with()->andReturn( $current_screen );

		self::assertSame( $expected, $subject->is_options_screen() );
	}

	/**
	 * Data provider for test_is_options_screen().
	 *
	 * @return array
	 */
	public function dp_test_is_options_screen(): array {
		return [
			'Current screen not set'        => [ null, false, false ],
			'Wrong screen'                  => [ (object) [ 'id' => 'something' ], false, false ],
			'Options screen'                => [ (object) [ 'id' => 'options' ], false, true ],
			'Plugin screen'                 => [ (object) [ 'id' => 'settings_page_hcaptcha' ], false, true ],
			'Plugin screen, main menu page' => [ (object) [ 'id' => 'toplevel_page_hcaptcha' ], true, true ],
		];
	}

	/**
	 * Test is_options_screen() when get_current_screen() does not exist.
	 */
	public function test_is_options_screen_when_get_current_screen_does_not_exist() {
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
}
