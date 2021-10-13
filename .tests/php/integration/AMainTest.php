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
use HCaptcha\Divi\Contact;
use HCaptcha\Jetpack\JetpackForm;
use HCaptcha\Main;
use HCaptcha\NF\NF;
use HCaptcha\ElementorPro\HCaptchaHandler;
use HCaptcha\WC\Checkout;
use HCaptcha\WC\OrderTracking;
use HCaptcha\WP\Comment;
use HCaptcha\WP\Login;
use HCaptcha\WP\LostPassword;
use HCaptcha\WP\Register;
use ReflectionException;
use tad\FunctionMocker\FunctionMocker;

/**
 * Test Main class.
 *
 * @group main
 *
 * @group bp
 * @group subscriber
 */
class AMainTest extends HCaptchaWPTestCase {

	/**
	 * Included components in test_load_modules().
	 *
	 * @var array
	 */
	private static $included_components = [];

	/**
	 * Tear down test.
	 */
	public function tearDown(): void {
		global $hcaptcha_wordpress_plugin;

		unset( $GLOBALS['current_user'], $GLOBALS['current_screen'] );

		wp_dequeue_script( 'hcaptcha' );
		wp_deregister_script( 'hcaptcha' );

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
			- PHP_INT_MAX + 1,
			has_action( 'plugins_loaded', [ $hcaptcha_wordpress_plugin, 'load_modules' ] )
		);
		self::assertSame(
			10,
			has_action( 'plugins_loaded', [ $hcaptcha_wordpress_plugin, 'load_textdomain' ] )
		);

		self::assertSame(
			10,
			has_filter(
				'wp_resource_hints',
				[ $hcaptcha_wordpress_plugin, 'prefetch_hcaptcha_dns' ]
			)
		);
		self::assertSame(
			10,
			has_action( 'wp_head', [ $hcaptcha_wordpress_plugin, 'print_inline_styles' ] )
		);
		self::assertSame(
			0,
			has_action( 'wp_print_footer_scripts', [ $hcaptcha_wordpress_plugin, 'print_footer_scripts' ] )
		);

