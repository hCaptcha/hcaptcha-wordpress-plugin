<?php
/**
 * ToolsTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Unit\Settings;

use HCaptcha\Settings\Tools;
use HCaptcha\Tests\Unit\HCaptchaTestCase;
use Mockery;
use tad\FunctionMocker\FunctionMocker;
use WP_Mock;

/**
 * Class ToolsTest
 *
 * @group settings
 * @group settings-tools
 */
class ToolsTest extends HCaptchaTestCase {

	/**
	 * Tear down the test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		$_POST  = [];
		$_FILES = [];

		parent::tearDown();
	}

	/**
	 * Test page_title().
	 */
	public function test_page_title(): void {
		$subject = Mockery::mock( Tools::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();

		self::assertSame( 'Tools', $subject->page_title() );
	}

	/**
	 * Test menu_title().
	 */
	public function test_menu_title(): void {
		$subject = Mockery::mock( Tools::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();

		self::assertSame( 'Tools', $subject->menu_title() );
	}

	/**
	 * Test section_title().
	 */
	public function test_section_title(): void {
		$subject = Mockery::mock( Tools::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();

		self::assertSame( 'tools', $subject->section_title() );
	}

	/**
	 * Test init_hooks().
	 */
	public function test_init_hooks(): void {
		$subject = Mockery::mock( Tools::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();

		// Avoid running real logic inside is_tab_active() that depends on WP env.
		$subject->shouldReceive( 'is_tab_active' )->with( $subject )->andReturn( false );

		// Expect registration of AJAX hooks for export and import actions.
		WP_Mock::expectActionAdded( 'wp_ajax_' . Tools::EXPORT_ACTION, [ $subject, 'ajax_handle_export' ] );
		WP_Mock::expectActionAdded( 'wp_ajax_' . Tools::IMPORT_ACTION, [ $subject, 'ajax_handle_import' ] );

		$subject->shouldAllowMockingProtectedMethods();

		$subject->init_hooks();
	}

	/**
	 * Test admin_enqueue_scripts().
	 */
	public function test_admin_enqueue_scripts(): void {
		$plugin_url     = 'http://test.test/wp-content/plugins/hcaptcha-wordpress-plugin';
		$plugin_version = '1.0.0';
		$min            = '.min';
		$ajax_url       = 'https://test.test/wp-admin/admin-ajax.php';
		$nonce          = 'some_nonce';

		$subject = Mockery::mock( Tools::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();

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
				Tools::HANDLE,
				$plugin_url . "/assets/js/tools$min.js",
				[ 'jquery' ],
				$plugin_version,
				true
			)
			->once();

		WP_Mock::userFunction( 'wp_enqueue_style' )
			->with(
				Tools::HANDLE,
				$plugin_url . "/assets/css/tools$min.css",
				[],
				$plugin_version
			)
			->once();

		WP_Mock::userFunction( 'admin_url' )
			->with( 'admin-ajax.php' )
			->andReturn( $ajax_url )
			->once();

		WP_Mock::userFunction( 'wp_create_nonce' )
			->with( Tools::EXPORT_ACTION )
			->andReturn( $nonce )
			->once();

		WP_Mock::userFunction( 'wp_create_nonce' )
			->with( Tools::IMPORT_ACTION )
			->andReturn( $nonce )
			->once();

		WP_Mock::userFunction( 'wp_localize_script' )
			->with(
				Tools::HANDLE,
				Tools::OBJECT,
				[
					'ajaxUrl'        => $ajax_url,
					'exportAction'   => Tools::EXPORT_ACTION,
					'exportNonce'    => $nonce,
					'importAction'   => Tools::IMPORT_ACTION,
					'importNonce'    => $nonce,
					'exportFailed'   => 'Export failed.',
					'importFailed'   => 'Import failed.',
					'selectJsonFile' => 'Please select a JSON file.',
				]
			)
			->once();

		$subject->admin_enqueue_scripts();
	}

	/**
	 * Test ajax_handle_export().
	 */
	public function test_ajax_handle_export(): void {
		$subject = Mockery::mock( Tools::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'run_checks' )->with( Tools::EXPORT_ACTION )->once();

		$include_keys = true;

		FunctionMocker::replace(
			'HCaptcha\Helpers\Request::filter_input',
			static function ( $type, $name ) use ( $include_keys ) {
				if ( INPUT_POST === $type && 'include_keys' === $name ) {
					return $include_keys ? 'on' : 'off';
				}

				return null;
			}
		);

		$export_data = [ 'some' => 'data' ];

		// Use overload to mock the internally instantiated class.
		$transfer = Mockery::mock( 'overload:HCaptcha\Settings\SettingsTransfer' );

		$transfer->shouldReceive( 'build_export_payload' )
			->with( $include_keys )
			->once()
			->andReturn( $export_data );

		WP_Mock::userFunction( 'wp_send_json' )
			->with( $export_data )
			->once();

		$subject->ajax_handle_export();
	}

	/**
	 * Test ajax_handle_import() success.
	 */
	public function test_ajax_handle_import_success(): void {
		$subject = Mockery::mock( Tools::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'run_checks' )->with( Tools::IMPORT_ACTION )->once();

		$tmp_name     = tempnam( sys_get_temp_dir(), 'hcap_import' );
		$json_content = '{"key":"value"}';
		$decoded_data = [ 'key' => 'value' ];

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $tmp_name, $json_content );

		$_FILES['import_file']['tmp_name'] = $tmp_name;

		WP_Mock::userFunction( 'sanitize_text_field' )
			->with( $tmp_name )
			->andReturn( $tmp_name )
			->once();

		$transfer = Mockery::mock( 'overload:HCaptcha\Settings\SettingsTransfer' );
		$transfer->shouldReceive( 'apply_import_payload' )
			->with( $decoded_data, true )
			->once()
			->andReturn( null );

		WP_Mock::userFunction( 'is_wp_error' )->andReturn( false );

		WP_Mock::userFunction( 'wp_send_json_success' )
			->with( [ 'message' => 'hCaptcha settings were successfully imported.' ] )
			->once();

		$subject->ajax_handle_import();

		if ( file_exists( $tmp_name ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			unlink( $tmp_name );
		}
	}

	/**
	 * Test ajax_handle_import() failure.
	 */
	public function test_ajax_handle_import_failure(): void {
		$subject = Mockery::mock( Tools::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'run_checks' )->with( Tools::IMPORT_ACTION )->once();

		$tmp_name     = tempnam( sys_get_temp_dir(), 'hcap_import' );
		$json_content = '{"error":"payload"}';
		$decoded_data = [ 'error' => 'payload' ];

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $tmp_name, $json_content );

		$_FILES['import_file']['tmp_name'] = $tmp_name;

		WP_Mock::userFunction( 'sanitize_text_field' )
			->with( $tmp_name )
			->andReturn( $tmp_name );

		$error_message = 'Import failed reason';
		$error         = Mockery::mock( 'overload:WP_Error' );

		$error->shouldReceive( 'get_error_message' )->andReturn( $error_message );

		$transfer = Mockery::mock( 'overload:HCaptcha\Settings\SettingsTransfer' );

		$transfer->shouldReceive( 'apply_import_payload' )
			->with( $decoded_data, true )
			->andReturn( $error );

		WP_Mock::userFunction( 'is_wp_error' )->with( $error )->andReturn( true );

		WP_Mock::userFunction( 'wp_send_json_error' )
			->with( [ 'message' => $error_message ] )
			->once();

		$subject->ajax_handle_import();

		if ( file_exists( $tmp_name ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			unlink( $tmp_name );
		}
	}

	/**
	 * Test section_callback() default (sections are open).
	 */
	public function test_section_callback(): void {
		$subject = Mockery::mock( Tools::class )->makePartial()->shouldAllowMockingProtectedMethods();
		$user    = (object) [ 'ID' => 1 ];

		WP_Mock::userFunction( 'wp_get_current_user' )->with()->andReturn( $user );
		WP_Mock::userFunction( 'get_user_meta' )->with( $user->ID, Tools::USER_SETTINGS_META, true )->andReturn( [] );
		WP_Mock::expectAction( 'kagg_settings_header' );

		WP_Mock::userFunction( 'esc_html_e' )->andReturnUsing(
			static function ( $text ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $text;
			}
		);
		WP_Mock::userFunction( 'esc_attr_e' )->andReturnUsing(
			static function ( $text ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $text;
			}
		);

		ob_start();
		$subject->section_callback( [] );
		$output = ob_get_clean();

		self::assertStringContainsString( '<div class="hcaptcha-header-bar">', $output );
		self::assertStringContainsString( '<div id="hcaptcha-message"></div>', $output );
		self::assertStringContainsString( '<h3 class="hcaptcha-section-export">', $output );
		self::assertStringContainsString( '<h3 class="hcaptcha-section-import">', $output );
		self::assertStringContainsString( 'Manage the export and import of hCaptcha plugin settings.', $output );
		self::assertStringContainsString( 'Export your hCaptcha settings to a JSON file.', $output );
		self::assertStringContainsString( 'Import your hCaptcha settings from a JSON file.', $output );
	}

	/**
	 * Test section_callback() with closed sections from user meta.
	 */
	public function test_section_callback_closed_sections(): void {
		$subject = Mockery::mock( Tools::class )->makePartial()->shouldAllowMockingProtectedMethods();
		$user    = (object) [ 'ID' => 1 ];

		WP_Mock::userFunction( 'wp_get_current_user' )->with()->andReturn( $user );
		WP_Mock::userFunction( 'get_user_meta' )->with( $user->ID, Tools::USER_SETTINGS_META, true )
			->andReturn(
				[
					'sections' =>
						[
							'export' => false,
							'import' => false,
						],
				]
			);
		WP_Mock::expectAction( 'kagg_settings_header' );

		WP_Mock::userFunction( 'esc_html_e' )->andReturnUsing(
			static function ( $text ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $text;
			}
		);
		WP_Mock::userFunction( 'esc_attr_e' )->andReturnUsing(
			static function ( $text ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $text;
			}
		);

		ob_start();
		$subject->section_callback( [] );
		$output = ob_get_clean();

		self::assertStringContainsString( '<h3 class="hcaptcha-section-export closed">', $output );
		self::assertStringContainsString( '<h3 class="hcaptcha-section-import closed">', $output );
	}
}
