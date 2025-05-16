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
	protected static ?Utils $instance = null;

	/**
	 * Get Utils Instance.
	 *
	 * @return self
	 */
	public static function instance(): Utils {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Remove action or filter.
	 *
	 * @param string $callback_pattern Callback pattern to match. A regex matching to
	 *                                 SomeNameSpace\SomeClass::some_method.
	 * @param string $hook_name        Action name.
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
				$this->maybe_remove_action_regex( $callback_pattern, $hook_name, $action, $priority );
			}
		}
	}

	/**
	 * Maybe remove action.
	 *
	 * @param string $callback_pattern Callback pattern to match. A regex matching to
	 *                                 SomeNameSpace\SomeClass::some_method.
	 * @param string $hook_name        Hook name.
	 * @param array  $action           Action data.
	 * @param int    $priority         Priority.
	 *
	 * @return void
	 */
	protected function maybe_remove_action_regex( string $callback_pattern, string $hook_name, array $action, int $priority ): void {
		$callback = $action['function'] ?? '';

		if ( $callback instanceof Closure ) {
			return;
		}

		if ( is_array( $callback ) ) {
			$callback_class  = is_object( $callback[0] ) ? get_class( $callback[0] ) : (string) $callback[0];
			$callback_method = (string) $callback[1];
			$callback_name   = $callback_class . '::' . $callback_method;
		} else {
			$callback_name = (string) $callback;
		}

		if ( ! preg_match( $callback_pattern, $callback_name ) ) {
			return;
		}

		remove_action( $hook_name, $callback, $priority );
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
}
