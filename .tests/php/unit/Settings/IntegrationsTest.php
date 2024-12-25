<?php
/**
 * IntegrationsTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedMethodInspection */
/** @noinspection PhpArrayShapeAttributeCanBeAddedInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Unit\Settings;

use HCaptcha\ACFE\Form;
use HCaptcha\Main;
use HCaptcha\Settings\PluginSettingsBase;
use KAGG\Settings\Abstracts\SettingsBase;
use HCaptcha\Settings\Integrations;
use HCaptcha\Tests\Unit\HCaptchaTestCase;
use Mockery;
use ReflectionException;
use tad\FunctionMocker\FunctionMocker;
use WP_Error;
use WP_Mock;

/**
 * Class IntegrationsTest
 *
 * @group settings
 * @group settings-integrations
 */
class IntegrationsTest extends HCaptchaTestCase {

	/**
	 * Tear down test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		unset( $GLOBALS['wp_filter'] );

		parent::tearDown();
	}

	/**
	 * Test page_title().
	 */
	public function test_page_title(): void {
		$subject = Mockery::mock( Integrations::class )->makePartial()->shouldAllowMockingProtectedMethods();

		$method = 'page_title';
		self::assertSame( 'Integrations', $subject->$method() );
	}

	/**
	 * Test section_title().
	 */
	public function test_section_title(): void {
		$subject = Mockery::mock( Integrations::class )->makePartial()->shouldAllowMockingProtectedMethods();

		$method = 'section_title';
		self::assertSame( 'integrations', $subject->$method() );
	}

	/**
	 * Test init().
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_init(): void {
		$plugins = [ 'some plugins' ];
		$themes  = [ 'some themes' ];

		$subject = Mockery::mock( Integrations::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'form_fields' )->once();
		$subject->shouldReceive( 'init_settings' )->once();
		$subject->shouldReceive( 'is_main_menu_page' )->once()->andReturn( false );
		$subject->shouldReceive( 'is_tab_active' )->with( $subject )->once()->andReturn( false );
		$this->set_protected_property( $subject, 'plugins', $plugins );
		$this->set_protected_property( $subject, 'themes', $themes );

		FunctionMocker::replace(
			'function_exists',
			static function ( $function_name ) {
				return 'get_plugins' === $function_name;
			}
		);

		WP_Mock::userFunction( 'get_plugins' )->andReturn( $plugins );
		WP_Mock::userFunction( 'wp_get_themes' )->andReturn( $themes );
		WP_Mock::userFunction( 'is_admin' )->andReturn( true );

		$method = 'init';
		$subject->$method();

		self::assertSame( $plugins, $this->get_protected_property( $subject, 'plugins' ) );
		self::assertSame( $themes, $this->get_protected_property( $subject, 'themes' ) );
	}

	/**
	 * Test init_hooks().
	 *
	 * @return void
	 */
	public function test_init_hooks(): void {
		$plugin_base_name = 'hcaptcha-wordpress-plugin/hcaptcha.php';

		$subject = Mockery::mock( Integrations::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'plugin_basename' )->andReturn( $plugin_base_name );
		$subject->shouldReceive( 'is_tab_active' )->with( $subject )->andReturn( false );

		WP_Mock::expectActionAdded( 'kagg_settings_header', [ $subject, 'search_box' ] );
		WP_Mock::expectActionAdded( 'wp_ajax_' . Integrations::ACTIVATE_ACTION, [ $subject, 'activate' ] );
		WP_Mock::expectActionAdded( 'after_switch_theme', [ $subject, 'after_switch_theme_action' ], 0 );

		$method = 'init_hooks';

		$subject->$method();
	}

