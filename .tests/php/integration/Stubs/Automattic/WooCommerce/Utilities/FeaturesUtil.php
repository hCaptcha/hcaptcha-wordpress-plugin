<?php
/**
 * FeaturesUtil stub file
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpIllegalPsrClassPathInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace Automattic\WooCommerce\Utilities;

/**
 * Class FeaturesUtil
 */
class FeaturesUtil {
	/**
	 * Declare (in)compatibility with a given feature for a given plugin.
	 *
	 * This method MUST be executed from inside a handler for the 'before_woocommerce_init' hook and
	 * SHOULD be executed from the main plugin file passing __FILE__ or 'my-plugin/my-plugin.php' for the
	 * $plugin_file argument.
	 *
	 * @param string $feature_id Unique feature id.
	 * @param string $plugin_file The full plugin file path.
	 * @param bool   $positive_compatibility True if the plugin declares being compatible with the feature, false if it declares being incompatible.
	 * @return bool True on success, false on error (feature doesn't exist or not inside the required hook).
	 */
	public static function declare_compatibility( string $feature_id, string $plugin_file, bool $positive_compatibility = true ): bool {
		return true;
	}
}
