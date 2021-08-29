<?php
/**
 * MainTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpLanguageLevelInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Integration;

use HCaptcha\AutoVerify\AutoVerify;
use HCaptcha\CF7\CF7;
use HCaptcha\Main;
use HCaptcha\NF\NF;
use ReflectionClass;
use ReflectionException;

/**
 * Test Main class.
 *
 * @group main
 *
 * @group bp
 * @group jetpack
 */
class AMainTest extends HCaptchaWPTestCase {

	/**
	 * Tear down test.
	 */
	public function tearDown(): void {
		global $hcaptcha_wordpress_plugin;

		unset( $GLOBALS['current_user'] );

		wp_dequeue_script( 'hcaptcha-script' );
		wp_deregister_script( 'hcaptcha-script' );

		wp_dequeue_style( 'hcaptcha-style' );
		wp_deregister_style( 'hcaptcha-style' );

		delete_option( 'hcaptcha_recaptchacompat' );
		delete_option( 'hcaptcha_language' );

		$hcaptcha_wordpress_plugin->form_shown = false;

		parent::tearDown();
	}

	/**
	 * Test init() and init_hooks().
	 *
	 * @param boolean $logged_in                   User is logged in.
	 * @param boolean $hcaptcha_off_when_logged_in Option 'hcaptcha_off_when_logged_in' is set.
	 * @param boolean $hcaptcha_active             Plugin should be active.
	 *
	 * @dataProvider dp_test_init
	 * @noinspection PhpUnitTestsInspection
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_init_and_init_hooks( $logged_in, $hcaptcha_off_when_logged_in, $hcaptcha_active ) {
		global $current_user, $hcaptcha_wordpress_plugin;

		// Plugin was loaded by codeception.
		self::assertTrue(
			in_array(
				$this->normalize_path( ABSPATH . 'wp-includes/pluggable.php' ),
				$this->normalize_path( get_included_files() ),
				true
			)
		);

		self::assertSame(
			- PHP_INT_MAX,
			has_action( 'plugins_loaded', [ $hcaptcha_wordpress_plugin, 'init_hooks' ] )
		);

		self::assertSame(
			10,
			has_filter(
				'wp_resource_hints',
				[ $hcaptcha_wordpress_plugin, 'prefetch_hcaptcha_dns' ]
			)
		);
		self::assertSame(
			0,
			has_action( 'wp_print_footer_scripts', [ $hcaptcha_wordpress_plugin, 'hcap_captcha_script' ] )
		);
		self::assertSame(
			- PHP_INT_MAX + 1,
			has_action( 'plugins_loaded', [ $hcaptcha_wordpress_plugin, 'hcap_load_modules' ] )
		);
		self::assertSame(
			10,
			has_filter(
				'woocommerce_login_credentials',
				[ $hcaptcha_wordpress_plugin, 'hcap_remove_wp_authenticate_user' ]
			)
		);
		self::assertSame(
			10,
			has_action( 'plugins_loaded', [ $hcaptcha_wordpress_plugin, 'hcaptcha_wp_load_textdomain' ] )
		);

		unset( $current_user );
		if ( $logged_in ) {
			wp_set_current_user( 1 );
		}

		if ( 'on' === $hcaptcha_off_when_logged_in ) {
			update_option( 'hcaptcha_off_when_logged_in', 'on' );
		} else {
			update_option( 'hcaptcha_off_when_logged_in', 'off' );
		}

		$subject = new Main();
		$subject->init_hooks();

		if ( $hcaptcha_active ) {
			self::assertSame(
				10,
				has_filter(
					'wp_resource_hints',
					[ $hcaptcha_wordpress_plugin, 'prefetch_hcaptcha_dns' ]
				)
			);
			self::assertSame(
				0,
				has_action( 'wp_print_footer_scripts', [ $hcaptcha_wordpress_plugin, 'hcap_captcha_script' ] )
			);
			self::assertSame(
				- PHP_INT_MAX + 1,
				has_action( 'plugins_loaded', [ $subject, 'hcap_load_modules' ] )
			);
			self::assertSame(
				10,
				has_filter(
					'woocommerce_login_credentials',
					[ $subject, 'hcap_remove_wp_authenticate_user' ]
				)
			);
			self::assertSame(
				10,
				has_action( 'plugins_loaded', [ $subject, 'hcaptcha_wp_load_textdomain' ] )
			);
			self::assertInstanceOf( AutoVerify::class, $this->get_protected_property( $subject, 'auto_verify' ) );
		} else {
			self::assertFalse(
				has_filter(
					'wp_resource_hints',
					[ $subject, 'prefetch_hcaptcha_dns' ]
				)
			);
			self::assertFalse(
				has_action( 'wp_print_footer_scripts', [ $subject, 'hcap_captcha_script' ] )
			);
			self::assertFalse(
				has_action( 'plugins_loaded', [ $subject, 'hcap_load_modules' ] )
			);
			self::assertFalse(
				has_filter(
					'woocommerce_login_credentials',
					[ $subject, 'hcap_remove_wp_authenticate_user' ]
				)
			);
			self::assertFalse(
				has_action( 'plugins_loaded', [ $subject, 'hcaptcha_wp_load_textdomain' ] )
			);
			self::assertNull( $this->get_protected_property( $subject, 'auto_verify' ) );
		}
	}

	/**
	 * Data provider for test_init().
	 *
	 * @return array[]
	 */
	public function dp_test_init() {
		return [
			'not logged in, not set' => [ false, 'off', true ],
			'not logged in, set'     => [ false, 'on', true ],
			'logged in, not set'     => [ true, 'off', true ],
			'logged in, set'         => [ true, 'on', false ],
		];
	}

