<?php
/**
 * GeneralTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedMethodInspection */
/** @noinspection PhpArrayShapeAttributeCanBeAddedInspection */
/** @noinspection PhpUndefinedClassInspection */
/** @noinspection PhpLanguageLevelInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Unit\Settings;

use HCaptcha\Admin\Notifications;
use HCaptcha\Main;
use HCaptcha\Settings\PluginSettingsBase;
use KAGG\Settings\Abstracts\SettingsBase;
use HCaptcha\Settings\General;
use HCaptcha\Settings\Settings;
use HCaptcha\Tests\Unit\HCaptchaTestCase;
use Mockery;
use ReflectionException;
use tad\FunctionMocker\FunctionMocker;
use WP_Mock;

/**
 * Class GeneralTest
 *
 * @group settings
 * @group settings-general
 */
class GeneralTest extends HCaptchaTestCase {

	/**
	 * Tear down test.
	 *
	 * @return void
	 */
	public function tearDown(): void { // phpcs:ignore PHPCompatibility.FunctionDeclarations.NewReturnTypeDeclarations.voidFound
		unset( $_POST );

		parent::tearDown();
	}

	/**
	 * Test page_title().
	 */
	public function test_page_title() {
		$subject = Mockery::mock( General::class )->makePartial()->shouldAllowMockingProtectedMethods();
		$method  = 'page_title';

		self::assertSame( 'General', $subject->$method() );
	}

	/**
	 * Test section_title().
	 */
	public function test_section_title() {
		$subject = Mockery::mock( General::class )->makePartial()->shouldAllowMockingProtectedMethods();
		$method  = 'section_title';

		self::assertSame( 'general', $subject->$method() );
	}

	/**
	 * Test init_hooks().
	 */
	public function test_init_hooks() {
		$plugin_base_name = 'hcaptcha-for-forms-and-more/hcaptcha.php';
		$option_name      = 'hcaptcha_settings';
		$hcaptcha         = Mockery::mock( Main::class )->makePartial();
		$subject          = Mockery::mock( General::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'plugin_basename' )->andReturn( $plugin_base_name );
		$subject->shouldReceive( 'is_tab_active' )->with( $subject )->andReturn( false );

		WP_Mock::userFunction( 'hcaptcha' )->with()->once()->andReturn( $hcaptcha );

		WP_Mock::expectActionAdded( 'plugins_loaded', [ $subject, 'init_notifications' ] );
		WP_Mock::expectActionAdded( 'admin_head', [ $hcaptcha, 'print_inline_styles' ] );
		WP_Mock::expectActionAdded( 'admin_print_footer_scripts', [ $hcaptcha, 'print_footer_scripts' ], 0 );

		WP_Mock::expectFilterAdded( 'kagg_settings_fields', [ $subject, 'settings_fields' ] );
		WP_Mock::expectActionAdded( 'wp_ajax_' . General::CHECK_CONFIG_ACTION, [ $subject, 'check_config' ] );
		WP_Mock::expectActionAdded( 'wp_ajax_' . General::TOGGLE_SECTION_ACTION, [ $subject, 'toggle_section' ] );

		WP_Mock::expectFilterAdded( 'pre_update_option_' . $option_name, [ $subject, 'maybe_send_stats' ], 20, 2 );

		$method = 'init_hooks';

		$subject->$method();
	}

