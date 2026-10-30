<?php
/**
 * StoreApiCheckoutRoutesTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedNamespaceInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Integration\WC;

use Automattic\WooCommerce\StoreApi\Routes\V1\Checkout as StoreApiCheckout;
use Automattic\WooCommerce\StoreApi\RoutesController;
use Automattic\WooCommerce\StoreApi\StoreApi;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\WC\Checkout;
use ReflectionClass;
use WC_Payment_Gateway;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Test checkout protection through the live WooCommerce Store API routes.
 *
 * @group wc
 * @group wc-checkout
 */
class StoreApiCheckoutRoutesTest extends WooCommerceTestCase {

	/**
	 * Error used to stop native callbacks after observing the verification result.
	 */
	private const OBSERVER_CODE = 'store_api_checkout_observed';

	/**
	 * Challenge sent to the controlled siteverify response.
	 */
	private const CAPTCHA_RESPONSE = 'store-api-checkout-response';

	/**
	 * REST server.
	 *
	 * @var WP_REST_Server
	 */
	private WP_REST_Server $server;

	/**
	 * Previous REST server.
	 *
	 * @var WP_REST_Server|null
	 */
	private ?WP_REST_Server $previous_server;

	/**
	 * Previous payment gateways.
	 *
	 * @var WC_Payment_Gateway[]
	 */
	private array $previous_gateways;

	/**
	 * Handler selected by WordPress before its callback executes.
	 *
	 * @var array
	 */
	private array $observed_handler = [];

	/**
	 * Response received after the hCaptcha filter.
	 *
	 * @var mixed
	 */
	private $observed_response;

	/**
	 * Raw route passed to the selected native handler.
	 *
	 * @var string
	 */
	private string $observed_route = '';

	/**
	 * Number of siteverify requests.
	 *
	 * @var int
	 */
	private int $siteverify_calls = 0;

	/**
	 * Set up the native route registry and a callback observer.
	 *
	 * @return void
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function setUp(): void {
		parent::setUp();

		$_POST                   = [];
		$this->previous_gateways = WC()->payment_gateways()->payment_gateways;

		$this->add_express_gateway();

		$this->previous_server = $GLOBALS['wp_rest_server'] ?? null;

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Isolate the native REST registry for this test.
		$GLOBALS['wp_rest_server'] = null;
		$this->server              = rest_get_server();

		// Use WooCommerce's route registrar even when its lifecycle hooks fired before plugin activation.
		StoreApi::container()->get( RoutesController::class )->register_all_routes();

		new Checkout();

		add_filter( 'rest_request_before_callbacks', [ $this, 'observe_native_callback' ], PHP_INT_MAX, 3 );
		add_filter( 'pre_http_request', [ $this, 'observe_siteverify' ], 9, 3 );
	}

	/**
	 * Restore the registry and gateways.
	 *
	 * @return void
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function tearDown(): void {
		remove_filter( 'rest_request_before_callbacks', [ $this, 'observe_native_callback' ], PHP_INT_MAX );
		remove_filter( 'pre_http_request', [ $this, 'observe_siteverify' ], 9 );

		WC()->payment_gateways()->payment_gateways = $this->previous_gateways;

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restore the registry that existed before this test.
		$GLOBALS['wp_rest_server'] = $this->previous_server;

		parent::tearDown();
	}

	/**
	 * Record the actual matched handler and stop native order and payment callbacks.
	 *
	 * @param mixed           $response Prior response.
	 * @param array           $handler  Native handler selected by WordPress.
	 * @param WP_REST_Request $request  Request.
	 *
	 * @return WP_Error
	 */
	public function observe_native_callback( $response, array $handler, WP_REST_Request $request ): WP_Error {
		$this->observed_handler  = $handler;
		$this->observed_response = $response;
		$this->observed_route    = $request->get_route();

		return is_wp_error( $response ) ? $response : new WP_Error(
			self::OBSERVER_CODE,
			'The native callback was intercepted.',
			[ 'status' => 418 ]
		);
	}

