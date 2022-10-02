<?php
/**
 * Main class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha;

use HCaptcha\AutoVerify\AutoVerify;
use HCaptcha\CF7\CF7;
use HCaptcha\DelayedScript\DelayedScript;
use HCaptcha\Divi\Fix;
use HCaptcha\ElementorPro\HCaptchaHandler;
use HCaptcha\Jetpack\JetpackForm;
use HCaptcha\Migrations\Migrations;
use HCaptcha\NF\NF;
use HCaptcha\Settings\General;
use HCaptcha\Settings\Integrations;
use HCaptcha\Settings\Settings;

/**
 * Class Main.
 */
class Main {
	/**
	 * Main script handle.
	 */
	const HANDLE = 'hcaptcha';

	/**
	 * Main script localization object.
	 */
	const OBJECT = 'HCaptchaMainObject';

	/**
	 * Form shown somewhere, use this flag to run the script.
	 *
	 * @var boolean
	 */
	public $form_shown = false;

	/**
	 * Loaded classes.
	 *
	 * @var array
	 */
	protected $loaded_classes = [];

	/**
	 * Settings class instance.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Instance of AutoVerify.
	 *
	 * @var AutoVerify
	 */
	protected $auto_verify;

	/**
	 * Whether hCaptcha is active.
	 *
	 * @var bool
	 */
	private $active;

	/**
	 * Input class.
	 */
	public function init() {
		if ( $this->is_xml_rpc() ) {
			return;
		}

		( new Fix() )->init();
		new Migrations();

		add_action( 'plugins_loaded', [ $this, 'init_hooks' ], - PHP_INT_MAX );
	}

	/**
	 * Init hooks.
	 */
	public function init_hooks() {
		$this->settings = new Settings(
			[
				'hCaptcha' => [ General::class, Integrations::class ],
			]
		);

		add_action( 'plugins_loaded', [ $this, 'load_modules' ], - PHP_INT_MAX + 1 );
		add_action( 'plugins_loaded', [ $this, 'load_textdomain' ] );
		add_filter( 'hcap_whitelist_ip', [ $this, 'whitelist_ip' ], - PHP_INT_MAX, 2 );

		$this->active = $this->activate_hcaptcha();

		if ( ! $this->active ) {
			return;
		}

		add_filter( 'wp_resource_hints', [ $this, 'prefetch_hcaptcha_dns' ], 10, 2 );
		add_filter( 'wp_headers', [ $this, 'csp_headers' ] );
		add_action( 'wp_head', [ $this, 'print_inline_styles' ] );
		add_action( 'login_head', [ $this, 'print_inline_styles' ] );
		add_action( 'login_head', [ $this, 'login_head' ] );
		add_action( 'wp_print_footer_scripts', [ $this, 'print_footer_scripts' ], 0 );

		$this->auto_verify = new AutoVerify();
		$this->auto_verify->init();
	}

	/**
	 * Get Settings instance.
	 *
	 * @return Settings
	 */
	public function settings() {
		return $this->settings;
	}

	/**
	 * Check if we have to activate the plugin.
	 *
	 * @return bool
	 */
	private function activate_hcaptcha() {
		// Make sure we can use is_user_logged_in().
		require_once ABSPATH . 'wp-includes/pluggable.php';

		/**
		 * Do not load hCaptcha functionality:
		 * - if user is logged in and the option 'off_when_logged_in' is set;
		 * - for whitelisted IPs.
		 */
		$deactivate = (
			( is_user_logged_in() && $this->settings()->is_on( 'off_when_logged_in' ) ) ||
			/**
			 * Filters the user IP to check whether it is whitelisted.
			 *
			 * @param bool         $whitelisted IP is whitelisted.
			 * @param string|false $ip          IP string or false for local addresses.
			 */
			apply_filters( 'hcap_whitelist_ip', false, hcap_get_user_ip() )
		);

		$activate = ( ! $deactivate ) || $this->is_elementor_pro_edit_page();

		/**
		 * Filters the hcaptcha activation flag.
		 *
		 * @param bool $activate Activate the hcaptcha functionality.
		 */
		return (bool) apply_filters( 'hcap_activate', $activate );
	}

