<?php
/**
 * MigrationWizardControllerTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Unit\MigrationWizard;

use HCaptcha\Main;
use HCaptcha\MigrationWizard\DetectionResult;
use HCaptcha\MigrationWizard\MigrationWizard;
use HCaptcha\MigrationWizard\ScanResult;
use HCaptcha\MigrationWizard\Scanner;
use HCaptcha\Settings\General;
use HCaptcha\Settings\Integrations;
use HCaptcha\Settings\Settings;
use HCaptcha\Tests\Unit\HCaptchaTestCase;
use Mockery;
use RuntimeException;
use tad\FunctionMocker\FunctionMocker;
use WP_Mock;

/**
 * Test MigrationWizard controller paths.
 *
 * @group migration-wizard
 */
class MigrationWizardControllerTest extends HCaptchaTestCase {

	/**
	 * Setup test.
	 */
	public function setUp(): void {
		if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
			define( 'HOUR_IN_SECONDS', 3600 );
		}

		parent::setUp();
	}

	/**
	 * Test init().
	 */
	public function test_init(): void {
		$subject = new MigrationWizard();

		WP_Mock::expectActionAdded( 'wp_ajax_' . MigrationWizard::SCAN_ACTION, [ $subject, 'ajax_scan' ] );
		WP_Mock::expectActionAdded( 'wp_ajax_' . MigrationWizard::APPLY_ACTION, [ $subject, 'ajax_apply' ] );

		$subject->init();
	}

	/**
	 * Test admin_enqueue_scripts().
	 */
	public function test_admin_enqueue_scripts(): void {
		$plugin_url     = 'https://example.test/wp-content/plugins/hcaptcha-wordpress-plugin';
		$plugin_version = '1.2.3';
		$min            = '.min';
		$ajax_url       = 'https://example.test/wp-admin/admin-ajax.php';
		$scan_nonce     = 'scan-nonce';
		$apply_nonce    = 'apply-nonce';

		FunctionMocker::replace(
			'constant',
			static function ( $name ) use ( $plugin_url, $plugin_version ) {
				if ( 'HCAPTCHA_URL' === $name ) {
					return $plugin_url;
				}

				if ( 'HCAPTCHA_VERSION' === $name ) {
					return $plugin_version;
				}

				return null;
			}
		);

		WP_Mock::passthruFunction( '__' );
		WP_Mock::userFunction( 'hcap_min_suffix' )->with()->once()->andReturn( $min );

		WP_Mock::userFunction( 'wp_enqueue_script' )
			->with(
				MigrationWizard::DIALOG_HANDLE,
				$plugin_url . "/assets/js/kagg-dialog$min.js",
				[],
				$plugin_version,
				true
			)
			->once();

		WP_Mock::userFunction( 'wp_enqueue_style' )
			->with(
				MigrationWizard::DIALOG_HANDLE,
				$plugin_url . "/assets/css/kagg-dialog$min.css",
				[],
				$plugin_version
			)
			->once();

		WP_Mock::userFunction( 'wp_enqueue_style' )
			->with(
				MigrationWizard::HANDLE,
				$plugin_url . "/assets/css/migration-wizard$min.css",
				[ MigrationWizard::DIALOG_HANDLE ],
				$plugin_version
			)
			->once();

		WP_Mock::userFunction( 'wp_enqueue_script' )
			->with(
				MigrationWizard::HANDLE,
				$plugin_url . "/assets/js/migration-wizard$min.js",
				[ MigrationWizard::DIALOG_HANDLE ],
				$plugin_version,
				true
			)
			->once();

		WP_Mock::userFunction( 'admin_url' )
			->with( 'admin-ajax.php' )
			->once()
			->andReturn( $ajax_url );

		WP_Mock::userFunction( 'wp_create_nonce' )
			->with( 'hcaptcha_migration_scan_nonce' )
			->once()
			->andReturn( $scan_nonce );

		WP_Mock::userFunction( 'wp_create_nonce' )
			->with( 'hcaptcha_migration_apply_nonce' )
			->once()
			->andReturn( $apply_nonce );

		WP_Mock::userFunction( 'wp_localize_script' )
			->with(
				MigrationWizard::HANDLE,
				MigrationWizard::OBJECT,
				Mockery::on(
					static function ( $data ) use ( $ajax_url, $scan_nonce, $apply_nonce ) {
						return $ajax_url === $data['ajaxUrl']
							&& MigrationWizard::SCAN_ACTION === $data['scanAction']
							&& $scan_nonce === $data['scanNonce']
							&& MigrationWizard::APPLY_ACTION === $data['applyAction']
							&& $apply_nonce === $data['applyNonce']
							&& 'OK' === $data['i18n']['okBtnText']
							&& 'reCAPTCHA' === $data['i18n']['providerRecaptcha']
							&& 'Turnstile' === $data['i18n']['providerTurnstile']
							&& isset( $data['i18n']['foundSurfaces'][0], $data['i18n']['migratableCount'][1] );
					}
				)
			)
			->once();

		$subject = new MigrationWizard();

		$subject->admin_enqueue_scripts();
	}

	/**
	 * Test render_section().
	 *
	 * @noinspection JsonEncodingApiUsageInspection
	 */
	public function test_render_section(): void {
		$saved_state = [
			'scan_data' => [
				'total' => 1,
			],
		];
		$settings    = Mockery::mock( Settings::class )->makePartial();
		$main        = Mockery::mock( Main::class )->makePartial();

		$settings->shouldReceive( 'get' )->with( 'site_key' )->once()->andReturn( 'site-key' );
		$settings->shouldReceive( 'get' )->with( 'secret_key' )->once()->andReturn( 'secret-key' );
		$settings->shouldReceive( 'tab_url' )->with( General::class )->once()->andReturn( 'https://example.test/general' );
		$settings->shouldReceive( 'tab_url' )->with( Integrations::class )->once()->andReturn( 'https://example.test/integrations' );
		$main->shouldReceive( 'settings' )->with()->once()->andReturn( $settings );

		WP_Mock::userFunction( 'hcaptcha' )->with()->once()->andReturn( $main );
		WP_Mock::userFunction( 'get_transient' )
			->with( MigrationWizard::STATE_TRANSIENT )
			->once()
			->andReturn( $saved_state );
		WP_Mock::passthruFunction( 'esc_url' );
		WP_Mock::passthruFunction( 'esc_attr' );
		WP_Mock::passthruFunction( 'esc_html__' );
		WP_Mock::userFunction( 'wp_json_encode' )
			->with( $saved_state )
			->once()
			->andReturnUsing(
				static function ( $data ) {
					// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Test fixture for mocked wp_json_encode().
					return json_encode( $data );
				}
			);
		WP_Mock::userFunction( 'esc_html_e' )
			->andReturnUsing(
				static function ( $text ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo $text;
				}
			);

		$subject = new MigrationWizard();

		ob_start();
		$subject->render_section();
		$output = ob_get_clean();

		self::assertStringContainsString( 'id="hcaptcha-migration-wizard"', $output );
		self::assertStringContainsString( 'data-has-keys="1"', $output );
		self::assertStringContainsString( 'data-settings-url="https://example.test/general"', $output );
		self::assertStringContainsString( 'Scan Site', $output );
		self::assertStringContainsString( 'View Integrations', $output );
	}

	/**
	 * Test ajax_scan() with invalid nonce.
	 */
	public function test_ajax_scan_with_invalid_nonce(): void {
		WP_Mock::passthruFunction( '__' );
		WP_Mock::userFunction( 'check_ajax_referer' )
			->with( 'hcaptcha_migration_scan_nonce', 'nonce', false )
			->once()
			->andReturn( false );
		WP_Mock::userFunction( 'wp_send_json_error' )
			->with( [ 'message' => 'Security check failed.' ] )
			->once()
			->andThrow( new RuntimeException( 'json-error' ) );

		$this->expectException( RuntimeException::class );

		$subject = new MigrationWizard();

		$subject->ajax_scan();
	}

	/**
	 * Test ajax_scan() with insufficient permissions.
	 */
	public function test_ajax_scan_with_insufficient_permissions(): void {
		WP_Mock::passthruFunction( '__' );
		WP_Mock::userFunction( 'check_ajax_referer' )
			->with( 'hcaptcha_migration_scan_nonce', 'nonce', false )
			->once()
			->andReturn( true );
		WP_Mock::userFunction( 'current_user_can' )
			->with( MigrationWizard::CAPABILITY )
			->once()
			->andReturn( false );
		WP_Mock::userFunction( 'wp_send_json_error' )
			->with( [ 'message' => 'Insufficient permissions.' ] )
			->once()
			->andThrow( new RuntimeException( 'json-error' ) );

		$this->expectException( RuntimeException::class );

		$subject = new MigrationWizard();

		$subject->ajax_scan();
	}

	/**
	 * Test ajax_scan() success.
	 */
	public function test_ajax_scan_success(): void {
		$data    = [ 'total' => 0 ];
		$subject = Mockery::mock( MigrationWizard::class )->makePartial();

		$subject->shouldReceive( 'scan' )->with()->once()->andReturn( $data );

		WP_Mock::userFunction( 'check_ajax_referer' )
			->with( 'hcaptcha_migration_scan_nonce', 'nonce', false )
			->once()
			->andReturn( true );
		WP_Mock::userFunction( 'current_user_can' )
			->with( MigrationWizard::CAPABILITY )
			->once()
			->andReturn( true );
		WP_Mock::userFunction( 'wp_send_json_success' )->with( $data )->once();

		$subject->ajax_scan();
	}

	/**
	 * Test ajax_apply() with invalid nonce.
	 */
	public function test_ajax_apply_with_invalid_nonce(): void {
		WP_Mock::passthruFunction( '__' );
		WP_Mock::userFunction( 'check_ajax_referer' )
			->with( 'hcaptcha_migration_apply_nonce', 'nonce', false )
			->once()
			->andReturn( false );
		WP_Mock::userFunction( 'wp_send_json_error' )
			->with( [ 'message' => 'Security check failed.' ] )
			->once()
			->andThrow( new RuntimeException( 'json-error' ) );

		$this->expectException( RuntimeException::class );

		$subject = new MigrationWizard();

		$subject->ajax_apply();
	}

	/**
	 * Test ajax_apply() with insufficient permissions.
	 */
	public function test_ajax_apply_with_insufficient_permissions(): void {
		WP_Mock::passthruFunction( '__' );
		WP_Mock::userFunction( 'check_ajax_referer' )
			->with( 'hcaptcha_migration_apply_nonce', 'nonce', false )
			->once()
			->andReturn( true );
		WP_Mock::userFunction( 'current_user_can' )
			->with( MigrationWizard::CAPABILITY )
			->once()
			->andReturn( false );
		WP_Mock::userFunction( 'wp_send_json_error' )
			->with( [ 'message' => 'Insufficient permissions.' ] )
			->once()
			->andThrow( new RuntimeException( 'json-error' ) );

		$this->expectException( RuntimeException::class );

		$subject = new MigrationWizard();

		$subject->ajax_apply();
	}

	/**
	 * Test ajax_apply() without surfaces.
	 */
	public function test_ajax_apply_without_surfaces(): void {
		unset( $_POST['surfaces'] );

		WP_Mock::passthruFunction( '__' );
		$this->mock_wp_error_class( 'no_surfaces_selected', 'No surfaces selected.' );
		$this->mock_is_wp_error();
		$this->mock_successful_apply_checks();
		WP_Mock::userFunction( 'wp_send_json_error' )
			->with( [ 'message' => 'No surfaces selected.' ] )
			->once();

		$subject = new MigrationWizard();

		$subject->ajax_apply();
	}

	/**
	 * Test ajax_apply() with invalid surfaces data.
	 */
	public function test_ajax_apply_with_invalid_surfaces_data(): void {
		$_POST['surfaces'] = 'not-json';

		WP_Mock::passthruFunction( '__' );
		WP_Mock::passthruFunction( 'wp_unslash' );
		WP_Mock::passthruFunction( 'sanitize_text_field' );
		$this->mock_wp_error_class( 'invalid_surfaces_data', 'Invalid surfaces data.' );
		$this->mock_is_wp_error();
		$this->mock_successful_apply_checks();
		WP_Mock::userFunction( 'wp_send_json_error' )
			->with( [ 'message' => 'Invalid surfaces data.' ] )
			->once();

		$subject = new MigrationWizard();

		$subject->ajax_apply();
	}

	/**
	 * Test ajax_apply() when apply() returns an error.
	 *
	 * @noinspection JsonEncodingApiUsageInspection
	 */
	public function test_ajax_apply_with_apply_error(): void {
		$surfaces = $this->get_request_surfaces();
		$error    = new class() {

			/**
			 * Get error message.
			 *
			 * @return string
			 */
			public function get_error_message(): string {
				return 'Apply failed.';
			}
		};
		$subject  = Mockery::mock( MigrationWizard::class )->makePartial();

		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Test request fixture.
		$_POST['surfaces'] = json_encode( $surfaces );

		$subject->shouldReceive( 'apply' )->with( $surfaces )->once()->andReturn( $error );

		WP_Mock::passthruFunction( 'wp_unslash' );
		WP_Mock::passthruFunction( 'sanitize_text_field' );
		$this->mock_is_wp_error();
		$this->mock_successful_apply_checks();
		WP_Mock::userFunction( 'wp_send_json_error' )
			->with( [ 'message' => 'Apply failed.' ] )
			->once();

		$subject->ajax_apply();
	}

	/**
	 * Test ajax_apply() success.
	 *
	 * @noinspection JsonEncodingApiUsageInspection
	 */
	public function test_ajax_apply_success(): void {
		$surfaces = $this->get_request_surfaces();
		$result   = [
			'enabled' => [ 'wp_login', 'wp_register' ],
			'failed'  => [ 'bad_surface' ],
		];
		$subject  = Mockery::mock( MigrationWizard::class )->makePartial();

		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Test request fixture.
		$_POST['surfaces'] = json_encode( $surfaces );

		$subject->shouldReceive( 'apply' )->with( $surfaces )->once()->andReturn( $result );

		WP_Mock::passthruFunction( 'wp_unslash' );
		WP_Mock::passthruFunction( 'sanitize_text_field' );
		$this->mock_is_wp_error();
		$this->mock_successful_apply_checks();
		FunctionMocker::replace(
			'HCaptcha\MigrationWizard\_n',
			static function ( $single, $plural ) {
				return $plural;
			}
		);
		WP_Mock::userFunction( 'wp_send_json_success' )
			->with(
				[
					'enabled' => [ 'wp_login', 'wp_register' ],
					'failed'  => [ 'bad_surface' ],
					'message' => 'Successfully enabled hCaptcha on 2 surfaces.',
				]
			)
			->once();

		$subject->ajax_apply();
	}

	/**
	 * Test apply() with empty surfaces.
	 */
	public function test_apply_with_empty_surfaces(): void {
		WP_Mock::passthruFunction( '__' );
		$this->mock_wp_error_class( 'no_surfaces_selected', 'No surfaces selected.' );

		$subject = new MigrationWizard();
		$result  = $subject->apply( [] );

		self::assertSame( 'no_surfaces_selected', $result->get_error_code() );
	}

	/**
	 * Test apply() without hCaptcha keys.
	 */
	public function test_apply_without_keys(): void {
		$settings = Mockery::mock( Settings::class )->makePartial();
		$main     = Mockery::mock( Main::class )->makePartial();

		$settings->shouldReceive( 'get' )->with( 'site_key' )->once()->andReturn( '' );
		$settings->shouldReceive( 'get' )->with( 'secret_key' )->once()->andReturn( 'secret-key' );
		$main->shouldReceive( 'settings' )->with()->once()->andReturn( $settings );

		WP_Mock::passthruFunction( '__' );
		$this->mock_wp_error_class( 'keys_not_configured', 'hCaptcha keys are not configured.' );
		WP_Mock::userFunction( 'hcaptcha' )->with()->once()->andReturn( $main );
		$this->mock_is_wp_error();

		$subject = new MigrationWizard();
		$result  = $subject->apply( $this->get_request_surfaces() );

		self::assertSame( 'keys_not_configured', $result->get_error_code() );
	}

	/**
	 * Test apply() with invalid and already enabled surfaces.
	 */
	public function test_apply_with_invalid_and_already_enabled_surfaces(): void {
		$settings = Mockery::mock( Settings::class )->makePartial();
		$main     = Mockery::mock( Main::class )->makePartial();

		$settings->shouldReceive( 'get' )->with( 'site_key' )->once()->andReturn( 'site-key' );
		$settings->shouldReceive( 'get' )->with( 'secret_key' )->once()->andReturn( 'secret-key' );
		$settings->shouldReceive( 'get' )->with( 'wp_status' )->once()->andReturn( [ 'login' ] );
		$main->shouldReceive( 'settings' )->with()->twice()->andReturn( $settings );

		WP_Mock::userFunction( 'hcaptcha' )->with()->twice()->andReturn( $main );
		WP_Mock::passthruFunction( 'sanitize_text_field' );
		WP_Mock::userFunction( 'get_transient' )
			->with( MigrationWizard::STATE_TRANSIENT )
			->once()
			->andReturn( false );
		$this->mock_is_wp_error();

		$subject = new MigrationWizard();
		$result  = $subject->apply(
			[
				[],
				[
					'surface'               => 'unknown_surface',
					'hcaptcha_option_key'   => 'wp_status',
					'hcaptcha_option_value' => 'login',
				],
				[
					'surface'               => 'wp_login',
					'hcaptcha_option_key'   => 'wp_status',
					'hcaptcha_option_value' => 'register',
				],
				[
					'surface'               => 'wp_login',
					'hcaptcha_option_key'   => 'wp_status',
					'hcaptcha_option_value' => 'login',
				],
			]
		);

		self::assertSame(
			[
				'enabled' => [ 'wp_login' ],
				'failed'  => [ 'unknown', 'unknown_surface', 'wp_login' ],
			],
			$result
		);
	}

	/**
	 * Test scan() with non-array hCaptcha settings.
	 */
	public function test_scan_with_non_array_settings(): void {
		$detection = new DetectionResult(
			[
				'surface'               => 'wp_login',
				'hcaptcha_option_key'   => 'wp_status',
				'hcaptcha_option_value' => 'login',
			]
		);
		$scanner   = Mockery::mock( Scanner::class );
		$settings  = Mockery::mock();
		$main      = Mockery::mock();
		$subject   = Mockery::mock( MigrationWizard::class )->makePartial();

		$scanner->shouldReceive( 'scan' )->once()->andReturn( new ScanResult( [ $detection ], [], [] ) );
		$settings->shouldReceive( 'get_raw_settings' )->with()->once()->andReturn( 'broken' );
		$main->shouldReceive( 'settings' )->with()->once()->andReturn( $settings );
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'create_scanner' )->once()->andReturn( $scanner );

		WP_Mock::userFunction( 'hcaptcha' )->with()->once()->andReturn( $main );
		WP_Mock::userFunction( 'set_transient' )
			->with(
				MigrationWizard::STATE_TRANSIENT,
				Mockery::on(
					static function ( $state ) {
						return [] === $state['scan_data']['already_enabled'];
					}
				),
				MigrationWizard::STATE_EXPIRATION
			)
			->once();

		$result = $subject->scan();

		self::assertSame( [], $result['already_enabled'] );
	}

	/**
	 * Test scan() skips results without mapping data.
	 */
	public function test_scan_skips_results_without_mapping_data(): void {
		$scanner  = Mockery::mock( Scanner::class );
		$settings = Mockery::mock( Settings::class )->makePartial();
		$main     = Mockery::mock( Main::class )->makePartial();
		$subject  = Mockery::mock( MigrationWizard::class )->makePartial();
		$results  = [
			new DetectionResult( [ 'surface' => 'unknown_surface' ] ),
			new DetectionResult(
				[
					'surface'               => 'wp_register',
					'hcaptcha_option_key'   => 'wp_status',
					'hcaptcha_option_value' => 'register',
				]
			),
		];

		$scanner->shouldReceive( 'scan' )->once()->andReturn( new ScanResult( $results, [], [] ) );
		$settings->shouldReceive( 'get_raw_settings' )
			->with()
			->once()
			->andReturn( [ 'wp_status' => [ 'login' ] ] );
		$main->shouldReceive( 'settings' )->with()->once()->andReturn( $settings );
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'create_scanner' )->once()->andReturn( $scanner );

		WP_Mock::userFunction( 'hcaptcha' )->with()->once()->andReturn( $main );
		WP_Mock::userFunction( 'set_transient' )
			->with(
				MigrationWizard::STATE_TRANSIENT,
				Mockery::on(
					static function ( $state ) {
						return [] === $state['scan_data']['already_enabled'];
					}
				),
				MigrationWizard::STATE_EXPIRATION
			)
			->once();

		$result = $subject->scan();

		self::assertSame( [], $result['already_enabled'] );
	}

	/**
	 * Get request surfaces.
	 *
	 * @return array
	 */
	private function get_request_surfaces(): array {
		return [
			[
				'surface'               => 'wp_login',
				'hcaptcha_option_key'   => 'wp_status',
				'hcaptcha_option_value' => 'login',
			],
		];
	}

	/**
	 * Mock successfully apply AJAX security checks.
	 */
	private function mock_successful_apply_checks(): void {
		WP_Mock::userFunction( 'check_ajax_referer' )
			->with( 'hcaptcha_migration_apply_nonce', 'nonce', false )
			->once()
			->andReturn( true );
		WP_Mock::userFunction( 'current_user_can' )
			->with( MigrationWizard::CAPABILITY )
			->once()
			->andReturn( true );
	}

	/**
	 * Mock WP_Error class.
	 *
	 * @param string $code    Error code.
	 * @param string $message Error message.
	 */
	private function mock_wp_error_class( string $code, string $message ): void {
		$wp_error = Mockery::mock( 'overload:WP_Error' );

		$wp_error->shouldReceive( 'get_error_code' )->andReturn( $code );
		$wp_error->shouldReceive( 'get_error_message' )->andReturn( $message );
	}

	/**
	 * Mock is_wp_error().
	 */
	private function mock_is_wp_error(): void {
		WP_Mock::userFunction( 'is_wp_error' )
			->andReturnUsing(
				static function ( $thing ) {
					return is_object( $thing ) &&
						( is_a( $thing, 'WP_Error', true ) || method_exists( $thing, 'get_error_message' ) );
				}
			);
	}
}
