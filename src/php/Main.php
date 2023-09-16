<?php
/**
 * Main class file.
 *
 * @package hcaptcha-wp
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedNamespaceInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha;

use Automattic\WooCommerce\Utilities\FeaturesUtil;
use HCaptcha\Admin\Notifications;
use HCaptcha\AutoVerify\AutoVerify;
use HCaptcha\CF7\CF7;
use HCaptcha\DelayedScript\DelayedScript;
use HCaptcha\Divi\Fix;
use HCaptcha\DownloadManager\DownloadManager;
use HCaptcha\ElementorPro\HCaptchaHandler;
use HCaptcha\Jetpack\JetpackForm;
use HCaptcha\Migrations\Migrations;
use HCaptcha\NF\NF;
use HCaptcha\Quform\Quform;
use HCaptcha\Sendinblue\Sendinblue;
use HCaptcha\Settings\General;
use HCaptcha\Settings\Integrations;
use HCaptcha\Settings\Settings;
use HCaptcha\Settings\SystemInfo;
use HCaptcha\WCWishlists\CreateList;
use HCaptcha\WP\PasswordProtected;

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
	 * We have the verification result of the hCaptcha widget.
	 * Use this flag to send remote request only once.
	 *
	 * @var boolean
	 */
	public $has_result = false;

	/**
	 * Plugin modules.
	 *
	 * @var array
	 */
	public $modules = [];

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
	 * Notifications class instance.
	 *
	 * @var Notifications
	 */
	private $notifications;

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
	 * Whether wpforo_template filter was used.
	 *
	 * @var bool
	 */
	private $did_wpforo_template_filter = false;

	/**
	 * Whether supportcandy shortcode was used.
	 *
	 * @var bool
	 */
	private $did_support_candy_shortcode_tag_filter = false;

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
	 *
	 * @return void
	 */
	public function init_hooks() {
		$this->notifications = new Notifications();
		$this->notifications->init();

		$this->settings = new Settings(
			[
				'hCaptcha' => [
					General::class,
					Integrations::class,
					SystemInfo::class,
				],
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
		add_action( 'before_woocommerce_init', [ $this, 'declare_wc_compatibility' ] );
		add_filter( 'wpforo_template', [ $this, 'wpforo_template_filter' ] );
		add_filter( 'do_shortcode_tag', [ $this, 'support_candy_shortcode_tag' ], 10, 4 );

		$this->auto_verify = new AutoVerify();
		$this->auto_verify->init();
	}

	/**
	 * Get plugin class instance.
	 *
	 * @param string $class_name Class name.
	 *
	 * @return object|null
	 */
	public function get( string $class_name ) {

		return $this->loaded_classes[ $class_name ] ?? null;
	}

	/**
	 * Get Notifications instance.
	 *
	 * @return Notifications
	 */
	public function notifications(): Notifications {
		return $this->notifications;
	}

	/**
	 * Get Settings instance.
	 *
	 * @return Settings
	 */
	public function settings(): Settings {
		return $this->settings;
	}

	/**
	 * Check if we have to activate the plugin.
	 *
	 * @return bool
	 */
	private function activate_hcaptcha(): bool {
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
	private function is_elementor_pro_edit_page(): bool {
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
	 * @param array|mixed $urls          URLs to print for resource hints.
	 * @param string      $relation_type The relation type the URLs are printed for.
	 *
	 * @return array
	 */
	public function prefetch_hcaptcha_dns( $urls, string $relation_type ): array {
		$urls = (array) $urls;

		if ( 'dns-prefetch' === $relation_type ) {
			$urls[] = 'https://hcaptcha.com';
		}

		return $urls;
	}

	/**
	 * Add Content Security Policy (CSP) headers.
	 *
	 * @param array|mixed $headers Headers.
	 *
	 * @return array
	 */
	public function csp_headers( $headers ): array {
		$headers  = (array) $headers;
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
	 *
	 * @return void
	 */
	public function print_inline_styles() {
		$div_logo_url       = HCAPTCHA_URL . '/assets/images/hcaptcha-div-logo.svg';
		$div_logo_white_url = HCAPTCHA_URL . '/assets/images/hcaptcha-div-logo-white.svg';

		?>
		<!--suppress CssUnresolvedCustomProperty, CssUnusedSymbol -->
		<style>
			#wpdiscuz-subscribe-form .h-captcha {
				margin-left: auto;
			}
			div.wpforms-container-full .wpforms-form .h-captcha,
			#wpforo #wpforo-wrap div .h-captcha,
			.h-captcha {
				position: relative;
				display: block;
				margin-bottom: 2rem;
				padding: 0;
				clear: both;
			}
			#hcaptcha-options .h-captcha {
				margin-bottom: 0;
			}
			#af-wrapper div.editor-row.editor-row-hcaptcha {
				display: flex;
				flex-direction: row-reverse;
			}
			#af-wrapper div.editor-row.editor-row-hcaptcha .h-captcha {
				margin-bottom: 0;
			}
			.brz-forms2.brz-forms2__item .h-captcha {
				margin-bottom: 0;
			}
			form.wpsc-create-ticket .h-captcha {
				margin: 0 15px 15px 15px;
			}
			.frm-fluent-form .h-captcha {
				line-height: 0;
				margin-bottom: 0;
			}
			.passster-form .h-captcha {
				margin-bottom: 5px;
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
				content: '';
				display: block;
				position: absolute;
				top: 0;
				left: 0;
				background: url(<?php echo esc_url( $div_logo_url ); ?>) no-repeat;
				border: 1px solid transparent;
				border-radius: 4px;
			}
			.h-captcha[data-size="normal"]::before {
				width: 300px;
				height: 74px;
				background-position: 94% 28%;
			}
			.h-captcha[data-size="compact"]::before {
				width: 156px;
				height: 136px;
				background-position: 50% 79%;
			}
			.h-captcha[data-theme="light"]::before {
				background-color: #fafafa;
				border: 1px solid #e0e0e0;
			}
			.h-captcha[data-theme="dark"]::before {
				background-image: url(<?php echo esc_url( $div_logo_white_url ); ?>);
				background-repeat: no-repeat;
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
			span[data-name="hcap-cf7"] ~ input[type="submit"],
			span[data-name="hcap-cf7"] ~ button[type="submit"] {
				margin-top: 2rem;
			}
			.elementor-field-type-hcaptcha .elementor-field {
				background: transparent !important;
			}
			.elementor-field-type-hcaptcha .h-captcha {
				margin-bottom: unset;
			}
			#wppb-loginform .h-captcha {
				margin-bottom: 14px;
			}
			div[style*="z-index: 2147483647"] div[style*="border-width: 11px"][style*="position: absolute"][style*="pointer-events: none"] {
				border-style: none;
			}
		</style>
		<?php
	}

	/**
	 * Print styles to fit hcaptcha widget to the login form.
	 *
	 * @return void
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
	public function get_api_src(): string {
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

		return add_query_arg( $params, 'https://js.hcaptcha.com/1/api.js' );
	}

	/**
	 * Add the hCaptcha script to footer.
	 *
	 * @return void
	 */
	public function print_footer_scripts() {
		$status =
			$this->form_shown ||
			$this->did_wpforo_template_filter ||
			$this->did_support_candy_shortcode_tag_filter;

		/**
		 * Filters whether to print hCaptcha scripts.
		 *
		 * @param bool $status Current print status.
		 */
		if ( ! apply_filters( 'hcap_print_hcaptcha_scripts', $status ) ) {
			return;
		}

		$settings = $this->settings();

		/**
		 * Filters delay time for the hCaptcha API script.
		 *
		 * Any negative value will prevent the API script from loading
		 * until user interaction: mouseenter, click, scroll or touch.
		 * This significantly improves Google Pagespeed Insights score.
		 *
		 * @param int $delay Number of milliseconds to delay hCaptcha API script.
		 *                   Any negative value means delay until user interaction.
		 */
		$delay = (int) apply_filters( 'hcap_delay_api', (int) $settings->get( 'delay' ) );

		DelayedScript::launch( [ 'src' => $this->get_api_src() ], $delay );

		wp_enqueue_script(
			self::HANDLE,
			HCAPTCHA_URL . '/assets/js/apps/hcaptcha.js',
			[],
			HCAPTCHA_VERSION,
			true
		);

		$params = [
			'sitekey' => $settings->get_site_key(),
			'theme'   => $settings->get( 'theme' ),
			'size'    => $settings->get( 'size' ),
			'hl'      => $settings->get_language(),
		];

		$config_params = [];

		if ( $settings->is_on( 'custom_themes' ) ) {
			$config_params = json_decode( $settings->get( 'config_params' ), true ) ?: [];
		}

		$params = array_merge( $params, $config_params );

		wp_localize_script(
			self::HANDLE,
			self::OBJECT,
			[ 'params' => wp_json_encode( $params ) ]
		);
	}

	/**
	 * Declare compatibility with WC features.
	 *
	 * @return void
	 */
	public function declare_wc_compatibility() {
		if ( class_exists( FeaturesUtil::class ) ) {
			FeaturesUtil::declare_compatibility( 'custom_order_tables', HCAPTCHA_FILE, true );
		}
	}

	/**
	 * Catch wpForo template filter.
	 *
	 * @param array|string $template Template.
	 *
	 * @return array|string
	 */
	public function wpforo_template_filter( $template ) {
		$this->did_wpforo_template_filter = true;

		return $template;
	}

	/**
	 * Catch Support Candy do shortcode tag filter.
	 *
	 * @param string|mixed $output Shortcode output.
	 * @param string       $tag    Shortcode name.
	 * @param array|string $attr   Shortcode attributes array or empty string.
	 * @param array        $m      Regular expression match array.
	 *
	 * @return string|mixed
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function support_candy_shortcode_tag( $output, string $tag, $attr, array $m ) {
		if ( 'supportcandy' === $tag ) {
			$this->did_support_candy_shortcode_tag_filter = true;
		}

		return $output;
	}

	/**
	 * Filter user IP to check if it is whitelisted.
	 * For whitelisted IPs, hCaptcha will not be shown.
	 *
	 * @param bool|mixed   $whitelisted Whether IP is whitelisted.
	 * @param string|false $client_ip   Client IP.
	 *
	 * @return bool|mixed
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
	 *
	 * @return void
	 * @noinspection PhpFullyQualifiedNameUsageInspection
	 */
	public function load_modules() {
		/**
		 * Plugins modules.
		 *
		 * @var                  $modules      {
		 *
		 * @type string[]        $module0      {
		 * @type string          $option_name  Option name.
		 * @type string          $option_value Option value.
		 *                                     }
		 * @type string|string[] $module1      Plugins to be active. For WP core features, an empty string.
		 * @type string|string[] $module2      Required hCaptcha plugin classes.
		 *                                     }
		 */
		$this->modules = [
			'Comment Form'                      => [
				[ 'wp_status', 'comment' ],
				'',
				WP\Comment::class,
			],
			'Login Form'                        => [
				[ 'wp_status', 'login' ],
				'',
				WP\Login::class,
			],
			'Lost Password Form'                => [
				[ 'wp_status', 'lost_pass' ],
				'',
				WP\LostPassword::class,
			],
			'Post/Page Password Form'           => [
				[ 'wp_status', 'password_protected' ],
				'',
				PasswordProtected::class,
			],
			'Register Form'                     => [
				[ 'wp_status', 'register' ],
				'',
				WP\Register::class,
			],
			'ACF Extended Form'                 => [
				[ 'acfe_status', 'form' ],
				[ 'acf-extended-pro/acf-extended.php', 'acf-extended/acf-extended.php' ],
				ACFE\Form::class,
			],
			'Asgaros Form'                      => [
				[ 'asgaros_status', 'form' ],
				'asgaros-forum/asgaros-forum.php',
				Asgaros\Form::class,
			],
			'Avada Form'                        => [
				[ 'avada_status', 'form' ],
				'Avada',
				Avada\Form::class,
			],
			'Back In Stock Notifier Form'       => [
				[ 'back_in_stock_notifier_status', 'form' ],
				'back-in-stock-notifier-for-woocommerce/cwginstocknotifier.php',
				BackInStockNotifier\Form::class,
			],
			'bbPress New Topic'                 => [
				[ 'bbp_status', 'new_topic' ],
				'bbpress/bbpress.php',
				BBPress\NewTopic::class,
			],
			'bbPress Reply'                     => [
				[ 'bbp_status', 'reply' ],
				'bbpress/bbpress.php',
				BBPress\Reply::class,
			],
			'Beaver Builder Contact Form'       => [
				[ 'beaver_builder_status', 'contact' ],
				'bb-plugin/fl-builder.php',
				BeaverBuilder\Contact::class,
			],
			'Beaver Builder Login Form'         => [
				[ 'beaver_builder_status', 'login' ],
				'bb-plugin/fl-builder.php',
				[ BeaverBuilder\Login::class ],
			],
			'Brizy Form'                        => [
				[ 'brizy_status', 'form' ],
				'brizy/brizy.php',
				[ Brizy\Form::class ],
			],
			'BuddyPress Create Group'           => [
				[ 'bp_status', 'create_group' ],
				'buddypress/bp-loader.php',
				BuddyPress\CreateGroup::class,
			],
			'BuddyPress Register'               => [
				[ 'bp_status', 'registration' ],
				'buddypress/bp-loader.php',
				BuddyPress\Register::class,
			],
			'Classified Listing Contact'        => [
				[ 'classified_listing_status', 'contact' ],
				'classified-listing/classified-listing.php',
				ClassifiedListing\Contact::class,
			],
			'Classified Listing Login'          => [
				[ 'classified_listing_status', 'login' ],
				'classified-listing/classified-listing.php',
				ClassifiedListing\Login::class,
			],
			'Classified Listing Lost Password'  => [
				[ 'classified_listing_status', 'lost_pass' ],
				'classified-listing/classified-listing.php',
				ClassifiedListing\LostPassword::class,
			],
			'Classified Listing Register'       => [
				[ 'classified_listing_status', 'register' ],
				'classified-listing/classified-listing.php',
				ClassifiedListing\Register::class,
			],
			'Colorlib Customizer Login'         => [
				[ 'colorlib_customizer_status', 'login' ],
				'colorlib-login-customizer/colorlib-login-customizer.php',
				ColorlibCustomizer\Login::class,
			],
			'Colorlib Customizer Lost Password' => [
				[ 'colorlib_customizer_status', 'lost_pass' ],
				'colorlib-login-customizer/colorlib-login-customizer.php',
				ColorlibCustomizer\LostPassword::class,
			],
			'Colorlib Customizer Register'      => [
				[ 'colorlib_customizer_status', 'register' ],
				'colorlib-login-customizer/colorlib-login-customizer.php',
				ColorlibCustomizer\Register::class,
			],
			'Contact Form 7'                    => [
				[ 'cf7_status', 'form' ],
				'contact-form-7/wp-contact-form-7.php',
				CF7::class,
			],
			'Divi Comment Form'                 => [
				[ 'divi_status', 'comment' ],
				'Divi',
				[ Divi\Comment::class, WP\Comment::class ],
			],
			'Divi Contact Form'                 => [
				[ 'divi_status', 'contact' ],
				'Divi',
				Divi\Contact::class,
			],
			'Divi Email Optin Form'             => [
				[ 'divi_status', 'email_optin' ],
				'Divi',
				Divi\EmailOptin::class,
			],
			'Divi Login Form'                   => [
				[ 'divi_status', null ],
				'Divi',
				[ Divi\Login::class ],
			],
			'Download Manager'                  => [
				[ 'download_manager_status', 'button' ],
				'download-manager/download-manager.php',
				DownloadManager::class,
			],
			'Easy Digital Downloads Checkout'   => [
				[ 'easy_digital_downloads_status', 'checkout' ],
				'easy-digital-downloads/easy-digital-downloads.php',
				EasyDigitalDownloads\Checkout::class,
			],
			'Elementor Pro Form'                => [
				[ 'elementor_pro_status', 'form' ],
				'elementor-pro/elementor-pro.php',
				HCaptchaHandler::class,
			],
			'Fluent Forms'                      => [
				[ 'fluent_status', 'form' ],
				'fluentform/fluentform.php',
				FluentForm\Form::class,
			],
			'Formidable Forms'                  => [
				[ 'formidable_forms_status', 'form' ],
				'formidable/formidable.php',
				FormidableForms\Form::class,
			],
			'Forminator'                        => [
				[ 'forminator_status', 'form' ],
				'forminator/forminator.php',
				Forminator\Form::class,
			],
			'GiveWP'                            => [
				[ 'give_wp_status', 'form' ],
				'give/give.php',
				GiveWP\Form::class,
			],
			'Gravity Forms'                     => [
				[ 'gravity_status', 'form' ],
				'gravityforms/gravityforms.php',
				GravityForms\Form::class,
			],
			'Jetpack'                           => [
				[ 'jetpack_status', 'contact' ],
				'jetpack/jetpack.php',
				JetpackForm::class,
			],
			'Kadence Form'                      => [
				[ 'kadence_status', 'form' ],
				'kadence-blocks/kadence-blocks.php',
				Kadence\Form::class,
			],
			'Kadence Advanced Form'             => [
				[ 'kadence_status', 'advanced_form' ],
				'kadence-blocks/kadence-blocks.php',
				Kadence\AdvancedForm::class,
			],
			'MailChimp'                         => [
				[ 'mailchimp_status', 'form' ],
				'mailchimp-for-wp/mailchimp-for-wp.php',
				Mailchimp\Form::class,
			],
			'MemberPress Login'                 => [
				[ 'memberpress_status', 'login' ],
				'memberpress/memberpress.php',
				[ MemberPress\Login::class ],
			],
			'MemberPress Register'              => [
				[ 'memberpress_status', 'register' ],
				'memberpress/memberpress.php',
				MemberPress\Register::class,
			],
			'Ninja Forms'                       => [
				[ 'ninja_status', 'form' ],
				'ninja-forms/ninja-forms.php',
				NF::class,
			],
			'Otter Blocks'                      => [
				[ 'otter_status', 'form' ],
				'otter-blocks/otter-blocks.php',
				Otter\Form::class,
			],
			'Paid Memberships Pro Checkout'     => [
				[ 'paid_memberships_pro_status', 'checkout' ],
				'paid-memberships-pro/paid-memberships-pro.php',
				PaidMembershipsPro\Checkout::class,
			],
			'Paid Memberships Pro Login'        => [
				[ 'paid_memberships_pro_status', null ],
				'paid-memberships-pro/paid-memberships-pro.php',
				PaidMembershipsPro\Login::class,
			],
			'Passster Protect'                  => [
				[ 'passster_status', 'protect' ],
				'content-protector/content-protector.php',
				Passster\Protect::class,
			],
			'Profile Builder Login'             => [
				[ 'profile_builder_status', 'login' ],
				'profile-builder/index.php',
				ProfileBuilder\Login::class,
			],
			'Profile Builder Register'          => [
				[ 'profile_builder_status', 'register' ],
				'profile-builder/index.php',
				ProfileBuilder\Register::class,
			],
			'Profile Builder Recover Password'  => [
				[ 'profile_builder_status', 'lost_pass' ],
				'profile-builder/index.php',
				ProfileBuilder\LostPassword::class,
			],
			'Quform'                            => [
				[ 'quform_status', 'form' ],
				'quform/quform.php',
				Quform::class,
			],
			'Sendinblue'                        => [
				[ 'sendinblue_status', 'form' ],
				'mailin/sendinblue.php',
				Sendinblue::class,
			],
			'Subscriber'                        => [
				[ 'subscriber_status', 'form' ],
				'subscriber/subscriber.php',
				Subscriber\Form::class,
			],
			'Support Candy Form'                => [
				[ 'supportcandy_status', 'form' ],
				'supportcandy/supportcandy.php',
				SupportCandy\Form::class,
			],
			'Theme My Login Login'              => [
				[ 'theme_my_login_status', 'login' ],
				'theme-my-login/theme-my-login.php',
				ThemeMyLogin\Login::class,
			],
			'Theme My Login LostPassword'       => [
				[ 'theme_my_login_status', 'lost_pass' ],
				'theme-my-login/theme-my-login.php',
				ThemeMyLogin\LostPassword::class,
			],
			'Theme My Login Register'           => [
				[ 'theme_my_login_status', 'register' ],
				'theme-my-login/theme-my-login.php',
				ThemeMyLogin\Register::class,
			],
			'Ultimate Member Login'             => [
				[ 'ultimate_member_status', 'login' ],
				'ultimate-member/ultimate-member.php',
				[ UM\Login::class ],
			],
			'Ultimate Member LostPassword'      => [
				[ 'ultimate_member_status', 'lost_pass' ],
				'ultimate-member/ultimate-member.php',
				UM\LostPassword::class,
			],
			'Ultimate Member Register'          => [
				[ 'ultimate_member_status', 'register' ],
				'ultimate-member/ultimate-member.php',
				UM\Register::class,
			],
			'UsersWP Forgot Password'           => [
				[ 'users_wp_status', 'forgot' ],
				'userswp/userswp.php',
				UsersWP\ForgotPassword::class,
			],
			'UsersWP Login'                     => [
				[ 'users_wp_status', 'login' ],
				'userswp/userswp.php',
				UsersWP\Login::class,
			],
			'UsersWP Register'                  => [
				[ 'users_wp_status', 'register' ],
				'userswp/userswp.php',
				UsersWP\Register::class,
			],
			'WooCommerce Checkout'              => [
				[ 'woocommerce_status', 'checkout' ],
				'woocommerce/woocommerce.php',
				WC\Checkout::class,
			],
			'WooCommerce Login'                 => [
				[ 'woocommerce_status', 'login' ],
				'woocommerce/woocommerce.php',
				WC\Login::class,
			],
			'WooCommerce Lost Password'         => [
				[ 'woocommerce_status', 'lost_pass' ],
				'woocommerce/woocommerce.php',
				[ WP\LostPassword::class, WC\LostPassword::class ],
			],
			'WooCommerce Order Tracking'        => [
				[ 'woocommerce_status', 'order_tracking' ],
				'woocommerce/woocommerce.php',
				WC\OrderTracking::class,
			],
			'WooCommerce Register'              => [
				[ 'woocommerce_status', 'register' ],
				'woocommerce/woocommerce.php',
				WC\Register::class,
			],
			'WooCommerce Wishlists'             => [
				[ 'woocommerce_wishlists_status', 'create_list' ],
				'woocommerce-wishlists/woocommerce-wishlists.php',
				CreateList::class,
			],
			'Wordfence Login'                   => [
				[ 'wordfence_status', null ],
				[ 'wordfence/wordfence.php', 'wordfence-login-security/wordfence-login-security.php' ],
				Wordfence\General::class,
			],
			'WPForms Lite'                      => [
				[ 'wpforms_status', 'lite' ],
				[ 'wpforms/wpforms.php', 'wpforms-lite/wpforms.php' ],
				\HCaptcha\WPForms\Form::class,
			],
			'WPForms Pro'                       => [
				[ 'wpforms_status', 'pro' ],
				[ 'wpforms/wpforms.php', 'wpforms-lite/wpforms.php' ],
				\HCaptcha\WPForms\Form::class,
			],
			'wpDiscuz Comment'                  => [
				[ 'wpdiscuz_status', 'comment_form' ],
				'wpdiscuz/class.WpdiscuzCore.php',
				WPDiscuz\Comment::class,
			],
			'wpDiscuz Subscribe'                => [
				[ 'wpdiscuz_status', 'subscribe_form' ],
				'wpdiscuz/class.WpdiscuzCore.php',
				WPDiscuz\Subscribe::class,
			],
			'wpForo New Topic'                  => [
				[ 'wpforo_status', 'new_topic' ],
				'wpforo/wpforo.php',
				WPForo\NewTopic::class,
			],
			'wpForo Reply'                      => [
				[ 'wpforo_status', 'reply' ],
				'wpforo/wpforo.php',
				WPForo\Reply::class,
			],
		];

		if ( ! function_exists( 'is_plugin_active' ) ) {
			// @codeCoverageIgnoreStart
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
			// @codeCoverageIgnoreEnd
		}

		foreach ( $this->modules as $module ) {
			list( $option_name, $option_value ) = $module[0];

			$option = (array) $this->settings()->get( $option_name );

			if ( ! $this->plugin_or_theme_active( $module[1] ) ) {
				$this->settings()->set_field( $option_name, 'disabled', true );
				continue;
			}

			if ( $option_value && ! in_array( $option_value, $option, true ) ) {
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
	private function plugin_or_theme_active( $plugin_or_theme_names ): bool {
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
	 *
	 * @return void
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
	protected function is_xml_rpc(): bool {
		return defined( 'XMLRPC_REQUEST' ) && constant( 'XMLRPC_REQUEST' );
	}
}