	/**
	 * Whether we are on the Elementor Pro edit post page and hCaptcha for Elementor Pro is active.
	 *
	 * @return bool
	 */
	private function is_elementor_pro_edit_page() {
		if ( ! $this->settings()->is_on( 'elementor_pro_status' ) ) {
			return false;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
		$request1 = (
			isset( $_SERVER['REQUEST_URI'], $_GET['post'], $_GET['action'] ) &&
			0 === strpos( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), '/wp-admin/post.php' ) &&
			'elementor' === $_GET['action']
		);
		$request2 = (
			isset( $_SERVER['REQUEST_URI'], $_GET['elementor-preview'] ) &&
			0 === strpos( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), '/elementor' )
		);
		$request3 = (
			isset( $_POST['action'] ) && 'elementor_ajax' === $_POST['action']
		);
		// phpcs:enable WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing

		return $request1 || $request2 || $request3;
	}

	/**
	 * Prefetch hCaptcha dns.
	 * We cannot control if hCaptcha form is shown here, as this is hooked on wp_head.
	 * So, we always prefetch hCaptcha dns if hCaptcha is active, but it is a small overhead.
	 *
	 * @param array  $urls          URLs to print for resource hints.
	 * @param string $relation_type The relation type the URLs are printed for.
	 *
	 * @return array
	 */
	public function prefetch_hcaptcha_dns( $urls, $relation_type ) {
		if ( 'dns-prefetch' === $relation_type ) {
			$urls[] = 'https://hcaptcha.com';
		}

		return $urls;
	}

	/**
	 * Add Content Security Policy (CSP) headers.
	 *
	 * @param array $headers Headers.
	 *
	 * @return array
	 */
	public function csp_headers( $headers ) {
		$hcap_csp = "'self' https://hcaptcha.com https://*.hcaptcha.com";

		$headers['X-Content-Security-Policy'] =
			"default-src 'self'; " .
			"script-src $hcap_csp; " .
			"frame-src $hcap_csp; " .
			"style-src $hcap_csp; " .
			"connect-src $hcap_csp; " .
			"unsafe-eval $hcap_csp; " .
			"unsafe-inline $hcap_csp;";

		return $headers;
	}

	/**
	 * Print inline styles.
	 */
	public function print_inline_styles() {
		$url = HCAPTCHA_URL . '/assets/images/hcaptcha-div-logo.svg';
		?>
		<style>
			.h-captcha {
				position: relative;
				display: block;
				margin-bottom: 2rem;
			}
			.h-captcha.hcap_cf7-h-captcha {
				margin-bottom: 0;
			}
			.h-captcha[data-size="normal"] {
				width: 303px;
				height: 78px;
			}
			.h-captcha[data-size="compact"] {
				width: 164px;
				height: 144px;
			}
			.h-captcha[data-size="invisible"] {
				display: none;
			}
			.h-captcha::before {
				content: '';
				display: block;
				position: absolute;
				top: 0;
				left: 0;
				background: url(<?php echo esc_url( $url ); ?>) no-repeat;
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
			.h-captcha iframe {
				position: relative;
			}
			.elementor-field-type-hcaptcha .elementor-field {
				background: transparent !important;
			}
			.elementor-field-type-hcaptcha .h-captcha {
				margin-bottom: -9px;
			}
			div[style*="z-index: 2147483647"] div[style*="border-width: 11px"][style*="position: absolute"][style*="pointer-events: none"] {
				border-style: none;
			}
		</style>
		<?php
	}

	/**
	 * Print styles to fit hcaptcha widget to the login form.
	 */
	public function login_head() {
		?>
		<style>
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
		<?php
	}

	/**
	 * Get API source url.
	 *
	 * @return string
	 */
	public function get_api_src() {
		$params = [
			'onload' => 'hCaptchaOnLoad',
			'render' => 'explicit',
		];

		if ( $this->settings()->is_on( 'recaptcha_compat_off' ) ) {
			$params['recaptchacompat'] = 'off';
		}

		if ( $this->settings()->is_on( 'custom_themes' ) ) {
			$params['custom'] = 'true';
		}

		/**
		 * Filters hCaptcha language.
		 *
		 * @param string $language Language.
		 */
		$language = (string) apply_filters( 'hcap_language', $this->settings()->get( 'language' ) );

		if ( $language ) {
			$params['hl'] = $language;
		}

		return add_query_arg( $params, 'https://js.hcaptcha.com/1/api.js' );
	}

	/**
	 * Add the hCaptcha script to footer.
	 */
	public function print_footer_scripts() {
		if ( is_admin() ) {
			return;
		}

		if ( ! $this->form_shown ) {
			return;
		}

		/**
		 * Filters delay time for hCaptcha API script.
		 *
		 * Any negative value will prevent API script from loading at all,
		 * until user interaction: mouseenter, click, scroll or touch.
		 * This significantly improves Google Pagespeed Insights score.
		 *
		 * @param int $delay Number of milliseconds to delay hCaptcha API script.
		 *                   Any negative value means delay until user interaction.
		 */
		$delay = (int) apply_filters( 'hcap_delay_api', - 1 );

		DelayedScript::launch( [ 'src' => $this->get_api_src() ], $delay );

		wp_enqueue_script(
			self::HANDLE,
			HCAPTCHA_URL . '/assets/js/apps/hcaptcha.js',
			[],
			HCAPTCHA_VERSION,
			true
		);

		wp_localize_script(
			self::HANDLE,
			self::OBJECT,
			[ 'params' => $this->settings()->get( 'config_params' ) ]
		);

		$min = hcap_min_suffix();

		if ( array_key_exists( HCaptchaHandler::class, $this->loaded_classes ) ) {
			wp_enqueue_script(
				'hcaptcha-elementor-pro-frontend',
				HCAPTCHA_URL . "/assets/js/hcaptcha-elementor-pro-frontend$min.js",
				[ 'jquery', self::HANDLE ],
				HCAPTCHA_VERSION,
				true
			);
		}
	}

	/**
	 * Filter user IP to check if it is whitelisted.
	 * For whitelisted IPs, hCaptcha will not be shown.
	 *
	 * @param bool   $whitelisted Whether IP is whitelisted.
	 * @param string $client_ip   Client IP.
	 *
	 * @return bool
	 */
	public function whitelist_ip( $whitelisted, $client_ip ) {

		$ips = explode(
			"\n",
			$this->settings()->get( 'whitelisted_ips' )
		);

		// Remove invalid IPs.
		$ips = array_filter(
			array_map(
				static function ( $ip ) {
					return filter_var(
						trim( $ip ),
						FILTER_VALIDATE_IP
					);
				},
				$ips
			)
		);

		// Convert local IPs to false.
		$ips = array_map(
			static function ( $ip ) {
				return filter_var(
					trim( $ip ),
					FILTER_VALIDATE_IP,
					FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
				);
			},
			$ips
		);

		if ( in_array( $client_ip, $ips, true ) ) {
			return true;
		}

		return $whitelisted;
	}

	/**
	 * Load plugin modules.
	 */
	public function load_modules() {
		$modules = [
			'Comment Form'                 => [
				[ 'wp_status', 'comment' ],
				'',
				WP\Comment::class,
			],
			'Login Form'                   => [
				[ 'wp_status', 'login' ],
				'',
				WP\Login::class,
			],
			'Lost Password Form'           => [
				[ 'wp_status', 'lost_pass' ],
				'',
				WP\LostPassword::class,
			],
			'Register Form'                => [
				[ 'wp_status', 'register' ],
				'',
				WP\Register::class,
			],
			'bbPress New Topic'            => [
				[ 'bbp_status', 'new_topic' ],
				'bbpress/bbpress.php',
				'bbp/bbp-new-topic.php',
			],
			'bbPress Reply'                => [
				[ 'bbp_status', 'reply' ],
				'bbpress/bbpress.php',
				'bbp/bbp-reply.php',
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
				[ Divi\Comment::class, WP\Comment::class ],
			],
			'Divi Contact Form'            => [
				[ 'divi_status', 'contact' ],
				'Divi',
				Divi\Contact::class,
			],
			'Divi Login Form'              => [
				[ 'divi_status', 'login' ],
				'Divi',
				Divi\Login::class,
			],
			'Elementor Pro Form'           => [
				[ 'elementor_pro_status', 'form' ],
				'elementor-pro/elementor-pro.php',
				HCaptchaHandler::class,
			],
			'Fluent Forms'                 => [
				[ 'fluent_status', 'form' ],
				'fluentform/fluentform.php',
				FluentForm\Form::class,
			],
			'Gravity Forms'                => [
				[ 'gravity_status', 'form' ],
				'gravityforms/gravityforms.php',
				GravityForms\Form::class,
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
				MemberPress\Register::class,
			],
			'Ninja Forms'                  => [
				[ 'ninja_status', 'form' ],
				'ninja-forms/ninja-forms.php',
				NF::class,
			],
			'Subscriber'                   => [
				[ 'subscriber_status', 'form' ],
				'subscriber/subscriber.php',
				'subscriber/subscriber.php',
			],
			'Ultimate Member Login'        => [
				[ 'ultimate_member_status', 'login' ],
				'ultimate-member/ultimate-member.php',
				UM\Login::class,
			],
			'Ultimate Member LostPassword' => [
				[ 'ultimate_member_status', 'lost_pass' ],
				'ultimate-member/ultimate-member.php',
				UM\LostPassword::class,
			],
			'Ultimate Member Register'     => [
				[ 'ultimate_member_status', 'register' ],
				'ultimate-member/ultimate-member.php',
				UM\Register::class,
			],
			'WooCommerce Checkout'         => [
				[ 'woocommerce_status', 'checkout' ],
				'woocommerce/woocommerce.php',
				WC\Checkout::class,
			],
			'WooCommerce Login'            => [
				[ 'woocommerce_status', 'login' ],
				'woocommerce/woocommerce.php',
				WC\Login::class,
			],
			'WooCommerce Lost Password'    => [
				[ 'woocommerce_status', 'lost_pass' ],
				'woocommerce/woocommerce.php',
				[ WP\LostPassword::class, WC\LostPassword::class ],
			],
			'WooCommerce Order Tracking'   => [
				[ 'woocommerce_status', 'order_tracking' ],
				'woocommerce/woocommerce.php',
				WC\OrderTracking::class,
			],
			'WooCommerce Register'         => [
				[ 'woocommerce_status', 'register' ],
				'woocommerce/woocommerce.php',
				WC\Register::class,
			],
			'WooCommerce Wishlists'        => [
				[ 'woocommerce_wishlists_status', 'create_list' ],
				'woocommerce-wishlists/woocommerce-wishlists.php',
				'wc_wl/wc-wl-create-list.php',
			],
			'WPForms Lite'                 => [
				[ 'wpforms_status', 'lite' ],
				[ 'wpforms-lite/wpforms.php', 'wpforms/wpforms.php' ],
				'wpforms/wpforms.php',
			],
			'WPForms Pro'                  => [
				[ 'wpforms_status', 'pro' ],
				[ 'wpforms-lite/wpforms.php', 'wpforms/wpforms.php' ],
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

		if ( ! function_exists( 'is_plugin_active' ) ) {
			// @codeCoverageIgnoreStart
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
			// @codeCoverageIgnoreEnd
		}

		foreach ( $modules as $module ) {
			list( $option_name, $option_value ) = $module[0];

			$option = (array) $this->settings()->get( $option_name );

			if ( ! $this->plugin_or_theme_active( $module[1] ) ) {
				$this->settings()->set_field( $option_name, 'disabled', true );
				continue;
			}

			if ( ! in_array( $option_value, $option, true ) ) {
				continue;
			}

			if ( ! $this->active ) {
				continue;
			}

			foreach ( (array) $module[2] as $component ) {
				if ( false === strpos( $component, '.php' ) ) {
					if ( ! class_exists( $component, false ) ) {
						$this->loaded_classes[ $component ] = new $component();
					}
					continue;
				}

				require_once HCAPTCHA_INC . '/' . $component;
			}
		}
	}

	/**
	 * Check whether one of the plugins or themes is active.
	 *
	 * @param string|array $plugin_or_theme_names Plugin or theme names.
	 *
	 * @return bool
	 */
	private function plugin_or_theme_active( $plugin_or_theme_names ) {
		foreach ( (array) $plugin_or_theme_names as $plugin_or_theme_name ) {
			if ( '' === $plugin_or_theme_name ) {
				// WP Core is always active.
				return true;
			}

			if (
				false !== strpos( $plugin_or_theme_name, '.php' ) &&
				is_plugin_active( $plugin_or_theme_name )
			) {
				// Plugin is active.
				return true;
			}

			if (
				false === strpos( $plugin_or_theme_name, '.php' ) &&
				get_template() === $plugin_or_theme_name
			) {
				// Theme is active.
				return true;
			}
		}

		return false;
	}

	/**
	 * Load plugin text domain.
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'hcaptcha-for-forms-and-more',
			false,
			dirname( plugin_basename( HCAPTCHA_FILE ) ) . '/languages/'
		);
	}

	/**
	 * Check of it is a xml-rpc request
	 *
	 * @return bool
	 */
	protected function is_xml_rpc() {
		return defined( 'XMLRPC_REQUEST' ) && constant( 'XMLRPC_REQUEST' );
	}
}