	/**
	 * Test init_notifications().
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_init_notifications() {
		$hcaptcha      = Mockery::mock( Main::class )->makePartial();
		$settings      = Mockery::mock( Settings::class )->makePartial();
		$notifications = Mockery::mock( Notifications::class )->makePartial();
		$subject       = Mockery::mock( General::class )->makePartial();

		$hcaptcha->shouldReceive( 'settings' )->andReturn( $settings );
		$hcaptcha->shouldReceive( 'is_pro' )->andReturn( false );
		$notifications->shouldReceive( 'init' );

		WP_Mock::passthruFunction( 'admin_url' );
		WP_Mock::userFunction( 'hcaptcha' )->with()->andReturn( $hcaptcha );

		$subject->init_notifications();

		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
		self::assertSame( json_encode( $notifications ), json_encode( $this->get_protected_property( $subject, 'notifications' ) ) );
	}

	/**
	 * Test init_form_fields()
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_init_form_fields() {
		$subject = Mockery::mock( General::class )->makePartial()->shouldAllowMockingProtectedMethods();

		WP_Mock::userFunction( 'is_multisite' )->andReturn( false );
		$expected = $this->get_test_general_form_fields();

		$subject->init_form_fields();
		self::assertSame( $expected, $this->get_protected_property( $subject, 'form_fields' ) );
	}

	/**
	 * Test setup_fields().
	 *
	 * @param string $mode hCaptcha mode.
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 * @dataProvider dp_test_setup_fields
	 */
	public function test_setup_fields( string $mode ) {
		$settings = Mockery::mock( Settings::class )->makePartial();
		$settings->shouldReceive( 'get_mode' )->andReturn( $mode );

		$main = Mockery::mock( Main::class )->makePartial();
		$main->shouldReceive( 'settings' )->andReturn( $settings );

		$subject = Mockery::mock( General::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'is_options_screen' )->andReturn( true );
		$this->set_protected_property( $subject, 'form_fields', $this->get_test_form_fields() );

		WP_Mock::passthruFunction( 'register_setting' );
		WP_Mock::passthruFunction( 'add_settings_field' );

		WP_Mock::userFunction( 'hcaptcha' )->with()->once()->andReturn( $main );

		$subject->setup_fields();

		$form_fields = $this->get_protected_property( $subject, 'form_fields' );

		foreach ( $form_fields as $form_field ) {
			self::assertArrayHasKey( 'class', $form_field );
		}

		if ( General::MODE_LIVE === $mode ) {
			$form_fields['site_key']['disabled']   = true;
			$form_fields['secret_key']['disabled'] = true;
		} else {
			$form_fields['site_key']['disabled']   = false;
			$form_fields['secret_key']['disabled'] = false;
		}
	}

	/**
	 * Data provider for test_setup_fields().
	 *
	 * @return array
	 */
	public function dp_test_setup_fields(): array {
		return [
			[ General::MODE_LIVE ],
			[ 'other_mode' ],
		];
	}

	/**
	 * Test setup_fields() not on the options screen.
	 *
	 * @return void
	 */
	public function test_setup_fields_not_on_options_screen() {
		$subject = Mockery::mock( General::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'is_options_screen' )->andReturn( false );

		$subject->setup_fields();
	}

	/**
	 * Test section_callback().
	 *
	 * @param string $section_id Section id.
	 * @param string $expected   Expected.
	 *
	 * @return void
	 * @dataProvider dp_test_section_callback
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_section_callback( string $section_id, string $expected ) {
		$user     = (object) [ 'ID' => 1 ];
		$settings = Mockery::mock( Settings::class )->makePartial();

		$settings->shouldReceive( 'get_license' )->andReturn( 'free' );

		$main = Mockery::mock( Main::class )->makePartial();

		$main->shouldReceive( 'settings' )->andReturn( $settings );
		WP_Mock::userFunction( 'hcaptcha' )->with()->andReturn( $main );

		$notifications = Mockery::mock( Notifications::class )->makePartial();
		$subject       = Mockery::mock( General::class )->makePartial()->shouldAllowMockingProtectedMethods();

		$this->set_protected_property( $subject, 'notifications', $notifications );

		if ( General::SECTION_KEYS === $section_id ) {
			$notifications->shouldReceive( 'show' )->once();
		}

		WP_Mock::userFunction( 'wp_get_current_user' )->andReturn( $user );
		WP_Mock::userFunction( 'get_user_meta' )->andReturn( [] );
		WP_Mock::passthruFunction( 'wp_kses_post' );

		ob_start();
		$subject->section_callback( [ 'id' => $section_id ] );
		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Data provider for test_section_callback().
	 *
	 * @return array
	 */
	public function dp_test_section_callback(): array {
		return [
			'keys'       => [
				General::SECTION_KEYS,
				'		<div class="hcaptcha-header-bar">
			<div class="hcaptcha-header">
				<h2>
					General				</h2>
			</div>
					</div>
						<div id="hcaptcha-message"></div>
						<h3 class="hcaptcha-section-keys">
			<span class="hcaptcha-section-header-title">
				Keys			</span>
			<span class="hcaptcha-section-header-toggle">
			</span>
		</h3>
		',
			],
			'appearance' => [
				General::SECTION_APPEARANCE,
				'		<h3 class="hcaptcha-section-appearance">
			<span class="hcaptcha-section-header-title">
				Appearance			</span>
			<span class="hcaptcha-section-header-toggle">
			</span>
		</h3>
		',
			],
			'custom'     => [
				General::SECTION_CUSTOM,
				'		<h3 class="hcaptcha-section-custom closed disabled">
			<span class="hcaptcha-section-header-title">
				Custom - hCaptcha Pro Required			</span>
			<span class="hcaptcha-section-header-toggle">
			</span>
		</h3>
		',
			],
			'enterprise' => [
				General::SECTION_ENTERPRISE,
				'		<h3 class="hcaptcha-section-enterprise closed disabled">
			<span class="hcaptcha-section-header-title">
				Enterprise - hCaptcha Enterprise Required			</span>
			<span class="hcaptcha-section-header-toggle">
			</span>
		</h3>
		',
			],
			'other'      => [
				General::SECTION_OTHER,
				'		<h3 class="hcaptcha-section-other">
			<span class="hcaptcha-section-header-title">
				Other			</span>
			<span class="hcaptcha-section-header-toggle">
			</span>
		</h3>
		',
			],
			'statistics' => [
				General::SECTION_STATISTICS,
				'		<h3 class="hcaptcha-section-statistics">
			<span class="hcaptcha-section-header-title">
				Statistics			</span>
			<span class="hcaptcha-section-header-toggle">
			</span>
		</h3>
		',
			],
			'wrong'      => [ 'wrong', '' ],
		];
	}

