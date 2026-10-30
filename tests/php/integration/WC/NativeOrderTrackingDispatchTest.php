<?php
/**
 * NativeOrderTrackingDispatchTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\WC;

use HCaptcha\Helpers\API;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Settings\General;
use HCaptcha\WC\OrderTracking;
use WC_Order;
use WC_Post_Types;
use WC_Shortcodes;

/**
 * Test hCaptcha at the native WooCommerce order lookup boundary.
 *
 * @group wc
 */
class NativeOrderTrackingDispatchTest extends WooCommerceTestCase {

	/**
	 * Original request values.
	 *
	 * @var array
	 */
	private array $original_request = [];

	/**
	 * Original query values.
	 *
	 * @var array
	 */
	private array $original_get = [];

	/**
	 * Original POST values.
	 *
	 * @var array
	 */
	private array $original_post = [];

	/**
	 * Original request method.
	 *
	 * @var string|null
	 */
	private ?string $original_method = null;

	/**
	 * Prepare the native WooCommerce integration.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		WC_Post_Types::register_post_types();
		if ( ! did_action( 'woocommerce_after_register_post_type' ) ) {
			// WPTestCase restores action counts while registered post types persist.
			do_action( 'woocommerce_after_register_post_type' );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Preserve test request globals.
		$this->original_request = $_REQUEST;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Preserve test query globals.
		$this->original_get = $_GET;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Preserve test POST globals.
		$this->original_post = $_POST;
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Preserve test request method.
		$this->original_method = $_SERVER['REQUEST_METHOD'] ?? null;

		wp_set_current_user( 0 );
		hcaptcha()->settings()->set( 'mode', General::MODE_TEST_PUBLISHER );
		hcaptcha()->settings()->set( 'woocommerce_status', [ 'order_tracking' ] );

		$post_id = $this->factory()->post->create( [ 'post_type' => 'page' ] );

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Render the native shortcode on its page.
		$GLOBALS['post'] = get_post( $post_id );

		WC_Shortcodes::init();
		new OrderTracking();
	}

	/**
	 * Restore request values after native dispatch.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		$_REQUEST = $this->original_request;
		$_GET     = $this->original_get;
		$_POST    = $this->original_post;

		if ( null === $this->original_method ) {
			unset( $_SERVER['REQUEST_METHOD'] );
		} else {
			$_SERVER['REQUEST_METHOD'] = $this->original_method;
		}

		parent::tearDown();
	}

	/**
	 * Native GET and POST requests without a proof cannot disclose tracking data.
	 *
	 * @param string $method Request method.
	 * @param bool   $in_post Whether native fields are in the POST body.
	 *
	 * @return void
	 * @dataProvider dp_unverified_requests
	 */
	public function test_unverified_native_request_is_blocked( string $method, bool $in_post ): void {
		$this->prepare_tracking_request( $method, $in_post );
		$tracked = 0;
		$die     = [];

		add_action(
			'woocommerce_track_order',
			static function () use ( &$tracked ): void {
				++$tracked;
			}
		);
		add_filter(
			'wp_die_handler',
			static function () use ( &$die ) {
				return static function ( $message, $title, $args ) use ( &$die ): void {
					$die = [ $message, $title, $args ];
				};
			}
		);

		$output = do_shortcode( '[woocommerce_order_tracking]' );

		self::assertSame( 403, $die[2]['response'] ?? null );
		self::assertSame( 'hCaptcha', $die[1] ?? null );
		self::assertSame( 0, $tracked );
		self::assertStringNotContainsString( 'class="order-info"', $output );
	}

	/**
	 * Valid POST reuses an already verified challenge at the native lookup.
	 *
	 * @return void
	 */
	public function test_valid_post_reuses_one_siteverify_and_tracks_order(): void {
		$order = $this->prepare_tracking_request( 'POST', true );
		$id    = $this->get_tracking_id();
		$this->prepare_verify_request( 'sec012-valid-proof' );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value( $id );

		$siteverify_calls = 0;
		$tracked          = 0;
		add_filter(
			'pre_http_request',
			static function ( $preempt, $args, $url ) use ( &$siteverify_calls ) {
				if ( 'https://api.hcaptcha.com/siteverify' === $url ) {
					++$siteverify_calls;
				}

				return $preempt;
			},
			5,
			3
		);
		add_action(
			'woocommerce_track_order',
			static function ( $order_id ) use ( &$tracked, $order ): void {
				self::assertSame( $order->get_id(), $order_id );
				++$tracked;
			}
		);

		self::assertNull(
			API::verify(
				[
					'nonce_name'   => HCAPTCHA_NONCE,
					'nonce_action' => HCAPTCHA_ACTION,
					'expected_id'  => $id,
					'data'         => [ 'order_email' => 'buyer@sec012.test' ],
				]
			)
		);

		$output = do_shortcode( '[woocommerce_order_tracking]' );

		self::assertStringContainsString( 'class="order-info"', $output );
		self::assertSame( 1, $tracked );
		self::assertSame( 1, $siteverify_calls );
	}

