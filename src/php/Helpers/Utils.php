<?php
/**
 * Utils class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Helpers;

use Closure;

/**
 * Class Utils.
 */
class Utils {
	/**
	 * Instance.
	 *
	 * @var Utils|null
	 */
	protected static $instance;

	/**
	 * Get Utils Instance.
	 *
	 * @return self
	 */
	public static function instance(): Utils {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Remove action or filter.
	 *
	 * @param string $callback_pattern Callback pattern to match.
	 *                                 A regex matching to SomeNameSpace\SomeClass::some_method.
	 * @param string $hook_name        Action name. Default is current_action().
	 *
	 * @return void
	 */
	public function remove_action_regex( string $callback_pattern, string $hook_name = '' ): void {
		global $wp_filter;

		$hook_name = $hook_name ?: current_action();
		$hooks     = $wp_filter[ $hook_name ] ?? null;
		$callbacks = $hooks->callbacks ?? [];

		foreach ( $callbacks as $priority => $actions ) {
			foreach ( $actions as $action ) {
				if ( $this->match_action_regex( $callback_pattern, $action ) ) {
					remove_action( $hook_name, $action['function'], $priority );
				}
			}
		}
	}

	/**
	 * Replace action or filter.
	 *
	 * @param string   $callback_pattern      Callback pattern to match.
	 *                                        A regex matching to SomeNameSpace\SomeClass::some_method.
	 * @param callable $replace               Replacement callback.
	 * @param string   $hook_name             Action name. Default is current_action().
	 *
	 * @return void
	 */
	public function replace_action_regex( string $callback_pattern, callable $replace, string $hook_name = '' ): void {
		global $wp_filter;

		$hook_name = $hook_name ?: current_action();

		if ( ! ( isset( $wp_filter[ $hook_name ]->callbacks ) ) ) {
			return;
		}

		foreach ( $wp_filter[ $hook_name ]->callbacks as &$actions ) {
			foreach ( $actions as &$action ) {
				if ( $this->match_action_regex( $callback_pattern, $action ) ) {
					$action['function'] = $replace;
				}
			}
		}
	}

	/**
	 * Maybe replace action.
	 *
	 * @param string $callback_pattern   Callback pattern to match.
	 *                                   A regex matching to SomeNameSpace\SomeClass::some_method.
	 * @param array  $action             Action data.
	 *
	 * @return bool
	 */
	protected function match_action_regex( string $callback_pattern, array $action ): bool {
		$callback = $action['function'] ?? '';

		if ( $callback instanceof Closure ) {
			return false;
		}

		if ( is_array( $callback ) ) {
			$callback_class  = is_object( $callback[0] ) ? get_class( $callback[0] ) : (string) $callback[0];
			$callback_method = (string) $callback[1];
			$callback_name   = $callback_class . '::' . $callback_method;
		} else {
			$callback_name = (string) $callback;
		}

		if ( ! preg_match( $callback_pattern, $callback_name ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Flatten multidimensional array.
	 *
	 * @param array  $arr Multidimensional array.
	 * @param string $sep Keys separator.
	 *
	 * @return array
	 */
	public static function flatten_array( array $arr, string $sep = '.' ): array {
		static $level = [];

		$result = [];

		foreach ( $arr as $key => $value ) {
			$level[] = $key;
			$new_key = implode( $sep, $level );

			if ( is_array( $value ) ) {
				$result[] = self::flatten_array( $value, $sep );
			} else {
				$result[] = [ $new_key => $value ];
			}

			array_pop( $level );
		}

		return array_merge( [], ...$result );
	}

	/**
	 * Unflatten array to multidimensional one.
	 *
	 * @param array  $arr Flattened array.
	 * @param string $sep Keys separator.
	 *
	 * @return array
	 */
	public static function unflatten_array( array $arr, string $sep = '.' ): array {
		$result = [];

		foreach ( $arr as $key => $value ) {
			$keys = explode( $sep, $key );
			$temp = &$result;

			foreach ( $keys as $inner_key ) {
				if ( ! isset( $temp[ $inner_key ] ) ) {
					$temp[ $inner_key ] = [];
				}

				$temp = &$temp[ $inner_key ];
			}

			$temp = $value;
		}

		return $result;
	}

	/**
	 * Insert an array into another associative array.
	 *
	 * @param array      $arr       Initial array.
	 * @param string|int $key       Key to place an insertion array before.
	 * @param array      $insertion New array to insert.
	 *
	 * @return array
	 */
	public static function array_insert( array $arr, $key, array $insertion ): array {
		$index = array_search( $key, array_keys( $arr ), true );
		$index = false === $index ? count( $arr ) : $index;

		return array_merge(
			array_slice( $arr, 0, $index ),
			$insertion,
			array_slice( $arr, $index )
		);
	}
}