	/**
	 * Test admin_enqueue_scripts().
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_admin_enqueue_scripts() {
		$plugin_url          = 'http://test.test/wp-content/plugins/hcaptcha-wordpress-plugin';
		$plugin_version      = '1.0.0';
		$min_suffix          = '.min';
		$ajax_url            = 'https://test.test/wp-admin/admin-ajax.php';
		$nonce               = 'some_nonce';
		$site_key            = 'some key';
		$check_config_notice =
			'Credentials changed.' . "\n" .
			'Please complete hCaptcha and check the site config.';

		$settings = Mockery::mock( Settings::class )->makePartial();
		$settings->shouldReceive( 'get' )->with( 'site_key' )->andReturn( $site_key );

		$main = Mockery::mock( Main::class )->makePartial();
		$main->shouldReceive( 'settings' )->andReturn( $settings );

		$subject = Mockery::mock( General::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
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

		WP_Mock::userFunction( 'hcaptcha' )->with()->once()->andReturn( $main );

		WP_Mock::userFunction( 'wp_enqueue_script' )
			->with(
				General::DIALOG_HANDLE,
				$plugin_url . "/assets/js/kagg-dialog$min_suffix.js",
				[],
				$plugin_version,
				true
			)
			->once();

		WP_Mock::userFunction( 'wp_enqueue_script' )
			->with(
				General::HANDLE,
				$plugin_url . "/assets/js/general$min_suffix.js",
				[ 'jquery', General::DIALOG_HANDLE ],
				$plugin_version,
				true
			)
			->once();

		WP_Mock::userFunction( 'admin_url' )
			->with( 'admin-ajax.php' )
			->andReturn( $ajax_url )
			->once();

		WP_Mock::userFunction( 'wp_create_nonce' )
			->with( General::CHECK_CONFIG_ACTION )
			->andReturn( $nonce )
			->once();

		WP_Mock::userFunction( 'wp_create_nonce' )
			->with( General::TOGGLE_SECTION_ACTION )
			->andReturn( $nonce )
			->once();

		WP_Mock::userFunction( 'wp_localize_script' )
			->with(
				General::HANDLE,
				General::OBJECT,
				[
					'ajaxUrl'                              => $ajax_url,
					'checkConfigAction'                    => General::CHECK_CONFIG_ACTION,
					'checkConfigNonce'                     => $nonce,
					'toggleSectionAction'                  => General::TOGGLE_SECTION_ACTION,
					'toggleSectionNonce'                   => $nonce,
					'modeLive'                             => General::MODE_LIVE,
					'modeTestPublisher'                    => General::MODE_TEST_PUBLISHER,
					'modeTestEnterpriseSafeEndUser'        => General::MODE_TEST_ENTERPRISE_SAFE_END_USER,
					'modeTestEnterpriseBotDetected'        => General::MODE_TEST_ENTERPRISE_BOT_DETECTED,
					'siteKey'                              => $site_key,
					'modeTestPublisherSiteKey'             => General::MODE_TEST_PUBLISHER_SITE_KEY,
					'modeTestEnterpriseSafeEndUserSiteKey' => General::MODE_TEST_ENTERPRISE_SAFE_END_USER_SITE_KEY,
					'modeTestEnterpriseBotDetectedSiteKey' => General::MODE_TEST_ENTERPRISE_BOT_DETECTED_SITE_KEY,
					'checkConfigNotice'                    => $check_config_notice,
					'checkingConfigMsg'                    => 'Checking site config...',
					'completeHCaptchaTitle'                => 'Please complete the hCaptcha.',
					'completeHCaptchaContent'              => 'Before checking the site config, please complete the Active hCaptcha in the current section.',
					'OKBtnText'                            => 'OK',
				]
			)
			->once();

		WP_Mock::userFunction( 'wp_enqueue_style' )
			->with(
				General::DIALOG_HANDLE,
				$plugin_url . "/assets/css/kagg-dialog$min_suffix.css",
				[],
				$plugin_version
			)
			->once();

		WP_Mock::userFunction( 'wp_enqueue_style' )
			->with(
				General::HANDLE,
				$plugin_url . "/assets/css/general$min_suffix.css",
				[ PluginSettingsBase::PREFIX . '-' . SettingsBase::HANDLE, General::DIALOG_HANDLE ],
				$plugin_version
			)
			->once();

		$subject->admin_enqueue_scripts();
	}

	/**
	 * Test settings_fields().
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_settings_fields() {
		$fields = [
			'text' => [ 'some_class', 'some_method' ],
		];

		$subject = Mockery::mock( General::class )->makePartial()->shouldAllowMockingProtectedMethods();
		$method  = 'settings_fields';

		$expected             = $fields;
		$expected['hcaptcha'] = [ $subject, 'print_hcaptcha_field' ];

		self::assertSame( $expected, $subject->$method( $fields ) );
	}

	/**
	 * Test print_hcaptcha_field().
	 */
	public function test_print_hcaptcha_field() {
		$size      = 'invisible';
		$hcap_form = '<div class="h-captcha"></div>';
		$main      = Mockery::mock( Main::class )->makePartial();
		$settings  = Mockery::mock( Settings::class )->makePartial();
		$subject   = Mockery::mock( General::class )->makePartial();
		$expected  = "$hcap_form		<div id=\"hcaptcha-invisible-notice\" style=\"display: block\">
			<p>
				hCaptcha is in invisible mode.			</p>
		</div>
		";

		$settings->shouldReceive( 'get' )->with( 'size' )->andReturn( $size );
		$main->shouldReceive( 'settings' )->andReturn( $settings );

		WP_Mock::userFunction( 'hcaptcha' )->with()->once()->andReturn( $main );

		FunctionMocker::replace(
			'\HCaptcha\Helpers\HCaptcha::form_display',
			static function () use ( $hcap_form ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $hcap_form;
			}
		);

		ob_start();
		$subject->print_hcaptcha_field();
		$output = ob_get_clean();

		self::assertSame( $expected, $output );
	}

