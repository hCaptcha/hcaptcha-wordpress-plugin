<?php
/**
 * AntiSpamPageBehaviorTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Unit\Settings;

use HCaptcha\Admin\MaxMindDb;
use HCaptcha\AntiSpam\AntiSpam;
use HCaptcha\AntiSpam\DisposableEmail;
use HCaptcha\Helpers\CloudflareDetector;
use HCaptcha\Migrations\Migrations;
use HCaptcha\Settings\AntiSpamPage;
use HCaptcha\Settings\PluginSettingsBase;
use HCaptcha\Tests\Unit\HCaptchaTestCase;
use Mockery;
use ReflectionClass;
use ReflectionException;
use tad\FunctionMocker\FunctionMocker;
use WP_Mock;

/**
 * Test Anti-Spam settings page behavior.
 *
 * @group settings
 * @group settings-anti-spam
 */
class AntiSpamPageBehaviorTest extends HCaptchaTestCase {
	/**
	 * Create a page without initializing the settings UI.
	 *
	 * @return AntiSpamPage
	 */
	private function create_subject(): AntiSpamPage {
		return ( new ReflectionClass( AntiSpamPage::class ) )->newInstanceWithoutConstructor();
	}

	/**
	 * Test the page registers its Ajax and option hooks.
	 */
	public function test_init_hooks(): void {
		$subject = Mockery::mock( AntiSpamPage::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'is_main_menu_page' )->once()->andReturn( false );
		$subject->shouldReceive( 'is_tab_active' )->with( $subject )->once()->andReturn( false );
		WP_Mock::expectActionAdded( 'wp_ajax_' . AntiSpamPage::CHECK_IPS_ACTION, [ $subject, 'check_ips' ] );
		WP_Mock::expectActionAdded( 'wp_ajax_' . AntiSpamPage::DETECT_CLOUDFLARE_ACTION, [ $subject, 'detect_cloudflare' ] );

		$subject->shouldAllowMockingProtectedMethods();
		$subject->init_hooks();
	}

	/**
	 * Test country controls are disabled until a MaxMind key is available.
	 *
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_setup_fields_without_maxmind_key(): void {
		$subject = Mockery::mock( AntiSpamPage::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'is_options_screen' )->twice()->andReturn( true, false );
		$subject->shouldReceive( 'option_name' )->andReturn( PluginSettingsBase::OPTION_NAME );
		$this->set_protected_property(
			$subject,
			'form_fields',
			[
				'blacklisted_countries'   => [],
				'whitelisted_countries'   => [],
				'trusted_address_headers' => [],
			]
		);
		$settings = Mockery::mock();
		$settings->shouldReceive( 'get' )->with( 'maxmind_key' )->once()->andReturn( '' );
		$main = Mockery::mock();
		$main->shouldReceive( 'settings' )->once()->andReturn( $settings );
		WP_Mock::userFunction( 'hcaptcha' )->once()->andReturn( $main );
		WP_Mock::userFunction( 'is_multisite' )->andReturn( false );
		WP_Mock::userFunction( 'get_option' )->with( PluginSettingsBase::OPTION_NAME, [] )->once()->andReturn( [] );

		$subject->setup_fields();
		$fields = $this->get_protected_property( $subject, 'form_fields' );
		self::assertTrue( $fields['blacklisted_countries']['disabled'] );
		self::assertTrue( $fields['whitelisted_countries']['disabled'] );
		self::assertSame( 'Canada', $fields['blacklisted_countries']['options']['CA'] );
	}

	/**
	 * Test each Anti-Spam section heading.
	 */
	public function test_section_callback(): void {
		$subject = Mockery::mock( AntiSpamPage::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'print_header' )->once();
		$subject->shouldReceive( 'get_section_open_status' )->times( 3 )->andReturn( true, false, true );
		WP_Mock::passthruFunction( 'esc_attr' );
		WP_Mock::passthruFunction( 'esc_html' );

		ob_start();
		$subject->section_callback( [ 'id' => AntiSpamPage::SECTION_BOT_DETECTION ] );
		$subject->section_callback( [ 'id' => AntiSpamPage::SECTION_ACCESS_CONTROL ] );
		$subject->section_callback( [ 'id' => AntiSpamPage::SECTION_LOGIN_PROTECTION ] );
		$subject->section_callback( [ 'id' => 'other' ] );
		$output = ob_get_clean();

		self::assertStringContainsString( 'Bot Detection', $output );
		self::assertStringContainsString( 'Access Control', $output );
		self::assertStringContainsString( 'Login Protection', $output );
		self::assertStringContainsString( 'hcaptcha-section-access-control closed', $output );
	}

