<?php
/**
 * WP-CLI utility function test doubles.
 *
 * @package HCaptcha\Tests
 */

namespace WP_CLI\Utils;

if ( ! function_exists( __NAMESPACE__ . '\get_flag_value' ) ) {
	/**
	 * Get a flag value from associative CLI args.
	 *
	 * @param array  $assoc_args Associative CLI args.
	 * @param string $flag       Flag name.
	 * @param mixed  $default_value    Default value.
	 *
	 * @return mixed
	 */
	function get_flag_value( array $assoc_args, string $flag, $default_value = null ) {
		if ( ! array_key_exists( $flag, $assoc_args ) ) {
			return $default_value;
		}

		return null === $assoc_args[ $flag ] ? true : $assoc_args[ $flag ];
	}
}
