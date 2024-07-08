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
	 * Test page_title().
	 */
	public function test_page_title() {
		$subject = Mockery::mock( Integrations::class )->makePartial()->shouldAllowMockingProtectedMethods();

		$method = 'page_title';
		self::assertSame( 'Integrations', $subject->$method() );
	}

	/**
	 * Test section_title().
	 */
	public function test_section_title() {
		$subject = Mockery::mock( Integrations::class )->makePartial()->shouldAllowMockingProtectedMethods();

		$method = 'section_title';
		self::assertSame( 'integrations', $subject->$method() );
	}

	/**
	 * Test init().
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_init() {
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
	public function test_init_hooks() {
		$plugin_base_name = 'hcaptcha-wordpress-plugin/hcaptcha.php';

		$subject = Mockery::mock( Integrations::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'plugin_basename' )->andReturn( $plugin_base_name );
		$subject->shouldReceive( 'is_tab_active' )->with( $subject )->andReturn( false );

		WP_Mock::expectActionAdded( 'kagg_settings_header', [ $subject, 'search_box' ] );
		WP_Mock::expectActionAdded( 'wp_ajax_' . Integrations::ACTIVATE_ACTION, [ $subject, 'activate' ] );

		$method = 'init_hooks';

		$subject->$method();
	}

	/**
	 * Test activated_plugin_action().
	 *
	 * @return void
	 */
	public function test_activated_plugin_action() {
		$subject = Mockery::mock( Integrations::class )->makePartial();

		WP_Mock::userFunction( 'remove_action' )
			->with( 'activated_plugin', 'Brizy_Admin_GettingStarted::redirectAfterActivation' )
			->once();

		$subject->activated_plugin_action();
	}

	/**
	 * Test init_form_fields().
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_init_form_fields() {
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
	public function test_setup_fields() {
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
	public function test_setup_fields_not_on_options_screen() {
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
	public function test_plugin_or_theme_installed() {
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
	public function test_search_box() {
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
	public function test_section_callback( string $id, string $expected ) {
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
			Manage integrations with popular plugins such as Contact Form 7, WPForms, Gravity Forms, and more.		</p>
		<p>
			You can activate and deactivate a plugin by clicking on its logo.		</p>
		<p>
			Don\'t see your plugin here? Use the `[hcaptcha]` <a href="https://wordpress.org/plugins/hcaptcha-for-forms-and-more/#does%20the%20%5Bhcaptcha%5D%20shortcode%20have%20arguments%3F" target="_blank">shortcode</a> or <a href="https://github.com/hCaptcha/hcaptcha-wordpress-plugin/issues" target="_blank">request an integration</a>.		</p>
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
	public function test_admin_enqueue_scripts() {
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
					'ajaxUrl'            => $ajax_url,
					'action'             => Integrations::ACTIVATE_ACTION,
					'nonce'              => $nonce,
					'activateMsg'        => 'Activate %s plugin?',
					'deactivateMsg'      => 'Deactivate %s plugin?',
					'installMsg'         => 'Please install %s plugin manually.',
					'activateThemeMsg'   => 'Activate %s theme?',
					'deactivateThemeMsg' => 'Deactivate %s theme?',
					'installThemeMsg'    => 'Please install %s theme manually.',
					'selectThemeMsg'     => 'Select theme to activate:',
					'onlyOneThemeMsg'    => 'Cannot deactivate the only theme on the site.',
					'unexpectedErrorMsg' => 'Unexpected error.',
					'OKBtnText'          => 'OK',
					'CancelBtnText'      => 'Cancel',
					'themes'             => [ 'twentytwentyone' => 'Twenty Twenty-One' ],
					'defaultTheme'       => 'twentytwentyone',
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
	public function test_activate_for_plugin() {
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
	public function test_activate_for_theme() {
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
	 * Test process_plugins() with activation.
	 *
	 * @noinspection PhpConditionAlreadyCheckedInspection
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_process_activate_plugins() {
		$activate         = true;
		$plugins          = [ 'acf-extended-pro/acf-extended.php', 'acf-extended/acf-extended.php' ];
		$plugin_name      = 'ACF Extended';
		$plugins_tree     = [ 'acf-extended-pro/acf-extended.php' => [] ];
		$activation_stati = [];

		$subject = Mockery::mock( Integrations::class )->makePartial();
		$this->set_protected_property( $subject, 'plugins_tree', $plugins_tree );
		$this->set_protected_property( $subject, 'entity', 'plugin' );

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'activate_plugins' )->with( $plugins )->once()->andReturn( false );
		$subject->shouldReceive( 'plugin_names_from_tree' )
			->with( $plugins_tree )->once()->andReturn( [ $plugin_name ] );
		$subject->shouldReceive( 'get_activation_stati' )->with()->andReturn( $activation_stati );

		WP_Mock::userFunction( 'wp_send_json_error' )
			->with(
				[
					'message' => 'Error activating ACF Extended plugin.',
					'stati'   => $activation_stati,
				]
			)->once();
		WP_Mock::userFunction( 'wp_send_json_success' )
			->with(
				[
					'message' => 'ACF Extended plugin is activated.',
					'stati'   => $activation_stati,
				]
			)
			->once();
		WP_Mock::userFunction( 'deactivate_plugins' )->with( $plugins )->once();
		WP_Mock::userFunction( 'wp_send_json_success' )
			->with(
				[
					'message' => 'ACF Extended plugin is deactivated.',
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
	public function test_get_activation_stati() {
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
	 * @noinspection PhpConditionAlreadyCheckedInspection
	 */
	public function test_process_deactivate_plugins() {
		$activate    = false;
		$plugins     = [ 'acf-extended-pro/acf-extended.php', 'acf-extended/acf-extended.php' ];
		$plugin_name = 'ACF Extended';
		$stati       = [
			'wp_status'   => true,
			'acfe_status' => false,
		];

		$subject = Mockery::mock( Integrations::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'get_activation_stati' )->with()->once()->andReturn( $stati );

		WP_Mock::userFunction( 'deactivate_plugins' )->with( $plugins )->once();
		WP_Mock::userFunction( 'wp_send_json_success' )->with(
			[
				'message' => 'ACF Extended plugin is deactivated.',
				'stati'   => $stati,
			]
		)->once();

		$subject->process_plugins( $activate, $plugins, $plugin_name );
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
	public function test_process_theme() {
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
		$error_arr     = [
			'message'      => 'Error activating Avada theme.',
			'stati'        => $stati,
			'themes'       => $themes,
			'defaultTheme' => $default_theme,
		];
		$success_arr   = [
			'message'      =>
				'Avada theme is activated. Also, dependent ' .
				implode( ', ', $plugin_names ) .
				' plugins are activated.',
			'stati'        => $stati,
			'themes'       => $themes,
			'defaultTheme' => $default_theme,
		];

		$subject = Mockery::mock( Integrations::class )->makePartial();
		$method  = 'process_theme';

		$subject->shouldAllowMockingProtectedMethods();
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
		$subject->shouldReceive( 'activate_theme' )->with( $theme )->once()->andReturn( false );
		$subject->shouldReceive( 'get_activation_stati' )->with()->twice()->andReturn( $stati );
		$subject->shouldReceive( 'get_themes' )->with()->twice()->andReturn( $themes );
		$subject->shouldReceive( 'get_default_theme' )->with()->twice()->andReturn( $default_theme );
		$this->set_protected_property( $subject, 'entity', 'theme' );
		$this->set_protected_property( $subject, 'plugins_tree', $plugin_tree );

		WP_Mock::userFunction( 'wp_send_json_error' )->with( $error_arr )->once();
		WP_Mock::userFunction( 'wp_send_json_success' )->with( $success_arr )->once();

		$subject->$method( $theme );
	}

	/**
	 * Test activate_plugins().
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_activate_plugins() {
		$plugins      = [
			'acf-extended-pro/acf-extended.php',
			'acf-extended/acf-extended.php',
		];
		$plugin_trees = [
			$plugins[0] => [],
			$plugins[1] => [],
		];

		$subject = Mockery::mock( Integrations::class )->makePartial();
		$method  = 'activate_plugins';

		$subject->shouldAllowMockingProtectedMethods();

		$subject->shouldReceive( 'build_plugins_tree' )
			->with( $plugins[0] )->once()->andReturn( $plugin_trees[ $plugins[0] ] );
		$subject->shouldReceive( 'build_plugins_tree' )
			->with( $plugins[1] )->once()->andReturn( $plugin_trees[ $plugins[1] ] );

		$subject->shouldReceive( 'activate_plugin_tree' )
			->with( $plugin_trees[ $plugins[0] ] )->once()->andReturn( false );
		$subject->shouldReceive( 'activate_plugin_tree' )
			->with( $plugin_trees[ $plugins[1] ] )->once()->andReturn( null );

		self::assertTrue( $subject->$method( $plugins ) );
	}

	/**
	 * Test activate_plugins() when no such plugins.
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_activate_plugins_when_no_such_plugins() {
		$plugins      = [
			'acf-extended-pro/acf-extended.php',
			'acf-extended/acf-extended.php',
		];
		$plugin_trees = [
			$plugins[0] => [],
			$plugins[1] => [],
		];

		$subject = Mockery::mock( Integrations::class )->makePartial();
		$method  = 'activate_plugins';

		$subject->shouldAllowMockingProtectedMethods();

		$subject->shouldReceive( 'build_plugins_tree' )
			->with( $plugins[0] )->once()->andReturn( $plugin_trees[ $plugins[0] ] );
		$subject->shouldReceive( 'build_plugins_tree' )
			->with( $plugins[1] )->once()->andReturn( $plugin_trees[ $plugins[1] ] );

		$subject->shouldReceive( 'activate_plugin_tree' )
			->with( $plugin_trees[ $plugins[0] ] )->once()->andReturn( false );
		$subject->shouldReceive( 'activate_plugin_tree' )
			->with( $plugin_trees[ $plugins[1] ] )->once()->andReturn( false );

		self::assertFalse( $subject->$method( $plugins ) );
	}

	/**
	 * Test activate_plugins() with plugins' tree.
	 *
	 * @param false|null $wish_result Result of activation Wishlist plugin.
	 * @param false|null $woo_result  Result of activation WooCommerce plugin.
	 * @param bool       $expected    Expected.
	 *
	 * @return void
	 * @dataProvider dp_test_activate_plugins_with_plugins_tree
	 * @throws ReflectionException ReflectionException.
	 * @noinspection PhpMissingParamTypeInspection
	 */
	public function test_activate_plugins_with_plugins_tree( $wish_result, $woo_result, bool $expected ) {
		$wish_slug    = 'woocommerce-wishlists/woocommerce-wishlists.php';
		$woo_slug     = 'woocommerce/woocommerce.php';
		$plugins_tree = [
			'plugin'   => $wish_slug,
			'children' => [
				[
					'plugin'   => $woo_slug,
					'children' => [],
				],
			],
		];

		$subject = Mockery::mock( Integrations::class )->makePartial();
		$method  = 'activate_plugins';

		$subject->shouldAllowMockingProtectedMethods();

		$subject->shouldReceive( 'build_plugins_tree' )
			->with( $wish_slug )->once()->andReturn( $plugins_tree );

		WP_Mock::userFunction( 'is_plugin_active' )->with( $wish_slug )->andReturn( false );
		WP_Mock::userFunction( 'activate_plugin' )->with( $wish_slug )->andReturn( $wish_result );
		WP_Mock::userFunction( 'is_plugin_active' )->with( $woo_slug )->andReturn( false );
		WP_Mock::userFunction( 'activate_plugin' )->with( $woo_slug )->andReturn( $woo_result );

		self::assertSame( $expected, $subject->$method( [ $wish_slug ] ) );
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
	 * Test activate_plugin().
	 *
	 * @return void
	 */
	public function test_activate_plugin() {
		$plugin = 'some-plugin/some-plugin.php';

		$subject = Mockery::mock( Integrations::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();

		WP_Mock::expectActionAdded( 'activated_plugin', [ $subject, 'activated_plugin_action' ], PHP_INT_MIN );
		WP_Mock::userFunction( 'is_plugin_active' )->with( $plugin )->once()->andReturn( false );
		WP_Mock::userFunction( 'activate_plugin' )->with( $plugin )->once()->andReturn( true );

		self::assertTrue( $subject->activate_plugin( $plugin ) );
	}

	/**
	 * Test activate_plugin() when plugin is active.
	 *
	 * @return void
	 */
	public function test_activate_plugin_when_plugin_is_active() {
		$plugin = 'some-plugin/some-plugin.php';

		$subject = Mockery::mock( Integrations::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();

		WP_Mock::expectActionNotAdded( 'activated_plugin', [ $subject, 'activated_plugin_action' ], PHP_INT_MIN );
		WP_Mock::userFunction( 'is_plugin_active' )->with( $plugin )->once()->andReturn( true );
		WP_Mock::userFunction( 'activate_plugin' )->with( $plugin )->never();

		self::assertTrue( $subject->activate_plugin( $plugin ) );
	}

	/**
	 * Test build_plugins_tree().
	 *
	 * @return void
	 */
	public function test_build_plugins_tree() {
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
	public function test_plugin_dirs_to_slugs() {
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
	 */
	public function test_plugin_names_from_tree() {
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
		$error = Mockery::mock( WP_Error::class );

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
	public function test_activate_theme() {
		$theme = 'Divi';

		$wp_theme = Mockery::mock( 'WP_Theme' );
		$subject  = Mockery::mock( Integrations::class )->makePartial();
		$method   = 'activate_theme';

		$wp_theme->shouldReceive( 'exists' )->andReturn( true );

		WP_Mock::userFunction( 'wp_get_theme' )->with( $theme )->once()->andReturn( $wp_theme );
		WP_Mock::userFunction( 'switch_theme' )->with( $theme )->once();

		$this->set_method_accessibility( $subject, 'activate_plugins' );
		self::assertTrue( $subject->$method( $theme ) );
	}

	/**
	 * Test activate_theme() when it does not exist.
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_activate_theme_when_not_exist() {
		$theme = 'Divi';

		$wp_theme = Mockery::mock( 'WP_Theme' );
		$subject  = Mockery::mock( Integrations::class )->makePartial();
		$method   = 'activate_theme';

		$wp_theme->shouldReceive( 'exists' )->andReturn( false );

		WP_Mock::userFunction( 'wp_get_theme' )->with( $theme )->once()->andReturn( $wp_theme );

		$this->set_method_accessibility( $subject, 'activate_plugins' );
		self::assertFalse( $subject->$method( $theme ) );
	}

	/**
	 * Test json_data().
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_json_data() {
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
}
