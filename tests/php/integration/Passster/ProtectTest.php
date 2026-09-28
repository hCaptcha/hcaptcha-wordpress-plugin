<?php
/**
 * ProtectTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\Passster;

use HCaptcha\Helpers\API;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Passster\Protect;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Test the Passster integration.
 *
 * @group passster
 */
class ProtectTest extends HCaptchaWPTestCase {

	/**
	 * Set up the test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		hcaptcha()->settings()->set( 'honeypot', 'on' );
		hcaptcha()->settings()->set( 'set_min_submit_time', 'on' );
		hcaptcha()->settings()->set( 'passster_status', 'protect' );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );
	}

	/**
	 * Test that the rendered form contains honeypot fields.
	 *
	 * @return void
	 */
	public function test_form_contains_honeypot(): void {
		$html = '<form class="passster-form"><div data-area="3866"></div>' .
			'<button name="submit" class="passster-submit">Submit</button></form>';

		$output = ( new Protect() )->do_shortcode_tag( $html, 'passster', [], [] );

		self::assertStringContainsString( 'name="hcap_hp_test"', $output );
		self::assertStringContainsString( 'name="hcap_hp_sig"', $output );
		self::assertStringContainsString( 'name="hcaptcha-widget-id"', $output );
	}

	/**
	 * Test that a filled honeypot blocks Passster validation.
	 *
	 * @return void
	 */
	public function test_filled_honeypot_is_rejected(): void {
		$this->prepare_verify_post( 'hcaptcha_passster_nonce', 'hcaptcha_passster' );

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'content-protector/content-protector.php' ],
				'form_id' => '3866',
			]
		);
		$_POST['hcap_hp_test']                 = 'bot';

		self::assertSame(
			'Anti-spam check failed.',
			API::verify_post( 'hcaptcha_passster_nonce', 'hcaptcha_passster' )
		);
	}

	/**
	 * Test that a filled honeypot blocks a modern REST unlock request.
	 *
	 * @return void
	 */
	public function test_filled_honeypot_is_rejected_in_rest_request(): void {
		$this->prepare_verify_post( 'hcaptcha_passster_nonce', 'hcaptcha_passster' );

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'content-protector/content-protector.php' ],
				'form_id' => '3866',
			]
		);
		$_POST['hcap_hp_test']                 = 'bot';

		$request = new WP_REST_Request( 'POST', '/passster/v1/unlock' );
		$request->set_header( 'Content-Type', 'application/json' );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$request->set_body( wp_json_encode( $_POST ) );

		$response = ( new Protect() )->verify_rest( null, null, $request );

		self::assertInstanceOf( WP_REST_Response::class, $response );
		self::assertSame(
			[
				'success' => false,
				'error'   => 'Anti-spam check failed.',
			],
			$response->get_data()
		);
	}

	/**
	 * Test that unrelated REST requests are ignored.
	 *
	 * @return void
	 */
	public function test_unrelated_rest_request_is_ignored(): void {
		$request = new WP_REST_Request( 'POST', '/some/v1/route' );

		self::assertNull( ( new Protect() )->verify_rest( null, null, $request ) );
	}
}
