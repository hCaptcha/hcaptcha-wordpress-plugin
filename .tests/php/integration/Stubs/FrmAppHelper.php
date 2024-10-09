<?php
/**
 * FrmAppHelper stub file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpIllegalPsrClassPathInspection */

/**
 * FrmAppHelper class.
 */
class FrmAppHelper {
	/**
	 * Get settings.
	 *
	 * @return FrmSettings
	 */
	public static function get_settings(): FrmSettings {
		return new FrmSettings();
	}
}
