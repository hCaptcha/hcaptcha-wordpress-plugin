<?php
/**
 * AdvancedTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Unit\Settings;

use HCaptcha\Main;
use HCaptcha\Settings\Advanced;
use HCaptcha\Settings\General;
use HCaptcha\Settings\PluginSettingsBase;
use HCaptcha\Settings\Settings;
use HCaptcha\Tests\Unit\HCaptchaTestCase;
use KAGG\Settings\Abstracts\SettingsBase;
use Mockery;
use tad\FunctionMocker\FunctionMocker;
use WP_Mock;

/**
 * Class AdvancedTest.
 *
 * @group settings
 */
class AdvancedTest extends HCaptchaTestCase {
	/**
	 * Advanced owns the fields removed from General.
	 */
	public function test_form_fields(): void {
		WP_Mock::userFunction( 'is_multisite' )->andReturn( true );

		$subject = Mockery::mock( Advanced::class )->makePartial();
		$subject->init_form_fields();

		$fields = $this->get_protected_property( $subject, 'form_fields' );

		self::assertSame( General::SECTION_ENTERPRISE, $fields['api_host']['section'] );
		self::assertSame( General::SECTION_CONTENT, $fields['protect_content']['section'] );
		self::assertSame( General::SECTION_OTHER, $fields[ SettingsBase::NETWORK_WIDE ]['section'] );
		self::assertSame( General::SECTION_STATISTICS, $fields['statistics']['section'] );
		self::assertArrayNotHasKey( 'site_key', $fields );
		self::assertArrayNotHasKey( 'mode', $fields );
		self::assertSame(
			[
				General::SECTION_CONTENT,
				General::SECTION_OTHER,
				General::SECTION_STATISTICS,
				General::SECTION_ENTERPRISE,
			],
			array_values( array_unique( array_column( $fields, 'section' ) ) )
		);
	}

	/**
	 * Enterprise fields remain disabled for a Free account.
	 */
	public function test_setup_fields_disables_enterprise_for_free(): void {
		$settings = Mockery::mock( Settings::class )->makePartial();
		$settings->shouldReceive( 'get_license' )->andReturn( 'free' );

		$main = Mockery::mock( Main::class )->makePartial();
		$main->shouldReceive( 'settings' )->andReturn( $settings );

		$subject = Mockery::mock( Advanced::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'is_options_screen' )->andReturn( true );

		$this->set_protected_property(
			$subject,
			'form_fields',
			[
				'api_host' => [
					'label'    => 'API Host',
					'type'     => 'text',
					'section'  => General::SECTION_ENTERPRISE,
					'disabled' => false,
				],
			]
		);

		WP_Mock::passthruFunction( 'register_setting' );
		WP_Mock::passthruFunction( 'add_settings_field' );
		WP_Mock::userFunction( 'hcaptcha' )->with()->andReturn( $main );

		$subject->setup_fields();

		$fields = $this->get_protected_property( $subject, 'form_fields' );
		self::assertTrue( $fields['api_host']['disabled'] );
	}

	/**
	 * Test the unminified stylesheet receives a file-based version.
	 */
	public function test_admin_enqueue_scripts_without_min_suffix(): void {
		$subject = Mockery::mock( Advanced::class )->makePartial();
		$this->set_protected_property( $subject, 'min_suffix', '' );

		FunctionMocker::replace(
			'constant',
			static function ( $name ) {
				return [
					'HCAPTCHA_VERSION' => '1.0.0',
					'HCAPTCHA_PATH'    => HCAPTCHA_TEST_PATH,
					'HCAPTCHA_URL'     => HCAPTCHA_TEST_URL,
				][ $name ];
			}
		);

		WP_Mock::userFunction( 'wp_enqueue_style' )
			->with(
				Advanced::HANDLE,
				HCAPTCHA_TEST_URL . '/assets/css/advanced.css',
				[ PluginSettingsBase::PREFIX . '-' . SettingsBase::HANDLE ],
				'1.0.0-' . filemtime( HCAPTCHA_TEST_PATH . '/assets/css/advanced.css' )
			)
			->once();

		$subject->admin_enqueue_scripts();
	}
}
