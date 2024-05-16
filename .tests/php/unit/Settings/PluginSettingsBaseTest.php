<?php
/**
 * PluginSettingsBaseTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedMethodInspection */
/** @noinspection PhpArrayShapeAttributeCanBeAddedInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Unit\Settings;

use HCaptcha\Settings\PluginSettingsBase;
use HCaptcha\Tests\Unit\HCaptchaTestCase;
use KAGG\Settings\Abstracts\SettingsBase;
use Mockery;
use ReflectionClass;
use ReflectionException;
use tad\FunctionMocker\FunctionMocker;
use WP_Mock;

/**
 * Class PluginSettingsBaseTest
 *
 * @group settings
 * @group plugin-settings-base
 */
class PluginSettingsBaseTest extends HCaptchaTestCase {

	/**
	 * Test constructor.
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_constructor() {
		$classname = PluginSettingsBase::class;

		$subject = Mockery::mock( $classname )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'process_args' )->once()->with( [] );
		$subject->shouldReceive( 'is_tab' )->once()->with()->andReturn( true );
		$subject->shouldReceive( 'init' )->once()->with();
		$this->set_protected_property( $subject, 'admin_mode', SettingsBase::MODE_TABS );

		WP_Mock::expectFilterAdded( 'admin_footer_text', [ $subject, 'admin_footer_text' ] );
		WP_Mock::expectFilterAdded( 'update_footer', [ $subject, 'update_footer' ], PHP_INT_MAX );

		WP_Mock::userFunction( 'wp_parse_args' )->andReturnUsing(
			function ( $args, $defaults ) {
				return array_merge( $defaults, $args );
			}
		);

		$constructor = ( new ReflectionClass( $classname ) )->getConstructor();

		self::assertNotNull( $constructor );

		$constructor->invoke( $subject );
	}

	/**
	 * Test menu_title() in tabs mode.
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_menu_title_in_tabs_mode() {
		$plugin_url = 'http://test.test/wp-content/plugins/hcaptcha-wordpress-plugin';
		$menu_title = 'hCaptcha';
		$icon       = "<img class=\"kagg-settings-menu-image\" src=\"$plugin_url/assets/images/hcaptcha-icon.svg\" alt=\"hCaptcha icon\">";
		$subject    = Mockery::mock( PluginSettingsBase::class )->makePartial()->shouldAllowMockingProtectedMethods();
		$constant   = FunctionMocker::replace( 'constant', $plugin_url );

		$this->set_protected_property( $subject, 'admin_mode', SettingsBase::MODE_TABS );

		$method = 'menu_title';
		self::assertSame( $icon . '<span class="kagg-settings-menu-title">' . $menu_title . '</span>', $subject->$method() );
		$constant->wasCalledWithOnce( [ 'HCAPTCHA_URL' ] );
	}

	/**
	 * Test menu_title() in pages mode.
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_menu_title_in_pages_mode() {
		$menu_title = 'hCaptcha';
		$subject    = Mockery::mock( PluginSettingsBase::class )->makePartial()->shouldAllowMockingProtectedMethods();

		$this->set_protected_property( $subject, 'admin_mode', SettingsBase::MODE_PAGES );

		$method = 'menu_title';
		self::assertSame( $menu_title, $subject->$method() );
	}

	/**
	 * Test option_group().
	 */
	public function test_option_group() {
		$subject = Mockery::mock( PluginSettingsBase::class )->makePartial()->shouldAllowMockingProtectedMethods();
		$method  = 'option_group';

		self::assertSame( 'hcaptcha_group', $subject->$method() );
	}

	/**
	 * Test option_page().
	 *
	 * @param string $admin_mode        Mode.
	 * @param bool   $is_main_menu_page Whether it is the main menu page.
	 * @param string $expected          Expected.
	 *
	 * @dataProvider dp_test_option_page
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_option_page( string $admin_mode, bool $is_main_menu_page, string $expected ) {
		$tab_name = 'integrations';

		$subject = Mockery::mock( PluginSettingsBase::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();

		$this->set_protected_property( $subject, 'admin_mode', $admin_mode );
		$subject->shouldReceive( 'is_main_menu_page' )->with()->andReturn( $is_main_menu_page );
		$subject->shouldReceive( 'tab_name' )->with()->andReturn( $tab_name );

		$method = 'option_page';

		self::assertSame( $expected, $subject->$method() );
	}

	/**
	 * Data provider for test_option_page().
	 *
	 * @return array
	 */
	public function dp_test_option_page(): array {
		return [
			'Tabs mode'            => [ SettingsBase::MODE_TABS, false, 'hcaptcha' ],
			'Pages mode, not main' => [ SettingsBase::MODE_PAGES, false, 'hcaptcha-integrations' ],
			'Pages mode, main'     => [ SettingsBase::MODE_PAGES, true, 'hcaptcha' ],
		];
	}

	/**
	 * Test option_name().
	 */
	public function test_option_name() {
		$subject = Mockery::mock( PluginSettingsBase::class )->makePartial()->shouldAllowMockingProtectedMethods();
		$method  = 'option_name';

		self::assertSame( 'hcaptcha_settings', $subject->$method() );
	}

