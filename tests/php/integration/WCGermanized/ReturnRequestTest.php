<?php
/**
 * ReturnRequestTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\WCGermanized;

use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Tests\Integration\WC\WooCommerceTestCase;
use HCaptcha\WCGermanized\ReturnRequest;
use tad\FunctionMocker\FunctionMocker;

/**
 * Test ReturnRequest class.
 *
 * @group woocommerce-germanized
 * @group woocommerce-germanized-return-request
 */
class ReturnRequestTest extends WooCommerceTestCase {

	/**
	 * Set up the test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		hcaptcha()->settings()->set( 'honeypot', 'on' );
		hcaptcha()->settings()->set( 'set_min_submit_time', 'on' );
		hcaptcha()->settings()->set( 'woocommerce_germanized_status', [ 'return_request' ] );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );
	}

	/**
	 * Test the return-request form contains honeypot fields.
	 *
	 * @return void
	 */
	public function test_add_hcaptcha(): void {
		$subject = new ReturnRequest();

		ob_start();
		$subject->before_submit_button();

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Test fixture.
		echo '<button type="submit">Submit</button>';

		$subject->after_submit_button();
		$output = (string) ob_get_clean();
		$id     = [
			'source'  => [ 'woocommerce-germanized/woocommerce-germanized.php' ],
			'form_id' => 'return_request',
		];

		self::assertStringContainsString( 'h-captcha', $output );
		self::assertStringContainsString( HCaptcha::widget_id_value( $id ), $output );
		self::assertStringContainsString( 'name="hcap_hp_test"', $output );
		self::assertStringContainsString( 'name="hcap_hp_sig"', $output );
	}

	/**
	 * Test a filled honeypot blocks return-request processing.
	 *
	 * @return void
	 */
	public function test_filled_honeypot_is_rejected(): void {
		$this->prepare_verify_request( 'some response' );

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'woocommerce-germanized/woocommerce-germanized.php' ],
				'form_id' => 'return_request',
			]
		);
		$_POST['hcap_hp_test']                 = 'bot';
		$_POST['return_request']               = '1';

		$notice = [];

		FunctionMocker::replace(
			'wc_add_notice',
			static function ( $message, $type ) use ( &$notice ) {
				$notice = [ $message, $type ];
			}
		);

		( new ReturnRequest() )->verify();

		self::assertSame( [ 'Anti-spam check failed.', 'error' ], $notice );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		self::assertArrayNotHasKey( 'return_request', $_POST );
	}

	/**
	 * Ignore requests outside the native return form.
	 */
	public function test_verify_without_return_request(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Ensure the native form marker is absent.
		unset( $_POST['return_request'] );

		( new ReturnRequest() )->verify();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		self::assertArrayNotHasKey( 'return_request', $_POST );
	}

	/**
	 * Check the native return email and preserve its submission stop on error.
	 *
	 * @dataProvider dp_test_native_email_policy
	 *
	 * @param string $email   Native return address.
	 * @param bool   $blocked Whether disposal policy rejects it.
	 */
	public function test_native_email_policy( string $email, bool $blocked ): void {
		add_filter(
			'hcap_disposable_email_domains',
			static function (): array {
				return [ 'disposable.example' => true ];
			}
		);
		$_POST['return_request'] = '1';
		$_POST['email']          = $email;
		$_POST['user_email']     = 'safe@example.com';
		$this->prepare_verify_post( 'unused_nonce', 'unused_action' );
		hcaptcha()->settings()->set( 'disposable_email', [ 'on' ] );
		hcaptcha()->settings()->set( 'woocommerce_germanized_status', [ 'return_request' ] );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );
		self::assertTrue( hcaptcha()->settings()->is_on( 'disposable_email' ) );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'woocommerce-germanized/woocommerce-germanized.php' ],
				'form_id' => 'return_request',
			]
		);
		$notice                                = '';
		FunctionMocker::replace(
			'wc_add_notice',
			static function ( $message ) use ( &$notice ): void {
				$notice = $message;
			}
		);

		( new ReturnRequest() )->verify();

		self::assertSame( $blocked ? hcap_get_error_messages()['disposable-email'] : '', $notice );
		// The production adapter removes this control after verifying the request.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		self::assertSame( ! $blocked, isset( $_POST['return_request'] ) );
	}

	/**
	 * Native return email controls.
	 *
	 * @return array
	 */
	public function dp_test_native_email_policy(): array {
		return [
			'disposable address' => [ 'user@disposable.example', true ],
			'permanent address'  => [ 'safe@example.com', false ],
			'email-free data'    => [ '', false ],
		];
	}
}