	/**
	 * Test check_config().
	 */
	public function test_check_config() {
		$ajax_mode         = 'live';
		$ajax_site_key     = 'some-site-key';
		$ajax_secret_key   = 'some-secret-key';
		$hcaptcha_response = 'some-response';
		$error1            = 'some error';
		$result1           = [
			'error'    => $error1,
			'features' => [ 'custom_theme' => true ],
		];
		$result2           = 'Some verify error';
		$license           = 'pro';
		$subject           = Mockery::mock( General::class )->makePartial();

		$_POST['mode']               = $ajax_mode;
		$_POST['siteKey']            = $ajax_site_key;
		$_POST['secretKey']          = $ajax_secret_key;
		$_POST['h-captcha-response'] = $hcaptcha_response;

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'update_option' )->with( 'license', $license )->once();

		WP_Mock::passthruFunction( 'wp_unslash' );
		WP_Mock::passthruFunction( 'sanitize_text_field' );
		WP_Mock::userFunction( 'check_ajax_referer' )->with( General::CHECK_CONFIG_ACTION, 'nonce', false )->once()
			->andReturn( true );
		WP_Mock::userFunction( 'current_user_can' )->with( 'manage_options' )->once()->andReturn( true );
		WP_Mock::userFunction( 'hcap_check_site_config' )->with()->once()->andReturn( $result1 );
		WP_Mock::userFunction( 'hcaptcha_request_verify' )->with( $hcaptcha_response )->once()->andReturn( $result2 );
		WP_Mock::userFunction( 'wp_send_json_error' )->with( 'Site configuration error: ' . $error1 )->once();
		WP_Mock::userFunction( 'wp_send_json_error' )->with( $result2 )->once();
		WP_Mock::userFunction( 'wp_send_json_success' )->with( 'Site config is valid.' )->once();