	/**
	 * A signed matching form keeps its intentional protection exemption.
	 *
	 * @return void
	 */
	public function test_signed_same_form_exemption_keeps_tracking_available(): void {
		$this->prepare_tracking_request( 'POST', true );
		$id                                    = $this->get_tracking_id();
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value( $id );
		add_filter(
			'hcap_protect_form',
			static function ( $protect, $source, $form_id ) use ( $id ) {
				return $id['source'] === $source && $id['form_id'] === $form_id ? false : $protect;
			},
			10,
			3
		);

		$output = do_shortcode( '[woocommerce_order_tracking]' );

		self::assertStringContainsString( 'class="order-info"', $output );
	}

	/**
	 * An exemption for another form cannot authorize the order lookup.
	 *
	 * @return void
	 */
	public function test_foreign_signed_exemption_is_rejected(): void {
		$this->prepare_tracking_request( 'POST', true );
		$foreign_id                            = [
			'source'  => [ 'WordPress' ],
			'form_id' => 'register',
		];
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value( $foreign_id );
		add_filter(
			'hcap_protect_form',
			static function ( $protect, $source, $form_id ) use ( $foreign_id ) {
				return $foreign_id['source'] === $source && $foreign_id['form_id'] === $form_id ? false : $protect;
			},
			10,
			3
		);

		$die = [];
		add_filter(
			'wp_die_handler',
			static function () use ( &$die ) {
				return static function ( $message, $title, $args ) use ( &$die ): void {
					$die = [ $message, $title, $args ];
				};
			}
		);

		$output = do_shortcode( '[woocommerce_order_tracking]' );

		self::assertSame( 403, $die[2]['response'] ?? null );
		self::assertStringNotContainsString( 'class="order-info"', $output );
	}

	/**
	 * WooCommerce's own tracking nonce and billing-email guards still apply.
	 *
	 * @return void
	 */
	public function test_native_tracking_nonce_and_email_guards_remain(): void {
		$this->prepare_tracking_request( 'GET', false );
		$_REQUEST['woocommerce-order-tracking-nonce'] = 'invalid';
		$_GET['woocommerce-order-tracking-nonce']     = 'invalid';
		$output                                       = do_shortcode( '[woocommerce_order_tracking]' );
		self::assertStringContainsString( 'woocommerce-form-track-order', $output );
		self::assertStringNotContainsString( 'class="order-info"', $output );

		$this->prepare_tracking_request( 'POST', true );
		$_REQUEST['order_email'] = 'other@sec012.test';
		$_POST['order_email']    = 'other@sec012.test';
		$id                      = $this->get_tracking_id();
		$this->prepare_verify_request( 'sec012-wrong-email' );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value( $id );
		$output                                = do_shortcode( '[woocommerce_order_tracking]' );
		self::assertStringContainsString( 'woocommerce-form-track-order', $output );
		self::assertStringNotContainsString( 'class="order-info"', $output );
	}

	/**
	 * Requests accepted by the native tracking shortcode.
	 *
	 * @return array
	 */
	public function dp_unverified_requests(): array {
		return [
			'GET query'          => [ 'GET', false ],
			'POST query hybrid'  => [ 'POST', false ],
			'POST without proof' => [ 'POST', true ],
		];
	}

	/**
	 * Create an order and its native tracking request.
	 *
	 * @param string $method Request method.
	 * @param bool   $in_post Whether native values are in the POST body.
	 *
	 * @return WC_Order
	 */
	private function prepare_tracking_request( string $method, bool $in_post ): WC_Order {
		$order = wc_create_order();
		self::assertInstanceOf( WC_Order::class, $order );
		$order->set_billing_email( 'buyer@sec012.test' );
		$order->save();

		$fields = [
			'orderid'                          => (string) $order->get_id(),
			'order_email'                      => 'buyer@sec012.test',
			'woocommerce-order-tracking-nonce' => wp_create_nonce( 'woocommerce-order_tracking' ),
		];

		$_SERVER['REQUEST_METHOD'] = $method;
		$_REQUEST                  = $fields;
		$_POST                     = $in_post ? $fields : [];
		$_GET                      = $in_post ? [] : $fields;

		return $order;
	}

	/**
	 * Get the server-selected native form identity.
	 *
	 * @return array
	 */
	private function get_tracking_id(): array {
		return [
			'source'  => HCaptcha::get_class_source( OrderTracking::class ),
			'form_id' => 'order_tracking',
		];
	}
}
