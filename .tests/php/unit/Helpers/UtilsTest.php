<?php
/**
 * UtilsTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpLanguageLevelInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Unit\Helpers;

use HCaptcha\Helpers\Utils;
use HCaptcha\Tests\Unit\HCaptchaTestCase;
use Mockery;
use WP_Mock;

/**
 * Test Utils class.
 *
 * @group helpers
 * @group helpers-utils
 */
class UtilsTest extends HCaptchaTestCase {

	/**
	 * Tear down.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		unset( $GLOBALS['wp_filter'] );

		parent::tearDown();
	}

	/**
	 * Test remove_action_regex().
	 *
	 * @return void
	 */
	public function test_remove_action_regex(): void {
		global $wp_filter;

		for ( $i = 1; $i <= 6; $i++ ) {
			$action[ $i ] = [
				'name' . $i => [
					'function'      => [ 'SomeClass' . $i, 'some_method' . $i ],
					'accepted_args' => 1,
				],
			];
		}

		$empty_callbacks = (object) [];
		$init_callbacks  = (object) [
			'callbacks' => [
				10 => array_merge( $action[1], $action[2] ),
				20 => $action[3],
			],
		];
		$ast_callbacks   = (object) [
			'callbacks' => [
				0 => $action[4],
				5 => array_merge( $action[5], $action[6] ),
			],
		];

		$callback_pattern = '/^Avada/';

		$subject = Mockery::mock( Utils::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();

		foreach ( $init_callbacks->callbacks as $priority => $actions ) {
			foreach ( $actions as $action ) {
				$subject->shouldReceive( 'match_action_regex' )
					->with( $callback_pattern, $action )->once()->andReturn( true );
				WP_Mock::userFunction( 'remove_action' )
					->with( 'init', $action['function'], $priority )->once();
			}
		}

		foreach ( $ast_callbacks->callbacks as $priority => $actions ) {
			foreach ( $actions as $action ) {
				$subject->shouldReceive( 'match_action_regex' )
					->with( $callback_pattern, $action )->once()->andReturn( true );
				WP_Mock::userFunction( 'remove_action' )
					->with( 'after_switch_theme', $action['function'], $priority )->once();
			}
		}

		WP_Mock::userFunction( 'current_action' )->andReturn( 'init' );

		// No actions.
		$subject->remove_action_regex( $callback_pattern );

		// No callbacks for 'init'.
		// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited
		$wp_filter['init'] = [];

		$subject->remove_action_regex( $callback_pattern );

		// Empty callbacks for 'init'.
		$wp_filter['init'] = $empty_callbacks;

		$subject->remove_action_regex( $callback_pattern );

		// Two callbacks for 'init'.
		$wp_filter['init'] = $init_callbacks;

		$subject->remove_action_regex( $callback_pattern );

		// Two callbacks for 'after_switch_theme'.
		$wp_filter['after_switch_theme'] = $ast_callbacks;

		$subject->remove_action_regex( $callback_pattern, 'after_switch_theme' );
		// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited
	}

	/**
	 * Test replace_action_regex().
	 *
	 * @return void
	 */
	public function test_replace_action_regex(): void {
		global $wp_filter;

		for ( $i = 1; $i <= 6; $i++ ) {
			$action[ $i ] = [
				'name' . $i => [
					'function'      => [ 'SomeClass' . $i, 'some_method' . $i ],
					'accepted_args' => 1,
				],
			];
		}

		$empty_callbacks = (object) [];
		$init_callbacks  = (object) [
			'callbacks' => [
				10 => array_merge( $action[1], $action[2] ),
				20 => $action[3],
			],
		];
		$ast_callbacks   = (object) [
			'callbacks' => [
				0 => $action[4],
				5 => array_merge( $action[5], $action[6] ),
			],
		];

		$callback_pattern = '/^Avada/';
		$replace          = [ $this, 'test_replace_action_regex' ];

		$subject = Mockery::mock( Utils::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();

		foreach ( $init_callbacks->callbacks as $actions ) {
			foreach ( $actions as $action ) {
				$subject->shouldReceive( 'match_action_regex' )
					->with( $callback_pattern, $action )->once()->andReturn( true );
			}
		}

		foreach ( $ast_callbacks->callbacks as $actions ) {
			foreach ( $actions as $action ) {
				$subject->shouldReceive( 'match_action_regex' )
					->with( $callback_pattern, $action )->once()->andReturn( true );
			}
		}

		WP_Mock::userFunction( 'current_action' )->andReturn( 'init' );

		// No actions.
		$subject->replace_action_regex( $callback_pattern, $replace, 'init' );

		self::assertNull( $wp_filter );

		// No callbacks for 'init'.
		// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited
		$wp_filter['init'] = [];

		$subject->replace_action_regex( $callback_pattern, $replace, 'init' );

		self::assertSame( [], $wp_filter['init'] );

		// Empty callbacks for 'init'.
		$wp_filter['init'] = $empty_callbacks;

		$subject->replace_action_regex( $callback_pattern, $replace, 'init' );

		self::assertSame( $empty_callbacks, $wp_filter['init'] );

		// Two callbacks for 'init'.
		$wp_filter['init'] = $init_callbacks;
		$expected['init']  = clone $wp_filter['init'];

		foreach ( $expected['init']->callbacks as &$actions ) {
			foreach ( $actions as &$action ) {
				$action['function'] = $replace;
			}
		}

		unset( $actions, $action );

		$subject->replace_action_regex( $callback_pattern, $replace, 'init' );

		self::assertEquals( $expected, $wp_filter );

		// Two callbacks for 'after_switch_theme'.
		$wp_filter['after_switch_theme'] = $ast_callbacks;
		$expected['after_switch_theme']  = clone $wp_filter['after_switch_theme'];

		foreach ( $expected['after_switch_theme']->callbacks as &$actions ) {
			foreach ( $actions as &$action ) {
				$action['function'] = $replace;
			}
		}

		unset( $actions, $action );

		$subject->replace_action_regex( $callback_pattern, $replace, 'after_switch_theme' );
		// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited

		self::assertEquals( $expected, $wp_filter );
	}

	/**
	 * Test match_action_regex().
	 *
	 * @return void
	 */
	public function test_match_action_regex(): void {
		$callback_pattern = '/^Avada/';
		$action           = [];

		$subject = Mockery::mock( Utils::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();

		// Callback is closure.
		$action['function'] = static function () {
			return true;
		};

		self::assertFalse( $subject->match_action_regex( $callback_pattern, $action ) );

		// Callback is array. Class is an object.
		$action['function'] = [ $this, 'some_method' ];

		self::assertFalse( $subject->match_action_regex( $callback_pattern, $action ) );

		// Callback is array. Class is a string.
		$action['function'] = [ 'SomeClass', 'some_method' ];

		self::assertFalse( $subject->match_action_regex( $callback_pattern, $action ) );

		// Callback is a string.
		$action['function'] = 'some_function';

		self::assertFalse( $subject->match_action_regex( $callback_pattern, $action ) );

		// Callback is matched class and method.
		$action['function'] = [ 'AvadaClass', 'some_method' ];

		self::assertTrue( $subject->match_action_regex( $callback_pattern, $action ) );
	}

	/**
	 * Test flatten_array().
	 *
	 * @return void
	 */
	public function test_flatten_array(): void {
		$multilevel_array = [
			'level1'  => [
				'level2'  => [
					'level3a' => 'value1',
					'level3b' => 'value2',
				],
				'level2b' => 'value3',
			],
			'level1b' => 'value4',
		];
		$expected         = [
			'level1--level2--level3a' => 'value1',
			'level1--level2--level3b' => 'value2',
			'level1--level2b'         => 'value3',
			'level1b'                 => 'value4',
		];

		self::assertSame( $expected, Utils::flatten_array( $multilevel_array, '--' ) );
	}

	/**
	 * Test unflatten_array().
	 *
	 * @return void
	 */
	public function test_unflatten_array(): void {
		$flattened_array = [
			'level1--level2--level3a' => 'value1',
			'level1--level2--level3b' => 'value2',
			'level1--level2b'         => 'value3',
			'level1b'                 => 'value4',
		];
		$expected        = [
			'level1'  => [
				'level2'  => [
					'level3a' => 'value1',
					'level3b' => 'value2',
				],
				'level2b' => 'value3',
			],
			'level1b' => 'value4',
		];

		self::assertSame( $expected, Utils::unflatten_array( $flattened_array, '--' ) );
	}
}
