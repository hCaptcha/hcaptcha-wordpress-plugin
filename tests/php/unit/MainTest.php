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
use Mockery;
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
