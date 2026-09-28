<?php
/**
 * ReviewTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\CustomerReviews;

use HCaptcha\CustomerReviews\Review;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;

/**
 * Test Review class.
 *
 * @group customer-reviews
 * @group customer-reviews-review
 */
class ReviewTest extends HCaptchaWPTestCase {

	/**
	 * Set up the test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		hcaptcha()->settings()->set( 'honeypot', 'on' );
		hcaptcha()->settings()->set( 'set_min_submit_time', 'on' );
		hcaptcha()->settings()->set( 'customer_reviews_status', 'review' );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );
	}

	/**
	 * Test the review form contains honeypot fields.
	 *
	 * @return void
	 */
	public function test_add_captcha(): void {
		$template = '<form id="review_form"><div class="cr-review-form-buttons"></div></form>';
		$subject  = new Review();

		ob_start();
		$subject->before_template_part( 'cr-review-form.php', '', '', [] );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Test fixture.
		echo $template;

		$subject->add_captcha( 'cr-review-form.php', '', '', [] );
		$output = (string) ob_get_clean();
		$id     = [
			'source'  => [ 'customer-reviews-woocommerce/ivole.php' ],
			'form_id' => 'review',
		];

		self::assertStringContainsString( 'h-captcha', $output );
		self::assertStringContainsString( HCaptcha::widget_id_value( $id ), $output );
		self::assertStringContainsString( 'name="hcap_hp_test"', $output );
		self::assertStringContainsString( 'name="hcap_hp_sig"', $output );
	}

	/**
	 * Test filled honeypot blocks review processing.
	 *
	 * @return void
	 */
	public function test_filled_honeypot_is_rejected(): void {
		$this->prepare_verify_post( 'hcaptcha_customer_reviews_nonce', 'hcaptcha_customer_reviews' );

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'customer-reviews-woocommerce/ivole.php' ],
				'form_id' => 'review',
			]
		);
		$_POST['hcap_hp_test']                 = 'bot';

		$die_arr = [];

		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter(
			'wp_die_ajax_handler',
			static function () use ( &$die_arr ) {
				return static function ( $message, $title, $args ) use ( &$die_arr ) {
					$die_arr = [ $message, $title, $args ];
				};
			}
		);

		ob_start();
		( new Review() )->verify();
		$output = (string) ob_get_clean();

		self::assertSame(
			'{"code":1,"description":"Anti-spam check failed.","button":"Try again"}',
			$output
		);
		self::assertSame( [ '', '', [ 'response' => null ] ], $die_arr );
	}
}
