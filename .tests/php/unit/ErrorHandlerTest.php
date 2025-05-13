<?php
/**
 * ErrorHandlerTest class file
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Unit;

use HCaptcha\ErrorHandler;
use Mockery;
use tad\FunctionMocker\FunctionMocker;
use WP_Mock;

/**
 * Test class for ErrorHandler.
 *
 * @group error-handler
 */
class ErrorHandlerTest extends HCaptchaTestCase {

	/**
	 * Tear down.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		unset( $GLOBALS['wp_version'], $GLOBALS['wp_filter'] );

		parent::tearDown();
	}

	/**
	 * Test init().
	 *
	 * @param string $wp_version WP version.
	 *
	 * @return void
	 * @dataProvider dp_test_init
	 */
	public function test_init( string $wp_version ): void {
		// Mock global $wp_version.
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wp_version'] = $wp_version;

		$subject = new ErrorHandler();

		switch ( $wp_version ) {
			case '5.3':
				WP_Mock::expectActionNotAdded( 'doing_it_wrong_run', [ $subject, 'action_doing_it_wrong_run' ] );
				WP_Mock::expectActionNotAdded( 'doing_it_wrong_run', [ $subject, 'action_doing_it_wrong_run' ] );
				WP_Mock::expectFilterNotAdded(
					'doing_it_wrong_trigger_error',
					[ $subject, 'filter_doing_it_wrong_trigger_error' ]
				);
				break;

			case '6.7':
				WP_Mock::expectActionAdded( 'doing_it_wrong_run', [ $subject, 'action_doing_it_wrong_run' ], 0, 3 );
				WP_Mock::expectActionAdded( 'doing_it_wrong_run', [ $subject, 'action_doing_it_wrong_run' ], 20, 3 );
				WP_Mock::expectFilterAdded(
					'doing_it_wrong_trigger_error',
					[ $subject, 'filter_doing_it_wrong_trigger_error' ],
					10,
					4
				);
				break;

			default:
				break;
		}

		$subject->init();
	}

	/**
	 * Data provider for test_init().
	 *
	 * @return array
	 */
	public function dp_test_init(): array {

		return [
			'WP 5.3' => [ '5.3' ],
			'WP 6.7' => [ '6.7' ],
		];
	}

	/**
	 * Test action_doing_it_wrong_run().
	 *
	 * @param bool      $qm_collectors_exists              Whether the QM Collectors class exists.
	 * @param bool      $is_just_in_time_for_plugin_domain Is just in time for plugin domain.
	 * @param ?object   $qm_collector_doing_it_wrong       The QM_Collector_Doing_It_Wrong object.
	 * @param int|false $current_priority                  The current priority.
	 *
	 * @return void
	 * @dataProvider dp_test_action_doing_it_wrong_run
	 */
	public function test_action_doing_it_wrong_run(
		bool $qm_collectors_exists,
		bool $is_just_in_time_for_plugin_domain,
		?object $qm_collector_doing_it_wrong,
		$current_priority
	): void {
		global $wp_filter;

		// Mock global $wp_filter.
		$doing_it_wrong_run_filter = Mockery::mock();

		$doing_it_wrong_run_filter->shouldReceive( 'current_priority' )->andReturn( $current_priority );

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$wp_filter = [
			'doing_it_wrong_run' => $doing_it_wrong_run_filter,
		];

		$function_name = 'some-function_name';
		$message       = 'some-message';
		$version       = 'some-version';

		FunctionMocker::replace(
			'class_exists',
			static function ( $classname ) use ( &$qm_collectors_exists ) {
				return 'QM_Collectors' === $classname ? $qm_collectors_exists : false;
			}
		);

		$qm_collectors = Mockery::mock( 'alias:QM_Collectors' );

		$qm_collectors->shouldReceive( 'get' )->with( 'doing_it_wrong' )->andReturn( $qm_collector_doing_it_wrong );

		$subject = Mockery::mock( ErrorHandler::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'is_just_in_time_for_plugin_domain' )
			->with( $function_name, $message )
			->andReturn( $is_just_in_time_for_plugin_domain );

		if ( 0 === $current_priority ) {
			WP_Mock::userFunction( 'remove_action' )
				->with( 'doing_it_wrong_run', [ $qm_collector_doing_it_wrong, 'action_doing_it_wrong_run' ] )
				->once();
			WP_Mock::expectActionNotAdded(
				'doing_it_wrong_run',
				[
					$qm_collector_doing_it_wrong,
					'action_doing_it_wrong_run',
				]
			);
		} elseif ( 20 === $current_priority ) {
			WP_Mock::expectActionAdded(
				'doing_it_wrong_run',
				[
					$qm_collector_doing_it_wrong,
					'action_doing_it_wrong_run',
				],
				10,
				3
			);
		} else {
			WP_Mock::userFunction( 'remove_action' )->never();
			WP_Mock::expectActionNotAdded(
				'doing_it_wrong_run',
				[
					$qm_collector_doing_it_wrong,
					'action_doing_it_wrong_run',
				]
			);
		}

		// Call testing method.
		$subject->action_doing_it_wrong_run( $function_name, $message, $version );
	}

	/**
	 * Data provider for test_action_doing_it_wrong_run().
	 *
	 * @return array
	 */
	public function dp_test_action_doing_it_wrong_run(): array {
		$qm_collector_doing_it_wrong = Mockery::mock();

		return [
			'No QM_Collectors class'               => [ false, false, null, false ],
			'Not just in time for plugin domain'   => [ true, false, null, false ],
			'No QM_Collector_Doing_It_Wrong class' => [ true, true, null, false ],
			'No current priority'                  => [ true, true, $qm_collector_doing_it_wrong, false ],
			'Current priority = 0'                 => [ true, true, $qm_collector_doing_it_wrong, 0 ],
			'Current priority = 20'                => [ true, true, $qm_collector_doing_it_wrong, 20 ],
			'Wrong current priority'               => [ true, true, $qm_collector_doing_it_wrong, 10 ],
		];
	}

	/**
	 * Test filter_doing_it_wrong_trigger_error().
	 *
	 * @return void
	 */
	public function test_filter_doing_it_wrong_trigger_error(): void {
		$subject = Mockery::mock( ErrorHandler::class )->makePartial();

		// Not proper function and message.
		self::assertTrue( $subject->filter_doing_it_wrong_trigger_error( true, 'some_function', 'some_message', 'some_version' ) );

		// Not a proper function.
		self::assertTrue( $subject->filter_doing_it_wrong_trigger_error( true, 'some_function', 'some_message<code>hcaptcha-for-forms-and-more</code>more_text', 'some_version' ) );

		// Not a proper message.
		self::assertTrue( $subject->filter_doing_it_wrong_trigger_error( true, '_load_textdomain_just_in_time', 'some_message', 'some_version' ) );

		// Proper function and message.
		self::assertFalse( $subject->filter_doing_it_wrong_trigger_error( true, '_load_textdomain_just_in_time', 'some_message<code>hcaptcha-for-forms-and-more</code>more_text', 'some_version' ) );
	}
}
