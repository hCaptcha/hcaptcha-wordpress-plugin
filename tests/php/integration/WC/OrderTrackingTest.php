<?php
/**
 * OrderTrackingTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */

namespace HCaptcha\Tests\Integration\WC;

use HCaptcha\Settings\General;
use HCaptcha\AutoVerify\AutoVerify;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\WC\OrderTracking;
use WC_Shortcodes;

/**
 * Test OrderTrackingTest class.
 *
 * @group wc
 */
class OrderTrackingTest extends WooCommerceTestCase {

	/**
	 * Test constructor and init hooks.
	 */
	public function test_constructor_and_init_hooks(): void {
		$subject = new OrderTracking();

		self::assertSame(
			10,
			has_filter( 'do_shortcode_tag', [ $subject, 'do_shortcode_tag' ] )
		);
	}

	/**
	 * Test do_shortcode_tag().
	 *
	 * @noinspection PhpUndefinedClassInspection
	 */
	public function test_do_shortcode_tag(): void {
		$site_key  = General::MODE_TEST_PUBLISHER_SITE_KEY;
		$theme     = 'some theme';
		$size      = 'some size';
		$args      = [
			'action'  => HCAPTCHA_ACTION,
			'name'    => HCAPTCHA_NONCE,
			'size'    => $size,
			'auto'    => true,
			'id'      => [
				'source'  => [ 'woocommerce/woocommerce.php' ],
				'form_id' => 'order_tracking',
			],
			'sitekey' => $site_key,
			'theme'   => $theme,
		];
		$hcap_form = $this->get_hcap_form( $args );

		update_option(
			'hcaptcha_settings',
			[
				'site_key' => $site_key,
				'theme'    => $theme,
				'size'     => $size,
			]
		);

		hcaptcha()->init_hooks();

		$post_id = $this->factory()->post->create( [ 'post_type' => 'page' ] );

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Set the page rendered by the WooCommerce shortcode.
		$GLOBALS['post'] = get_post( $post_id );

		WC_Shortcodes::init();
		new OrderTracking();

		$output = do_shortcode( '[woocommerce_order_tracking]' );

		self::assertStringContainsString( 'woocommerce-form-track-order', $output );
		self::assertSame( 1, substr_count( $output, $hcap_form ) );
		self::assertLessThan( strpos( $output, 'name="track"' ), strpos( $output, $hcap_form ) );
	}

	/**
	 * Direct native rendering includes the widget without a shortcode output filter.
	 *
	 * @return void
	 */
	public function test_direct_native_render_includes_one_widget(): void {
		hcaptcha()->settings()->set( 'mode', General::MODE_TEST_PUBLISHER );
		$post_id = $this->factory()->post->create( [ 'post_type' => 'page' ] );

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Render the native tracking form on its page.
		$GLOBALS['post'] = get_post( $post_id );

		new OrderTracking();

		$output = WC_Shortcodes::order_tracking( [] );

		self::assertStringContainsString( 'woocommerce-form-track-order', $output );
		self::assertSame( 1, substr_count( $output, '<h-captcha' ) );
		self::assertSame( 1, substr_count( $output, 'name="' . HCaptcha::HCAPTCHA_WIDGET_ID . '"' ) );
	}

	/**
	 * Test do_shortcode_tag() when not order_tracking tag.
	 */
	public function test_do_shortcode_tag_when_NOT_order_tracking(): void {
		$output  = 'some output';
		$tag     = 'some_tag';
		$subject = new OrderTracking();

		self::assertSame( $output, $subject->do_shortcode_tag( $output, $tag, [], [] ) );
	}

	/**
	 * The registered native tracking route checks its normalized order email.
	 *
	 * @param bool $blocked Whether the native tracking email is disposable.
	 *
	 * @return void
	 * @dataProvider dp_email_policy
	 */
	public function test_native_tracking_email_policy( bool $blocked ): void {
		$email = $blocked ? 'Buyer@BLOCKED.test' : 'Buyer@allowed.test';
		$id    = [
			'source'  => [ 'woocommerce/woocommerce.php' ],
			'form_id' => 'order_tracking',
		];
		$this->prepare_verify_request( 'valid-tracking-response' );
		hcaptcha()->settings()->set( 'woocommerce_status', [ 'order_tracking' ] );
		hcaptcha()->settings()->set( 'disposable_email', [ 'on' ] );
		$_SERVER['REQUEST_METHOD']             = 'POST';
		$_SERVER['REQUEST_URI']                = '/native-tracking';
		$_POST['orderid']                      = '123';
		$_POST['order_email']                  = $email;
		$_POST['email']                        = 'forged@allowed.test';
		$_REQUEST['order_email']               = $email;
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value( $id );
		set_transient(
			AutoVerify::TRANSIENT,
			[
				'/native-tracking' => [
					[
						'inputs'    => [ 'orderid', 'order_email' ],
						'args'      => [
							'name'   => HCAPTCHA_NONCE,
							'action' => HCAPTCHA_ACTION,
							'id'     => $id,
						],
						'widget_id' => HCaptcha::widget_id_value( $id ),
					],
				],
			]
		);
		$checked = [];
		$die_arr = [];
		add_filter(
			'hcap_is_disposable_email',
			static function ( $disposable, string $address ) use ( &$checked ): bool {
				$checked[] = $address;

				return 'buyer@blocked.test' === strtolower( $address );
			},
			10,
			2
		);
		add_filter(
			'wp_die_handler',
			static function () use ( &$die_arr ) {
				return static function ( $message, $title, $args ) use ( &$die_arr ) {
					$die_arr = [ $message, $title, $args ];
				};
			}
		);
		new OrderTracking();

		try {
			( new AutoVerify() )->verify();
			self::assertSame( $blocked ? 'Please use a permanent email address.' : null, $die_arr[0] ?? null );
			self::assertSame( $blocked ? 403 : null, $die_arr[2]['response'] ?? null );
			self::assertSame( [ $email ], $checked );
		} finally {
			unset( $_SERVER['REQUEST_METHOD'], $_REQUEST['order_email'] );
			delete_transient( AutoVerify::TRANSIENT );
		}
	}

	/**
	 * The native order email mapping does not affect other automatic forms.
	 *
	 * @return void
	 */
	public function test_email_mapping_requires_registered_native_identity(): void {
		$entry                   = [
			'expected_id' => [
				'source'  => [ 'WordPress' ],
				'form_id' => 'register',
			],
			'data'        => [ 'email' => 'native@allowed.test' ],
		];
		$_REQUEST['order_email'] = 'forged@blocked.test';

		try {
			self::assertSame( $entry, ( new OrderTracking() )->add_email( $entry ) );
		} finally {
			unset( $_REQUEST['order_email'] );
		}
	}

	/**
	 * Native tracking email outcomes.
	 *
	 * @return array
	 */
	public function dp_email_policy(): array {
		return [
			'blocked address' => [ true ],
			'allowed address' => [ false ],
		];
	}
}