	/**
	 * Test plugin_basename().
	 */
	public function test_plugin_basename() {
		$plugin_file      = '/var/www/wp-content/plugins/hcaptcha-wordpress-plugin/hcaptcha.php';
		$plugin_base_name = 'hcaptcha-wordpress-plugin/hcaptcha.php';

		$subject = Mockery::mock( PluginSettingsBase::class )->makePartial()->shouldAllowMockingProtectedMethods();

		$constant = FunctionMocker::replace( 'constant', $plugin_file );

		WP_Mock::userFunction( 'plugin_basename' )->with( $plugin_file )->once()->andReturn( $plugin_base_name );

		$method = 'plugin_basename';
		self::assertSame( $plugin_base_name, $subject->$method() );
		$constant->wasCalledWithOnce( [ 'HCAPTCHA_FILE' ] );
	}

	/**
	 * Test plugin_url().
	 */
	public function test_plugin_url() {
		$plugin_url = 'http://test.test/wp-content/plugins/hcaptcha-wordpress-plugin';
		$subject    = Mockery::mock( PluginSettingsBase::class )->makePartial()->shouldAllowMockingProtectedMethods();
		$constant   = FunctionMocker::replace( 'constant', $plugin_url );

		$method = 'plugin_url';
		self::assertSame( $plugin_url, $subject->$method() );
		$constant->wasCalledWithOnce( [ 'HCAPTCHA_URL' ] );
	}

	/**
	 * Test plugin_version().
	 */
	public function test_plugin_version() {
		$plugin_version = '1.0.0';

		$subject = Mockery::mock( PluginSettingsBase::class )->makePartial()->shouldAllowMockingProtectedMethods();

		$constant = FunctionMocker::replace( 'constant', $plugin_version );

		$method = 'plugin_version';
		self::assertSame( $plugin_version, $subject->$method() );
		$constant->wasCalledWithOnce( [ 'HCAPTCHA_VERSION' ] );
	}

	/**
	 * Test settings_link_label().
	 */
	public function test_settings_link_label() {
		$subject = Mockery::mock( PluginSettingsBase::class )->makePartial()->shouldAllowMockingProtectedMethods();

		$method = 'settings_link_label';
		self::assertSame( 'hCaptcha Settings', $subject->$method() );
	}

	/**
	 * Test settings_link_text().
	 */
	public function test_settings_link_text() {
		$subject = Mockery::mock( PluginSettingsBase::class )->makePartial()->shouldAllowMockingProtectedMethods();
		$method  = 'settings_link_text';

		self::assertSame( 'Settings', $subject->$method() );
	}

	/**
	 * Test text_domain().
	 */
	public function test_text_domain() {
		$subject = Mockery::mock( PluginSettingsBase::class )->makePartial()->shouldAllowMockingProtectedMethods();
		$method  = 'text_domain';

		self::assertSame( 'hcaptcha-for-forms-and-more', $subject->$method() );
	}

