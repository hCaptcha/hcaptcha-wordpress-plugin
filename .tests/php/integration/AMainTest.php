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
use HCaptcha\BBPress\NewTopic;
use HCaptcha\BBPress\Reply;
use HCaptcha\CF7\CF7;
use HCaptcha\Divi\Contact;
use HCaptcha\FluentForm\Form;
use HCaptcha\Jetpack\JetpackForm;
use HCaptcha\Main;
use HCaptcha\ElementorPro\HCaptchaHandler;
use HCaptcha\NF\NF;
use HCaptcha\Quform\Quform;
use HCaptcha\WC\Checkout;
use HCaptcha\WC\OrderTracking;
use HCaptcha\WP\Comment;
use HCaptcha\WP\Login;
use HCaptcha\WP\LostPassword;
use HCaptcha\WP\Register;
use Mockery;
use ReflectionException;
use stdClass;

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
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function tearDown(): void {
		$hcaptcha_wordpress_plugin = hcaptcha();

		$loaded_classes = $this->get_protected_property( $hcaptcha_wordpress_plugin, 'loaded_classes' );

		// phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
		unset(
			$GLOBALS['current_user'],
			$GLOBALS['current_screen'],
			$loaded_classes[ HCaptchaHandler::class ],
			$_SERVER['REQUEST_URI'],
			$_GET['post'],
			$_GET['action'],
			$_GET['elementor-preview'],
			$_POST['action']
		);
		// phpcs:enable WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing

		$this->set_protected_property( $hcaptcha_wordpress_plugin, 'loaded_classes', $loaded_classes );

		wp_dequeue_script( 'hcaptcha' );
		wp_deregister_script( 'hcaptcha' );

		wp_dequeue_script( 'hcaptcha-elementor-pro-frontend' );
		wp_deregister_script( 'hcaptcha-elementor-pro-frontend' );

		wp_dequeue_script( 'jquery' );
		wp_deregister_script( 'jquery' );

		$hcaptcha_wordpress_plugin->form_shown = false;

		parent::tearDown();
	}

	/**
	 * Test init().
	 *
	 * @return void
	 */
	public function test_init() {
		$hcaptcha_wordpress_plugin = hcaptcha();

		// Plugin was loaded by codeception.
		self::assertSame(
			- PHP_INT_MAX,
			has_action( 'plugins_loaded', [ $hcaptcha_wordpress_plugin, 'init_hooks' ] )
		);

		remove_action( 'plugins_loaded', [ $hcaptcha_wordpress_plugin, 'init_hooks' ], -PHP_INT_MAX );

		self::assertFalse(
			has_action( 'plugins_loaded', [ $hcaptcha_wordpress_plugin, 'init_hooks' ] )
		);

		$hcaptcha_wordpress_plugin->init();

		self::assertSame(
			- PHP_INT_MAX,
			has_action( 'plugins_loaded', [ $hcaptcha_wordpress_plugin, 'init_hooks' ] )
		);
	}

	/**
	 * Test init() and init_hooks().
	 *
	 * @param boolean        $logged_in                   User is logged in.
	 * @param boolean        $hcaptcha_off_when_logged_in Option 'hcaptcha_off_when_logged_in' is set.
	 * @param boolean|string $whitelisted                 Whether IP is whitelisted.
	 * @param boolean        $hcaptcha_active             Plugin should be active.
	 *
	 * @dataProvider dp_test_init
	 * @noinspection PhpUnitTestsInspection
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_init_and_init_hooks( $logged_in, $hcaptcha_off_when_logged_in, $whitelisted, $hcaptcha_active ) {
		global $current_user;

		$hcaptcha_wordpress_plugin = hcaptcha();

		add_filter(
			'hcap_whitelist_ip',
			static function () use ( $whitelisted ) {
				return $whitelisted;
			},
			10
		);

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
			10,
			has_action( 'login_head', [ $hcaptcha_wordpress_plugin, 'login_head' ] )
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
			update_option( 'hcaptcha_settings', [ 'off_when_logged_in' => [ 'on' ] ] );
		} else {
			update_option( 'hcaptcha_settings', [ 'off_when_logged_in' => [] ] );
		}

		$subject = new Main();
		$subject->init_hooks();

		self::assertSame(
			- PHP_INT_MAX + 1,
			has_action( 'plugins_loaded', [ $subject, 'load_modules' ] )
		);
		self::assertSame(
			10,
			has_action( 'plugins_loaded', [ $subject, 'load_textdomain' ] )
		);
		self::assertSame(
			- PHP_INT_MAX,
			has_filter(
				'hcap_whitelist_ip',
				[ $subject, 'whitelist_ip' ]
			)
		);

		if ( $hcaptcha_active ) {
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
				10,
				has_action( 'login_head', [ $subject, 'login_head' ] )
			);
			self::assertSame(
				0,
				has_action( 'wp_print_footer_scripts', [ $subject, 'print_footer_scripts' ] )
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
				has_action( 'wp_head', [ $subject, 'print_inline_styles' ] )
			);
			self::assertFalse(
				has_action( 'login_head', [ $subject, 'login_head' ] )
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
			'not logged in, not set, not whitelisted' => [ false, 'off', false, true ],
			'not logged in, set, not whitelisted'     => [ false, 'on', false, true ],
			'logged in, not set, not whitelisted'     => [ true, 'off', false, true ],
			'logged in, set, not whitelisted'         => [ true, 'on', false, false ],
			'not logged in, not set, whitelisted'     => [ false, 'off', true, false ],
			'not logged in, set, whitelisted'         => [ false, 'on', true, false ],
			'logged in, not set, whitelisted'         => [ true, 'off', true, false ],
			'logged in, set, whitelisted'             => [ true, 'on', true, false ],
		];
	}

	/**
	 * Test init() and init_hooks() on Elementor Pro edit page.
	 *
	 * @param boolean $elementor_pro_status Option 'elementor_pro_status' is set.
	 * @param array   $server               $_SERVER variable.
	 * @param array   $get                  $_GET variable.
	 * @param array   $post                 $_POST variable.
	 * @param boolean $hcaptcha_active      Plugin should be active.
	 *
	 * @dataProvider dp_test_init_and_init_hooks_on_elementor_pro_edit_page
	 * @noinspection PhpUnitTestsInspection
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_init_and_init_hooks_on_elementor_pro_edit_page(
		$elementor_pro_status, $server, $get, $post, $hcaptcha_active
	) {
		global $current_user;

		add_filter(
			'hcap_whitelist_ip',
			static function () {
				return true;
			},
			10
		);

		unset( $current_user );
		wp_set_current_user( 1 );

		if ( 'on' === $elementor_pro_status ) {
			update_option( 'hcaptcha_settings', [ 'elementor_pro_status' => [ 'on' ] ] );
		} else {
			update_option( 'hcaptcha_settings', [ 'elementor_pro_status' => [] ] );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
		$_SERVER = array_merge( $_SERVER, $server );
		$_GET    = array_merge( $_GET, $get );
		$_POST   = array_merge( $_POST, $post );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing

		$subject = new Main();
		$subject->init_hooks();

		self::assertSame(
			- PHP_INT_MAX + 1,
			has_action( 'plugins_loaded', [ $subject, 'load_modules' ] )
		);
		self::assertSame(
			10,
			has_action( 'plugins_loaded', [ $subject, 'load_textdomain' ] )
		);
		self::assertSame(
			- PHP_INT_MAX,
			has_filter(
				'hcap_whitelist_ip',
				[ $subject, 'whitelist_ip' ]
			)
		);

		if ( $hcaptcha_active ) {
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
				10,
				has_action( 'login_head', [ $subject, 'login_head' ] )
			);
			self::assertSame(
				0,
				has_action( 'wp_print_footer_scripts', [ $subject, 'print_footer_scripts' ] )
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
				has_action( 'wp_head', [ $subject, 'print_inline_styles' ] )
			);
			self::assertFalse(
				has_action( 'login_head', [ $subject, 'login_head' ] )
			);
			self::assertFalse(
				has_action( 'wp_print_footer_scripts', [ $subject, 'print_footer_scripts' ] )
			);

			self::assertNull( $this->get_protected_property( $subject, 'auto_verify' ) );
		}
	}

	/**
	 * Data provider for test_init_and_init_hooks_on_elementor_pro_edit_page().
	 *
	 * @return array
	 */
	public function dp_test_init_and_init_hooks_on_elementor_pro_edit_page() {
		return [
			'elementor option off' => [
				'off',
				[],
				[],
				[],
				false,
			],
			'request1'             => [
				'on',
				[ 'REQUEST_URI' => '/wp-admin/post.php?post=23&action=elementor' ],
				[
					'post'   => 23,
					'action' => 'elementor',
				],
				[],
				true,
			],
			'request2'             => [
				'on',
				[ 'REQUEST_URI' => '/elementor?elementor-preview=23' ],
				[ 'elementor-preview' => 23 ],
				[],
				true,
			],
			'request3'             => [
				'on',
				[],
				[],
				[ 'action' => 'elementor_ajax' ],
				true,
			],
			'other request'        => [
				'on',
				[],
				[],
				[ 'action' => 'some_ajax' ],
				false,
			],
		];
	}

	/**
	 * Test init() and init_hooks() on XMLRPC_REQUEST.
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_init_and_init_hooks_on_xml_rpc_request() {
		$subject = Mockery::mock( Main::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods()->shouldReceive( 'is_xml_rpc' )->andReturn( true );

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
			has_action( 'login_head', [ $subject, 'login_head' ] )
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
		$url = HCAPTCHA_URL . '/assets/images/hcaptcha-div-logo.svg';

		$expected = '		<style>
			div.wpforms-container-full .wpforms-form .h-captcha,
			#wpforo #wpforo-wrap div .h-captcha,
			.h-captcha {
				position: relative;
				display: block;
				margin-bottom: 2rem;
				padding: 0;
				clear: both;
			}
			.gform_previous_button + .h-captcha {
				margin-top: 2rem;
			}
			#wpforo #wpforo-wrap.wpft-topic div .h-captcha,
			#wpforo #wpforo-wrap.wpft-forum div .h-captcha {
				margin: 0 -20px;
			}
			.wpdm-button-area + .h-captcha {
				margin-bottom: 1rem;
			}
			.w3eden .btn-primary {
				background-color: var(--color-primary) !important;
				color: #fff !important;
			}
			div.wpforms-container-full .wpforms-form .h-captcha[data-size="normal"],
			.h-captcha[data-size="normal"] {
				width: 303px;
				height: 78px;
			}
			div.wpforms-container-full .wpforms-form .h-captcha[data-size="compact"],
			.h-captcha[data-size="compact"] {
				width: 164px;
				height: 144px;
			}
			div.wpforms-container-full .wpforms-form .h-captcha[data-size="invisible"],
			.h-captcha[data-size="invisible"] {
				display: none;
			}
			.h-captcha::before {
				content: \'\';
				display: block;
				position: absolute;
				top: 0;
				left: 0;
				background: url(' . $url . ') no-repeat;
				border: 1px solid transparent;
				border-radius: 4px;
			}
			.h-captcha[data-size="normal"]::before {
				width: 300px;
				height: 74px;
				background-position: 94% 27%;
			}
			.h-captcha[data-size="compact"]::before {
				width: 156px;
				height: 136px;
				background-position: 50% 77%;
			}
			.h-captcha[data-theme="light"]::before {
				background-color: #fafafa;
				border: 1px solid #e0e0e0;
			}
			.h-captcha[data-theme="dark"]::before {
				background-color: #333;
				border: 1px solid #f5f5f5;
			}
			.h-captcha[data-size="invisible"]::before {
				display: none;
			}
			div.wpforms-container-full .wpforms-form .h-captcha iframe,
			.h-captcha iframe {
				position: relative;
			}
			span[data-name="hcap-cf7"] .h-captcha {
				margin-bottom: 0;
			}
			span[data-name="hcap-cf7"] ~ input[type="submit"] {
				margin-top: 2rem;
			}
			.elementor-field-type-hcaptcha .elementor-field {
				background: transparent !important;
			}
			.elementor-field-type-hcaptcha .h-captcha {
				margin-bottom: unset;
			}
			div[style*="z-index: 2147483647"] div[style*="border-width: 11px"][style*="position: absolute"][style*="pointer-events: none"] {
				border-style: none;
			}
		</style>
		';
		$subject  = new Main();

		ob_start();

		$subject->print_inline_styles();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test login_head().
	 */
	public function test_login_head() {
		$expected = '		<style>
			@media (max-width: 349px) {
				.h-captcha {
					display: flex;
					justify-content: center;
				}
			}
			@media (min-width: 350px) {
				#login {
					width: 350px;
				}
			}
		</style>
		';

		$subject = new Main();

		ob_start();

		$subject->login_head();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test hcap_language filter in get_api_src().
	 *
	 * @return void
	 */
	public function test_hcap_language_filter_in_get_api_scr() {
		$language          = 'en';
		$filtered_language = 'de';
		$expected          = 'https://js.hcaptcha.com/1/api.js?onload=hCaptchaOnLoad&render=explicit&hl=' . $filtered_language;

		update_option( 'hcaptcha_settings', [ 'language' => $language ] );

		add_filter(
			'hcap_language',
			static function ( $language ) use ( $filtered_language ) {
				return $filtered_language;
			}
		);

		$subject = new Main();
		$subject->init_hooks();

		self::assertSame( $expected, $subject->get_api_src() );
	}

	/**
	 * Test print_footer_scripts().
	 *
	 * @param string|false $compat              Compat option value.
	 * @param string|false $language            Language option value.
	 * @param string|false $custom_themes       Compat option value.
	 * @param string       $expected_script_src Expected script source.
	 *
	 * @dataProvider dp_test_print_footer_scripts
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_print_footer_scripts( $compat, $language, $custom_themes, $expected_script_src ): void {
		$hcaptcha_wordpress_plugin = hcaptcha();

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
					const delay = -100;

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

		$config_params  = '{}';
		$expected_extra = [
			'group' => 1,
			'data'  => 'var HCaptchaMainObject = {"params":"' . $config_params . '"};',
		];

		update_option(
			'hcaptcha_settings',
			[
				'recaptcha_compat_off' => $compat ? [ $compat ] : [],
				'language'             => $language ?: '',
				'custom_themes'        => $custom_themes ? [ $custom_themes ] : [],
				'config_params'        => $config_params,
				'delay'                => - 100,
			]
		);

		$hcaptcha_wordpress_plugin->init_hooks();

		// Test when Elementor Pro is not loaded.
		self::assertFalse( wp_script_is( 'hcaptcha' ) );
		self::assertFalse( wp_script_is( 'hcaptcha-elementor-pro-frontend' ) );

		ob_start();
		do_action( 'wp_print_footer_scripts' );
		$scripts = ob_get_clean();

		self::assertTrue( wp_script_is( 'hcaptcha' ) );

		$hcaptcha = wp_scripts()->registered['hcaptcha'];
		self::assertSame( HCAPTCHA_URL . '/assets/js/apps/hcaptcha.js', $hcaptcha->src );
		self::assertSame( [], $hcaptcha->deps );
		self::assertSame( HCAPTCHA_VERSION, $hcaptcha->ver );
		self::assertSame( $expected_extra, $hcaptcha->extra );

		self::assertFalse( wp_script_is( 'hcaptcha-elementor-pro-frontend' ) );

		self::assertNotFalse( strpos( $scripts, $expected_scripts ) );

		// Test when Elementor Pro is loaded.
		wp_dequeue_script( 'hcaptcha' );
		wp_deregister_script( 'hcaptcha' );

		wp_dequeue_script( 'hcaptcha-elementor-pro-frontend' );
		wp_deregister_script( 'hcaptcha-elementor-pro-frontend' );

		wp_dequeue_script( 'jquery' );
		wp_deregister_script( 'jquery' );

		$loaded_classes = $this->get_protected_property( $hcaptcha_wordpress_plugin, 'loaded_classes' );

		$loaded_classes[ HCaptchaHandler::class ] = new stdClass();

		$this->set_protected_property( $hcaptcha_wordpress_plugin, 'loaded_classes', $loaded_classes );

		self::assertFalse( wp_script_is( 'hcaptcha' ) );
		self::assertFalse( wp_script_is( 'hcaptcha-elementor-pro-frontend' ) );

		ob_start();
		do_action( 'wp_print_footer_scripts' );
		$scripts = ob_get_clean();

		self::assertTrue( wp_script_is( 'hcaptcha' ) );

		$hcaptcha = wp_scripts()->registered['hcaptcha'];
		self::assertSame( HCAPTCHA_URL . '/assets/js/apps/hcaptcha.js', $hcaptcha->src );
		self::assertSame( [], $hcaptcha->deps );
		self::assertSame( HCAPTCHA_VERSION, $hcaptcha->ver );
		self::assertSame( $expected_extra, $hcaptcha->extra );

		self::assertTrue( wp_script_is( 'hcaptcha-elementor-pro-frontend' ) );

		$hcaptcha_elementor_pro_frontend = wp_scripts()->registered['hcaptcha-elementor-pro-frontend'];
		self::assertSame( HCAPTCHA_URL . '/assets/js/hcaptcha-elementor-pro-frontend.min.js', $hcaptcha_elementor_pro_frontend->src );
		self::assertSame( [ 'jquery', 'hcaptcha' ], $hcaptcha_elementor_pro_frontend->deps );
		self::assertSame( HCAPTCHA_VERSION, $hcaptcha_elementor_pro_frontend->ver );
		self::assertSame( [ 'group' => 1 ], $hcaptcha_elementor_pro_frontend->extra );

		self::assertNotFalse( strpos( $scripts, $expected_scripts ) );
	}

	/**
	 * Data provider for test_print_footer_scripts().
	 *
	 * @return array
	 */
	public function dp_test_print_footer_scripts() {
		return [
			'no options'         => [
				false,
				false,
				false,
				'https://js.hcaptcha.com/1/api.js?onload=hCaptchaOnLoad&render=explicit',
			],
			'empty options'      => [
				'',
				'',
				'',
				'https://js.hcaptcha.com/1/api.js?onload=hCaptchaOnLoad&render=explicit',
			],
			'compat only'        => [
				'on',
				false,
				false,
				'https://js.hcaptcha.com/1/api.js?onload=hCaptchaOnLoad&render=explicit&recaptchacompat=off',
			],
			'language only'      => [
				false,
				'ru',
				false,
				'https://js.hcaptcha.com/1/api.js?onload=hCaptchaOnLoad&render=explicit&hl=ru',
			],
			'custom themes only' => [
				false,
				false,
				'on',
				'https://js.hcaptcha.com/1/api.js?onload=hCaptchaOnLoad&render=explicit&custom=true',
			],
			'all options'        => [
				'on',
				'ru',
				'on',
				'https://js.hcaptcha.com/1/api.js?onload=hCaptchaOnLoad&render=explicit&recaptchacompat=off&custom=true&hl=ru',
			],
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
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_load_modules( $module ): void {
		list( $option_name, $option_value ) = $module[0];

		update_option(
			'hcaptcha_settings',
			[ $option_name => [ $option_value ] ]
		);

		$subject = new Main();
		$subject->init_hooks();

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
		self::assertSame(
			$expected_loaded_classes,
			$this->get_protected_property( $subject, 'loaded_classes' )
		);

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

		$loaded_classes = $this->get_protected_property( $subject, 'loaded_classes' );

		self::assertSame( $expected_loaded_classes, array_keys( $loaded_classes ) );

		foreach ( $loaded_classes as $class_name => $loaded_class ) {
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
			'Comment Form'                 => [
				[ 'wp_status', 'comment' ],
				'',
				Comment::class,
			],
			'Login Form'                   => [
				[ 'wp_status', 'login' ],
				'',
				Login::class,
			],
			'Lost Password Form'           => [
				[ 'wp_status', 'lost_pass' ],
				'',
				LostPassword::class,
			],
			'Register Form'                => [
				[ 'wp_status', 'register' ],
				'',
				Register::class,
			],
			'Avada Form'                   => [
				[ 'avada_status', 'form' ],
				'Avada',
				[ \HCaptcha\Avada\Form::class ],
			],
			'bbPress New Topic'            => [
				[ 'bbp_status', 'new_topic' ],
				'bbpress/bbpress.php',
				NewTopic::class,
			],
			'bbPress Reply'                => [
				[ 'bbp_status', 'reply' ],
				'bbpress/bbpress.php',
				Reply::class,
			],
			'Beaver Builder Contact Form'  => [
				[ 'beaver_builder_status', 'contact' ],
				'bb-plugin/fl-builder.php',
				\HCaptcha\BeaverBuilder\Contact::class,
			],
			'Beaver Builder Login Form'    => [
				[ 'beaver_builder_status', 'login' ],
				'bb-plugin/fl-builder.php',
				[ \HCaptcha\BeaverBuilder\Login::class, Login::class ],
			],
			'BuddyPress Create Group'      => [
				[ 'bp_status', 'create_group' ],
				'buddypress/bp-loader.php',
				'bp/bp-create-group.php',
			],
			'BuddyPress Register'          => [
				[ 'bp_status', 'registration' ],
				'buddypress/bp-loader.php',
				'bp/bp-register.php',
			],
			'Contact Form 7'               => [
				[ 'cf7_status', 'form' ],
				'contact-form-7/wp-contact-form-7.php',
				CF7::class,
			],
			'Divi Comment Form'            => [
				[ 'divi_status', 'comment' ],
				'Divi',
				[ \HCaptcha\Divi\Comment::class, Comment::class ],
			],
			'Divi Contact Form'            => [
				[ 'divi_status', 'contact' ],
				'Divi',
				Contact::class,
			],
			'Divi Login Form'              => [
				[ 'divi_status', 'login' ],
				'Divi',
				\HCaptcha\Divi\Login::class,
			],
			'Elementor Pro Form'           => [
				[ 'elementor_pro_status', 'form' ],
				'elementor-pro/elementor-pro.php',
				HCaptchaHandler::class,
			],
			'Fluent Forms'                 => [
				[ 'fluent_status', 'form' ],
				'fluentform/fluentform.php',
				Form::class,
			],
			'Forminator'                   => [
				[ 'forminator_status', 'form' ],
				'forminator/forminator.php',
				\HCaptcha\Forminator\Form::class,
			],
			'Gravity Forms'                => [
				[ 'gravity_status', 'form' ],
				'gravityforms/gravityforms.php',
				\HCaptcha\GravityForms\Form::class,
			],
			'Jetpack'                      => [
				[ 'jetpack_status', 'contact' ],
				'jetpack/jetpack.php',
				JetpackForm::class,
			],
			'MailChimp'                    => [
				[ 'mailchimp_status', 'form' ],
				'mailchimp-for-wp/mailchimp-for-wp.php',
				'mailchimp/mailchimp-for-wp.php',
			],
			'MemberPress Register'         => [
				[ 'memberpress_status', 'register' ],
				'memberpress/memberpress.php',
				\HCaptcha\MemberPress\Register::class,
			],
			'Ninja Forms'                  => [
				[ 'ninja_status', 'form' ],
				'ninja-forms/ninja-forms.php',
				NF::class,
			],
			'Quform'                       => [
				[ 'quform_status', 'form' ],
				'quform/quform.php',
				Quform::class,
			],
			'Subscriber'                   => [
				[ 'subscriber_status', 'form' ],
				'subscriber/subscriber.php',
				'subscriber/subscriber.php',
			],
			'Ultimate Member Login'        => [
				[ 'ultimate_member_status', 'login' ],
				'ultimate-member/ultimate-member.php',
				\HCaptcha\UM\Login::class,
			],
			'Ultimate Member LostPassword' => [
				[ 'ultimate_member_status', 'lost_pass' ],
				'ultimate-member/ultimate-member.php',
				\HCaptcha\UM\LostPassword::class,
			],
			'Ultimate Member Register'     => [
				[ 'ultimate_member_status', 'register' ],
				'ultimate-member/ultimate-member.php',
				\HCaptcha\UM\Register::class,
			],
			'WooCommerce Checkout'         => [
				[ 'woocommerce_status', 'checkout' ],
				'woocommerce/woocommerce.php',
				Checkout::class,
			],
			'WooCommerce Login'            => [
				[ 'woocommerce_status', 'login' ],
				'woocommerce/woocommerce.php',
				\HCaptcha\WC\Login::class,
			],
			'WooCommerce Lost Password'    => [
				[ 'woocommerce_status', 'lost_pass' ],
				'woocommerce/woocommerce.php',
				[ LostPassword::class, \HCaptcha\WC\LostPassword::class ],
			],
			'WooCommerce Order Tracking'   => [
				[ 'woocommerce_status', 'order_tracking' ],
				'woocommerce/woocommerce.php',
				OrderTracking::class,
			],
			'WooCommerce Register'         => [
				[ 'woocommerce_status', 'register' ],
				'woocommerce/woocommerce.php',
				\HCaptcha\WC\Register::class,
			],
			'WooCommerce Wishlists'        => [
				[ 'woocommerce_wishlists_status', 'create_list' ],
				'woocommerce-wishlists/woocommerce-wishlists.php',
				'wc_wl/wc-wl-create-list.php',
			],
			'WPForms Lite'                 => [
				[ 'wpforms_status', 'lite' ],
				'wpforms-lite/wpforms.php',
				'wpforms/wpforms.php',
			],
			'WPForms Pro'                  => [
				[ 'wpforms_status', 'pro' ],
				'wpforms/wpforms.php',
				'wpforms/wpforms.php',
			],
			'wpForo New Topic'             => [
				[ 'wpforo_status', 'new_topic' ],
				'wpforo/wpforo.php',
				'wpforo/wpforo-new-topic.php',
			],
			'wpForo Reply'                 => [
				[ 'wpforo_status', 'reply' ],
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
			static function ( $override, $domain, $mofile ) use ( &$override_filter_params ) {
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
