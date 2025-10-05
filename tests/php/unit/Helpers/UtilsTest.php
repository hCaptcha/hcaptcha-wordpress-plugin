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
use ReflectionException;
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
	 * Test instance().
	 *
	 * @return void
	 * @noinspection UnnecessaryAssertionInspection
	 * @noinspection PhpConditionAlreadyCheckedInspection
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_instance(): void {
		$subject = Utils::instance();

		self::assertInstanceOf( Utils::class, $subject );

		$this->set_protected_property( $subject, 'instance', null );

		self::assertInstanceOf( Utils::class, $subject );
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

		// Callback is an array. Class is an object.
		$action['function'] = [ $this, 'some_method' ];

		self::assertFalse( $subject->match_action_regex( $callback_pattern, $action ) );

		// Callback is an array. Class is a string.
		$action['function'] = [ 'SomeClass', 'some_method' ];

		self::assertFalse( $subject->match_action_regex( $callback_pattern, $action ) );

		// Callback is a string.
		$action['function'] = 'some_function';

		self::assertFalse( $subject->match_action_regex( $callback_pattern, $action ) );

		// Callback is a matched class and method.
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

	/**
	 * Test array_insert().
	 *
	 * @return void
	 */
	public function test_array_insert(): void {
		$array    = [
			'a' => 'b',
			'c' => 'd',
			'e' => 'f',
		];
		$expected = [
			'a' => 'b',
			'x' => 'y',
			'c' => 'd',
			'e' => 'f',
		];

		self::assertSame( $expected, Utils::array_insert( $array, 'c', [ 'x' => 'y' ] ) );
	}
	/**
	 * Test list_array() with the default separator (and).
	 *
	 * @return void
	 */
	public function test_list_array_with_and_separator(): void {
		self::assertSame( 'Alice and Bob', Utils::list_array( [ 'Alice', 'Bob' ] ) );
		self::assertSame( 'Alice, Bob and Charlie', Utils::list_array( [ 'Alice', 'Bob', 'Charlie' ] ) );
		self::assertSame( 'Solo', Utils::list_array( [ 'Solo' ] ) );
		self::assertSame( '', Utils::list_array( [] ) );
	}

	/**
	 * Test list_array() with "or" as the last separator.
	 *
	 * @return void
	 */
	public function test_list_array_with_or_separator(): void {
		self::assertSame( 'Red or Blue', Utils::list_array( [ 'Red', 'Blue' ], false ) );
		self::assertSame( 'Red, Blue or Green', Utils::list_array( [ 'Red', 'Blue', 'Green' ], false ) );
	}

	/**
	 * Test list_array() casts non-string values to strings as PHP implode does.
	 *
	 * @return void
	 */
	public function test_list_array_with_mixed_value_types(): void {
		self::assertSame( '1, B and 1', Utils::list_array( [ 1, 'B', true ] ) );
	}
}
