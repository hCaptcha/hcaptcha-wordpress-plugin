<?php
/**
 * EmailPolicyTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\CustomerReviews;

use HCaptcha\CustomerReviews\QuestionAnswer;
use HCaptcha\CustomerReviews\Review;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;

/**
 * Check review and Q&A native emails through actual JSON error responses.
 */
class EmailPolicyTest extends HCaptchaWPTestCase {

	/**
	 * Check the canonical native email rather than an extra alias.
	 *
	 * @dataProvider dp_test_native_email_policy
	 *
	 * @param string $integration_class Native integration class.
	 * @param string $form_id Native form ID.
	 * @param string $email   Native email.
	 * @param bool   $blocked Whether disposal policy rejects it.
	 */
	public function test_native_email_policy( string $integration_class, string $form_id, string $email, bool $blocked ): void {
		add_filter(
			'hcap_disposable_email_domains',
			static function (): array {
				return [ 'disposable.example' => true ];
			}
		);
		$_POST['email']      = $email;
		$_POST['user_email'] = 'safe@example.com';
		$this->prepare_verify_post( 'hcaptcha_customer_reviews_nonce', 'hcaptcha_customer_reviews' );
		hcaptcha()->settings()->set( 'disposable_email', [ 'on' ] );
		hcaptcha()->settings()->set( 'customer_reviews_status', [ 'review', 'q&a' ] );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );
		self::assertTrue( hcaptcha()->settings()->is_on( 'disposable_email' ) );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'customer-reviews-woocommerce/ivole.php' ],
				'form_id' => $form_id,
			]
		);
		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter(
			'wp_die_ajax_handler',
			static function () {
				return static function (): void {};
			}
		);

		ob_start();
		( new $integration_class() )->verify();
		$output = (string) ob_get_clean();

		self::assertSame(
			$blocked ? wp_json_encode(
				[
					'code'        => 1,
					'description' => hcap_get_error_messages()['disposable-email'],
					'button'      => 'Try again',
				]
			) : '',
			$output
		);
	}

	/**
	 * Native review and question address controls.
	 *
	 * @return array
	 */
	public function dp_test_native_email_policy(): array {
		return [
			'blocked review'  => [ Review::class, 'review', 'user@disposable.example', true ],
			'allowed review'  => [ Review::class, 'review', 'safe@example.com', false ],
			'no review email' => [ Review::class, 'review', '', false ],
			'blocked Q&A'     => [ QuestionAnswer::class, 'q&a', 'user@disposable.example', true ],
			'allowed Q&A'     => [ QuestionAnswer::class, 'q&a', 'safe@example.com', false ],
			'no Q&A email'    => [ QuestionAnswer::class, 'q&a', '', false ],
		];
	}
}