		self::assertInstanceOf( AutoVerify::class, $this->get_protected_property( $hcaptcha_wordpress_plugin, 'auto_verify' ) );

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
				- PHP_INT_MAX + 1,
				has_action( 'plugins_loaded', [ $subject, 'load_modules' ] )
			);
			self::assertSame(
				10,
				has_action( 'plugins_loaded', [ $subject, 'load_textdomain' ] )
			);

			self::assertSame(
				10,
				has_filter(
					'wp_resource_hints',
					[ $subject, 'prefetch_hcaptcha_dns' ]
				)
			);
			self::assertSame(
				10,
				has_action( 'wp_head', [ $subject, 'print_inline_styles' ] )
			);
			self::assertSame(
				0,
				has_action( 'wp_print_footer_scripts', [ $subject, 'print_footer_scripts' ] )
			);

			self::assertInstanceOf( AutoVerify::class, $this->get_protected_property( $subject, 'auto_verify' ) );
		} else {
			self::assertFalse(
				has_action( 'plugins_loaded', [ $subject, 'load_modules' ] )
			);
			self::assertFalse(
				has_action( 'plugins_loaded', [ $subject, 'load_textdomain' ] )
			);

			self::assertFalse(
				has_filter(
					'wp_resource_hints',
					[ $subject, 'prefetch_hcaptcha_dns' ]
				)
			);
			self::assertFalse(
				has_action( 'wp_head', [ $subject, 'print_inline_styles' ] )
			);
			self::assertFalse(
				has_action( 'wp_print_footer_scripts', [ $subject, 'print_footer_scripts' ] )
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
	 * Test init() and init_hooks() on XMLRPC_REQUEST.
	 *
	 * @noinspection PhpUndefinedMethodInspection
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_init_and_init_hooks_on_xml_rpc_request() {
		$subject = \Mockery::mock( Main::class )->shouldAllowMockingProtectedMethods()->makePartial();
		$subject->shouldReceive( 'is_xml_rpc' )->andReturn( true );

		$subject->init();

		self::assertFalse(
			has_action( 'plugins_loaded', [ $subject, 'load_modules' ] )
		);
		self::assertFalse(
			has_action( 'plugins_loaded', [ $subject, 'load_textdomain' ] )
		);

		self::assertFalse(
			has_filter(
				'wp_resource_hints',
				[ $subject, 'prefetch_hcaptcha_dns' ]
			)
		);
		self::assertFalse(
			has_action( 'wp_head', [ $subject, 'print_inline_styles' ] )
		);
		self::assertFalse(
			has_action( 'wp_print_footer_scripts', [ $subject, 'print_footer_scripts' ] )
		);

		self::assertNull( $this->get_protected_property( $subject, 'auto_verify' ) );
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
	 * Test print_inline_styles().
	 */
	public function test_print_inline_styles() {
		$expected = '		<style>
			.h-captcha:not([data-size="invisible"]) {
				margin-bottom: 2rem;
			}
			.elementor-field-type-hcaptcha .elementor-field {
				background: transparent !important;
			}
			.elementor-field-type-hcaptcha .h-captcha {
				margin-bottom: -9px;
			}
		</style>
		';
		$subject  = new Main();

		ob_start();

		$subject->print_inline_styles();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test print_footer_scripts().
	 *
	 * @param string|false $compat              Compat option value.
	 * @param string|false $language            Language option value.
	 * @param string       $expected_script_src Expected script source.
	 *
	 * @dataProvider dp_test_print_footer_scripts
	 */
	public function test_print_footer_scripts( $compat, $language, $expected_script_src ): void {
		global $hcaptcha_wordpress_plugin;

		$hcaptcha_wordpress_plugin->form_shown = true;

		$expected_scripts = '<script>
			( () => {
				\'use strict\';

				let loaded = false,
					scrolled = false,
					timerId;

				function load() {
					if ( loaded ) {
						return;
					}

					loaded = true;
					clearTimeout( timerId );

					window.removeEventListener( \'touchstart\', load );
					document.removeEventListener( \'mouseenter\', load );
					document.removeEventListener( \'click\', load );
					window.removeEventListener( \'load\', delayedLoad );

							const t = document.getElementsByTagName( \'script\' )[0];
		const s = document.createElement(\'script\');
		s.type  = \'text/javascript\';
		s[\'src\'] = \'' . $expected_script_src . '\';
		s.async = true;
		t.parentNode.insertBefore( s, t );
						}

				function scrollHandler() {
					if ( ! scrolled ) {
						// Ignore first scroll event, which can be on page load.
						scrolled = true;
						return;
					}

					window.removeEventListener( \'scroll\', scrollHandler );
					load();
				}

				function delayedLoad() {
					window.addEventListener( \'scroll\', scrollHandler );
					const delay = -1;

					if ( delay >= 0 ) {
						setTimeout( load, delay );
					}
				}

				window.addEventListener( \'touchstart\', load );
				document.addEventListener( \'mouseenter\', load );
				document.addEventListener( \'click\', load );
				window.addEventListener( \'load\', delayedLoad );
			} )();
		</script>';

		update_option( 'hcaptcha_recaptchacompat', $compat );
		update_option( 'hcaptcha_language', $language );

		self::assertFalse( wp_script_is( 'hcaptcha' ) );

		ob_start();
		do_action( 'wp_print_footer_scripts' );
		$scripts = ob_get_clean();

		self::assertTrue( wp_script_is( 'hcaptcha' ) );

		self::assertNotFalse( strpos( $scripts, $expected_scripts ) );
	}

	/**
	 * Data provider for test_print_footer_scripts().
	 *
	 * @return array
	 */
	public function dp_test_print_footer_scripts() {
		return [
			'no options'    => [ false, false, 'https://js.hcaptcha.com/1/api.js?onload=hCaptchaOnLoad&render=explicit' ],
			'empty options' => [ '', '', 'https://js.hcaptcha.com/1/api.js?onload=hCaptchaOnLoad&render=explicit' ],
			'compat only'   => [ 'on', false, 'https://js.hcaptcha.com/1/api.js?onload=hCaptchaOnLoad&render=explicit&recaptchacompat=off' ],
			'language only' => [ false, 'ru', 'https://js.hcaptcha.com/1/api.js?onload=hCaptchaOnLoad&render=explicit&hl=ru' ],
			'both options'  => [ 'on', 'ru', 'https://js.hcaptcha.com/1/api.js?onload=hCaptchaOnLoad&render=explicit&recaptchacompat=off&hl=ru' ],
		];
	}

	/**
	 * Test print_footer_scripts() in admin.
	 */
	public function test_print_footer_scripts_in_admin(): void {
		set_current_screen( 'edit-post' );

		self::assertFalse( wp_script_is( 'hcaptcha' ) );

		ob_start();
		do_action( 'wp_print_footer_scripts' );
		$scripts = ob_get_clean();

		self::assertFalse( strpos( $scripts, '<style>' ) );
		self::assertFalse( strpos( $scripts, 'api.js' ) );

		self::assertFalse( wp_script_is( 'hcaptcha' ) );
	}

	/**
	 * Test print_footer_scripts() when form NOT shown.
	 */
	public function test_print_footer_scripts_when_form_NOT_shown(): void {
		self::assertFalse( wp_script_is( 'hcaptcha' ) );

		ob_start();
		do_action( 'wp_print_footer_scripts' );
		$scripts = ob_get_clean();

		self::assertFalse( strpos( $scripts, '<style>' ) );
		self::assertFalse( strpos( $scripts, 'api.js' ) );

		self::assertFalse( wp_script_is( 'hcaptcha' ) );
	}

	/**
	 * Test load_modules().
	 *
	 * @param array $module Module to load.
	 *
	 * @dataProvider dp_test_load_modules
	 */
	public function test_load_modules( $module ): void {
		$subject = new Main();

		$plugin_option = $module[0];

		add_filter(
			'pre_option_' . $plugin_option,
			static function () {
				return 'on';
			},
			10,
			3
		);

		$plugin_path = '';
		$template    = '';

		if (
			$module[1] &&
			false !== strpos( $module[1], '.php' ) ) {
			$plugin_path = $module[1];
		}

		if (
			$module[1] &&
			false === strpos( $module[1], '.php' ) ) {
			$template = $module[1];
		}

		$component = (array) $module[2];

		$expected_loaded_classes = [];
		self::assertSame( $expected_loaded_classes, $subject->loaded_classes );

		array_walk(
			$component,
			function ( &$value ) use ( &$expected_loaded_classes ) {
				if ( false === strpos( $value, '.php' ) ) {
					if ( ! class_exists( $value, false ) ) {
						$expected_loaded_classes[] = $value;
					}

					$value = str_replace( 'HCaptcha\\', HCAPTCHA_PATH . '/src/php/', $value );

					$value .= '.php';
				} else {
					$value = HCAPTCHA_INC . '/' . $value;
				}

				$value = $this->normalize_path( $value );
			}
		);

		$this->check_component_loaded( $component );

		// Test with plugin not active.
		$subject->load_modules();

		if ( ! $module[1] ) {
			self::$included_components = array_unique( array_merge( self::$included_components, $component ) );
		}
		$this->check_component_loaded( $component );

		if ( $plugin_path ) {
			add_filter(
				'pre_option_active_plugins',
				static function () use ( &$plugin_path ) {
					return [ $plugin_path ];
				},
				10,
				3
			);
		}

		if ( $template ) {
			add_filter(
				'template',
				static function () use ( $template ) {
					return $template;
				},
				20
			);
		}

		// Test with plugin active.
		$subject->load_modules();

		self::$included_components = array_unique( array_merge( self::$included_components, $component ) );
		$this->check_component_loaded( $component );

		self::assertSame( $expected_loaded_classes, array_keys( $subject->loaded_classes ) );

		foreach ( $subject->loaded_classes as $class_name => $loaded_class ) {
			self::assertInstanceOf( $class_name, $loaded_class );
		}
	}

	/**
	 * Data provider for test_load_modules().
	 *
	 * @return array
	 */
	public function dp_test_load_modules() {
		$modules = [
			'Login Form'                 => [
				'hcaptcha_lf_status',
				'',
				Login::class,
			],
			'Register Form'              => [
				'hcaptcha_rf_status',
				'',
				Register::class,
			],
			'Lost Password Form'         => [
				'hcaptcha_lpf_status',
				'',
				LostPassword::class,
			],
			'Comment Form'               => [
				'hcaptcha_cmf_status',
				'',
				Comment::class,
			],
			'bbPress New Topic'          => [
				'hcaptcha_bbp_new_topic_status',
				'bbpress/bbpress.php',
				'bbp/bbp-new-topic.php',
			],
			'bbPress Reply'              => [
				'hcaptcha_bbp_reply_status',
				'bbpress/bbpress.php',
				'bbp/bbp-reply.php',
			],
			'BuddyPress Create Group'    => [
				'hcaptcha_bp_create_group_status',
				'buddypress/bp-loader.php',
				'bp/bp-create-group.php',
			],
			'BuddyPress Register'        => [
				'hcaptcha_bp_reg_status',
				'buddypress/bp-loader.php',
				'bp/bp-register.php',
			],
			'Contact Form 7'             => [
				'hcaptcha_cf7_status',
				'contact-form-7/wp-contact-form-7.php',
				CF7::class,
			],
			'Divi Contact Form'          => [
				'hcaptcha_divi_cf_status',
				'Divi',
				Contact::class,
			],
			'Elementor Pro Form'         => [
				'hcaptcha_elementor__pro_form_status',
				'elementor-pro/elementor-pro.php',
				HCaptchaHandler::class,
			],
			'Jetpack'                    => [
				'hcaptcha_jetpack_cf_status',
				'jetpack/jetpack.php',
				JetpackForm::class,
			],
			'MailChimp'                  => [
				'hcaptcha_mc4wp_status',
				'mailchimp-for-wp/mailchimp-for-wp.php',
				'mailchimp/mailchimp-for-wp.php',
			],
			'MemberPress Register'       => [
				'hcaptcha_memberpress_register_status',
				'memberpress/memberpress.php',
				\HCaptcha\MemberPress\Register::class,
			],
			'Ninja Forms'                => [
				'hcaptcha_nf_status',
				'ninja-forms/ninja-forms.php',
				NF::class,
			],
			'Subscriber'                 => [
				'hcaptcha_subscribers_status',
				'subscriber/subscriber.php',
				'subscriber/subscriber.php',
			],
			'WooCommerce Login'          => [
				'hcaptcha_wc_login_status',
				'woocommerce/woocommerce.php',
				\HCaptcha\WC\Login::class,
			],
			'WooCommerce Register'       => [
				'hcaptcha_wc_reg_status',
				'woocommerce/woocommerce.php',
				\HCaptcha\WC\Register::class,
			],
			'WooCommerce Lost Password'  => [
				'hcaptcha_wc_lost_pass_status',
				'woocommerce/woocommerce.php',
				[ LostPassword::class, \HCaptcha\WC\LostPassword::class ],
			],
			'WooCommerce Checkout'       => [
				'hcaptcha_wc_checkout_status',
				'woocommerce/woocommerce.php',
				Checkout::class,
			],
			'WooCommerce Order Tracking' => [
				'hcaptcha_wc_order_tracking_status',
				'woocommerce/woocommerce.php',
				OrderTracking::class,
			],
			'WooCommerce Wishlists'      => [
				'hcaptcha_wc_wl_create_list_status',
				'woocommerce-wishlists/woocommerce-wishlists.php',
				'wc_wl/wc-wl-create-list.php',
			],
			'WPForms Lite'               => [
				'hcaptcha_wpforms_status',
				'wpforms-lite/wpforms.php',
				'wpforms/wpforms.php',
			],
			'WPForms Pro'                => [
				'hcaptcha_wpforms_pro_status',
				'wpforms/wpforms.php',
				'wpforms/wpforms.php',
			],
			'wpForo New Topic'           => [
				'hcaptcha_wpforo_new_topic_status',
				'wpforo/wpforo.php',
				'wpforo/wpforo-new-topic.php',
			],
			'wpForo Reply'               => [
				'hcaptcha_wpforo_reply_status',
				'wpforo/wpforo.php',
				'wpforo/wpforo-reply.php',
			],
		];

		array_walk(
			$modules,
			static function ( &$value ) {
				$value = [ $value ];
			}
		);

		return $modules;
	}

	/**
	 * Test load_textdomain().
	 */
	public function test_load_textdomain(): void {
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

		self::assertEquals( 10, has_action( 'plugins_loaded', [ $subject, 'load_textdomain' ] ) );

		$subject->load_textdomain();

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

	/**
	 * Check that component is loaded.
	 *
	 * @param array $component Component.
	 */
	public function check_component_loaded( array $component ) {
		$intersect = array_intersect( $component, $this->normalize_path( get_included_files() ) );
		$included  = array_intersect( $component, self::$included_components );
		self::assertSame( $included, $intersect );
	}
}
