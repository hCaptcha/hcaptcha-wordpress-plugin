<?php
/**
 * MigrationWizardTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Unit\MigrationWizard;

use HCaptcha\Main;
use HCaptcha\MigrationWizard\DetectionResult;
use HCaptcha\MigrationWizard\MigrationWizard;
use HCaptcha\MigrationWizard\ScanResult;
use HCaptcha\MigrationWizard\Scanner;
use HCaptcha\Settings\PluginSettingsBase;
use HCaptcha\Settings\Settings;
use HCaptcha\Tests\Unit\HCaptchaTestCase;
use Mockery;
use WP_Mock;

/**
 * Test MigrationWizard class.
 *
 * @group migration-wizard
 */
class MigrationWizardTest extends HCaptchaTestCase {

	/**
	 * Test scan().
	 *
	 * @return void
	 */
	public function test_scan(): void {
		if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
			define( 'HOUR_IN_SECONDS', 3600 );
		}

		$detection = new DetectionResult(
			[
				'provider'              => 'recaptcha',
				'surface'               => 'wp_login',
				'support_status'        => DetectionResult::STATUS_SUPPORTED,
				'hcaptcha_option_key'   => 'wp_status',
				'hcaptcha_option_value' => 'login',
			]
		);

		$main     = Mockery::mock( Main::class )->makePartial();
		$settings = Mockery::mock( Settings::class )->makePartial();

		$main->shouldReceive( 'settings' )->andReturn( $settings );
		$settings->shouldReceive( 'get_raw_settings' )
			->with()
			->once()
			->andReturn(
				[
					'wp_status' => [ 'login' ],
				]
			);

		$scanner = Mockery::mock( Scanner::class );
		$scanner->shouldReceive( 'scan' )->once()->andReturn( new ScanResult( [ $detection ], [ 'WordPress' ], [] ) );

		$subject = Mockery::mock( MigrationWizard::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'create_scanner' )->once()->andReturn( $scanner );

		WP_Mock::userFunction( 'hcaptcha' )->andReturn( $main );
		WP_Mock::userFunction( 'set_transient' )
			->once()
			->with(
				MigrationWizard::STATE_TRANSIENT,
				Mockery::on(
					static function ( $state ) {
						return isset( $state['scan_timestamp'], $state['scan_data'] ) &&
							[ 'wp_login' ] === $state['scan_data']['already_enabled'];
					}
				),
				MigrationWizard::STATE_EXPIRATION
			);

		$data = $subject->scan();

		self::assertSame( [ 'wp_login' ], $data['already_enabled'] );
		self::assertSame( 1, $data['total'] );
	}

	/**
	 * Test apply().
	 *
	 * @return void
	 */
	public function test_apply(): void {
		$settings = Mockery::mock( Settings::class )->makePartial();
		$settings->shouldReceive( 'get' )->with( 'site_key' )->once()->andReturn( 'site-key' );
		$settings->shouldReceive( 'get' )->with( 'secret_key' )->once()->andReturn( 'secret-key' );
		$settings->shouldReceive( 'get' )->with( 'wp_status' )->once()->andReturn( [] );
		$settings->shouldReceive( 'update' )->with( 'wp_status', [ 'login' ] )->once()->andReturn( true );

		$main = Mockery::mock( Main::class )->makePartial();
		$main->shouldReceive( 'settings' )->twice()->andReturn( $settings );

		WP_Mock::userFunction( 'hcaptcha' )->with()->twice()->andReturn( $main );
		WP_Mock::userFunction( 'is_wp_error' )->with( null )->once()->andReturn( false );
		WP_Mock::passthruFunction( 'sanitize_text_field' );

		WP_Mock::userFunction( 'get_transient' )
			->with( MigrationWizard::STATE_TRANSIENT )
			->once()
			->andReturn(
				[
					'scan_data' => [ 'total' => 1 ],
				]
			);

		WP_Mock::userFunction( 'set_transient' )
			->with(
				MigrationWizard::STATE_TRANSIENT,
				[
					'scan_data'    => [ 'total' => 1 ],
					'apply_result' => [
						'enabled' => [ 'wp_login' ],
						'failed'  => [],
					],
				],
				MigrationWizard::STATE_EXPIRATION
			)
			->once();

		$subject = new MigrationWizard();

		$result = $subject->apply(
			[
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
				'failed'  => [],
			],
			$result
		);
	}
}