	/**
	 * Test Anti-Spam script and style registration.
	 *
	 * @param bool $has_maxmind_key Whether country search is enabled.
	 *
	 * @dataProvider dp_test_admin_enqueue_scripts
	 */
	public function test_admin_enqueue_scripts( bool $has_maxmind_key ): void {
		$subject  = $this->create_subject();
		$settings = Mockery::mock();
		$settings->shouldReceive( 'get' )->with( 'maxmind_key' )->andReturn( 'license-key' );
		$main = Mockery::mock();
		$main->shouldReceive( 'settings' )->once()->andReturn( $has_maxmind_key ? $settings : null );
		WP_Mock::userFunction( 'hcaptcha' )->once()->andReturn( $main );
		WP_Mock::userFunction( 'wp_enqueue_script' )->times( 3 );
		WP_Mock::userFunction( 'wp_enqueue_style' )->twice();
		WP_Mock::userFunction( 'wp_localize_script' )->twice();
		WP_Mock::userFunction( 'admin_url' )->andReturn( 'https://example.test/wp-admin/admin-ajax.php' );
		WP_Mock::userFunction( 'wp_create_nonce' )->andReturn( 'nonce' );
		FunctionMocker::replace( '\\' . AntiSpam::class . '::get_configured_providers', [] );
		FunctionMocker::replace(
			'constant',
			static function ( $name ) {
				return 'HCAPTCHA_URL' === $name ? 'https://example.test/plugin' : '1.0.0';
			}
		);

		$subject->admin_enqueue_scripts();
	}

	/**
	 * Asset localization cases.
	 *
	 * @return array
	 */
	public function dp_test_admin_enqueue_scripts(): array {
		return [
			'without MaxMind key' => [ false ],
			'with MaxMind key'    => [ true ],
		];
	}

	/**
	 * Test MaxMind key activation and deactivation.
	 */
	public function test_maybe_load_maxmind_db(): void {
		$db   = Mockery::mock( MaxMindDb::class );
		$main = Mockery::mock();
		$main->shouldReceive( 'get' )->with( MaxMindDb::class )->twice()->andReturn( $db );
		WP_Mock::userFunction( 'hcaptcha' )->twice()->andReturn( $main );
		$db->shouldReceive( 'activate' )->with( 'new-key' )->once();
		$db->shouldReceive( 'deactivate' )->once();
		$subject = $this->create_subject();

		self::assertSame( [], $subject->maybe_load_maxmind_db( [], [] ) );
		self::assertSame( [ 'maxmind_key' => 'new-key' ], $subject->maybe_load_maxmind_db( [ 'maxmind_key' => 'new-key' ], [] ) );
		self::assertSame( [], $subject->maybe_load_maxmind_db( [], [ 'maxmind_key' => 'old-key' ] ) );
	}

