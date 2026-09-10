<?php
/**
 * OrderTrackingTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */

namespace HCaptcha\Tests\Integration\WC;

use HCaptcha\Settings\General;
use HCaptcha\WC\OrderTracking;
use WC_Shortcodes;

/**
 * Test OrderTrackingTest class.
 *
 * @group wc
 */
class OrderTrackingTest extends WooCommerceTestCase {

	/**
	 * Test constructor and init hooks.
	 */
	public function test_constructor_and_init_hooks(): void {
		$subject = new OrderTracking();

		self::assertSame(
			10,
			has_filter( 'do_shortcode_tag', [ $subject, 'do_shortcode_tag' ] )
		);
	}

	/**
	 * Test do_shortcode_tag().
	 *
	 * @noinspection PhpUndefinedClassInspection
	 */
	public function test_do_shortcode_tag(): void {
		$site_key  = General::MODE_TEST_PUBLISHER_SITE_KEY;
		$theme     = 'some theme';
		$size      = 'some size';
		$args      = [
			'action'  => HCAPTCHA_ACTION,
			'name'    => HCAPTCHA_NONCE,
			'size'    => $size,
			'auto'    => true,
			'id'      => [
				'source'  => [ 'woocommerce/woocommerce.php' ],
				'form_id' => 'order_tracking',
			],
			'sitekey' => $site_key,
			'theme'   => $theme,
		];
		$hcap_form = $this->get_hcap_form( $args );

		update_option(
			'hcaptcha_settings',
			[
				'site_key' => $site_key,
				'theme'    => $theme,
				'size'     => $size,
			]
		);

		hcaptcha()->init_hooks();

		$post_id = $this->factory()->post->create( [ 'post_type' => 'page' ] );

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Set the page rendered by the WooCommerce shortcode.
		$GLOBALS['post'] = get_post( $post_id );

		WC_Shortcodes::init();
		new OrderTracking();

		$output = do_shortcode( '[woocommerce_order_tracking]' );

		self::assertStringContainsString( 'woocommerce-form-track-order', $output );
		self::assertSame( 1, substr_count( $output, $hcap_form ) );
		self::assertLessThan( strpos( $output, 'name="track"' ), strpos( $output, $hcap_form ) );
	}

	/**
	 * Test do_shortcode_tag() when not order_tracking tag.
	 */
	public function test_do_shortcode_tag_when_NOT_order_tracking(): void {
		$output  = 'some output';
		$tag     = 'some_tag';
		$subject = new OrderTracking();

		self::assertSame( $output, $subject->do_shortcode_tag( $output, $tag, [], [] ) );
	}
}
