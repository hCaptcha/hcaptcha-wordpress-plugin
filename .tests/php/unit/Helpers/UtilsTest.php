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

		$init_callbacks = (object) [
			'callbacks' => [
				10 => array_merge( $action[1], $action[2] ),
				20 => $action[3],
			],
		];
		$ast_callbacks  = (object) [
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
				$subject->shouldReceive( 'maybe_remove_action_regex' )
					->with( $callback_pattern, 'init', $action, $priority )->once();
			}
		}

		foreach ( $ast_callbacks->callbacks as $priority => $actions ) {
			foreach ( $actions as $action ) {
				$subject->shouldReceive( 'maybe_remove_action_regex' )
					->with( $callback_pattern, 'after_switch_theme', $action, $priority )->once();
			}
		}

		WP_Mock::userFunction( 'current_action' )->andReturn( 'init' );

		// No actions.
		$subject->remove_action_regex( $callback_pattern );

		// No callbacks for 'init'.
		// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited
		$wp_filter['init'] = [];

		$subject->remove_action_regex( $callback_pattern );

		// Two callbacks for 'init'.
		// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited
		$wp_filter['init'] = $init_callbacks;

		$subject->remove_action_regex( $callback_pattern );

		// Two callbacks for 'after_switch_theme'.
		// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited
		$wp_filter['after_switch_theme'] = $ast_callbacks;

		$subject->remove_action_regex( $callback_pattern, 'after_switch_theme' );
		// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited
	}

	/**
	 * Test maybe_remove_action_regex().
	 *
	 * @return void
	 */
	public function test_maybe_remove_action_regex(): void {
		$callback_pattern = '/^Avada/';
		$hook_name        = 'after_switch_theme';
		$action           = [];
		$priority         = 10;

		$subject = Mockery::mock( Utils::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();

		// Callback is closure.
		$action['function'] = static function () {
			return true;
		};

		$subject->maybe_remove_action_regex( $callback_pattern, $hook_name, $action, $priority );

		// Callback is array. Class is an object.
		$action['function'] = [ $this, 'some_method' ];

		$subject->maybe_remove_action_regex( $callback_pattern, $hook_name, $action, $priority );

		// Callback is array. Class is a string.
		$action['function'] = [ 'SomeClass', 'some_method' ];

		$subject->maybe_remove_action_regex( $callback_pattern, $hook_name, $action, $priority );

		// Callback is a string.
		$action['function'] = 'some_function';

		$subject->maybe_remove_action_regex( $callback_pattern, $hook_name, $action, $priority );

		// Callback is matched class and method.
		$action['function'] = [ 'AvadaClass', 'some_method' ];

		WP_Mock::userFunction( 'remove_action' )->with( $hook_name, $action['function'], $priority )->once();

		$subject->maybe_remove_action_regex( $callback_pattern, $hook_name, $action, $priority );
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
