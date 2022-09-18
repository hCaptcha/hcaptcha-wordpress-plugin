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
use HCaptcha\Settings\Settings;

/**
 * Class Main.
 */
class Main {

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
		$this->settings = new Settings();

		if ( $this->activate_hcaptcha() ) {
			add_action( 'plugins_loaded', [ $this, 'load_modules' ], - PHP_INT_MAX + 1 );
			add_action( 'plugins_loaded', [ $this, 'load_textdomain' ] );

			add_filter( 'wp_resource_hints', [ $this, 'prefetch_hcaptcha_dns' ], 10, 2 );
			add_action( 'wp_head', [ $this, 'print_inline_styles' ] );
			add_action( 'wp_print_footer_scripts', [ $this, 'print_footer_scripts' ], 0 );

			$this->auto_verify = new AutoVerify();
			$this->auto_verify->init();
		}
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
	 * Print inline styles.
	 */
	public function print_inline_styles() {
		?>
		<style>
			.h-captcha:not([data-size="invisible"]) {
				margin-bottom: 2rem;
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
			$params['custom'] = 1;
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
			'hcaptcha',
			HCAPTCHA_URL . '/assets/js/apps/hcaptcha.js',
			[],
			HCAPTCHA_VERSION,
			true
		);

		$min = hcap_min_suffix();

		if ( array_key_exists( HCaptchaHandler::class, $this->loaded_classes ) ) {
			wp_enqueue_script(
				'hcaptcha-elementor-pro-frontend',
				HCAPTCHA_URL . "/assets/js/hcaptcha-elementor-pro-frontend$min.js",
				[ 'jquery', 'hcaptcha' ],
				HCAPTCHA_VERSION,
				true
			);
		}
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
			'WooCommerce Wishlist'         => [
				[ 'woocommerce_wishlist_status', 'create_list' ],
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

		if ( ! function_exists( 'is_plugin_active' ) ) {
			// @codeCoverageIgnoreStart
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
			// @codeCoverageIgnoreEnd
		}

		foreach ( $modules as $module ) {
			list( $option_name, $option_value ) = $module[0];

			$option = (array) $this->settings()->get( $option_name );

			if (
				$module[1] &&
				false !== strpos( $module[1], '.php' ) &&
				! is_plugin_active( $module[1] )
			) {
				// Plugin is not active.
				$this->settings()->disable_field( $option_name );
				continue;
			}

			if (
				$module[1] &&
				false === strpos( $module[1], '.php' ) &&
				get_template() !== $module[1]
			) {
				// Theme is not active.
				$this->settings()->disable_field( $option_name );
				continue;
			}

			if ( ! in_array( $option_value, $option, true ) ) {
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
