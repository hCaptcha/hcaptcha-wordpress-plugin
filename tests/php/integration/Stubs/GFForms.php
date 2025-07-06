<?php
/**
 * Gravity Forms GFForms stub file
 *
 * @package HCaptcha\Tests
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpIllegalPsrClassPathInspection */

/**
 * Class GFForms
 */
class GFForms {

	/**
	 * Obtains $_GET values or values from an array.
	 *
	 * @param string     $name The ID of a specific value.
	 * @param array|null $arr  An optional array to search through. Defaults to null.
	 *
	 * @return string The value. Empty if not found.
	 * @noinspection PhpMissingParamTypeInspection
	 * @noinspection PhpMissingReturnTypeInspection
	 * @noinspection ReturnTypeCanBeDeclaredInspection
	 */
	public static function get( $name, $arr = null ) {
		if ( ! isset( $arr ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$arr = $_GET;
		}

		return $arr[ $name ] ?? '';
	}
}