	/**
	 * Count the siteverify request controlled by prepare_verify_request().
	 *
	 * @param mixed  $response Prior HTTP response.
	 * @param array  $args     HTTP arguments.
	 * @param string $url      HTTP URL.
	 *
	 * @return mixed
	 */
	public function observe_siteverify( $response, array $args, string $url ) {
		if ( 'https://api.hcaptcha.com/siteverify' === $url ) {
			++$this->siteverify_calls;

			self::assertSame( self::CAPTCHA_RESPONSE, $args['body']['response'] );
		}

		return $response;
	}

	/**
	 * Test that all native checkout spellings reject an absent or invalid widget.
	 *
	 * @dataProvider invalid_widget_provider
	 *
	 * @param string $route       Native route spelling.
	 * @param string $widget_type Missing, malformed, or foreign widget.
	 *
	 * @return void
	 */
	public function test_native_checkout_aliases_reject_missing_or_invalid_widget( string $route, string $widget_type ): void {
		$payload = [];

		if ( 'malformed' === $widget_type ) {
			$payload[ HCaptcha::HCAPTCHA_WIDGET_ID ] = $this->get_widget_id() . '-tampered';
		} elseif ( 'foreign' === $widget_type ) {
			$payload[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
				[
					'source'  => [ 'woocommerce/woocommerce.php' ],
					'form_id' => 'login',
				]
			);
		}

		$response = $this->dispatch_request( 'POST', $route, $payload );

		$this->assert_checkout_handler( $route );

		self::assertInstanceOf( WP_Error::class, $this->observed_response );
		self::assertSame( 400, $response->get_status() );
		self::assertSame( 'bad-signature', $response->get_data()['code'] );
		self::assertSame( 0, $this->siteverify_calls );
	}

	/**
	 * Supply native routes and invalid widget states.
	 *
	 * @return array
	 */
	public function invalid_widget_provider(): array {
		$cases = [];

		foreach ( $this->checkout_route_provider() as $name => $route ) {
			$cases[ $name . ' missing' ]   = [ $route[0], 'missing' ];
			$cases[ $name . ' malformed' ] = [ $route[0], 'malformed' ];
			$cases[ $name . ' foreign' ]   = [ $route[0], 'foreign' ];
		}

		return $cases;
	}

	/**
	 * A signed checkout widget still requires a CAPTCHA response.
	 *
	 * @dataProvider checkout_route_provider
	 *
	 * @param string $route Native route spelling.
	 *
	 * @return void
	 */
	public function test_native_checkout_aliases_reject_empty_captcha( string $route ): void {
		$payload                       = $this->get_captcha_payload();
		$payload['h-captcha-response'] = '';
		$response                      = $this->dispatch_request( 'POST', $route, $payload );

		$this->assert_checkout_handler( $route );

		self::assertInstanceOf( WP_Error::class, $this->observed_response );
		self::assertSame( 400, $response->get_status() );
		self::assertSame( 'empty', $response->get_data()['code'] );
		self::assertSame( 0, $this->siteverify_calls );
	}

	/**
	 * Test that a genuine matching signed widget reaches successful siteverify.
	 *
	 * @dataProvider checkout_route_provider
	 *
	 * @param string $route Native route spelling.
	 *
	 * @return void
	 */
	public function test_native_checkout_aliases_accept_verified_captcha( string $route ): void {
		$response = $this->dispatch_request( 'POST', $route, $this->get_captcha_payload() );

		$this->assert_checkout_handler( $route );

		self::assertNull( $this->observed_response );
		self::assertSame( self::OBSERVER_CODE, $response->get_data()['code'] );
		self::assertSame( 1, $this->siteverify_calls );
	}