	/**
	 * Test disposable email filter activation and deactivation.
	 */
	public function test_maybe_toggle_disposable_email(): void {
		$filter = Mockery::mock( DisposableEmail::class );
		$filter->shouldReceive( 'activate' )->once();
		$filter->shouldReceive( 'deactivate' )->once();
		$main = Mockery::mock();
		$main->shouldReceive( 'get' )->with( DisposableEmail::class )->twice()->andReturn( $filter );
		WP_Mock::userFunction( 'hcaptcha' )->twice()->andReturn( $main );
		$subject = $this->create_subject();

		self::assertSame( [], $subject->maybe_toggle_disposable_email( [], [] ) );
		self::assertSame( [ 'disposable_email' => [ 'on' ] ], $subject->maybe_toggle_disposable_email( [ 'disposable_email' => [ 'on' ] ], [] ) );
		self::assertSame( [], $subject->maybe_toggle_disposable_email( [], [ 'disposable_email' => [ 'on' ] ] ) );
	}

	/**
	 * Test settings storage paths for site and network contexts.
	 *
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_stored_settings(): void {
		$subject = $this->create_subject();
		WP_Mock::userFunction( 'is_multisite' )->andReturn( true );
		WP_Mock::userFunction( 'get_site_option' )
			->with( PluginSettingsBase::OPTION_NAME . '_network_wide', [] )->andReturn( [ 'on' ] );
		WP_Mock::userFunction( 'get_site_option' )->with( PluginSettingsBase::OPTION_NAME, [] )->andReturn( [ 'key' => 'value' ] );
		WP_Mock::userFunction( 'update_site_option' )->with( PluginSettingsBase::OPTION_NAME, [ 'key' => 'new' ] )->once();

		$get = $this->set_method_accessibility( $subject, 'get_stored_settings' );
		$set = $this->set_method_accessibility( $subject, 'update_stored_settings' );
		self::assertSame( [ 'key' => 'value' ], $get->invoke( $subject ) );
		$set->invoke( $subject, [ 'key' => 'new' ] );
	}

	/**
	 * Test saving trusted headers clears stale review and detection data.
	 *
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_clear_trusted_address_headers_review_notice(): void {
		$subject = $this->create_subject();
		$this->set_protected_property( $subject, 'form_fields', [ 'trusted_address_headers' => [] ] );
		$value = [
			'trusted_address_headers' => [ 'HTTP_X_REAL_IP' ],
			Migrations::REVIEW_TRUSTED_ADDRESS_HEADERS_OPTION => 'on',
			AntiSpamPage::CLOUDFLARE_DETECTION_STATUS_OPTION => [ 'status' => 'detected' ],
		];
		WP_Mock::passthruFunction( 'wp_unslash' );
		WP_Mock::passthruFunction( 'sanitize_key' );

		self::assertSame( 'invalid', $subject->clear_trusted_address_headers_review_notice( 'invalid', [] ) );
		self::assertSame( $value, $subject->clear_trusted_address_headers_review_notice( $value, [] ) );

		$_POST[ PluginSettingsBase::OPTION_NAME ] = [ 'trusted_address_headers' => [ 'HTTP_X_REAL_IP' ] ];
		try {
			$unchanged = $subject->clear_trusted_address_headers_review_notice(
				$value,
				[ 'trusted_address_headers' => [ 'HTTP_X_REAL_IP' ] ]
			);
			self::assertArrayNotHasKey( Migrations::REVIEW_TRUSTED_ADDRESS_HEADERS_OPTION, $unchanged );
			self::assertArrayHasKey( AntiSpamPage::CLOUDFLARE_DETECTION_STATUS_OPTION, $unchanged );

			$changed = $subject->clear_trusted_address_headers_review_notice( $value, [] );
			self::assertArrayNotHasKey( AntiSpamPage::CLOUDFLARE_DETECTION_STATUS_OPTION, $changed );
		} finally {
			unset( $_POST[ PluginSettingsBase::OPTION_NAME ] );
		}
	}

	/**
	 * Test valid and invalid IP Ajax requests.
	 *
	 * @param string $ips   Submitted IPs.
	 * @param bool   $valid Whether the request should succeed.
	 *
	 * @dataProvider dp_test_check_ips
	 */
	public function test_check_ips( string $ips, bool $valid ): void {
		$subject = Mockery::mock( AntiSpamPage::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'run_checks' )->with( AntiSpamPage::CHECK_IPS_ACTION )->once();
		$_POST['ips'] = $ips;
		WP_Mock::passthruFunction( 'wp_unslash' );
		WP_Mock::passthruFunction( 'sanitize_text_field' );
		WP_Mock::passthruFunction( 'esc_html' );

		if ( $valid ) {
			WP_Mock::userFunction( 'wp_send_json_success' )->once();
		} else {
			WP_Mock::userFunction( 'wp_send_json_error' )->with( 'Invalid IP or CIDR range: invalid' )->once();
		}

		try {
			$subject->check_ips();
		} finally {
			unset( $_POST['ips'] );
		}
	}