	/**
	 * Test after_switch_theme_action().
	 *
	 * @return void
	 */
	public function test_after_switch_theme_action(): void {
		$subject = Mockery::mock( Integrations::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();

		$subject->shouldReceive( 'run_checks' )->once()->with( $subject::ACTIVATE_ACTION );
		$subject->shouldReceive( 'remove_action_regex' )
			->once()->with( '/^Avada/', 'after_switch_theme' );

		WP_Mock::userFunction( 'wp_doing_ajax' )->once()->with()->andReturn( true );
		WP_Mock::userFunction( 'remove_action' )->once()->with( 'after_switch_theme', 'et_onboarding_trigger_redirect' );
		WP_Mock::userFunction( 'remove_action' )->once()->with( 'after_switch_theme', 'avada_compat_switch_theme' );

		$subject->after_switch_theme_action();
	}

	/**
	 * Test after_switch_theme_action() when not ajax.
	 *
	 * @return void
	 */
	public function test_after_switch_theme_action_when_not_ajax(): void {
		$subject = Mockery::mock( Integrations::class )->makePartial();

		WP_Mock::userFunction( 'wp_doing_ajax' )->once()->with()->andReturn( false );

		$subject->after_switch_theme_action();
	}

	/**
	 * Test init_form_fields().
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_init_form_fields(): void {
		$expected = $this->get_test_integrations_form_fields();

		$mock = Mockery::mock( Integrations::class )->makePartial()->shouldAllowMockingProtectedMethods();

		$mock->init_form_fields();

		self::assertSame( $expected, $this->get_protected_property( $mock, 'form_fields' ) );
	}

	/**
	 * Test setup_fields().
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_setup_fields(): void {
		$plugin_url  = 'http://test.test/wp-content/plugins/hcaptcha-wordpress-plugin';
		$form_fields = $this->get_test_form_fields();

		foreach ( $form_fields as &$form_field ) {
			$form_field['disabled'] = true;
		}

		unset( $form_field );

		$form_fields['wp_status']['disabled']          = false;
		$form_fields['woocommerce_status']['disabled'] = false;

		$main = Mockery::mock( Main::class )->makePartial();

		$main->modules = [
			'WooCommerce Register'  => [
				[ 'woocommerce_status', 'register' ],
				'woocommerce/woocommerce.php',
				'wc_register_class',
			],
			'WooCommerce Wishlists' => [
				[ 'woocommerce_wishlists_status', 'create_list' ],
				'woocommerce-wishlists/woocommerce-wishlists.php',
				'create_class',
			],
		];

		$subject = Mockery::mock( Integrations::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'is_options_screen' )->andReturn( true );
		$subject->shouldReceive( 'plugin_or_theme_installed' )
			->with( 'woocommerce/woocommerce.php' )->andReturn( true );
		$subject->shouldReceive( 'plugin_or_theme_installed' )
			->with( 'woocommerce-wishlists/woocommerce-wishlists.php' )->andReturn( false );

		$this->set_protected_property( $subject, 'form_fields', $form_fields );

		WP_Mock::userFunction( 'hcaptcha' )->andReturn( $main );
		WP_Mock::passthruFunction( 'register_setting' );
		WP_Mock::passthruFunction( 'add_settings_field' );
		WP_Mock::passthruFunction( 'sanitize_file_name' );

		FunctionMocker::replace(
			'constant',
			static function ( $name ) use ( $plugin_url ) {
				if ( 'HCAPTCHA_URL' === $name ) {
					return $plugin_url;
				}

				return '';
			}
		);

		$subject->setup_fields();

		$form_fields = $this->get_protected_property( $subject, 'form_fields' );

		reset( $form_fields );
		$first_key = key( $form_fields );

		self::assertSame( 'woocommerce_status', $first_key );

		foreach ( $form_fields as $form_field ) {
			$section = ( ! $form_field['installed'] ) || $form_field['disabled']
				? Integrations::SECTION_DISABLED
				: Integrations::SECTION_ENABLED;

			self::assertTrue( (bool) preg_match( '<img src="' . $plugin_url . '/assets/images/.+?" alt=".+?">', $form_field['label'] ) );
			self::assertArrayHasKey( 'class', $form_field );
			self::assertSame( $section, $form_field['section'] );
		}
	}

	/**
	 * Test setup_fields() not on the options screen.
	 *
	 * @return void
	 */
	public function test_setup_fields_not_on_options_screen(): void {
		$subject = Mockery::mock( Integrations::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'is_options_screen' )->andReturn( false );

		$subject->setup_fields();
	}

	/**
	 * Test plugin_or_theme_installed().
	 *
	 * @return void
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_plugin_or_theme_installed(): void {
		$plugins = [
			'woocommerce/woocommerce.php' => [ 'some plugin data' ],
		];
		$themes  = [
			'twentytwentyone' => [ 'some theme data' ],
		];

		$subject = Mockery::mock( Integrations::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$method = 'plugin_or_theme_installed';
		$this->set_protected_property( $subject, 'plugins', $plugins );
		$this->set_protected_property( $subject, 'themes', $themes );

		self::assertTrue( $subject->$method( '' ) );

		self::assertFalse( $subject->$method( 'contact-form-7/wp-contact-form-7.php' ) );
		self::assertTrue( $subject->$method( 'woocommerce/woocommerce.php' ) );

		self::assertFalse( $subject->$method( 'Divi' ) );
		self::assertTrue( $subject->$method( 'twentytwentyone' ) );
	}

	/**
	 * Test search_box().
	 */
	public function test_search_box(): void {
		$subject  = Mockery::mock( Integrations::class )->makePartial();
		$expected = '		<div id="hcaptcha-integrations-search-wrap">
			<label for="hcaptcha-integrations-search"></label>
			<input
					type="search" id="hcaptcha-integrations-search"
					placeholder="Search plugins and themes...">
		</div>
		';

		ob_start();
		$subject->search_box();
		$output = ob_get_clean();

		self::assertSame( $expected, $output );
	}

	/**
	 * Test section_callback()
	 *
	 * @param string $id       Section id.
	 * @param string $expected Expected value.
	 *
	 * @dataProvider dp_test_section_callback
	 */
	public function test_section_callback( string $id, string $expected ): void {
		WP_Mock::passthruFunction( 'wp_kses_post' );
		WP_Mock::userFunction( 'submit_button' );

		$subject = Mockery::mock( Integrations::class )->makePartial()->shouldAllowMockingProtectedMethods();

		ob_start();
		$subject->section_callback( [ 'id' => $id ] );
		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Data provider for test_section_callback().
	 *
	 * @return array
	 */
	public function dp_test_section_callback(): array {
		return [
			'disabled' => [
				Integrations::SECTION_DISABLED,
				'			<hr class="hcaptcha-disabled-section">
			<h3>Inactive plugins and themes</h3>
			',
			],
			'default'  => [
				'',
				'		<div class="hcaptcha-header-bar">
			<div class="hcaptcha-header">
				<h2>
					Integrations				</h2>
			</div>
					</div>
				<div id="hcaptcha-message"></div>
		<p>
			Manage integrations with popular plugins and themes such as Contact Form 7, Elementor Pro, WPForms, and more.		</p>
		<p>
			You can activate and deactivate a plugin or theme by clicking on its logo.		</p>
		<p>
			Don\'t see your plugin or theme here? Use the `[hcaptcha]` <a href="https://wordpress.org/plugins/hcaptcha-for-forms-and-more/#does%20the%20%5Bhcaptcha%5D%20shortcode%20have%20arguments%3F" target="_blank">shortcode</a> or <a href="https://github.com/hCaptcha/hcaptcha-wordpress-plugin/issues" target="_blank">request an integration</a>.		</p>
		<h3>Active plugins and themes</h3>
		',
			],
		];
	}

	/**
	 * Test admin_enqueue_scripts().
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_admin_enqueue_scripts(): void {
		$plugin_url     = 'http://test.test/wp-content/plugins/hcaptcha-wordpress-plugin';
		$plugin_version = '1.0.0';
		$min_suffix     = '.min';
		$ajax_url       = 'https://test.test/wp-admin/admin-ajax.php';
		$nonce          = 'some_nonce';

		$theme         = Mockery::mock( 'WP_Theme' );
		$default_theme = Mockery::mock( 'WP_Theme' );

		FunctionMocker::replace(
			'\WP_Theme::get_core_default_theme',
			static function () use ( $default_theme ) {
				return $default_theme;
			}
		);

		$theme->shouldReceive( 'get_stylesheet' )->andReturn( 'Divi' );
		$theme->shouldReceive( 'get' )->with( 'Name' )->andReturn( 'Divi' );
		$default_theme->shouldReceive( 'get_stylesheet' )->andReturn( 'twentytwentyone' );
		$default_theme->shouldReceive( 'get' )->andReturn( 'Twenty Twenty-One' );

		$themes = [
			'Divi'            => $theme,
			'twentytwentyone' => $default_theme,
		];

		$subject = Mockery::mock( Integrations::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'is_options_screen' )->with()->andReturn( true );
		$this->set_protected_property( $subject, 'min_suffix', $min_suffix );
		$this->set_protected_property( $subject, 'themes', $themes );

		FunctionMocker::replace(
			'constant',
			static function ( $name ) use ( $plugin_url, $plugin_version ) {
				if ( 'HCAPTCHA_URL' === $name ) {
					return $plugin_url;
				}

				if ( 'HCAPTCHA_VERSION' === $name ) {
					return $plugin_version;
				}

				return '';
			}
		);

		WP_Mock::userFunction( 'wp_enqueue_script' )
			->with(
				Integrations::DIALOG_HANDLE,
				$plugin_url . "/assets/js/kagg-dialog$min_suffix.js",
				[],
				$plugin_version,
				true
			)
			->once();

		WP_Mock::userFunction( 'wp_enqueue_style' )
			->with(
				Integrations::DIALOG_HANDLE,
				$plugin_url . "/assets/css/kagg-dialog$min_suffix.css",
				[],
				$plugin_version
			)
			->once();

		WP_Mock::userFunction( 'wp_enqueue_script' )
			->with(
				Integrations::HANDLE,
				$plugin_url . "/assets/js/integrations$min_suffix.js",
				[ 'jquery', Integrations::DIALOG_HANDLE ],
				$plugin_version,
				true
			)
			->once();

		WP_Mock::userFunction( 'wp_get_theme' )->with()->andReturn( $theme )->once();

		WP_Mock::userFunction( 'admin_url' )
			->with( 'admin-ajax.php' )
			->andReturn( $ajax_url )
			->once();

		WP_Mock::userFunction( 'wp_create_nonce' )
			->with( Integrations::ACTIVATE_ACTION )
			->andReturn( $nonce )
			->once();

		WP_Mock::userFunction( 'wp_localize_script' )
			->with(
				Integrations::HANDLE,
				Integrations::OBJECT,
				[
					'ajaxUrl'             => $ajax_url,
					'action'              => Integrations::ACTIVATE_ACTION,
					'nonce'               => $nonce,
					'installPluginMsg'    => 'Install and activate %s plugin?',
					'installThemeMsg'     => 'Install and activate %s theme?',
					'activatePluginMsg'   => 'Activate %s plugin?',
					'deactivatePluginMsg' => 'Deactivate %s plugin?',
					'activateThemeMsg'    => 'Activate %s theme?',
					'deactivateThemeMsg'  => 'Deactivate %s theme?',
					'selectThemeMsg'      => 'Select theme to activate:',
					'onlyOneThemeMsg'     => 'Cannot deactivate the only theme on the site.',
					'unexpectedErrorMsg'  => 'Unexpected error.',
					'OKBtnText'           => 'OK',
					'CancelBtnText'       => 'Cancel',
					'themes'              => [ 'twentytwentyone' => 'Twenty Twenty-One' ],
					'defaultTheme'        => 'twentytwentyone',
				]
			)
			->once();

		WP_Mock::userFunction( 'wp_enqueue_style' )
			->with(
				Integrations::HANDLE,
				$plugin_url . "/assets/css/integrations$min_suffix.css",
				[ PluginSettingsBase::PREFIX . '-' . SettingsBase::HANDLE, Integrations::DIALOG_HANDLE ],
				$plugin_version
			)
			->once();

		$subject->admin_enqueue_scripts();
	}

	/**
	 * Test activate() for plugin.
	 *
	 * @throws ReflectionException ReflectionException.
	 * @noinspection PhpConditionAlreadyCheckedInspection
	 * @noinspection PhpVariableIsUsedOnlyInClosureInspection
	 */
	public function test_activate_for_plugin(): void {
		$activate    = true;
		$entity      = 'plugin';
		$new_theme   = '';
		$status      = 'acfe_status';
		$form_fields = $this->get_test_form_fields();
		$entity_name = $form_fields[ $status ]['label'];

		$main    = Mockery::mock( Main::class )->makePartial();
		$form    = Mockery::mock( Form::class )->makePartial();
		$subject = Mockery::mock( Integrations::class )->makePartial();

		$modules  = [
			'ACF Extended Form' => [
				[ $status, 'form' ],
				[ 'acf-extended-pro/acf-extended.php', 'acf-extended/acf-extended.php' ],
				$form,
			],
		];
		$entities = $modules['ACF Extended Form'][1];

		$main->modules = $modules;

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'run_checks' )->with( Integrations::ACTIVATE_ACTION )->once();
		$subject->shouldReceive( 'process_plugins' )->with( $activate, $entities, $entity_name )->once();

		$this->set_protected_property( $subject, 'form_fields', $form_fields );

		FunctionMocker::replace(
			'filter_input',
			static function ( $type, $var_name, $filter ) use ( $activate, $entity, $new_theme, $status ) {
				if ( INPUT_POST === $type && 'activate' === $var_name && FILTER_VALIDATE_BOOLEAN === $filter ) {
					return $activate;
				}

				if ( INPUT_POST === $type && 'entity' === $var_name && FILTER_SANITIZE_FULL_SPECIAL_CHARS === $filter ) {
					return $entity;
				}

				if ( INPUT_POST === $type && 'newTheme' === $var_name && FILTER_SANITIZE_FULL_SPECIAL_CHARS === $filter ) {
					return $new_theme;
				}

				if ( INPUT_POST === $type && 'status' === $var_name && FILTER_SANITIZE_FULL_SPECIAL_CHARS === $filter ) {
					return $status;
				}

				return null;
			}
		);

		$header_remove      = FunctionMocker::replace( 'header_remove' );
		$http_response_code = FunctionMocker::replace( 'http_response_code' );

		WP_Mock::userFunction( 'hcaptcha' )->with()->once()->andReturn( $main );

		$subject->activate();

		$header_remove->wasCalledWithOnce( [ 'Location' ] );
		$http_response_code->wasCalledWithOnce( [ 200 ] );
	}

	/**
	 * Test activate() for theme.
	 *
	 * @throws ReflectionException ReflectionException.
	 * @noinspection PhpConditionAlreadyCheckedInspection
	 * @noinspection PhpVariableIsUsedOnlyInClosureInspection
	 */
	public function test_activate_for_theme(): void {
		$activate    = false; // Deactivate theme.
		$entity      = 'theme';
		$new_theme   = 'twentytwentyfour';
		$status      = 'divi_status';
		$form_fields = $this->get_test_form_fields();

		$subject = Mockery::mock( Integrations::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'run_checks' )->with( Integrations::ACTIVATE_ACTION )->once();
		$subject->shouldReceive( 'process_theme' )->with( $new_theme )->once();

		$this->set_protected_property( $subject, 'form_fields', $form_fields );

		FunctionMocker::replace(
			'filter_input',
			static function ( $type, $var_name, $filter ) use ( $activate, $entity, $new_theme, $status ) {
				if ( INPUT_POST === $type && 'activate' === $var_name && FILTER_VALIDATE_BOOLEAN === $filter ) {
					return $activate;
				}

				if ( INPUT_POST === $type && 'entity' === $var_name && FILTER_SANITIZE_FULL_SPECIAL_CHARS === $filter ) {
					return $entity;
				}

				if ( INPUT_POST === $type && 'newTheme' === $var_name && FILTER_SANITIZE_FULL_SPECIAL_CHARS === $filter ) {
					return $new_theme;
				}

				if ( INPUT_POST === $type && 'status' === $var_name && FILTER_SANITIZE_FULL_SPECIAL_CHARS === $filter ) {
					return $status;
				}

				return null;
			}
		);

		$header_remove      = FunctionMocker::replace( 'header_remove' );
		$http_response_code = FunctionMocker::replace( 'http_response_code' );

		$subject->activate();

		$header_remove->wasCalledWithOnce( [ 'Location' ] );
		$http_response_code->wasCalledWithOnce( [ 200 ] );
	}

	/**
	 * Test process_plugins() with activation when success.
	 *
	 * @noinspection PhpConditionAlreadyCheckedInspection
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_process_activate_plugins_success(): void {
		$activate         = true;
		$plugins          = [ 'acf-extended-pro/acf-extended.php', 'acf-extended/acf-extended.php' ];
		$plugin_name      = 'ACF Extended';
		$plugins_tree     = [
			'plugin'   => 'acf-extended-pro/acf-extended.php',
			'children' => [],
		];
		$activation_stati = [];
		$result           = null;

		$subject = Mockery::mock( Integrations::class )->makePartial();
		$this->set_protected_property( $subject, 'plugins_tree', $plugins_tree );
		$this->set_protected_property( $subject, 'entity', 'plugin' );

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'activate_plugins' )->with( $plugins )->once()->andReturn( $result );
		$subject->shouldReceive( 'plugin_names_from_tree' )
			->with( $plugins_tree )->once()->andReturn( [ $plugin_name ] );
		$subject->shouldReceive( 'get_activation_stati' )->with()->andReturn( $activation_stati );

		WP_Mock::userFunction( 'is_wp_error' )->with( $result )->andReturn( false );
		WP_Mock::userFunction( 'wp_send_json_success' )
			->with(
				[
					'message' => 'ACF Extended plugin is activated.',
					'stati'   => $activation_stati,
				]
			)
			->once();

		$subject->process_plugins( $activate, $plugins, $plugin_name );
	}

	/**
	 * Test process_plugins() with activation when already activated.
	 *
	 * @noinspection PhpConditionAlreadyCheckedInspection
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_process_activate_plugins_already_activated(): void {
		$activate         = true;
		$plugins          = [ 'acf-extended-pro/acf-extended.php', 'acf-extended/acf-extended.php' ];
		$plugin_name      = 'ACF Extended';
		$plugins_tree     = [
			'plugin'   => 'acf-extended-pro/acf-extended.php',
			'children' => [],
		];
		$activation_stati = [];
		$result           = true;

		$subject = Mockery::mock( Integrations::class )->makePartial();
		$this->set_protected_property( $subject, 'plugins_tree', $plugins_tree );
		$this->set_protected_property( $subject, 'entity', 'plugin' );

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'activate_plugins' )->with( $plugins )->once()->andReturn( $result );
		$subject->shouldReceive( 'plugin_names_from_tree' )
			->with( $plugins_tree )->once()->andReturn( [] );
		$subject->shouldReceive( 'get_activation_stati' )->with()->andReturn( $activation_stati );

		WP_Mock::userFunction( 'is_wp_error' )->with( $result )->andReturn( false );
		WP_Mock::userFunction( 'wp_send_json_error' )
			->with(
				[
					'message' => 'Error activating ACF Extended plugin.',
					'stati'   => $activation_stati,
				]
			)
			->once();

		$subject->process_plugins( $activate, $plugins, $plugin_name );
	}

	/**
	 * Test process_plugins() with activation when error.
	 *
	 * @noinspection PhpConditionAlreadyCheckedInspection
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_process_activate_plugins_error(): void {
		$activate         = true;
		$plugins          = [ 'acf-extended-pro/acf-extended.php', 'acf-extended/acf-extended.php' ];
		$plugin_name      = 'ACF Extended';
		$plugins_tree     = [
			'plugin'   => 'acf-extended-pro/acf-extended.php',
			'children' => [],
			'result'   => 'some error',
		];
		$activation_stati = [];

		$subject = Mockery::mock( Integrations::class )->makePartial();
		$this->set_protected_property( $subject, 'plugins_tree', $plugins_tree );
		$this->set_protected_property( $subject, 'entity', 'plugin' );

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'activate_plugins' )->with( $plugins )->once()->andReturn( false );
		$subject->shouldReceive( 'get_activation_stati' )->with()->andReturn( $activation_stati );

		WP_Mock::userFunction( 'wp_send_json_error' )
			->with(
				[
					'message' => 'Error activating ACF Extended plugin.',
					'stati'   => $activation_stati,
				]
			)->once();

		$subject->process_plugins( $activate, $plugins, $plugin_name );
	}

	/**
	 * Test get_activation_stati().
	 *
	 * @return void
	 */
	public function test_get_activation_stati(): void {
		$main = Mockery::mock( Main::class )->makePartial();
		$main->shouldReceive( 'plugin_or_theme_active' )
			->with( 'woocommerce/woocommerce.php' )->andReturn( true );
		$main->shouldReceive( 'plugin_or_theme_active' )
			->with( 'woocommerce-wishlists/woocommerce-wishlists.php' )->andReturn( false );
		$expected = [
			'woocommerce_status'           => true,
			'woocommerce_wishlists_status' => false,
		];

		$main->modules = [
			'WooCommerce Register'  => [
				[ 'woocommerce_status', 'register' ],
				'woocommerce/woocommerce.php',
				'wc_register_class',
			],
			'WooCommerce Wishlists' => [
				[ 'woocommerce_wishlists_status', 'create_list' ],
				'woocommerce-wishlists/woocommerce-wishlists.php',
				'create_class',
			],
		];

		WP_Mock::userFunction( 'hcaptcha' )->andReturn( $main );

		$subject = Mockery::mock( Integrations::class )->makePartial();
		$method  = 'get_activation_stati';

		self::assertSame( $expected, $subject->$method() );
	}

	/**
	 * Test process_plugins() with deactivation.
	 *
	 * @param bool $is_multisite    Is multisite.
	 * @param bool $is_network_wide Is network wide.
	 *
	 * @return void
	 * @noinspection PhpConditionAlreadyCheckedInspection
	 * @dataProvider dp_test_process_deactivate_plugins
	 */
	public function test_process_deactivate_plugins( bool $is_multisite, bool $is_network_wide ): void {
		$activate    = false;
		$plugins     = [ 'acf-extended-pro/acf-extended.php', 'acf-extended/acf-extended.php' ];
		$plugin_name = 'ACF Extended';
		$stati       = [
			'wp_status'   => true,
			'acfe_status' => false,
		];

		$subject = Mockery::mock( Integrations::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'is_network_wide' )->with()->andReturn( $is_network_wide );
		$subject->shouldReceive( 'get_activation_stati' )->with()->once()->andReturn( $stati );

		$network_wide = $is_multisite && $is_network_wide;

		WP_Mock::userFunction( 'is_multisite' )->with()->once()->andReturn( $is_multisite );
		WP_Mock::userFunction( 'deactivate_plugins' )->with( $plugins, true, $network_wide )->once();
		WP_Mock::userFunction( 'wp_send_json_success' )->with(
			[
				'message' => 'ACF Extended plugin is deactivated.',
				'stati'   => $stati,
			]
		)->once();

		$subject->process_plugins( $activate, $plugins, $plugin_name );
	}

	/**
	 * Data provider for test_process_deactivate_plugins().
	 *
	 * @return array
	 */
	public function dp_test_process_deactivate_plugins(): array {
		return [
			'not multisite, not network wide' => [ false, false ],
			'multisite, not network wide'     => [ true, false ],
			'not multisite, network wide'     => [ false, true ],
			'multisite, network wide'         => [ true, true ],
		];
	}

	/**
	 * Test process_theme().
	 *
	 * There is a unique issue with _n() and lower phpunit versions.
	 *
	 * @requires PHP >= 7.4
	 *
	 * @noinspection PhpConditionAlreadyCheckedInspection
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_process_theme(): void {
		$theme         = 'Avada';
		$plugin_tree   = [ 'some plugin tree' ];
		$plugin_names  = [ 'Avada Builder', 'Avada Core' ];
		$stati         = [
			'wp_status' => true,
			'Avada'     => true,
		];
		$themes        = [
			'twentytwentyone' => 'Twenty Twenty-One',
		];
		$default_theme = 'twentytwentyfour';
		$success_arr   = [
			'message'      =>
				'Avada theme is activated. Also, dependent ' .
				implode( ', ', $plugin_names ) .
				' plugins are activated.',
			'stati'        => $stati,
			'themes'       => $themes,
			'defaultTheme' => $default_theme,
		];

		$theme_obj = Mockery::mock( 'WP_Theme' );

		$theme_obj->shouldReceive( 'get' )->with( 'Name' )->andReturn( $theme );

		WP_Mock::userFunction( 'wp_get_theme' )->with()->andReturn( $theme_obj );

		$subject = Mockery::mock( Integrations::class )->makePartial();
		$method  = 'process_theme';

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'get_default_theme' )->with()->once()->andReturn( $default_theme );
		$subject->shouldReceive( 'activate_plugins' )
			->with( [ 'fusion-builder/fusion-builder.php' ] )->once()->andReturn( true );
		$subject->shouldReceive( 'activate_plugins' )
			->with( [ 'fusion-core/fusion-core.php' ] )->once()->andReturn( true );
		$subject->shouldReceive( 'plugin_names_from_tree' )
			->with( $plugin_tree )->andReturnUsing(
				static function () use ( $plugin_names ) {
					static $i = -1;
					$i++;

					return [ $plugin_names[ $i ] ];
				}
			);
		$subject->shouldReceive( 'activate_theme' )->with( $theme )->once()->andReturn( null );
		$subject->shouldReceive( 'get_activation_stati' )->with()->once()->andReturn( $stati );
		$subject->shouldReceive( 'get_themes' )->with()->once()->andReturn( $themes );
		$this->set_protected_property( $subject, 'entity', 'theme' );
		$this->set_protected_property( $subject, 'plugins_tree', $plugin_tree );

		WP_Mock::userFunction( 'wp_send_json_success' )->with( $success_arr )->once();

		$subject->$method( $theme );
	}

	/**
	 * Test process_theme() with empty theme.
	 *
	 * There is a unique issue with _n() and lower phpunit versions.
	 *
	 * @requires PHP >= 7.4
	 *
	 * @noinspection PhpConditionAlreadyCheckedInspection
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_process_theme_with_empty_theme(): void {
		$theme         = '';
		$plugin_tree   = [];
		$stati         = [
			'wp_status' => true,
			'Avada'     => true,
		];
		$default_theme = 'twentytwentyfour';
		$themes        = [
			$default_theme => 'Twenty Twenty-Four',
		];
		$success_arr   = [
			'message'      =>
				"$default_theme theme is activated.",
			'stati'        => $stati,
			'themes'       => $themes,
			'defaultTheme' => $default_theme,
		];

		$theme_obj = Mockery::mock( 'WP_Theme' );

		$theme_obj->shouldReceive( 'get' )->with( 'Name' )->andReturn( $default_theme );

		WP_Mock::userFunction( 'wp_get_theme' )->with()->andReturn( $theme_obj );

		$subject = Mockery::mock( Integrations::class )->makePartial();
		$method  = 'process_theme';

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'get_default_theme' )->with()->twice()->andReturn( $default_theme );
		$subject->shouldReceive( 'activate_theme' )->with( $default_theme )->once()->andReturn( null );
		$subject->shouldReceive( 'get_activation_stati' )->with()->once()->andReturn( $stati );
		$subject->shouldReceive( 'get_themes' )->with()->once()->andReturn( $themes );
		$this->set_protected_property( $subject, 'entity', 'theme' );
		$this->set_protected_property( $subject, 'plugins_tree', $plugin_tree );

		WP_Mock::userFunction( 'wp_send_json_success' )->with( $success_arr )->once();

		$subject->$method( $theme );
	}

	/**
	 * Test process_theme() with empty theme and no default theme.
	 *
	 * There is a unique issue with _n() and lower phpunit versions.
	 *
	 * @requires PHP >= 7.4
	 *
	 * @noinspection PhpConditionAlreadyCheckedInspection
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_process_theme_with_empty_theme_and_no_default_theme(): void {
		$theme         = '';
		$stati         = [
			'wp_status' => true,
			'Avada'     => true,
		];
		$themes        = [
			'twentytwentyone' => 'Twenty Twenty-One',
		];
		$default_theme = '';
		$error_arr     = [
			'message'      =>
				'No default theme found.',
			'stati'        => $stati,
			'themes'       => $themes,
			'defaultTheme' => $default_theme,
		];

		$subject = Mockery::mock( Integrations::class )->makePartial();
		$method  = 'process_theme';

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'get_default_theme' )->with()->twice()->andReturn( '' );
		$subject->shouldReceive( 'get_activation_stati' )->with()->once()->andReturn( $stati );
		$subject->shouldReceive( 'get_themes' )->with()->once()->andReturn( $themes );
		$this->set_protected_property( $subject, 'entity', 'theme' );

		WP_Mock::userFunction( 'wp_send_json_error' )->with( $error_arr )->once();

		$subject->$method( $theme );
	}

	/**
	 * Test process_theme() when cannot be activated.
	 *
	 * There is a unique issue with _n() and lower phpunit versions.
	 *
	 * @requires PHP >= 7.4
	 *
	 * @noinspection PhpConditionAlreadyCheckedInspection
	 * @throws ReflectionException ReflectionException.
	 *
	 * @runTestsInSeparateProcesses
	 * @preserveGlobalState disabled
	 */
	public function test_process_theme_when_cannot_be_activated(): void {
		$theme         = 'Avada';
		$plugin_tree   = [ 'some plugin tree' ];
		$stati         = [
			'wp_status' => true,
			'Avada'     => true,
		];
		$themes        = [
			'twentytwentyone' => 'Twenty Twenty-One',
		];
		$default_theme = 'twentytwentyfour';
		$error_code    = 'some error code';
		$error_message = 'some error message';
		$error_arr     = [
			'message'      => "Error activating $theme theme: $error_message",
			'stati'        => $stati,
			'themes'       => $themes,
			'defaultTheme' => $default_theme,
		];

		$wp_error = Mockery::mock( 'overload:WP_Error' );

		$wp_error->shouldReceive( 'get_error_code' )->andReturn( $error_code );
		$wp_error->shouldReceive( 'get_error_message' )->andReturn( $error_message );
		$wp_error->shouldReceive( 'add' )->with( $error_code, $error_message );

		$subject = Mockery::mock( Integrations::class )->makePartial();
		$method  = 'process_theme';

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'get_default_theme' )->with()->once()->andReturn( $default_theme );
		$subject->shouldReceive( 'activate_plugins' )
			->with( [ 'fusion-builder/fusion-builder.php' ] )->once()->andReturn( true );
		$subject->shouldReceive( 'activate_plugins' )
			->with( [ 'fusion-core/fusion-core.php' ] )->once()->andReturn( true );
		$subject->shouldReceive( 'plugin_names_from_tree' )
			->with( $plugin_tree )->andReturnUsing(
				static function () {
					$plugin_names = [ 'Avada Builder', 'Avada Core' ];

					static $i = -1;
					$i++;

					return [ $plugin_names[ $i ] ];
				}
			);
		$subject->shouldReceive( 'activate_theme' )->with( $theme )->once()->andReturn( $wp_error );
		$subject->shouldReceive( 'get_activation_stati' )->with()->once()->andReturn( $stati );
		$subject->shouldReceive( 'get_themes' )->with()->once()->andReturn( $themes );
		$this->set_protected_property( $subject, 'entity', 'theme' );
		$this->set_protected_property( $subject, 'plugins_tree', $plugin_tree );

		WP_Mock::userFunction( 'is_wp_error' )->andReturnUsing(
			static function ( $thing ) {
				return is_a( $thing, 'WP_Error', true );
			}
		);
		WP_Mock::userFunction( 'wp_send_json_error' )->with( $error_arr )->once();

		$subject->$method( $theme );
	}