	/**
	 * Test that a matching signed checkout identity retains its configured exemption.
	 *
	 * @dataProvider checkout_route_provider
	 *
	 * @param string $route Native route spelling.
	 *
	 * @return void
	 */
	public function test_native_checkout_aliases_accept_matching_exemption( string $route ): void {
		$policy_calls = 0;
		$policy       = static function ( bool $protect, array $source, $form_id ) use ( &$policy_calls ): bool {
			if ( [ 'woocommerce/woocommerce.php' ] === $source && 'checkout' === $form_id ) {
				++$policy_calls;

				return false;
			}

			return $protect;
		};

		add_filter( 'hcap_protect_form', $policy, 20, 3 );

		try {
			$response = $this->dispatch_request(
				'POST',
				$route,
				[ HCaptcha::HCAPTCHA_WIDGET_ID => $this->get_widget_id() ]
			);
		} finally {
			remove_filter( 'hcap_protect_form', $policy, 20 );
		}

		$this->assert_checkout_handler( $route );

		self::assertNull( $this->observed_response );
		self::assertSame( self::OBSERVER_CODE, $response->get_data()['code'] );
		self::assertGreaterThan( 0, $policy_calls );
		self::assertSame( 0, $this->siteverify_calls );
	}

	/**
	 * Test that native read and draft-update methods retain their existing behavior.
	 *
	 * @dataProvider non_post_method_provider
	 *
	 * @param string $route  Native route spelling.
	 * @param string $method Native HTTP method.
	 *
	 * @return void
	 */
	public function test_native_checkout_aliases_preserve_non_post_methods( string $route, string $method ): void {
		$response = $this->dispatch_request( $method, $route );

		$this->assert_checkout_handler( $route );

		self::assertNull( $this->observed_response );
		self::assertSame( self::OBSERVER_CODE, $response->get_data()['code'] );
		self::assertFalse( hcaptcha()->has_result );
		self::assertSame( 0, $this->siteverify_calls );
	}

	/**
	 * Supply native checkout read and draft-update requests.
	 *
	 * @return array
	 */
	public function non_post_method_provider(): array {
		$cases = [];

		foreach ( $this->checkout_route_provider() as $name => $route ) {
			$cases[ $name . ' GET' ]   = [ $route[0], 'GET' ];
			$cases[ $name . ' PATCH' ] = [ $route[0], 'PATCH' ];
		}

		return $cases;
	}

	/**
	 * A configured express gateway cannot exempt native paid checkout requests.
	 *
	 * @dataProvider checkout_route_provider
	 *
	 * @param string $route Native route spelling.
	 *
	 * @return void
	 */
	public function test_native_checkout_aliases_reject_configured_express_gateway_without_captcha( string $route ): void {
		$response = $this->dispatch_request( 'POST', $route, [ 'payment_method' => 'store_api_express_gateway' ] );

		$this->assert_checkout_handler( $route );

		self::assertInstanceOf( WP_Error::class, $this->observed_response );
		self::assertSame( 400, $response->get_status() );
		self::assertSame( 'bad-signature', $response->get_data()['code'] );
		self::assertFalse( hcaptcha()->has_result );
		self::assertSame( 0, $this->siteverify_calls );
	}

	/**
	 * Test that a different native Store API handler does not enter checkout verification.
	 *
	 * @return void
	 */
	public function test_unrelated_native_route_is_unchanged(): void {
		$response = $this->dispatch_request( 'GET', '/wc/store/v1/cart' );

		self::assertNotEmpty( $this->observed_handler );
		self::assertNotInstanceOf( StoreApiCheckout::class, $this->observed_handler['callback'][0] );
		self::assertNull( $this->observed_response );
		self::assertSame( self::OBSERVER_CODE, $response->get_data()['code'] );
		self::assertFalse( hcaptcha()->has_result );
	}

	/**
	 * Test that native checkout routes do not dispatch trailing-slash spellings.
	 *
	 * @return void
	 */
	public function test_trailing_slash_routes_do_not_dispatch(): void {
		foreach ( [ '/wc/store/v1/checkout/', '/wc/store/checkout/' ] as $route ) {
			$response = $this->dispatch_request( 'POST', $route );

			self::assertSame( 'rest_no_route', $response->get_data()['code'] );
			self::assertSame( [], $this->observed_handler );
			self::assertFalse( hcaptcha()->has_result );
		}
	}

