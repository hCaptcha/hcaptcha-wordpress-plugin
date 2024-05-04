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

		WP_Mock::expectActionAdded( 'kagg_settings_tab', [ $subject, 'search_box' ] );
		WP_Mock::expectActionAdded( 'wp_ajax_' . Integrations::ACTIVATE_ACTION, [ $subject, 'activate' ] );

		$method = 'init_hooks';

		$subject->$method();
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

		$form_fields['wp_status']['disabled'] = false;

		$subject = Mockery::mock( Integrations::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'is_options_screen' )->andReturn( true );

		$this->set_protected_property( $subject, 'form_fields', $form_fields );

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

		self::assertSame( 'wp_status', $first_key );

		foreach ( $form_fields as $form_field ) {
			$section = $form_field['disabled'] ? Integrations::SECTION_DISABLED : Integrations::SECTION_ENABLED;

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
	 * Test search_box().
	 */
	public function test_search_box() {
		$subject  = Mockery::mock( Integrations::class )->makePartial();
		$expected = '		<span id="hcaptcha-integrations-search-wrap">
			<label for="hcaptcha-integrations-search"></label>
			<input
					type="search" id="hcaptcha-integrations-search"
					placeholder="Search plugins and themes...">
		</span>
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
				'		<h2>
			Integrations		</h2>
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

		WP_Mock::userFunction( 'wp_get_themes' )->with()->andReturn( $themes )->once();
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
					'activateThemeMsg'   => 'Activate %s theme?',
					'deactivateThemeMsg' => 'Deactivate %s theme?',
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
	 */
	public function test_process_activate_plugins() {
		$activate    = true;
		$plugins     = [ 'acf-extended-pro/acf-extended.php', 'acf-extended/acf-extended.php' ];
		$plugin_name = 'ACF Extended';

		$subject = Mockery::mock( Integrations::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'activate_plugins' )->with( $plugins )->once()->andReturn( false );

		WP_Mock::userFunction( 'wp_send_json_error' )->with( [ 'message' => 'Error activating ACF Extended plugin.' ] )->once();
		WP_Mock::userFunction( 'wp_send_json_success' )->with( [ 'message' => 'ACF Extended plugin is activated.' ] )->once();
		WP_Mock::userFunction( 'deactivate_plugins' )->with( $plugins )->once();
		WP_Mock::userFunction( 'wp_send_json_success' )->with( [ 'message' => 'ACF Extended plugin is deactivated.' ] )->once();

		$subject->process_plugins( $activate, $plugins, $plugin_name );
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

		$subject = Mockery::mock( Integrations::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();

		WP_Mock::userFunction( 'deactivate_plugins' )->with( $plugins )->once();
		WP_Mock::userFunction( 'wp_send_json_success' )->with( [ 'message' => 'ACF Extended plugin is deactivated.' ] )->once();

		$subject->process_plugins( $activate, $plugins, $plugin_name );
	}

	/**
	 * Test process_theme().
	 *
	 * @noinspection PhpConditionAlreadyCheckedInspection
	 */
	public function test_process_theme() {
		$theme = 'Divi';

		$subject = Mockery::mock( Integrations::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'activate_theme' )->with( $theme )->once()->andReturn( false );

		WP_Mock::userFunction( 'wp_send_json_error' )->with( [ 'message' => 'Error activating Divi theme.' ] )->once();
		WP_Mock::userFunction( 'wp_send_json_success' )->with( [ 'message' => 'Divi theme is activated.' ] )->once();

		$subject->process_theme( $theme );
	}

	/**
	 * Test activate_plugins().
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_activate_plugins() {
		$plugins = [ 'acf-extended-pro/acf-extended.php', 'acf-extended/acf-extended.php' ];

		$subject = Mockery::mock( Integrations::class )->makePartial();
		$method  = 'activate_plugins';

		WP_Mock::userFunction( 'activate_plugin' )->with( $plugins[0] )->once()->andReturn( false );
		WP_Mock::userFunction( 'activate_plugin' )->with( $plugins[1] )->once()->andReturn( null );

		$this->set_method_accessibility( $subject, 'activate_plugins' );
		self::assertTrue( $subject->$method( $plugins ) );
	}

	/**
	 * Test activate_plugins() when no such plugins.
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_activate_plugins_when_no_such_plugins() {
		$plugins = [ 'acf-extended-pro/acf-extended.php', 'acf-extended/acf-extended.php' ];

		$subject = Mockery::mock( Integrations::class )->makePartial();
		$method  = 'activate_plugins';

		WP_Mock::userFunction( 'activate_plugin' )->with( $plugins[0] )->once()->andReturn( false );
		WP_Mock::userFunction( 'activate_plugin' )->with( $plugins[1] )->once()->andReturn( false );

		$this->set_method_accessibility( $subject, 'activate_plugins' );
		self::assertFalse( $subject->$method( $plugins ) );
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
	 */
	public function test_json_data() {
		$message  = 'Test message';
		$subject  = Mockery::mock( Integrations::class )->makePartial();
		$method   = 'json_data';
		$expected = [
			'message' => $message,
		];

		self::assertSame( $expected, $subject->$method( $message ) );
	}

	/**
	 * Test json_data() for theme.
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_json_data_for_theme() {
		$message       = 'Test message';
		$subject       = Mockery::mock( Integrations::class )->makePartial();
		$method        = 'json_data';
		$default_theme = 'twentytwentyone';
		$themes        = [ $default_theme => 'Twenty Twenty-One' ];
		$expected      = [
			'message'      => $message,
			'themes'       => $themes,
			'defaultTheme' => $default_theme,
		];

		$this->set_protected_property( $subject, 'entity', 'theme' );
		$subject->shouldReceive( 'get_themes' )->andReturn( $themes );
		$subject->shouldReceive( 'get_default_theme' )->andReturn( $default_theme );

		self::assertSame( $expected, $subject->$method( $message ) );
	}
}
