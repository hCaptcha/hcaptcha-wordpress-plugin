<?php
/**
 * CheckoutTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\EasyDigitalDownloads;

use HCaptcha\EasyDigitalDownloads\Checkout;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;

/**
 * Test the Easy Digital Downloads checkout integration.
 *
 * @group easy-digital-downloads
 * @group easy-digital-downloads-checkout
 */
class CheckoutTest extends HCaptchaWPTestCase {

	/**
	 * Set up the test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		hcaptcha()->settings()->set( 'honeypot', 'on' );
		hcaptcha()->settings()->set( 'set_min_submit_time', 'on' );
		hcaptcha()->settings()->set( 'easy_digital_downloads_status', [ 'checkout' ] );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );
	}

	/**
	 * Test the checkout form contains honeypot fields.
	 *
	 * @return void
	 */
	public function test_add_captcha(): void {
		ob_start();
		( new Checkout() )->add_captcha();
		$output = (string) ob_get_clean();
		$id     = [
			'source'  => [ 'easy-digital-downloads/easy-digital-downloads.php' ],
			'form_id' => 'checkout',
		];

		self::assertStringContainsString( 'h-captcha', $output );
		self::assertStringContainsString( HCaptcha::widget_id_value( $id ), $output );
		self::assertStringContainsString( 'name="hcap_hp_test"', $output );
		self::assertStringContainsString( 'name="hcap_hp_sig"', $output );
		self::assertTrue( wp_script_is( 'hcaptcha-easy-digital-downloads' ) );
	}