	/**
	 * Test activate_plugins().
	 *
	 * @runTestsInSeparateProcesses
	 * @preserveGlobalState disabled
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_activate_plugins(): void {
		$plugins       = [
			'acf-extended-pro/acf-extended.php',
			'acf-extended/acf-extended.php',
		];
		$plugin_trees  = [
			$plugins[0] => [],
			$plugins[1] => [],
		];
		$error_code    = 'some error code';
		$error_message = 'some error message';

		$wp_error = Mockery::mock( 'overload:WP_Error' );

		$wp_error->shouldReceive( 'get_error_code' )->andReturn( $error_code );
		$wp_error->shouldReceive( 'get_error_message' )->andReturn( $error_message );
		$wp_error->shouldReceive( 'add' )->with( $error_code, $error_message );

		WP_Mock::userFunction( 'is_wp_error' )->andReturnUsing(
			static function ( $thing ) {
				return is_a( $thing, 'WP_Error', true );
			}
		);

		$subject = Mockery::mock( Integrations::class )->makePartial();
		$method  = 'activate_plugins';

		$subject->shouldAllowMockingProtectedMethods();

		$subject->shouldReceive( 'build_plugins_tree' )
			->with( $plugins[0] )->once()->andReturn( $plugin_trees[ $plugins[0] ] );
		$subject->shouldReceive( 'build_plugins_tree' )
			->with( $plugins[1] )->once()->andReturn( $plugin_trees[ $plugins[1] ] );

		$subject->shouldReceive( 'activate_plugin_tree' )
			->with( $plugin_trees[ $plugins[0] ] )->once()->andReturn( $wp_error );
		$subject->shouldReceive( 'activate_plugin_tree' )
			->with( $plugin_trees[ $plugins[1] ] )->once()->andReturn( null );

		self::assertNull( $subject->$method( $plugins ) );
	}

	/**
	 * Test activate_plugins() when no such plugins.
	 *
	 * @runTestsInSeparateProcesses
	 * @preserveGlobalState disabled
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_activate_plugins_when_no_such_plugins(): void {
		$plugins       = [
			'acf-extended-pro/acf-extended.php',
			'acf-extended/acf-extended.php',
		];
		$plugin_trees  = [
			$plugins[0] => [],
			$plugins[1] => [],
		];
		$error_code    = 'some error code';
		$error_message = 'some error message';

		$wp_error = Mockery::mock( 'overload:WP_Error' );

		$wp_error->shouldReceive( 'get_error_code' )->andReturn( $error_code );
		$wp_error->shouldReceive( 'get_error_message' )->andReturn( $error_message );
		$wp_error->shouldReceive( 'add' )->with( $error_code, $error_message );

		WP_Mock::userFunction( 'is_wp_error' )->andReturnUsing(
			static function ( $thing ) {
				return is_a( $thing, 'WP_Error', true );
			}
		);

		$subject = Mockery::mock( Integrations::class )->makePartial();
		$method  = 'activate_plugins';

		$subject->shouldAllowMockingProtectedMethods();

		$subject->shouldReceive( 'build_plugins_tree' )
			->with( $plugins[0] )->once()->andReturn( $plugin_trees[ $plugins[0] ] );
		$subject->shouldReceive( 'build_plugins_tree' )
			->with( $plugins[1] )->once()->andReturn( $plugin_trees[ $plugins[1] ] );

		$subject->shouldReceive( 'activate_plugin_tree' )
			->with( $plugin_trees[ $plugins[0] ] )->once()->andReturn( $wp_error );
		$subject->shouldReceive( 'activate_plugin_tree' )
			->with( $plugin_trees[ $plugins[1] ] )->once()->andReturn( $wp_error );

		$results = $subject->$method( $plugins );

		self::assertEquals( $error_code, $results->get_error_code() );
		self::assertEquals( $error_message, $results->get_error_message() );
	}

	/**
	 * Test activate_plugins() with plugins' tree.
	 *
	 * @param false|null $wish_result Result of activation Wishlist plugin.
	 * @param false|null $woo_result  Result of activation WooCommerce plugin.
	 * @param bool       $expected    Expected.
	 *
	 * @return void
	 * @dataProvider        dp_test_activate_plugins_with_plugins_tree
	 * @noinspection        PhpMissingParamTypeInspection
	 *
	 * @runTestsInSeparateProcesses
	 * @preserveGlobalState disabled
	 */
	public function test_activate_plugins_with_plugins_tree( $wish_result, $woo_result, bool $expected ): void {
		$wish_slug     = 'woocommerce-wishlists/woocommerce-wishlists.php';
		$woo_slug      = 'woocommerce/woocommerce.php';
		$plugins_tree  = [
			'plugin'   => $wish_slug,
			'children' => [
				[
					'plugin'   => $woo_slug,
					'children' => [],
				],
			],
		];
		$error_code    = 'some error code';
		$error_message = 'some error message';

		$wp_error = Mockery::mock( 'overload:WP_Error' );

		$wp_error->shouldReceive( 'get_error_code' )->andReturn( $error_code );
		$wp_error->shouldReceive( 'get_error_message' )->andReturn( $error_message );
		$wp_error->shouldReceive( 'add' )->with( $error_code, $error_message );

		WP_Mock::userFunction( 'is_wp_error' )->andReturnUsing(
			static function ( $thing ) {
				return is_a( $thing, 'WP_Error', true );
			}
		);

		$wish_result = false === $wish_result ? $wp_error : $wish_result;
		$woo_result  = false === $woo_result ? $wp_error : $woo_result;

		$main = Mockery::mock( Main::class )->makePartial();

		$main->shouldReceive( 'is_plugin_active' )->with( $wish_slug )->andReturn( false );
		$main->shouldReceive( 'is_plugin_active' )->with( $woo_slug )->andReturn( false );

		$subject = Mockery::mock( Integrations::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'build_plugins_tree' )
			->with( $wish_slug )->once()->andReturn( $plugins_tree );
		$subject->shouldReceive( 'activate_plugin' )->with( $wish_slug )->andReturn( $wish_result );
		$subject->shouldReceive( 'activate_plugin' )->with( $woo_slug )->andReturn( $woo_result );

		WP_Mock::userFunction( 'hcaptcha' )->with()->andReturn( $main );

		$results = $subject->activate_plugins( [ $wish_slug ] );

		if ( $expected ) {
			self::assertNull( $results );
		} else {
			self::assertEquals( $error_code, $results->get_error_code() );
			self::assertEquals( $error_message, $results->get_error_message() );
		}
	}