		$subject->check_config();
	}

	/**
	 * Test toggle_section().
	 */
	public function test_toggle_section() {
		$section                = 'some-section';
		$status                 = '1';
		$user_id                = 1;
		$user                   = (object) [ 'ID' => $user_id ];
		$hcaptcha_user_settings = [
			'sections' => [
				$section => $status,
			],
		];
		$subject                = Mockery::mock( General::class )->makePartial();

		$_POST['section'] = $section;
		$_POST['status']  = $status;

		FunctionMocker::replace(
			'filter_input',
			static function ( $type, $var_name, $filter ) {
				return (
					INPUT_POST === $type &&
					'status' === $var_name &&
					FILTER_VALIDATE_BOOLEAN === $filter
				);
			}
		);

		WP_Mock::passthruFunction( 'wp_unslash' );
		WP_Mock::passthruFunction( 'sanitize_text_field' );
		WP_Mock::userFunction( 'check_ajax_referer' )->with( General::TOGGLE_SECTION_ACTION, 'nonce', false )->once()
			->andReturn( true );
		WP_Mock::userFunction( 'current_user_can' )->with( 'manage_options' )->once()->andReturn( true );
		WP_Mock::userFunction( 'wp_get_current_user' )->with()->once()->andReturn( $user );
		WP_Mock::userFunction( 'get_user_meta' )->with( $user_id, General::USER_SETTINGS_META, true )->once()
			->andReturn( false );
		WP_Mock::userFunction( 'update_user_meta' )->with( $user_id, General::USER_SETTINGS_META, $hcaptcha_user_settings )->once();
		WP_Mock::userFunction( 'wp_send_json_success' )->with()->once();

		$subject->toggle_section();
	}

	/**
	 * Test toggle_section() without a user.
	 */
	public function test_toggle_section_without_a_user() {
		$section                = 'some-section';
		$status                 = '1';
		$hcaptcha_user_settings = [
			'sections' => [
				$section => $status,
			],
		];
		$subject                = Mockery::mock( General::class )->makePartial();

		$_POST['section'] = $section;
		$_POST['status']  = $status;

		FunctionMocker::replace(
			'filter_input',
			static function ( $type, $var_name, $filter ) {
				return (
					INPUT_POST === $type &&
					'status' === $var_name &&
					FILTER_VALIDATE_BOOLEAN === $filter
				);
			}
		);

		WP_Mock::passthruFunction( 'wp_unslash' );
		WP_Mock::passthruFunction( 'sanitize_text_field' );
		WP_Mock::userFunction( 'check_ajax_referer' )->with( General::TOGGLE_SECTION_ACTION, 'nonce', false )->once()
			->andReturn( true );
		WP_Mock::userFunction( 'current_user_can' )->with( 'manage_options' )->once()->andReturn( true );
		WP_Mock::userFunction( 'wp_get_current_user' )->with()->once()->andReturn( null );
		WP_Mock::userFunction( 'get_user_meta' )->with( 0, General::USER_SETTINGS_META, true )->once()
			->andReturn( false );
		WP_Mock::userFunction( 'update_user_meta' )->with( 0, General::USER_SETTINGS_META, $hcaptcha_user_settings )->once();
		WP_Mock::userFunction( 'wp_send_json_success' )->with()->once();

		$subject->toggle_section();
	}

	/**
	 * Test maybe_send_stats().
	 *
	 * @param bool $stats     New stats value.
	 * @param bool $old_stats Old stats value.
	 * @param bool $expected  Stats to be sent.
	 *
	 * @dataProvider dp_test_maybe_send_stats
	 */
	public function test_maybe_send_stats( bool $stats, bool $old_stats, bool $expected ) {
		$value['statistics'][0]     = $stats ? 'on' : 'off';
		$old_value['statistics'][0] = $old_stats ? 'on' : 'off';

		$subject = Mockery::mock( General::class )->makePartial();

		if ( $expected ) {
			WP_Mock::expectAction( 'hcap_send_plugin_stats' );
		} else {
			WP_Mock::userFunction( 'do_action' )->with( 'hcap_send_plugin_stats' )->never();
		}

		self::assertSame( $value, $subject->maybe_send_stats( $value, $old_value ) );
	}

	/**
	 * Data provider for test_maybe_send_stats().
	 *
	 * @return array
	 */
	public function dp_test_maybe_send_stats(): array {
		return [
			'No stats'   => [ false, false, false ],
			'Turned on'  => [ true, false, true ],
			'Turned off' => [ false, true, false ],
			'Already on' => [ true, true, false ],
		];
	}
}