	/**
	 * Test a filled honeypot blocks checkout.
	 *
	 * @return void
	 */
	public function test_filled_honeypot_is_rejected(): void {
		$this->prepare_verify_post(
			'hcaptcha_easy_digital_downloads_register_nonce',
			'hcaptcha_easy_digital_downloads_register'
		);

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'easy-digital-downloads/easy-digital-downloads.php' ],
				'form_id' => 'checkout',
			]
		);
		$_POST['hcap_hp_test']                 = 'bot';
		$_POST['action']                       = 'edd_process_checkout';

		self::assertSame(
			[ 'spam' => 'Anti-spam check failed.' ],
			( new Checkout() )->verify( [] )
		);
	}

	/**
	 * Native non-AJAX checkout has edd_action=purchase and no AJAX action field.
	 *
	 * @return void
	 */
	public function test_native_purchase_requires_captcha(): void {
		$_POST['edd_action'] = 'purchase';
		$_POST['edd_email']  = 'buyer@example.com';

		$subject = new Checkout();
		self::assertSame( [], $subject->verify( [] ) );

		$result = null;
		add_action(
			'edd_purchase',
			static function () use ( $subject, &$result ): void {
				$result = $subject->verify( [] );
			}
		);
		do_action( 'edd_purchase' );

		self::assertSame( [ 'bad-signature' => hcap_get_error_messages()['bad-signature'] ], $result );
	}

	/**
	 * A signed native purchase still passes its checkout verification.
	 *
	 * @return void
	 */
	public function test_native_purchase_with_valid_captcha(): void {
		$this->prepare_verify_post( 'hcaptcha_easy_digital_downloads_register_nonce', 'hcaptcha_easy_digital_downloads_register' );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'easy-digital-downloads/easy-digital-downloads.php' ],
				'form_id' => 'checkout',
			]
		);
		$_POST['edd_action']                   = 'purchase';
		$_POST['edd_email']                    = 'buyer@example.com';

		$subject = new Checkout();
		$result  = null;
		add_action(
			'edd_purchase',
			static function () use ( $subject, &$result ): void {
				$result = $subject->verify( [] );
			}
		);
		do_action( 'edd_purchase' );

		self::assertSame( [], $result );
	}

	/**
	 * WordPress can select EDD's AJAX handler from a query action without a POST action.
	 *
	 * @return void
	 */
	public function test_ajax_query_action_requires_captcha(): void {
		$_GET['action']     = 'edd_process_checkout';
		$_POST['edd_email'] = 'buyer@example.com';

		$subject = new Checkout();
		self::assertSame( [], $subject->verify( [] ) );

		$result = null;
		add_action(
			'wp_ajax_nopriv_edd_process_checkout',
			static function () use ( $subject, &$result ): void {
				$result = $subject->verify( [] );
			}
		);
		do_action( 'wp_ajax_nopriv_edd_process_checkout' );

		self::assertSame( [ 'bad-signature' => hcap_get_error_messages()['bad-signature'] ], $result );
	}

	/**
	 * A signed checkout request also passes the native query-action AJAX route.
	 *
	 * @return void
	 */
	public function test_ajax_query_action_with_valid_captcha(): void {
		$this->prepare_verify_post( 'hcaptcha_easy_digital_downloads_register_nonce', 'hcaptcha_easy_digital_downloads_register' );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'easy-digital-downloads/easy-digital-downloads.php' ],
				'form_id' => 'checkout',
			]
		);
		$_GET['action']                        = 'edd_process_checkout';
		$_POST['edd_email']                    = 'buyer@example.com';

		$subject = new Checkout();
		$result  = null;
		add_action(
			'wp_ajax_edd_process_checkout',
			static function () use ( $subject, &$result ): void {
				$result = $subject->verify( [] );
			}
		);
		do_action( 'wp_ajax_edd_process_checkout' );

		self::assertSame( [], $result );
	}

	/**
	 * Check the authoritative native email through the production API.
	 *
	 * @dataProvider dp_test_native_email_policy
	 *
	 * @param string $email   Native address.
	 * @param bool   $blocked Whether disposal policy rejects it.
	 */
	public function test_native_email_policy( string $email, bool $blocked ): void {
		add_filter(
			'hcap_disposable_email_domains',
			static function (): array {
				return [ 'disposable.example' => true ];
			}
		);
		$_POST['action']    = 'edd_process_checkout';
		$_POST['edd_email'] = $email;
		$_POST['email']     = 'safe@example.com';
		$this->prepare_verify_post( 'hcaptcha_easy_digital_downloads_register_nonce', 'hcaptcha_easy_digital_downloads_register' );
		hcaptcha()->settings()->set( 'disposable_email', [ 'on' ] );
		hcaptcha()->settings()->set( 'easy_digital_downloads_status', [ 'checkout' ] );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );
		self::assertTrue( hcaptcha()->settings()->is_on( 'disposable_email' ) );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'easy-digital-downloads/easy-digital-downloads.php' ],
				'form_id' => 'checkout',
			]
		);

		$result = ( new Checkout() )->verify( [] );

		self::assertSame( $blocked ? [ 'disposable-email' => hcap_get_error_messages()['disposable-email'] ] : [], $result );
	}

	/**
	 * Native email policy controls.
	 *
	 * @return array
	 */
	public function dp_test_native_email_policy(): array {
		return [
			'disposable email' => [ 'user@disposable.example', true ],
			'permanent email'  => [ 'safe@example.com', false ],
			'email-free data'  => [ '', false ],
		];
	}

	/**
	 * An omitted checkout email keeps the native current-user fallback.
	 *
	 * @dataProvider dp_test_current_checkout_email
	 *
	 * @param string      $stored  Current customer's address.
	 * @param string|null $posted  Native override or omitted field.
	 * @param bool        $blocked Whether disposal policy rejects it.
	 */
	public function test_current_checkout_email( string $stored, ?string $posted, bool $blocked ): void {
		add_filter(
			'hcap_disposable_email_domains',
			static function (): array {
				return [ 'disposable.example' => true ];
			}
		);
		wp_set_current_user( $this->factory()->user->create( [ 'user_email' => $stored ] ) );
		$_POST['action'] = 'edd_process_checkout';
		$_POST['email']  = 'safe@example.com';
		if ( null !== $posted ) {
			$_POST['edd_email'] = $posted;
		}
		$this->prepare_verify_post( 'hcaptcha_easy_digital_downloads_register_nonce', 'hcaptcha_easy_digital_downloads_register' );
		hcaptcha()->settings()->set( 'disposable_email', [ 'on' ] );
		hcaptcha()->settings()->set( 'easy_digital_downloads_status', [ 'checkout' ] );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );
		self::assertTrue( hcaptcha()->settings()->is_on( 'disposable_email' ) );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'easy-digital-downloads/easy-digital-downloads.php' ],
				'form_id' => 'checkout',
			]
		);

		$result = ( new Checkout() )->verify( [] );

		self::assertSame( $blocked ? [ 'disposable-email' => hcap_get_error_messages()['disposable-email'] ] : [], $result );
		wp_set_current_user( 0 );
	}

	/**
	 * Current customer address controls.
	 *
	 * @return array
	 */
	public function dp_test_current_checkout_email(): array {
		return [
			'blocked fallback' => [ 'user@disposable.example', null, true ],
			'allowed fallback' => [ 'safe@example.com', null, false ],
			'allowed override' => [ 'user@disposable.example', 'safe@example.com', false ],
			'blocked override' => [ 'safe@example.com', 'user@disposable.example', true ],
		];
	}
}
