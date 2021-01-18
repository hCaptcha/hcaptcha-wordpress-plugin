<?php
/**
 * MainPluginFileTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration;

/**
 * Test main plugin file.
 */
class AMainPluginFileTest extends HCaptchaWPTestCase {

	/**
	 * Test main plugin file content.
	 */
	public function test_main_file_content(): void {
		self::assertSame( '1.6.4', HCAPTCHA_VERSION );
		self::assertSame( realpath( __DIR__ . '/../../../' ), HCAPTCHA_PATH );

		$config = include __DIR__ . '/../../../.codeception/_config/params.local.php';
		$wp_url = $config['WP_URL'];
		self::assertSame( 'http://' . $wp_url . '/wp-content/plugins/hcaptcha-wordpress-plugin', HCAPTCHA_URL );

		self::assertSame( realpath( __DIR__ . '/../../../hcaptcha.php' ), HCAPTCHA_FILE );

		// request.php was required.
		self::assertTrue( function_exists( 'hcaptcha_request_verify' ) );
		self::assertTrue( function_exists( 'hcaptcha_verify_POST' ) );
		self::assertTrue( function_exists( 'hcaptcha_get_verify_output' ) );
		self::assertTrue( function_exists( 'hcaptcha_get_verify_message' ) );
		self::assertTrue( function_exists( 'hcaptcha_get_verify_message_html' ) );

		// functions.php was required.
		self::assertTrue( function_exists( 'hcap_form' ) );
		self::assertTrue( function_exists( 'hcap_form_display' ) );
		self::assertTrue( function_exists( 'hcap_shortcode' ) );
		self::assertTrue( shortcode_exists( 'hcaptcha' ) );
		self::assertTrue( function_exists( 'hcap_options' ) );

		self::assertTrue( function_exists( 'hcap_captcha_script' ) );
		self::assertSame( 10, has_action( 'wp_enqueue_scripts', 'hcap_captcha_script' ) );
		self::assertSame( 10, has_action( 'login_enqueue_scripts', 'hcap_captcha_script' ) );

		self::assertTrue( function_exists( 'hcap_load_modules' ) );
		self::assertSame( - PHP_INT_MAX, has_action( 'plugins_loaded', 'hcap_load_modules' ) );

		self::assertTrue( function_exists( 'hcaptcha_wp_load_textdomain' ) );
		self::assertSame( 10, has_action( 'plugins_loaded', 'hcaptcha_wp_load_textdomain' ) );
	}

	/**
	 * Test hcap_captcha_script().
	 */
	public function test_hcap_captcha_script(): void {
		self::assertFalse( wp_style_is( 'hcaptcha-style', 'enqueued' ) );
		self::assertFalse( wp_script_is( 'hcaptcha-script', 'enqueued' ) );

		do_action( 'wp_enqueue_scripts' );

		self::assertTrue( wp_style_is( 'hcaptcha-style', 'enqueued' ) );
		self::assertTrue( wp_script_is( 'hcaptcha-script', 'enqueued' ) );
	}

	/**
	 * Test hcap_hcaptcha_error_message().
	 */
	public function test_hcap_hcaptcha_error_message(): void {
		$hcaptcha_content = 'Some content';
		$expected         = '<p id="hcap_error" class="error hcap_error">The Captcha is invalid.</p>' . $hcaptcha_content;

		self::assertSame( $expected, hcap_hcaptcha_error_message( $hcaptcha_content ) );
	}

	/**
	 * Test hcap_load_modules().
	 *
	 * @param array $module Module to load.
	 *
	 * @dataProvider dp_test_hcap_load_modules
	 */
	public function test_hcap_load_modules( $module ): void {
		$plugin_option = $module[0];

		add_filter(
			'pre_option_' . $plugin_option,
			function ( $pre_option, $option, $default ) {
				return 'on';
			},
			10,
			3
		);

		$plugin_path = $module[1];

		$require = (array) $module[2];
		array_walk(
			$require,
			function ( &$value, $key ) {
				$value = WP_PLUGIN_DIR . '\\' . dirname( plugin_basename( HCAPTCHA_FILE ) ) . '\\' . $value;
				$value = str_replace( '/', '\\', $value );
			}
		);

		$intersect = array_intersect( $require, get_included_files() );
		if ( ! empty( $intersect ) ) {
			self::assertSame( $intersect, array_intersect( $intersect, $require ) );
		}

		hcap_load_modules();

		$intersect = array_intersect( $require, get_included_files() );
		if ( ! empty( $intersect ) ) {
			self::assertSame( $intersect, array_intersect( $intersect, $require ) );
		}

		add_filter(
			'pre_option_active_plugins',
			function ( $pre_option, $option, $default ) use ( &$plugin_path ) {
				return [ $plugin_path ];
			},
			10,
			3
		);

		hcap_load_modules();

		self::assertSame( $require, array_intersect( $require, get_included_files() ) );
	}

