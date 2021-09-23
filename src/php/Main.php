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
use HCaptcha\Divi\FixDivi;
use HCaptcha\ElementorPro\Modules\Forms\Classes\HCaptchaHandler;
use HCaptcha\Jetpack\JetpackForm;
use HCaptcha\WC\OrderTracking;
use HCaptcha\NF\NF;

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
	 * Instance of AutoVerify.
	 *
	 * @var AutoVerify
	 */
	protected $auto_verify;

	/**
	 * Input class.
	 */
	public function init() {
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
			add_action( 'wp_print_footer_scripts', [ $this, 'print_footer_scripts' ], 0 );
			add_filter( 'woocommerce_login_credentials', [ $this, 'remove_filter_wp_authenticate_user' ] );
			$this->auto_verify = new AutoVerify();
			$this->auto_verify->init();
		}

		( new FixDivi() )->init();
	}

	/**
	 * Check if we have to activate the plugin.
	 *
	 * @return bool
	 */
	private function activate_hcaptcha() {
		// Do not load hCaptcha functionality if user is logged in and the option 'hcaptcha_off_when_logged_in' is set.
		$activate = ! ( is_user_logged_in() && 'on' === get_option( 'hcaptcha_off_when_logged_in' ) );

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

		$compat   = get_option( 'hcaptcha_recaptchacompat' );
		$language = get_option( 'hcaptcha_language' );

		if ( $compat ) {
			$params['recaptchacompat'] = 'off';
		}

		if ( $language ) {
			$params['hl'] = $language;
		}

		$src_params = add_query_arg( $params, '' );

		return 'https://js.hcaptcha.com/1/api.js' . $src_params;
	}

	/**
	 * Add the hcaptcha script to footer.
	 */
	public function print_footer_scripts() {
		if ( is_admin() ) {
			return;
		}

		if ( ! $this->form_shown ) {
			return;
		}

		$this->print_inline_styles();

		/**
		 * Filter delay time for hcaptcha API script.
		 * Any negative value will prevent API script from loading at all,
		 * until user interaction: mouseenter, click, scroll or touch.
		 * This significantly improves Google Pagespeed Insights score.
		 */
		$delay = (int) apply_filters( 'hcap_delay_api', - 1 );
		DelayedScript::launch( [ 'src' => $this->get_api_src() ], $delay );

		wp_enqueue_script(
			'hcaptcha',
			HCAPTCHA_URL . '/assets/js/hcaptcha.js',
			[],
			HCAPTCHA_VERSION,
			true
		);
	}

	/**
	 * Load plugin modules.
	 */
	public function load_modules() {
		$modules = [
			'Login Form'                 => [
				'hcaptcha_lf_status',
				'',
				'default/login-form.php',
			],
			'Register Form'              => [
				'hcaptcha_rf_status',
				'',
				'default/register-form.php',
			],
			'Lost Password Form'         => [
				'hcaptcha_lpf_status',
				'',
				[ 'common/lost-password-form.php', 'default/lost-password.php' ],
			],
			'Comment Form'               => [
				'hcaptcha_cmf_status',
				'',
				'default/comment-form.php',
			],
			'BB Press New Topic'         => [
				'hcaptcha_bbp_new_topic_status',
				'bbpress/bbpress.php',
				'bbp/bbp-new-topic.php',
			],
			'BB Press Reply'             => [
				'hcaptcha_bbp_reply_status',
				'bbpress/bbpress.php',
				'bbp/bbp-reply.php',
			],
			'BuddyPress Register'        => [
				'hcaptcha_bp_reg_status',
				'buddypress/bp-loader.php',
				'bp/bp-register.php',
			],
			'BuddyPress Create Group'    => [
				'hcaptcha_bp_create_group_status',
				'buddypress/bp-loader.php',
				'bp/bp-create-group.php',
			],
			'Contact Form 7'             => [
				'hcaptcha_cf7_status',
				'contact-form-7/wp-contact-form-7.php',
				CF7::class,
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
				'wc/wc-login.php',
			],
			'WooCommerce Register'       => [
				'hcaptcha_wc_reg_status',
				'woocommerce/woocommerce.php',
				'wc/wc-register.php',
			],
			'WooCommerce Lost Password'  => [
				'hcaptcha_wc_lost_pass_status',
				'woocommerce/woocommerce.php',
				[ 'common/lost-password-form.php', 'wc/wc-lost-password.php' ],
			],
			'WooCommerce Checkout'       => [
				'hcaptcha_wc_checkout_status',
				'woocommerce/woocommerce.php',
				'wc/wc-checkout.php',
			],
			'WooCommerce Order Tracking' => [
				'hcaptcha_wc_order_tracking_status',
				'woocommerce/woocommerce.php',
				OrderTracking::class,
			],
			'WC Wishlist'                => [
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

			if ( ( $module[1] && ! is_plugin_active( $module[1] ) ) ) {
				continue;
			}

			foreach ( (array) $module[2] as $component ) {
				if ( false === strpos( $component, '.php' ) ) {
					if ( ! class_exists( $component, false ) ) {
						new $component();
					}
					continue;
				}

				require_once HCAPTCHA_PATH . '/' . $component;
			}
		}
	}

	/**
	 * Remove standard WP login captcha if we do logging in via WC.
	 *
	 * @param array $credentials Credentials.
	 *
	 * @return array
	 */
	public function remove_filter_wp_authenticate_user( $credentials ) {
		remove_filter( 'wp_authenticate_user', 'hcap_verify_login_captcha' );

		return $credentials;
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
}