	/**
	 * Test setup_fields().
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_setup_fields() {
		$subject       = Mockery::mock( PluginSettingsBase::class )->makePartial();
		$method        = 'setup_fields';
		$option_page   = 'hcaptcha';
		$section_title = 'some_section_title';
		$prefix        = $option_page . '-' . $section_title . '-';
		$form_fields   = $this->get_test_form_fields();

		$this->set_protected_property( $subject, 'form_fields', $form_fields );

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'section_title' )->with()->once()->andReturn( $section_title );
		$subject->shouldReceive( 'is_options_screen' )->with()->once()->andReturn( false );

		$subject->$method();

		$form_fields = $this->get_protected_property( $subject, 'form_fields' );

		foreach ( $form_fields as $key => $form_field ) {
			self::assertSame( str_replace( '_', '-', $prefix . $key ), $form_field['class'] );
		}
	}

	/**
	 * Test settings_page().
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_settings_page() {
		$plugin_url  = 'http://test.test/wp-content/plugins/hcaptcha-wordpress-plugin';
		$title       = 'some-section-title';
		$option_page = 'hcaptcha';
		$submit      = '<input type="submit" name="submit" id="submit" class="button button-primary" value="Save Changes">';
		$subject     = Mockery::mock( PluginSettingsBase::class )->makePartial();
		$method      = 'settings_page';
		$constant    = FunctionMocker::replace( 'constant', $plugin_url );
		$expected    = "		<img
				src=\"$plugin_url/assets/images/hcaptcha-logo.svg\"
				alt=\"hCaptcha Logo\"
				class=\"hcaptcha-logo\"
		/>

		<form
				id=\"hcaptcha-options\"
				class=\"hcaptcha-$title\"
				action=\"options.php\"
				method=\"post\">
			$submit		</form>
		";

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'section_title' )->with()->once()->andReturn( $title );
		$subject->shouldReceive( 'option_page' )->with()->once()->andReturn( $option_page );

		$this->set_protected_property( $subject, 'form_fields', [ 'some' ] );

		WP_Mock::passthruFunction( 'admin_url' );
		WP_Mock::userFunction( 'do_settings_sections' )->with( $option_page )->once();
		WP_Mock::userFunction( 'settings_fields' )->with( 'hcaptcha_group' )->once();
		WP_Mock::userFunction( 'submit_button' )->with()->once()->andReturnUsing(
			function () use ( $submit ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $submit;
			}
		);

		ob_start();
		$subject->$method();

		self::assertSame( $expected, ob_get_clean() );
		$constant->wasCalledWithOnce( [ 'HCAPTCHA_URL' ] );
	}

	/**
	 * Test submit_button().
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_submit_button() {
		$subject = Mockery::mock( PluginSettingsBase::class )->makePartial();
		$method  = 'submit_button';
		$submit  = '<input type="submit" name="submit" id="submit" class="button button-primary" value="Save Changes">';

		$this->set_protected_property( $subject, 'submit_shown', false );

		WP_Mock::userFunction( 'submit_button' )->with()->once()->andReturnUsing(
			function () use ( $submit ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $submit;
			}
		);

		ob_start();
		$subject->$method();
		self::assertSame( $submit, ob_get_clean() );

		ob_start();
		$subject->$method();
		self::assertSame( '', ob_get_clean() );
	}

	/**
	 * Test admin_footer_text().
	 */
	public function test_admin_footer_text() {
		$subject  = Mockery::mock( PluginSettingsBase::class )->makePartial();
		$method   = 'admin_footer_text';
		$text     = 'Some text';
		$expected = 'Please rate <strong>hCaptcha for WordPress</strong> <a href="https://wordpress.org/support/plugin/hcaptcha-for-forms-and-more/reviews/?filter=5#new-post" target="_blank" rel="noopener noreferrer">★★★★★</a> on <a href="https://wordpress.org/support/plugin/hcaptcha-for-forms-and-more/reviews/?filter=5#new-post" target="_blank" rel="noopener noreferrer">WordPress.org</a>. Thank you!';

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'is_options_screen' )->with( [] )->andReturn( true );

		WP_Mock::passthruFunction( 'wp_kses' );

		self::assertSame( $expected, $subject->$method( $text ) );
	}

	/**
	 * Test admin_footer_text() on not options' screen.
	 */
	public function test_admin_footer_text_on_not_options_screen() {
		$subject = Mockery::mock( PluginSettingsBase::class )->makePartial();
		$method  = 'admin_footer_text';
		$text    = 'Some text';

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'is_options_screen' )->with( [] )->andReturn( false );

		self::assertSame( $text, $subject->$method( $text ) );
	}

	/**
	 * Test update_footer().
	 */
	public function test_update_footer() {
		$plugin_version = '1.0.0';
		$constant       = FunctionMocker::replace( 'constant', $plugin_version );
		$subject        = Mockery::mock( PluginSettingsBase::class )->makePartial();
		$method         = 'update_footer';
		$content        = 'Some content';
		$expected       = 'Version ' . $plugin_version;

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'is_options_screen' )->withNoArgs()->andReturn( true );

		self::assertSame( $expected, $subject->$method( $content ) );
		$constant->wasCalledWithOnce( [ 'HCAPTCHA_VERSION' ] );
	}

	/**
	 * Test update_footer() not on options' screen.
	 */
	public function test_update_footer_not_on_options_screen() {
		$subject = Mockery::mock( PluginSettingsBase::class )->makePartial();
		$method  = 'update_footer';
		$content = 'Some content';

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'is_options_screen' )->withNoArgs()->andReturn( false );

		self::assertSame( $content, $subject->$method( $content ) );
	}

	/**
	 * Test run_checks().
	 *
	 * @param bool   $referer  Referer.
	 * @param bool   $user_can User can.
	 * @param string $expected Expected check.
	 *
	 * @return void
	 *
	 * @dataProvider dp_test_run_checks
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_run_checks( bool $referer, bool $user_can, string $expected ) {
		$action  = 'some-action';
		$subject = Mockery::mock( PluginSettingsBase::class )->makePartial();
		$method  = 'run_checks';

		$this->set_method_accessibility( $subject, 'run_checks' );

		WP_Mock::userFunction( 'check_ajax_referer' )->with( $action, 'nonce', false )->once()->andReturn( $referer );
		WP_Mock::userFunction( 'current_user_can' )->with( 'manage_options' )->once()->andReturn( $user_can );

		if ( $expected ) {
			WP_Mock::userFunction( 'wp_send_json_error' )->with( $expected )->once();
		}

		$subject->$method( $action );
	}

	/**
	 * Data provider for test_run_checks().
	 *
	 * @return array
	 */
	public function dp_test_run_checks(): array {
		return [
			'OK'              => [ true, true, '' ],
			'Bad referer'     => [ false, true, 'Your session has expired. Please reload the page.' ],
			'No capabilities' => [ true, false, 'You are not allowed to perform this action.' ],
		];
	}
}