	/**
	 * Data provider for test_activate_plugins_with_plugins_tree().
	 *
	 * @return array
	 */
	public function dp_test_activate_plugins_with_plugins_tree(): array {
		return [
			'Wishlist activated, WooCommerce not activated' => [ null, false, false ],
			'Wishlist not activated, WooCommerce activated' => [ false, null, false ],
			// phpcs:ignore WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned
			'Wishlist and WooCommerce activated'            => [ null, null, true ],
		];
	}

	/**
	 * Test maybe_activate_plugin().
	 *
	 * @return void
	 */
	public function test_maybe_activate_plugin(): void {
		$plugin = 'some-plugin/some-plugin.php';

		$main = Mockery::mock( Main::class )->makePartial();

		$main->shouldReceive( 'is_plugin_active' )->with( $plugin )->once()->andReturn( false );

		$subject = Mockery::mock( Integrations::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'activate_plugin' )->with( $plugin )->once()->andReturn( null );

		WP_Mock::userFunction( 'hcaptcha' )->with()->once()->andReturn( $main );

		self::assertNull( $subject->maybe_activate_plugin( $plugin ) );
	}

	/**
	 * Test maybe_activate_plugin() when plugin is active.
	 *
	 * @return void
	 */
	public function test_maybe_activate_plugin_when_plugin_is_active(): void {
		$plugin = 'some-plugin/some-plugin.php';

		$main = Mockery::mock( Main::class )->makePartial();

		$main->shouldReceive( 'is_plugin_active' )->with( $plugin )->once()->andReturn( true );

		$subject = Mockery::mock( Integrations::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();

		WP_Mock::userFunction( 'hcaptcha' )->with()->once()->andReturn( $main );

		self::assertTrue( $subject->maybe_activate_plugin( $plugin ) );
	}

	/**
	 * Test build_plugins_tree().
	 *
	 * @return void
	 */
	public function test_build_plugins_tree(): void {
		$plugin_dir    = '/path/to/plugins';
		$wish_req_slug = 'some-requiring-wishlist/some-requiring-wishlist.php';
		$wish          = 'woocommerce-wishlists';
		$wish_slug     = "$wish/$wish.php";
		$woo_slug      = 'woocommerce/woocommerce.php';
		$plugins_tree  = [
			'plugin'   => $wish_req_slug,
			'children' => [
				[
					'plugin'   => $wish_slug,
					'children' => [
						[
							'plugin'   => $woo_slug,
							'children' => [],
						],
					],
				],
			],
		];

		$subject = Mockery::mock( Integrations::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'plugin_dirs_to_slugs' )
			->with( [ $wish ] )->andReturn( [ $wish_slug ] );

		FunctionMocker::replace(
			'constant',
			static function ( $name ) use ( $plugin_dir ) {
				return 'WP_PLUGIN_DIR' === $name ? $plugin_dir : '';
			}
		);

		WP_Mock::userFunction( 'get_plugin_data' )
			->andReturnUsing(
				static function ( $plugin_file ) use ( $plugin_dir, $wish, $wish_req_slug ) {
					if ( $plugin_file === $plugin_dir . '/' . $wish_req_slug ) {
						return [ 'RequiresPlugins' => $wish ];
					}

					return [];
				}
			);

		self::assertSame( $plugins_tree, $subject->build_plugins_tree( $wish_req_slug ) );
	}

	/**
	 * Test plugin_dirs_to_slugs().
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_plugin_dirs_to_slugs(): void {
		$dirs    = [ 'woocommerce-wishlists', 'woocommerce/woocommerce.php' ];
		$plugins = [
			'woocommerce-wishlists/woocommerce-wishlists.php' => [],
			// phpcs:ignore WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned
			'woocommerce/woocommerce.php'                     => [],
		];

		$subject = Mockery::mock( Integrations::class )->makePartial();
		$this->set_protected_property( $subject, 'plugins', $plugins );

		$subject->shouldAllowMockingProtectedMethods();

		self::assertSame( [], $subject->plugin_dirs_to_slugs( [] ) );

		WP_Mock::userFunction( 'get_plugins' )->andReturn( $plugins );

		self::assertSame( array_keys( $plugins ), $subject->plugin_dirs_to_slugs( $dirs ) );
	}

	/**
	 * Test plugin_names_from_tree().
	 *
	 * @return void
	 * @noinspection PhpVariableIsUsedOnlyInClosureInspection
	 *
	 * @runTestsInSeparateProcesses
	 * @preserveGlobalState disabled
	 */
	public function test_plugin_names_from_tree(): void {
		$plugin_dir    = '/path/to/plugins';
		$wish_req_slug = 'some-requiring-wishlist/some-requiring-wishlist.php';
		$wish_req_name = 'Some Plugin Requiring Wishlist';
		$wish          = 'woocommerce-wishlists';
		$wish_slug     = "$wish/$wish.php";
		$woo_slug      = 'woocommerce/woocommerce.php';
		$plugins_tree  = [
			'plugin'   => $wish_req_slug,
			'children' => [
				[
					'plugin'   => $wish_slug,
					'children' => [
						[
							'plugin'   => $woo_slug,
							'children' => [],
							'result'   => null,
						],
					],
					'result'   => null,
				],
			],
			'result'   => null,
		];
		$expected      = [ 'Some Plugin Requiring Wishlist', 'WooCommerce Wishlists', 'WooCommerce' ];
		$error_code    = 'some error code';
		$error_message = 'some error message';

		$main = Mockery::mock( Main::class )->makePartial();

		$main->modules = [
			'WooCommerce Register'  => [
				[ 'woocommerce_status', 'register' ],
				'woocommerce/woocommerce.php',
				'wc_register_class',
			],
			'WooCommerce Wishlists' => [
				[ 'woocommerce_wishlists_status', 'create_list' ],
				'woocommerce-wishlists/woocommerce-wishlists.php',
				'create_class',
			],
		];

		$subject = Mockery::mock( Integrations::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();

		FunctionMocker::replace(
			'constant',
			static function ( $name ) use ( $plugin_dir ) {
				return 'WP_PLUGIN_DIR' === $name ? $plugin_dir : '';
			}
		);

		WP_Mock::userFunction( 'hcaptcha' )->andReturn( $main );
		WP_Mock::userFunction( 'get_plugin_data' )
			->andReturnUsing(
				static function ( $plugin_file ) use ( $plugin_dir, $wish_req_name, $wish_req_slug ) {
					if ( $plugin_file === $plugin_dir . '/' . $wish_req_slug ) {
						return [ 'Name' => $wish_req_name ];
					}

					return [];
				}
			);

		$subject->init_form_fields();

		// All plugins are successfully activated.
		self::assertSame( $expected, $subject->plugin_names_from_tree( $plugins_tree ) );

		// One plugin was not activated.
		$error = Mockery::mock( 'overload:WP_Error' );

		$plugins_tree['result'] = $error;

		unset( $expected[0] );

		$expected = array_values( $expected );

		self::assertSame( $expected, $subject->plugin_names_from_tree( $plugins_tree ) );
	}

	/**
	 * Test activate_theme().
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_activate_theme(): void {
		$theme = 'Divi';

		$wp_theme = Mockery::mock( 'WP_Theme' );
		$subject  = Mockery::mock( Integrations::class )->makePartial();
		$method   = 'activate_theme';

		$wp_theme->shouldReceive( 'exists' )->andReturn( true );

		WP_Mock::userFunction( 'wp_get_theme' )->with( $theme )->once()->andReturn( $wp_theme );
		WP_Mock::userFunction( 'switch_theme' )->with( $theme )->once();

		$this->set_method_accessibility( $subject, 'activate_plugins' );
		self::assertNull( $subject->$method( $theme ) );
	}

	/**
	 * Test activate_theme() when it does not exist.
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_activate_theme_when_not_exist(): void {
		$theme         = 'Divi';
		$error_code    = 'some error code';
		$error_message = 'some error message';

		$wp_error = Mockery::mock( 'overload:WP_Error' );

		$wp_error->shouldReceive( 'get_error_code' )->andReturn( $error_code );
		$wp_error->shouldReceive( 'get_error_message' )->andReturn( $error_message );
		$wp_error->shouldReceive( 'add' )->with( $error_code, $error_message );

		$wp_theme = Mockery::mock( 'WP_Theme' );
		$subject  = Mockery::mock( Integrations::class )->makePartial();
		$method   = 'activate_theme';

		$wp_theme->shouldReceive( 'exists' )->andReturn( false );

		WP_Mock::userFunction( 'wp_get_theme' )->with( $theme )->once()->andReturn( $wp_theme );

		$this->set_method_accessibility( $subject, 'activate_plugins' );

		$result = $subject->$method( $theme );

		self::assertEquals( $error_code, $result->get_error_code() );
		self::assertEquals( $error_message, $result->get_error_message() );
	}

	/**
	 * Test json_data().
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_json_data(): void {
		$message       = 'Test message';
		$stati         = [
			'wp_status'   => true,
			'acfe_status' => false,
		];
		$expected      = [
			'message' => $message,
			'stati'   => $stati,
		];
		$themes        = [
			'twentytwentyone' => 'Twenty Twenty-One',
		];
		$default_theme = 'twentytwentyfour';

		$subject = Mockery::mock( Integrations::class )->makePartial();
		$method  = 'json_data';

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'get_activation_stati' )->with()->twice()->andReturn( $stati );
		$subject->shouldReceive( 'get_themes' )->with()->once()->andReturn( $themes );
		$subject->shouldReceive( 'get_default_theme' )->with()->once()->andReturn( $default_theme );

		// Plugin.
		self::assertSame( $expected, $subject->$method( $message ) );

		// Theme.
		$this->set_protected_property( $subject, 'entity', 'theme' );

		$expected['themes']       = $themes;
		$expected['defaultTheme'] = $default_theme;

		self::assertSame( $expected, $subject->$method( $message ) );
	}

	/**
	 * Test remove_action_regex().
	 *
	 * @return void
	 */
	public function test_remove_action_regex(): void {
		global $wp_filter;

		for ( $i = 1; $i <= 6; $i++ ) {
			$action[ $i ] = [
				'name' . $i => [
					'function'      => [ 'SomeClass' . $i, 'some_method' . $i ],
					'accepted_args' => 1,
				],
			];
		}

		$init_callbacks = (object) [
			'callbacks' => [
				10 => array_merge( $action[1], $action[2] ),
				20 => $action[3],
			],
		];
		$ast_callbacks  = (object) [
			'callbacks' => [
				0 => $action[4],
				5 => array_merge( $action[5], $action[6] ),
			],
		];

		$callback_pattern = '/^Avada/';

		$subject = Mockery::mock( Integrations::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();

		foreach ( $init_callbacks->callbacks as $priority => $actions ) {
			foreach ( $actions as $action ) {
				$subject->shouldReceive( 'maybe_remove_action_regex' )
					->with( $callback_pattern, 'init', $action, $priority )->once();
			}
		}

		foreach ( $ast_callbacks->callbacks as $priority => $actions ) {
			foreach ( $actions as $action ) {
				$subject->shouldReceive( 'maybe_remove_action_regex' )
					->with( $callback_pattern, 'after_switch_theme', $action, $priority )->once();
			}
		}

		WP_Mock::userFunction( 'current_action' )->andReturn( 'init' );

		// No actions.
		$subject->remove_action_regex( $callback_pattern );

		// No callbacks for 'init'.
		// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited
		$wp_filter['init'] = [];

		$subject->remove_action_regex( $callback_pattern );

		// Three callback for 'init'.
		// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited
		$wp_filter['init'] = $init_callbacks;

		$subject->remove_action_regex( $callback_pattern );

		// Three callback for 'after_switch_theme'.
		// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited
		$wp_filter['after_switch_theme'] = $ast_callbacks;

		$subject->remove_action_regex( $callback_pattern, 'after_switch_theme' );
		// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited
	}

	/**
	 * Test maybe_remove_action_regex().
	 *
	 * @return void
	 */
	public function test_maybe_remove_action_regex(): void {
		$callback_pattern = '/^Avada/';
		$hook_name        = 'after_switch_theme';
		$action           = [];
		$priority         = 10;

		$subject = Mockery::mock( Integrations::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();

		// Callback is closure.
		$action['function'] = static function () {
			return true;
		};

		$subject->maybe_remove_action_regex( $callback_pattern, $hook_name, $action, $priority );

		// Callback is array. Class is an object.
		$action['function'] = [ $this, 'some_method' ];

		$subject->maybe_remove_action_regex( $callback_pattern, $hook_name, $action, $priority );

		// Callback is array. Class is a string.
		$action['function'] = [ 'SomeClass', 'some_method' ];

		$subject->maybe_remove_action_regex( $callback_pattern, $hook_name, $action, $priority );

		// Callback is a string.
		$action['function'] = 'some_function';

		$subject->maybe_remove_action_regex( $callback_pattern, $hook_name, $action, $priority );

		// Callback is matched class and method.
		$action['function'] = [ 'AvadaClass', 'some_method' ];

		WP_Mock::userFunction( 'remove_action' )->with( $hook_name, $action['function'], $priority )->once();

		$subject->maybe_remove_action_regex( $callback_pattern, $hook_name, $action, $priority );
	}
}