	/**
	 * Supply registered aliases and case variants accepted by the native dispatcher.
	 *
	 * @return array
	 */
	public function checkout_route_provider(): array {
		return [
			'versioned'        => [ '/wc/store/v1/checkout' ],
			'unversioned'      => [ '/wc/store/checkout' ],
			'versioned case'   => [ '/wc/store/v1/CHECKOUT' ],
			'unversioned case' => [ '/wc/store/CHECKOUT' ],
		];
	}

	/**
	 * Dispatch through WordPress with native address validation before the observer.
	 *
	 * @param string $method  HTTP method.
	 * @param string $route   Native route spelling.
	 * @param array  $payload Extra request data.
	 *
	 * @return WP_REST_Response
	 */
	private function dispatch_request( string $method, string $route, array $payload = [] ): WP_REST_Response {
		$request = new WP_REST_Request( $method, $route );

		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			wp_json_encode(
				array_merge(
					[
						'billing_address' => [
							'first_name' => 'Jane',
							'last_name'  => 'Doe',
							'address_1'  => '1 Test Street',
							'city'       => 'New York',
							'state'      => 'NY',
							'postcode'   => '10001',
							'country'    => 'US',
							'email'      => 'buyer@example.com',
						],
					],
					$payload
				)
			)
		);

		return $this->server->dispatch( $request );
	}

	/**
	 * Assert that WordPress selected the installed native Checkout callback.
	 *
	 * @param string $route Requested route.
	 *
	 * @return void
	 */
	private function assert_checkout_handler( string $route ): void {
		self::assertNotEmpty( $this->observed_handler );
		self::assertInstanceOf( StoreApiCheckout::class, $this->observed_handler['callback'][0] );
		self::assertSame( 'get_response', $this->observed_handler['callback'][1] );
		self::assertSame( $route, $this->observed_route );
		self::assertStringStartsWith(
			wp_normalize_path( WP_PLUGIN_DIR . '/woocommerce/' ),
			wp_normalize_path( ( new ReflectionClass( $this->observed_handler['callback'][0] ) )->getFileName() )
		);
	}

	/**
	 * Get a genuine signed widget for this integration's fixed checkout identity.
	 *
	 * @return string
	 */
	private function get_widget_id(): string {
		return HCaptcha::widget_id_value(
			[
				'source'  => [ 'woocommerce/woocommerce.php' ],
				'form_id' => 'checkout',
			]
		);
	}

	/**
	 * Prepare a genuine signed widget and controlled successful challenge response.
	 *
	 * @return array
	 */
	private function get_captcha_payload(): array {
		$this->prepare_verify_request( self::CAPTCHA_RESPONSE );

		$payload = [
			HCaptcha::HCAPTCHA_WIDGET_ID => $this->get_widget_id(),
			'h-captcha-response'         => self::CAPTCHA_RESPONSE,
			'hcap_hp_test'               => '',
			'hcap_hp_sig'                => wp_create_nonce( 'hcap_hp_test' ),
			'hcap_fst_token'             => 'test_token',
		];

		$_POST = [];

		return $payload;
	}

	/**
	 * Register an available express gateway before native route schemas are generated.
	 *
	 * @return void
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	private function add_express_gateway(): void {
		WC()->payment_gateways()->payment_gateways[] = new class() extends WC_Payment_Gateway {

			/**
			 * Constructor.
			 *
			 * @noinspection PhpDynamicFieldDeclarationInspection*/
			public function __construct() {
				$this->id       = 'store_api_express_gateway';
				$this->supports = [ 'wc_stripe_banner_checkout' ];
			}

			/**
			 * Return native availability for this configured test gateway.
			 *
			 * @return bool
			 * @noinspection PhpUnused
			 */
			public function is_available(): bool {
				return true;
			}

			/**
			 * Return the configured express checkout flag.
			 *
			 * @return bool
			 * @noinspection PhpUnused
			 */
			public function banner_checkout_enabled(): bool {
				return true;
			}
		};
	}
}