	/**
	 * Test prefetch_hcaptcha_dns().
	 */
	public function test_prefetch_hcaptcha_dns() {
		$urls     = [
			'//s.w.org',
		];
		$expected = [
			'//s.w.org',
			'https://hcaptcha.com',
		];

		$subject = new Main();

		self::assertSame( $urls, $subject->prefetch_hcaptcha_dns( $urls, 'some-type' ) );
		self::assertSame( $expected, $subject->prefetch_hcaptcha_dns( $urls, 'dns-prefetch' ) );
	}

	/**
	 * Test hcap_captcha_script().
	 *
	 * @param string|false $compat              Compat option value.
	 * @param string|false $language            Language option value.
	 * @param string       $expected_script_src Expected script source.
	 *
	 * @dataProvider dp_test_hcap_captcha_script
	 */
	public function test_hcap_captcha_script( $compat, $language, $expected_script_src ): void {
		global $wp_scripts, $wp_styles, $hcaptcha_wordpress_plugin;

		$hcaptcha_wordpress_plugin->form_shown = true;

		$expected_style_src = HCAPTCHA_URL . '/css/style.css';

		update_option( 'hcaptcha_recaptchacompat', $compat );
		update_option( 'hcaptcha_language', $language );

		self::assertFalse( wp_style_is( 'hcaptcha-style', 'enqueued' ) );
		self::assertFalse( wp_script_is( 'hcaptcha-script', 'enqueued' ) );

		ob_start();
		do_action( 'wp_print_footer_scripts' );
		ob_get_clean();

		self::assertTrue( wp_style_is( 'hcaptcha-style', 'enqueued' ) );
		self::assertTrue( wp_script_is( 'hcaptcha-script', 'enqueued' ) );

		self::assertSame( $expected_style_src, $wp_styles->registered['hcaptcha-style']->src );
		self::assertSame( $expected_script_src, $wp_scripts->registered['hcaptcha-script']->src );
	}

	/**
	 * Data provider for test_hcap_captcha_script().
	 *
	 * @return array
	 */
	public function dp_test_hcap_captcha_script() {
		return [
			'no options'    => [ false, false, '//hcaptcha.com/1/api.js' ],
			'empty options' => [ '', '', '//hcaptcha.com/1/api.js' ],
			'compat only'   => [ 'on', false, '//hcaptcha.com/1/api.js?recaptchacompat=off' ],
			'language only' => [ false, 'ru', '//hcaptcha.com/1/api.js?hl=ru' ],
			'both options'  => [ 'on', 'ru', '//hcaptcha.com/1/api.js?recaptchacompat=off&hl=ru' ],
		];
	}