	/**
	 * IP check cases.
	 *
	 * @return array
	 */
	public function dp_test_check_ips(): array {
		return [
			'valid'   => [ '192.0.2.1 2001:db8::1', true ],
			'invalid' => [ '192.0.2.1 invalid', false ],
		];
	}

	/**
	 * Test Cloudflare detection persists a sanitized status.
	 */
	public function test_detect_cloudflare(): void {
		$subject = Mockery::mock( AntiSpamPage::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'run_checks' )->with( AntiSpamPage::DETECT_CLOUDFLARE_ACTION )->once();
		FunctionMocker::replace(
			'\\' . CloudflareDetector::class . '::get_context',
			[
				'status'     => CloudflareDetector::STATUS_VERIFIED_REQUEST,
				'confidence' => 'high',
			]
		);
		FunctionMocker::replace( '\\' . CloudflareDetector::class . '::get_recommendation', 'Use CF-Connecting-IP.' );
		WP_Mock::passthruFunction( 'sanitize_key' );
		WP_Mock::userFunction( 'is_multisite' )->andReturn( false );
		WP_Mock::userFunction( 'get_option' )->with( PluginSettingsBase::OPTION_NAME, [] )->once()->andReturn( [] );
		WP_Mock::userFunction( 'update_option' )
			->with(
				PluginSettingsBase::OPTION_NAME,
				Mockery::on(
					static function ( $settings ) {
						$status = $settings[ AntiSpamPage::CLOUDFLARE_DETECTION_STATUS_OPTION ] ?? [];
						return CloudflareDetector::STATUS_VERIFIED_REQUEST === ( $status['status'] ?? '' ) &&
							'high' === ( $status['confidence'] ?? '' ) &&
							is_int( $status['checked_at'] ?? null );
					}
				)
			)->once();
		WP_Mock::userFunction( 'wp_send_json_success' )
			->with( [ 'message' => 'Use CF-Connecting-IP.' ] )->once();

		$subject->detect_cloudflare();
	}

	/**
	 * Test trusted header guidance and invalid Cloudflare status fallback.
	 *
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_trusted_header_description(): void {
		$subject = $this->create_subject();
		WP_Mock::userFunction( 'is_multisite' )->andReturn( false );
		WP_Mock::userFunction( 'get_option' )->with( PluginSettingsBase::OPTION_NAME, [] )
			->andReturn(
				[ 'trusted_address_headers' => [ 'HTTP_CF_CONNECTING_IP' ] ],
				[ AntiSpamPage::CLOUDFLARE_DETECTION_STATUS_OPTION => [ 'status' => 'invalid' ] ]
			);
		FunctionMocker::replace( '\\' . CloudflareDetector::class . '::get_recommendation', 'No Cloudflare detected.' );

		$method = $this->set_method_accessibility( $subject, 'get_trusted_ip_headers_description' );
		self::assertSame( '', $method->invoke( $subject ) );
		self::assertSame( 'No Cloudflare detected.', $method->invoke( $subject ) );
	}

	/**
	 * Test source control setup stops off the settings screen.
	 */
	public function test_setup_fields_off_screen(): void {
		$subject = Mockery::mock( AntiSpamPage::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'is_options_screen' )->once()->andReturn( false );
		WP_Mock::userFunction( 'hcaptcha' )->never();

		$subject->setup_fields();
	}
}
