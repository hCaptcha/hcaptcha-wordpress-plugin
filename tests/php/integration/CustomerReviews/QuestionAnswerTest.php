<?php
/**
 * QuestionAnswerTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\CustomerReviews;

use HCaptcha\CustomerReviews\QuestionAnswer;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;

/**
 * Test challenge placement in the Customer Reviews Q&A product tab.
 *
 * @group customer-reviews
 */
class QuestionAnswerTest extends HCaptchaWPTestCase {
	/**
	 * Prepare the Q&A form identity.
	 */
	public function setUp(): void {
		parent::setUp();

		hcaptcha()->settings()->set( 'customer_reviews_status', [ 'q&a' ] );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );
	}

	/**
	 * Keep unrelated product tabs and templates untouched.
	 */
	public function test_other_tabs_and_templates_are_ignored(): void {
		$subject = new QuestionAnswer();

		self::assertSame( [], $subject->product_tabs( null ) );
		self::assertSame( [ 'description' => [] ], $subject->product_tabs( [ 'description' => [] ] ) );
		ob_start();
		$subject->add_captcha( 'other-template.php', '', '', [] );
		self::assertSame( '', ob_get_clean() );
	}

	/**
	 * Wrap the native Q&A callback and place one challenge before its submit button.
	 */
	public function test_native_question_tab_receives_one_challenge(): void {
		$subject  = new QuestionAnswer();
		$template = '<div class="cr-review-form-buttons"><button type="button" class="cr-review-form-submit" data-crcptcha="1">Send</button></div>';
		$tabs     = $subject->product_tabs(
			[
				'cr_qna' => [
					'callback' => static function ( $attributes ) use ( $template ): void {
						self::assertSame( [ 'product_id' => 42 ], $attributes );
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Test fixture.
						echo $template;
					},
				],
			]
		);

		self::assertSame( [ $subject, 'display_qna_tab' ], $tabs['cr_qna']['callback'] );
		$buffer_level = ob_get_level();
		ob_start();
		try {
			call_user_func( $tabs['cr_qna']['callback'], [ 'product_id' => 42 ] );
			$output = (string) ob_get_clean();
		} finally {
			while ( ob_get_level() > $buffer_level ) {
				ob_end_clean();
			}
		}

		self::assertStringContainsString(
			HCaptcha::widget_id_value(
				[
					'source'  => [ 'customer-reviews-woocommerce/ivole.php' ],
					'form_id' => 'q&a',
				]
			),
			$output
		);
		self::assertStringContainsString( 'name="hcaptcha_customer_reviews_nonce"', $output );
		self::assertLessThan( strpos( $output, 'cr-review-form-submit' ), strpos( $output, 'hcaptcha-widget-id' ) );

		ob_start();
		ob_start();
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Test fixture.
		echo $template;
		$subject->add_captcha( 'single-product/tabs/tabs.php', '', '', [] );
		self::assertSame( $template, ob_get_clean() );
	}
}
