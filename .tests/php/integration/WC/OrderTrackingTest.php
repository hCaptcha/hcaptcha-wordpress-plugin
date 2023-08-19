<?php
/**
 * OrderTrackingTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\WC;

use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use HCaptcha\WC\OrderTracking;

/**
 * Test OrderTrackingTest class.
 *
 * @group wc
 */
class OrderTrackingTest extends HCaptchaWPTestCase {

	/**
	 * Test constructor and init hooks.
	 */
	public function test_constructor_and_init_hooks() {
		$subject = new OrderTracking();

		self::assertSame(
			10,
			has_filter( 'do_shortcode_tag', [ $subject, 'do_shortcode_tag' ] )
		);
	}

	/**
	 * Test do_shortcode_tag().
	 */
	public function test_do_shortcode_tag() {
		$site_key = 'some site key';
		$theme    = 'some theme';
		$size     = 'some size';
		$nonce    = wp_nonce_field( HCAPTCHA_ACTION, HCAPTCHA_NONCE, true, false );

		update_option(
			'hcaptcha_settings',
			[
				'site_key' => $site_key,
				'theme'    => $theme,
				'size'     => $size,
			]
		);

		hcaptcha()->init_hooks();

		$tag = 'woocommerce_order_tracking';

		$output = '<div class="woocommerce">
<form action="http://test.test/wc-order-tracking/" method="post" class="woocommerce-form woocommerce-form-track-order track_order">

	<p>To track your order please enter your Order ID in the box below and press the &quot;Track&quot; button. This was given to you on your receipt and in the confirmation email you should have received.</p>

	<p class="form-row form-row-first"><label for="orderid">Order ID</label> <input class="input-text" type="text" name="orderid" id="orderid" value="" placeholder="Found in your order confirmation email." /></p>	<p class="form-row form-row-last"><label for="order_email">Billing email</label> <input class="input-text" type="text" name="order_email" id="order_email" value="" placeholder="Email you used during checkout." /></p>	<div class="clear"></div>

	<p class="form-row"><button type="submit" class="button" name="track" value="Track">Track</button></p>
	<input type="hidden" id="woocommerce-order-tracking-nonce" name="woocommerce-order-tracking-nonce" value="3f0f69409a" /><input type="hidden" name="_wp_http_referer" value="/wc-order-tracking/" />
</form>
</div>';

		$expected = '<div class="woocommerce">
<form action="http://test.test/wc-order-tracking/" method="post" class="woocommerce-form woocommerce-form-track-order track_order">

	<p>To track your order please enter your Order ID in the box below and press the &quot;Track&quot; button. This was given to you on your receipt and in the confirmation email you should have received.</p>

	<p class="form-row form-row-first"><label for="orderid">Order ID</label> <input class="input-text" type="text" name="orderid" id="orderid" value="" placeholder="Found in your order confirmation email." /></p>	<p class="form-row form-row-last"><label for="order_email">Billing email</label> <input class="input-text" type="text" name="order_email" id="order_email" value="" placeholder="Email you used during checkout." /></p>	<div class="clear"></div>

	<div class="form-row"  style="margin-top: 2rem;">		<div
			class="h-captcha"
			data-sitekey="' . $site_key . '"
			data-theme="' . $theme . '"
			data-size="' . $size . '"
			data-auto="true">
		</div>
		' . $nonce . '</div><p class="form-row"><button type="submit" class="button" name="track" value="Track">Track</button></p>
	<input type="hidden" id="woocommerce-order-tracking-nonce" name="woocommerce-order-tracking-nonce" value="3f0f69409a" /><input type="hidden" name="_wp_http_referer" value="/wc-order-tracking/" />
</form>
</div>';

		$subject = new OrderTracking();

		self::assertSame( $expected, $subject->do_shortcode_tag( $output, $tag, [], [] ) );

		$output   = str_replace( '<p class="form-row"><button type="submit"', '<p class="form-actions"><button type="submit"', $output );
		$expected = str_replace( '<p class="form-row"><button type="submit"', '<p class="form-actions"><button type="submit"', $expected );

		self::assertSame( $expected, $subject->do_shortcode_tag( $output, $tag, [], [] ) );

		$output   = str_replace( '<p class="form-row"><button type="submit"', "<p class=\"form-actions\"> \t\n <button type=\"submit\"", $output );
		$expected = str_replace( '<p class="form-row"><button type="submit"', "<p class=\"form-actions\"> \t\n <button type=\"submit\"", $expected );

		self::assertSame( $expected, $subject->do_shortcode_tag( $output, $tag, [], [] ) );
	}
}
