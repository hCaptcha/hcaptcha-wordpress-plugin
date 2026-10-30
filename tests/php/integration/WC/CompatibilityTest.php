<?php
/**
 * CompatibilityTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedNamespaceInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Integration\WC;

use Automattic\WooCommerce\Utilities\FeaturesUtil;

/**
 * Test compatibility declarations with the live WooCommerce plugin.
 *
 * @group wc
 */
class CompatibilityTest extends WooCommerceTestCase {

	/**
	 * Test that hCaptcha declares compatibility with custom order tables during WooCommerce initialization.
	 *
	 * @return void
	 */
	public function test_declare_wc_compatibility(): void {
		$features = FeaturesUtil::get_compatible_features_for_plugin( plugin_basename( HCAPTCHA_FILE ) );

		self::assertContains( 'custom_order_tables', $features['compatible'] );
	}
}
