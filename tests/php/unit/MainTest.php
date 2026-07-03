<?php
/**
 * MainTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Unit;

use HCaptcha\Admin\Events\Events;
use HCaptcha\Admin\MaxMindDb;
use HCaptcha\Main;
use HCaptcha\Settings\Settings;
use HCaptcha\WP\Signup;
use Mockery;
use ReflectionException;
use RuntimeException;
use tad\FunctionMocker\FunctionMocker;
use WP_Mock;

/**
 * Test Main class.
 *
 * @group main
 */
class MainTest extends HCaptchaTestCase {

	/**
	 * Test declare_wc_compatibility().
	 *
	 * @return void
	 */
	public function test_declare_wc_compatibility(): void {
		$mock = Mockery::mock( 'alias:Automattic\WooCommerce\Utilities\FeaturesUtil' );
		$mock->shouldReceive( 'declare_compatibility' )
			->with( 'custom_order_tables', HCAPTCHA_TEST_FILE )
			->andReturn( true );

		FunctionMocker::replace(
			'constant',
			static function ( $name ) {
				if ( 'HCAPTCHA_FILE' === $name ) {
					return HCAPTCHA_TEST_FILE;
				}

				return '';
			}
		);

		$subject = new Main();
		$subject->declare_wc_compatibility();
	}

	/**
	 * Test register_recurring_actions() schedules cleanup when statistics are on.
	 *
	 * @return void
	 */
	public function test_register_recurring_actions_schedules_events_cleanup_when_statistics_are_on(): void {
		$this->define_day_in_seconds();

		$settings = Mockery::mock( Settings::class );
		$settings->shouldReceive( 'is_on' )->once()->with( 'statistics' )->andReturn( true );

		$subject = Mockery::mock( Main::class )->makePartial();
		$subject->shouldReceive( 'settings' )->once()->andReturn( $settings );

		WP_Mock::userFunction( 'get_option' )->once()->with( 'gmt_offset' )->andReturn( 0 );
		WP_Mock::userFunction( 'absint' )->once()->with( 0 )->andReturn( 0 );
		WP_Mock::userFunction( 'as_schedule_recurring_action' )
			->once()
			->with(
				Mockery::type( 'int' ),
				15 * DAY_IN_SECONDS,
				MaxMindDb::UPDATE_ACTION,
				[],
				'hcaptcha',
				true
			);
		WP_Mock::userFunction( 'as_schedule_recurring_action' )
			->once()
			->with(
				Mockery::type( 'int' ),
				DAY_IN_SECONDS,
				Events::CLEANUP_ACTION,
				[],
				'hcaptcha',
				true
			);
		WP_Mock::userFunction( 'as_unschedule_all_actions' )->never();

		$subject->register_recurring_actions();
	}

	/**
	 * Test register_recurring_actions() unschedules cleanup when statistics are off.
	 *
	 * @return void
	 */
	public function test_register_recurring_actions_unschedules_events_cleanup_when_statistics_are_off(): void {
		$this->define_day_in_seconds();

		$settings = Mockery::mock( Settings::class );
		$settings->shouldReceive( 'is_on' )->once()->with( 'statistics' )->andReturn( false );

		$subject = Mockery::mock( Main::class )->makePartial();
		$subject->shouldReceive( 'settings' )->once()->andReturn( $settings );

		WP_Mock::userFunction( 'get_option' )->once()->with( 'gmt_offset' )->andReturn( 0 );
		WP_Mock::userFunction( 'absint' )->once()->with( 0 )->andReturn( 0 );
		WP_Mock::userFunction( 'as_schedule_recurring_action' )
			->once()
			->with(
				Mockery::type( 'int' ),
				15 * DAY_IN_SECONDS,
				MaxMindDb::UPDATE_ACTION,
				[],
				'hcaptcha',
				true
			);
		WP_Mock::userFunction( 'as_unschedule_all_actions' )
			->once()
			->with( Events::CLEANUP_ACTION, [], 'hcaptcha' );

		$subject->register_recurring_actions();
	}

	/**
	 * Test get_client_country_code().
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_get_client_country_code(): void {
		$client_ip = '203.0.113.40';
		$error_ip  = '203.0.113.41';

		$db_path = WP_CONTENT_DIR . '/uploads/hcaptcha/GeoLite2-Country.mmdb';

		FunctionMocker::replace( 'is_readable', true );

		$reader = Mockery::mock( 'overload:HCaptcha\Vendors\GeoIp2\Database\Reader' );
		$reader->shouldReceive( '__construct' )->with( $db_path );
		$reader->shouldReceive( 'country' )->andReturnUsing(
			static function ( string $ip ) use ( $client_ip, $error_ip ) {
				if ( $error_ip === $ip ) {
					throw new RuntimeException( 'Reader error.' );
				}

				self::assertSame( $client_ip, $ip );

				return (object) [
					'country' => (object) [
						'isoCode' => ' us ',
					],
				];
			}
		);
		$reader->shouldReceive( 'close' )->andReturnNull();

		$subject = new Main();
		$method  = $this->set_method_accessibility( $subject, 'get_client_country_code' );

		self::assertSame( 'US', $method->invoke( $subject, $client_ip ) );
		self::assertSame( '', $method->invoke( $subject, $error_ip ) );
	}

	/**
	 * Test load_modules() on multisite.
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_load_modules_on_multisite(): void {
		FunctionMocker::replace( 'is_multisite', true );
		FunctionMocker::replace(
			'function_exists',
			static function ( $function_name ) {
				return 'is_plugin_active' === $function_name;
			}
		);

		$subject = new Main();

		$this->set_protected_property( $subject, 'active', false );
		$subject->load_modules();

		self::assertSame(
			[
				[ 'wp_status', 'signup' ],
				'',
				Signup::class,
			],
			$subject->modules['Signup Form']
		);
		self::assertSame(
			[
				[ 'theme_my_login_status', 'signup' ],
				'theme-my-login/theme-my-login.php',
				\HCaptcha\ThemeMyLogin\Signup::class,
			],
			$subject->modules['Theme My Login Signup']
		);
		self::assertArrayNotHasKey( 'Theme My Login Register', $subject->modules );
	}

	/**
	 * Define the DAY_IN_SECONDS constant if WordPress has not defined it.
	 *
	 * @return void
	 */
	private function define_day_in_seconds(): void {
		if ( defined( 'DAY_IN_SECONDS' ) ) {
			return;
		}

		define( 'DAY_IN_SECONDS', 24 * 60 * 60 );
	}
}