	/**
	 * Data provider for test_hcap_load_modules().
	 *
	 * @return array
	 */
	public function dp_test_hcap_load_modules() {
		$modules = [
			'Ninja Forms'               => [
				'hcaptcha_nf_status',
				'ninja-forms/ninja-forms.php',
				'nf/ninja-forms-hcaptcha.php',
			],
			'Contact Form 7'            => [
				'hcaptcha_cf7_status',
				'contact-form-7/wp-contact-form-7.php',
				'cf7/hcaptcha-cf7.php',
			],
			'Login Form'                => [
				'hcaptcha_lf_status',
				'',
				'default/login-form.php',
			],
			'Register Form'             => [
				'hcaptcha_rf_status',
				'',
				'default/register-form.php',
			],
			'Comment Form'              => [
				'hcaptcha_cmf_status',
				'',
				'default/comment-form.php',
			],
			'Lost Password Form'        => [
				'hcaptcha_lpf_status',
				'',
				[ 'common/lost-password-form.php', 'default/lost-password.php' ],
			],
			'WooCommerce Login'         => [
				'hcaptcha_wc_login_status',
				'woocommerce/woocommerce.php',
				'wc/wc-login.php',
			],
			'WooCommerce Register'      => [
				'hcaptcha_wc_reg_status',
				'woocommerce/woocommerce.php',
				'wc/wc-register.php',
			],
			'WooCommerce Lost Password' => [
				'hcaptcha_wc_lost_pass_status',
				'woocommerce/woocommerce.php',
				[ 'common/lost-password-form.php', 'wc/wc-lost-password.php' ],
			],
			'WooCommerce Checkout'      => [
				'hcaptcha_wc_checkout_status',
				'woocommerce/woocommerce.php',
				'wc/wc-checkout.php',
			],
			'BuddyPress Register'       => [
				'hcaptcha_bp_reg_status',
				'buddypress/bp-loader.php',
				'bp/bp-register.php',
			],
			'BuddyPress Create Group'   => [
				'hcaptcha_bp_create_group_status',
				'buddypress/bp-loader.php',
				'bp/bp-create-group.php',
			],
			'BB Press New Topic'        => [
				'hcaptcha_bbp_new_topic_status',
				'bbpress/bbpress.php',
				'bbp/bbp-new-topic.php',
			],
			'BB Press Reply'            => [
				'hcaptcha_bbp_reply_status',
				'bbpress/bbpress.php',
				'bbp/bbp-reply.php',
			],
			'WPForms Lite'              => [
				'hcaptcha_wpforms_status',
				'wpforms-lite/wpforms.php',
				'wpforms/wpforms.php',
			],
			'WPForms Pro'               => [
				'hcaptcha_wpforms_pro_status',
				'wpforms/wpforms.php',
				'wpforms/wpforms.php',
			],
			'wpForo New Topic'          => [
				'hcaptcha_wpforo_new_topic_status',
				'wpforo/wpforo.php',
				'wpforo/wpforo-new-topic.php',
			],
			'wpForo Reply'              => [
				'hcaptcha_wpforo_reply_status',
				'wpforo/wpforo.php',
				'wpforo/wpforo-reply.php',
			],
			'MailChimp'                 => [
				'hcaptcha_mc4wp_status',
				'mailchimp-for-wp/mailchimp-for-wp.php',
				'mailchimp/mailchimp-for-wp.php',
			],
			'Jetpack'                   => [
				'hcaptcha_jetpack_cf_status',
				'jetpack/jetpack.php',
				'jetpack/jetpack.php',
			],
			'Subscriber'                => [
				'hcaptcha_subscribers_status',
				'subscriber/subscriber.php',
				'subscriber/subscriber.php',
			],
			'WC Wishlist'               => [
				'hcaptcha_wc_wl_create_list_status',
				'woocommerce-wishlists/woocommerce-wishlists.php',
				'wc_wl/wc-wl-create-list.php',
			],
		];

		array_walk(
			$modules,
			function ( &$value, $key ) {
				$value = [ $value ];
			}
		);

		return $modules;
	}

	/**
	 * Test hcaptcha_wp_load_textdomain().
	 */
	public function test_hcaptcha_wp_load_textdomain(): void {
		$domain = 'hcaptcha-for-forms-and-more';
		$locale = 'en_US';

		$mofile =
			WP_PLUGIN_DIR . '/' . dirname( plugin_basename( HCAPTCHA_FILE ) ) . '/languages/' .
			$domain . '-' . $locale . '.mo';

		$override_filter_params = [];

		add_filter(
			'override_load_textdomain',
			function ( $override, $domain, $mofile ) use ( &$override_filter_params ) {
				$override_filter_params = [ $override, $domain, $mofile ];

				return $override;
			},
			10,
			3
		);

		self::assertEquals( 10, has_action( 'plugins_loaded', 'hcaptcha_wp_load_textdomain' ) );

		hcaptcha_wp_load_textdomain();

		self::assertFalse( $override_filter_params[0] );
		self::assertSame( $domain, $override_filter_params[1] );
		self::assertSame( $mofile, $override_filter_params[2] );
	}
}
