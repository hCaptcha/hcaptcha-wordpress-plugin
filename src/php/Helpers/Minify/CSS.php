<?php
/**
 * CSS class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Helpers\Minify;

use HCaptcha\Vendors\MatthiasMullie\Minify\CSS as MinifyCSS;

/**
 * Class CSS.
 */
class CSS extends MinifyCSS {

	/**
	 * Check if the path is a regular file and can be read.
	 *
	 * @param string $path A path.
	 *
	 * @return bool
	 * @noinspection PhpMissingReturnTypeInspection
	 * @noinspection ReturnTypeCanBeDeclaredInspection
	 */
	protected function canImportFile( $path ) {
		// It is the fix of the library code.
		// The realpath() function does not throw warning when file does not exist.
		// In the parent method, is_file is silenced, but with the Query Monitor plugin, it becomes visible.
		if ( ! realpath( $path ) ) {
			return \false;
		}

		return parent::canImportFile( $path );
	}
}
