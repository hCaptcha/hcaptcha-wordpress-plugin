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
use HCaptcha\Divi\Contact;
use HCaptcha\Divi\Fix;
use HCaptcha\ElementorPro\HCaptchaHandler;
use HCaptcha\Jetpack\JetpackForm;
use HCaptcha\NF\NF;
use HCaptcha\WC\Checkout;
use HCaptcha\WC\OrderTracking;
use HCaptcha\WP\Comment;

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
	public $loaded_classes = [];

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

		add_action( 'plugins_loaded', [ $this, 'init_hooks' ], - PHP_INT_MAX );
	}

	/**
	 * Init hooks.
	 */
	public function init_hooks() {
		// Make sure we can use is_user_logged_in().
		require_once ABSPATH . 'wp-includes/pluggable.php';

		if ( $this->activate_hcaptcha() ) {
			add_action( 'plugins_loaded', [ $this, 'load_modules' ], - PHP_INT_MAX + 1 );
			add_action( 'plugins_loaded', [ $this, 'load_textdomain' ] );

			add_filter( 'wp_resource_hints', [ $this, 'prefetch_hcaptcha_dns' ], 10, 2 );
			add_action( 'wp_head', [ $this, 'print_inline_styles' ] );
			add_action( 'wp_print_footer_scripts', [ $this, 'print_footer_scripts' ], 0 );

			$this->auto_verify = new AutoVerify();
			$this->auto_verify->init();
		}

		( new Fix() )->init();
	}

	/**
	 * Check if we have to activate the plugin.
	 *
	 * @return bool
	 */
	private function activate_hcaptcha() {
		// Do not load hCaptcha functionality if user is logged in and the option 'hcaptcha_off_when_logged_in' is set.
		$activate = ! (
			( is_user_logged_in() && 'on' === get_option( 'hcaptcha_off_when_logged_in' ) ) ||
			apply_filters( 'hcap_whitelist_ip', false, hcap_get_user_ip() )
		);

		/**
		 * Filters the hcaptcha activation flag.
		 *
		 * @param bool $activate Activate the hcaptcha functionality.
		 */
		return (bool) apply_filters( 'hcap_activate', $activate );
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
			div[style*="z-index: 2147483647"] {
				div[style*="border-width: 11px"][style*="position: absolute"][style*="pointer-events: none"] {
					border-style: none;
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

		$compat = get_option( 'hcaptcha_recaptchacompat' );

		if ( $compat ) {
			$params['recaptchacompat'] = 'off';
		}

		/**
		 * Filters hCaptcha language.
		 *
		 * @param string $language Language.
		 */
		$language = (string) apply_filters( 'hcap_language', get_option( 'hcaptcha_language' ) );

		if ( $language ) {
			$params['hl'] = $language;
		}

		return add_query_arg( $params, 'https://js.hcaptcha.com/1/api.js' );
	}

	/**
	 * Add the hCaptcha script to footer.
	 */
	public function print_footer_scripts() {
		global $hcaptcha_wordpress_plugin;

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
			HCAPTCHA_URL . '/assets/js/hcaptcha/app.js',
			[],
			HCAPTCHA_VERSION,
			true
		);

		if ( array_key_exists( HCaptchaHandler::class, $hcaptcha_wordpress_plugin->loaded_classes ) ) {
			wp_enqueue_script(
				'hcaptcha-elementor-pro-frontend',
				HCAPTCHA_URL . '/assets/js/hcaptcha-elementor-pro-frontend.js',
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
			'Login Form'                   => [
				'hcaptcha_lf_status',
				'',
				WP\Login::class,
			],
			'Register Form'                => [
				'hcaptcha_rf_status',
				'',
				WP\Register::class,
			],
			'Lost Password Form'           => [
				'hcaptcha_lpf_status',
				'',
				WP\LostPassword::class,
			],
			'Comment Form'                 => [
				'hcaptcha_cmf_status',
				'',
				Comment::class,
			],
			'bbPress New Topic'            => [
				'hcaptcha_bbp_new_topic_status',
				'bbpress/bbpress.php',
				'bbp/bbp-new-topic.php',
			],
			'bbPress Reply'                => [
				'hcaptcha_bbp_reply_status',
				'bbpress/bbpress.php',
				'bbp/bbp-reply.php',
			],
			'BuddyPress Create Group'      => [
				'hcaptcha_bp_create_group_status',
				'buddypress/bp-loader.php',
				'bp/bp-create-group.php',
			],
			'BuddyPress Register'          => [
				'hcaptcha_bp_reg_status',
				'buddypress/bp-loader.php',
				'bp/bp-register.php',
			],
			'Contact Form 7'               => [
				'hcaptcha_cf7_status',
				'contact-form-7/wp-contact-form-7.php',
				CF7::class,
			],
			'Divi Contact Form'            => [
				'hcaptcha_divi_cf_status',
				'Divi',
				Contact::class,
			],
			'Divi Login Form'              => [
				'hcaptcha_divi_lf_status',
				'Divi',
				Divi\Login::class,
			],
			'Elementor Pro Form'           => [
				'hcaptcha_elementor__pro_form_status',
				'elementor-pro/elementor-pro.php',
				HCaptchaHandler::class,
			],
			'Fluent Forms'                 => [
				'hcaptcha_fluentform_status',
				'fluentform/fluentform.php',
				FluentForm\Form::class,
			],
			'Gravity Forms'                => [
				'hcaptcha_gravityform_status',
				'gravityforms/gravityforms.php',
				GravityForms\Form::class,
			],
			'Jetpack'                      => [
				'hcaptcha_jetpack_cf_status',
				'jetpack/jetpack.php',
				JetpackForm::class,
			],
			'MailChimp'                    => [
				'hcaptcha_mc4wp_status',
				'mailchimp-for-wp/mailchimp-for-wp.php',
				'mailchimp/mailchimp-for-wp.php',
			],
			'MemberPress Register'         => [
				'hcaptcha_memberpress_register_status',
				'memberpress/memberpress.php',
				MemberPress\Register::class,
			],
			'Ninja Forms'                  => [
				'hcaptcha_nf_status',
				'ninja-forms/ninja-forms.php',
				NF::class,
			],
			'Subscriber'                   => [
				'hcaptcha_subscribers_status',
				'subscriber/subscriber.php',
				'subscriber/subscriber.php',
			],
			'Ultimate Member Login'        => [
				'hcaptcha_um_login_status',
				'ultimate-member/ultimate-member.php',
				UM\Login::class,
			],
			'Ultimate Member LostPassword' => [
				'hcaptcha_um_lost_pass_status',
				'ultimate-member/ultimate-member.php',
				UM\LostPassword::class,
			],
			'Ultimate Member Register'     => [
				'hcaptcha_um_register_status',
				'ultimate-member/ultimate-member.php',
				UM\Register::class,
			],
			'WooCommerce Login'            => [
				'hcaptcha_wc_login_status',
				'woocommerce/woocommerce.php',
				WC\Login::class,
			],
			'WooCommerce Register'         => [
				'hcaptcha_wc_reg_status',
				'woocommerce/woocommerce.php',
				WC\Register::class,
			],
			'WooCommerce Lost Password'    => [
				'hcaptcha_wc_lost_pass_status',
				'woocommerce/woocommerce.php',
				[ WP\LostPassword::class, WC\LostPassword::class ],
			],
			'WooCommerce Checkout'         => [
				'hcaptcha_wc_checkout_status',
				'woocommerce/woocommerce.php',
				Checkout::class,
			],
			'WooCommerce Order Tracking'   => [
				'hcaptcha_wc_order_tracking_status',
				'woocommerce/woocommerce.php',
				OrderTracking::class,
			],
			'WooCommerce Wishlists'        => [
				'hcaptcha_wc_wl_create_list_status',
				'woocommerce-wishlists/woocommerce-wishlists.php',
				'wc_wl/wc-wl-create-list.php',
			],
			'WPForms Lite'                 => [
				'hcaptcha_wpforms_status',
				'wpforms-lite/wpforms.php',
				'wpforms/wpforms.php',
			],
			'WPForms Pro'                  => [
				'hcaptcha_wpforms_pro_status',
				'wpforms/wpforms.php',
				'wpforms/wpforms.php',
			],
			'wpForo New Topic'             => [
				'hcaptcha_wpforo_new_topic_status',
				'wpforo/wpforo.php',
				'wpforo/wpforo-new-topic.php',
			],
			'wpForo Reply'                 => [
				'hcaptcha_wpforo_reply_status',
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
			$status = get_option( $module[0] );
			if ( 'on' !== $status ) {
				continue;
			}

			if (
				$module[1] &&
				false !== strpos( $module[1], '.php' ) &&
				! is_plugin_active( $module[1] )
			) {
				// Plugin is not active.
				continue;
			}

			if (
				$module[1] &&
				false === strpos( $module[1], '.php' ) &&
				get_template() !== $module[1]
			) {
				// Theme is not active.
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