	/**
	 * Test hcap_captcha_script() when form NOT shown.
	 */
	public function test_hcap_captcha_script_when_form_NOT_shown(): void {
		global $hcaptcha_wordpress_plugin;

		self::assertFalse( wp_style_is( 'hcaptcha-style', 'enqueued' ) );
		self::assertFalse( wp_script_is( 'hcaptcha-script', 'enqueued' ) );

		ob_start();
		do_action( 'wp_print_footer_scripts' );
		ob_get_clean();

		self::assertFalse( wp_style_is( 'hcaptcha-style', 'enqueued' ) );
		self::assertFalse( wp_script_is( 'hcaptcha-script', 'enqueued' ) );
	}

	/**
	 * Test hcap_load_modules().
	 *
	 * @param array $module Module to load.
	 *
	 * @dataProvider dp_test_hcap_load_modules
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_hcap_load_modules( $module ): void {
		$subject = new Main();

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

		$component = (array) $module[2];

		array_walk(
			$component,
			function ( &$value, $key ) {
				if ( false === strpos( $value, '.php' ) ) {
					$reflection = new ReflectionClass( $value );
					$value      = $reflection->getFileName();
				} else {
					$value = WP_PLUGIN_DIR . '\\' . dirname( plugin_basename( HCAPTCHA_FILE ) ) . '\\' . $value;
				}

				$value = $this->normalize_path( $value );
			}
		);

		$intersect = array_intersect( $component, $this->normalize_path( get_included_files() ) );
		if ( ! empty( $intersect ) ) {
			self::assertSame( $intersect, array_intersect( $intersect, $component ) );
		}

		$subject->hcap_load_modules();

		$intersect = array_intersect( $component, $this->normalize_path( get_included_files() ) );
		if ( ! empty( $intersect ) ) {
			self::assertSame( $intersect, array_intersect( $intersect, $component ) );
		}

		add_filter(
			'pre_option_active_plugins',
			function ( $pre_option, $option, $default ) use ( &$plugin_path ) {
				return [ $plugin_path ];
			},
			10,
			3
		);

		$subject->hcap_load_modules();

		self::assertSame( $component, array_intersect( $component, $this->normalize_path( get_included_files() ) ) );
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
				NF::class,
			],
			'Contact Form 7'            => [
				'hcaptcha_cf7_status',
				'contact-form-7/wp-contact-form-7.php',
				CF7::class,
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
			static function ( &$value, $key ) {
				$value = [ $value ];
			}
		);

		return $modules;
	}

	/**
	 * Test hcap_remove_wp_authenticate_user().
	 *
	 * Must be after test_hcap_load_modules().
	 */
	public function test_hcap_remove_wp_authenticate_user(): void {
		add_filter( 'wp_authenticate_user', 'hcap_verify_login_captcha', 10, 2 );

		self::assertSame(
			10,
			has_filter( 'wp_authenticate_user', 'hcap_verify_login_captcha' )
		);

		$credentials = [
			'user_login'    => 'KAGG',
			'user_password' => 'Design',
			'remember'      => false,
		];
		self::assertSame( $credentials, apply_filters( 'woocommerce_login_credentials', $credentials ) );

		self::assertFalse(
			has_filter( 'wp_authenticate_user', 'hcap_verify_login_captcha' )
		);
	}

	/**
	 * Test hcaptcha_wp_load_textdomain().
	 */
	public function test_hcaptcha_wp_load_textdomain(): void {
		$subject = new Main();
		$subject->init_hooks();

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

		self::assertEquals( 10, has_action( 'plugins_loaded', [ $subject, 'hcaptcha_wp_load_textdomain' ] ) );

		$subject->hcaptcha_wp_load_textdomain();

		self::assertFalse( $override_filter_params[0] );
		self::assertSame( $domain, $override_filter_params[1] );
		self::assertSame( $mofile, $override_filter_params[2] );
	}

	/**
	 * Convert Windows path to Linux style to make tests OS-independent.
	 *
	 * @param string|string[] $path Path.
	 *
	 * @return string|string[]
	 */
	private function normalize_path( $path ) {
		return str_replace( '\\', '/', $path );
	}
}
