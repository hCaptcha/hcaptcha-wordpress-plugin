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
use ReflectionException;
use ReflectionMethod;
use tad\FunctionMocker\FunctionMocker;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

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
	 * Unrelated shortcodes retain their original output.
	 */
	public function test_unrelated_shortcode_is_unchanged(): void {
		$subject = new Protect();

		self::assertSame( 'original', $subject->do_shortcode_tag( 'original', 'other', [], [] ) );
	}

	/**
	 * Script and style helpers act only after a Passster hCaptcha form is shown.
	 *
	 * @noinspection HtmlUnknownTarget
	 */
	public function test_frontend_script_and_style_helpers(): void {
		$subject    = new Protect();
		$form_shown = hcaptcha()->form_shown;
		$handle     = 'hcaptcha-passster';

		try {
			hcaptcha()->form_shown = false;
			$subject->enqueue_scripts();
			self::assertFalse( wp_script_is( $handle ) );

			hcaptcha()->form_shown = true;
			$subject->enqueue_scripts();
			self::assertTrue( wp_script_is( $handle ) );
			self::assertStringContainsString( 'hcaptcha-passster', wp_scripts()->registered[ $handle ]->src );

			// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- Input fixture for a tag filter.
			$tag = '<script src="passster.js"></script>';
			self::assertSame( $tag, $subject->add_type_module( $tag, 'other', '' ) );
			self::assertStringContainsString( 'type="module"', $subject->add_type_module( $tag, $handle, '' ) );

			ob_start();
			$subject->print_inline_styles();
			self::assertStringContainsString( '.passster-form .h-captcha', ob_get_clean() );
		} finally {
			hcaptcha()->form_shown = $form_shown;
			wp_dequeue_script( $handle );
			wp_deregister_script( $handle );
		}
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
		self::assertStringContainsString(
			HCaptcha::widget_id_value(
				[
					'source'  => [ 'content-protector/content-protector.php' ],
					'form_id' => '3866',
				]
			),
			$output
		);
	}

	/**
	 * Password and legacy input fields never become anti-spam entry data.
	 *
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_entry_contains_only_unlock_context(): void {
		$method = new ReflectionMethod( Protect::class, 'get_entry' );
		$method->setAccessible( true );

		self::assertSame(
			[
				'data' => [
					'form'    => 'protect',
					'area'    => '3866',
					'post_id' => '123',
					'type'    => 'password',
				],
			],
			$method->invoke(
				new Protect(),
				[
					'password' => 'private-password',
					'input'    => 'private-input',
					'area'     => '3866',
					'post_id'  => 123,
					'type'     => 'password',
				]
			)
		);
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
		$_POST['area_id']                      = 3866;

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
	 * Native Passster unlock requests must reject missing or failed hCaptcha proof at every route casing.
	 *
	 * @dataProvider dp_invalid_rest_unlock_requests
	 *
	 * @param string $route           Unlock route as sent by the client.
	 * @param bool   $failed_response Whether to supply a failed hCaptcha response.
	 *
	 * @return void
	 */
	public function test_rest_unlock_case_variants_reject_invalid_hcaptcha( string $route, bool $failed_response ): void {
		$_POST = [];
		$data  = [
			'password' => 'local-test-password',
			'type'     => 'password',
			'post_id'  => 123,
		];

		if ( $failed_response ) {
			$this->prepare_verify_post( 'hcaptcha_passster_nonce', 'hcaptcha_passster', false );
			$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
				[
					'source'  => [ 'content-protector/content-protector.php' ],
					'form_id' => 0,
				]
			);

			$data = array_merge( $data, $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}

		$request = new WP_REST_Request( 'POST', $route );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $data ) );

		$response = ( new Protect() )->verify_rest( null, null, $request );

		self::assertInstanceOf( WP_REST_Response::class, $response );
		self::assertFalse( $response->get_data()['success'] );
		self::assertNotSame( '', $response->get_data()['error'] );
		self::assertSame( $failed_response, hcaptcha()->has_result );
	}

	/**
	 * Invalid native unlock requests with canonical and case-variant routes.
	 *
	 * @return array
	 */
	public function dp_invalid_rest_unlock_requests(): array {
		return [
			'canonical without proof'   => [ '/passster/v1/unlock', false ],
			'uppercase without proof'   => [ '/PASSSTER/v1/UNLOCK', false ],
			'mixed case without proof'  => [ '/Passster/V1/UnLock', false ],
			'terminal LF without proof' => [ "/passster/v1/unlock\n", false ],
			'canonical failed proof'    => [ '/passster/v1/unlock', true ],
			'uppercase failed proof'    => [ '/PASSSTER/v1/UNLOCK', true ],
			'mixed case failed proof'   => [ '/Passster/V1/UnLock', true ],
			'terminal LF failed proof'  => [ "/Passster/V1/UnLock\n", true ],
		];
	}

	/**
	 * WordPress routes terminal-LF unlock requests to the native callback, so hCaptcha must run first.
	 *
	 * @return void
	 */
	public function test_rest_dispatch_guards_terminal_lf_unlock(): void {
		$subject    = new Protect();
		$server     = new WP_REST_Server();
		$dispatched = 0;

		$server->register_route(
			'passster/v1',
			'/passster/v1/unlock',
			[
				[
					'methods'             => 'POST',
					'callback'            => static function () use ( &$dispatched ): WP_REST_Response {
						++$dispatched;

						return new WP_REST_Response( [ 'dispatched' => true ], 202 );
					},
					'permission_callback' => '__return_true',
				],
			]
		);

		$request = new WP_REST_Request( 'POST', "/Passster/V1/UnLock\n" );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( '{}' );

		try {
			$response = $server->dispatch( $request );

			self::assertFalse( $response->get_data()['success'] );
			self::assertSame( 0, $dispatched );

			remove_filter( 'rest_pre_dispatch', [ $subject, 'verify_rest' ], 9 );
			$response = $server->dispatch( $request );

			self::assertSame( 202, $response->get_status() );
			self::assertSame( [ 'dispatched' => true ], $response->get_data() );
			self::assertSame( 1, $dispatched );
		} finally {
			remove_filter( 'rest_pre_dispatch', [ $subject, 'verify_rest' ], 9 );
		}
	}

	/**
	 * A valid hCaptcha response still lets a case-variant unlock reach Passster.
	 *
	 * @return void
	 */
	public function test_valid_case_variant_rest_unlock_is_unchanged(): void {
		$this->prepare_verify_post( 'hcaptcha_passster_nonce', 'hcaptcha_passster' );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'content-protector/content-protector.php' ],
				'form_id' => 0,
			]
		);

		$request = new WP_REST_Request( 'POST', '/PASSSTER/v1/UNLOCK' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			wp_json_encode(
				array_merge(
					[
						'password' => 'local-test-password',
						'type'     => 'password',
						'post_id'  => 123,
					],
					$_POST // phpcs:ignore WordPress.Security.NonceVerification.Missing
				)
			)
		);

		self::assertNull( ( new Protect() )->verify_rest( null, null, $request ) );
		self::assertTrue( hcaptcha()->has_result );
	}

	/**
	 * REST unlocks reject a widget for a different protected area.
	 */
	public function test_rest_unlock_rejects_foreign_area_widget(): void {
		$this->prepare_verify_post( 'hcaptcha_passster_nonce', 'hcaptcha_passster' );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'content-protector/content-protector.php' ],
				'form_id' => '3866',
			]
		);

		$request = new WP_REST_Request( 'POST', '/passster/v1/unlock' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( array_merge( $_POST, [ 'area_id' => 3867 ] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		$response = ( new Protect() )->verify_rest( null, null, $request );

		self::assertInstanceOf( WP_REST_Response::class, $response );
		self::assertSame( hcap_get_error_messages()['bad-signature'], $response->get_data()['error'] );
	}

	/**
	 * Legacy AJAX verification uses the native area field as the expected identity.
	 */
	public function test_legacy_verification_uses_native_area(): void {
		$_POST['area'] = '3866';
		$expected_id   = [];
		FunctionMocker::replace(
			'\HCaptcha\Helpers\API::verify_post',
			static function ( string $name, string $action, array $id ) use ( &$expected_id ): ?string {
				$expected_id = $id;

				return null;
			}
		);

		( new Protect() )->verify( 'password' );

		self::assertSame(
			[
				'source'  => [ 'content-protector/content-protector.php' ],
				'form_id' => '3866',
			],
			$expected_id
		);
	}

	/**
	 * Only POST requests matched to the native unlock route with no earlier response are intercepted.
	 *
	 * @dataProvider dp_unrelated_rest_unlock_requests
	 *
	 * @param string $method REST method.
	 * @param string $route  REST route.
	 *
	 * @return void
	 */
	public function test_unrelated_rest_unlock_requests_are_ignored( string $method, string $route ): void {
		$request = new WP_REST_Request( $method, $route );

		self::assertNull( ( new Protect() )->verify_rest( null, null, $request ) );
		self::assertFalse( hcaptcha()->has_result );
	}

	/**
	 * Similar routes and non-POST methods must not be guarded.
	 *
	 * @return array
	 */
	public function dp_unrelated_rest_unlock_requests(): array {
		return [
			'prefix'     => [ 'POST', '/passster/v1/unlock-more' ],
			'suffix'     => [ 'POST', '/other/passster/v1/unlock' ],
			'nearby'     => [ 'POST', '/passster/v1/unlocked' ],
			'GET unlock' => [ 'GET', '/PASSSTER/v1/UNLOCK' ],
			'PUT unlock' => [ 'PUT', '/Passster/V1/UnLock' ],
		];
	}

	/**
	 * An earlier REST pre-dispatch result must pass through unchanged.
	 *
	 * @return void
	 */
	public function test_existing_rest_pre_dispatch_result_is_unchanged(): void {
		$request = new WP_REST_Request( 'POST', '/PASSSTER/v1/UNLOCK' );
		$result  = new WP_REST_Response( [ 'handled' => true ], 202 );

		self::assertSame( $result, ( new Protect() )->verify_rest( $result, null, $request ) );
		self::assertFalse( hcaptcha()->has_result );
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
