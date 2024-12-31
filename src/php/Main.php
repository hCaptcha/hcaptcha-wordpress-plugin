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
use HCaptcha\Admin\Events\Events;
use HCaptcha\Admin\PluginStats;
use HCaptcha\AutoVerify\AutoVerify;
use HCaptcha\CF7\Admin;
use HCaptcha\CF7\CF7;
use HCaptcha\DelayedScript\DelayedScript;
use HCaptcha\Divi\Fix;
use HCaptcha\DownloadManager\DownloadManager;
use HCaptcha\ElementorPro\HCaptchaHandler;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Helpers\Pages;
use HCaptcha\Helpers\Request;
use HCaptcha\Migrations\Migrations;
use HCaptcha\NF\NF;
use HCaptcha\Quform\Quform;
use HCaptcha\Sendinblue\Sendinblue;
use HCaptcha\Settings\EventsPage;
use HCaptcha\Settings\FormsPage;
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
	public const HANDLE = 'hcaptcha';

	/**
	 * WP hooks handle.
	 */
	private const WP_HOOKS_HANDLE = 'wp-hooks';

	/**
	 * Main script localization object.
	 */
	private const OBJECT = 'HCaptchaMainObject';

	/**
	 * Default API host.
	 */
	public const API_HOST = 'js.hcaptcha.com';

	/**
	 * Default verify host.
	 */
	public const VERIFY_HOST = 'api.hcaptcha.com';

	/**
	 * Priority of the plugins_loaded action to load Main.
	 */
	public const LOAD_PRIORITY = Migrations::LOAD_PRIORITY + 1;

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
	 * Loaded integration-related classes.
	 *
	 * @var array
	 */
	protected $loaded_classes = [];

	/**
	 * Migrations class instance.
	 *
	 * @var Migrations
	 */
	protected $migrations;

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
	 * Init class.
	 *
	 * @return void
	 */
	public function init(): void {
		if ( Request::is_xml_rpc() ) {
			// @codeCoverageIgnoreStart
			return;
			// @codeCoverageIgnoreEnd
		}

		$this->migrations = new Migrations();

		if ( wp_doing_cron() ) {
			return;
		}

		( new Fix() )->init();

		add_action( 'plugins_loaded', [ $this, 'init_hooks' ], self::LOAD_PRIORITY );
	}

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	public function init_hooks(): void {
		( new ErrorHandler() )->init();

		$this->load_textdomain();

		/**
		 *  Filters the settings system initialization arguments.
		 *
		 * @param array $args Settings system initialization arguments.
		 */
		$args = (array) apply_filters( 'hcap_settings_init_args', [] );

		$this->settings = new Settings(
			[
				'hCaptcha' => [
					'classes' => [
						General::class,
						Integrations::class,
						FormsPage::class,
						EventsPage::class,
						SystemInfo::class,
					],
					'args'    => $args,
				],
			]
		);

		$this->load( PluginStats::class );
		$this->load( Events::class );

		add_action( 'plugins_loaded', [ $this, 'load_modules' ], self::LOAD_PRIORITY + 1 );
		add_filter( 'hcap_whitelist_ip', [ $this, 'whitelist_ip' ], -PHP_INT_MAX, 2 );
		add_action( 'before_woocommerce_init', [ $this, 'declare_wc_compatibility' ] );

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
	 * Get plugin class instance.
	 *
	 * @param string $class_name Class name.
	 *
	 * @return object|null
	 */
	public function get( string $class_name ): ?object {
		return $this->loaded_classes[ $class_name ] ?? null;
	}

	/**
	 * Load a service class.
	 *
	 * @param string $class_name Class name.
	 *
	 * @return void
	 */
	private function load( string $class_name ): void {
		$this->loaded_classes[ $class_name ] = new $class_name();
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
		$settings = $this->settings();

		/**
		 * Do not load hCaptcha functionality:
		 * - if a user is logged in and the option 'off_when_logged_in' is set;
		 * - for whitelisted IPs;
		 * - when the site key or the secret key is empty (after first plugin activation).
		 */
		$deactivate = (
			( is_user_logged_in() && $settings->is_on( 'off_when_logged_in' ) ) ||
			/**
			 * Filters the user IP to check whether it is whitelisted.
			 *
			 * @param bool         $whitelisted IP is whitelisted.
			 * @param string|false $ip          IP string or false for local addresses.
			 */
			apply_filters( 'hcap_whitelist_ip', false, hcap_get_user_ip() ) ||
			( '' === $settings->get_site_key() || '' === $settings->get_secret_key() )
		);

		$activate = ( ! $deactivate ) || $this->is_edit_page();

		/**
		 * Filters the hCaptcha activation flag.
		 *
		 * @param bool $activate Activate the hcaptcha functionality.
		 */
		return (bool) apply_filters( 'hcap_activate', $activate );
	}

	/**
	 * Whether we are on the admin edit page for a component and component is active.
	 *
	 * @return bool
	 */
	private function is_edit_page(): bool {
		$settings   = $this->settings();
		$components = [
			'beaver_builder',
			'cf7',
			'elementor_pro',
			'gravity',
			'fluent',
			'forminator',
			'formidable_forms',
			'ninja',
			'wpforms',
		];

		return array_reduce(
			$components,
			static function ( $carry, $component ) use ( $settings ) {
				$method = 'is_' . $component . '_edit_page';

				if (
					! method_exists( Pages::class, $method ) ||
					! $settings->is_on( $component . '_status' )
				) {
					return $carry;
				}

				return $carry || Pages::$method();
			},
			false
		);
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
		$headers = (array) $headers;

		/**
		 * Filters whether to add Content Security Policy (CSP) headers.
		 *
		 * @param bool  $add_csp_headers Add Content Security Policy (CSP) headers.
		 * @param array $headers         Current headers.
		 */
		if ( ! apply_filters( 'hcap_add_csp_headers', false, $headers ) ) {
			return $headers;
		}

		$keys_lower    = array_map( 'strtolower', array_keys( $headers ) );
		$csp_key       = 'Content-Security-Policy';
		$csp_key_lower = strtolower( $csp_key );

		if ( ! in_array( $csp_key_lower, $keys_lower, true ) ) {
			return $headers;
		}

		$hcap_src     = "'self' 'unsafe-inline' 'unsafe-eval' https://hcaptcha.com https://*.hcaptcha.com";
		$hcap_csp     = "script-src $hcap_src; frame-src $hcap_src; style-src $hcap_src; connect-src $hcap_src";
		$hcap_csp_arr = $this->parse_csp( $hcap_csp );

		foreach ( $headers as $key => $header ) {
			if ( strtolower( $key ) === $csp_key_lower ) {
				$hcap_csp_arr = $this->merge_csp( $hcap_csp_arr, $this->parse_csp( $header ) );
			}
		}

		$hcap_csp_headers = [];

		foreach ( $hcap_csp_arr as $key => $value ) {
			$hcap_csp_headers[] = $key . ' ' . implode( ' ', $value );
		}

		/**
		 * Filters the Content Security Policy (CSP) headers.
		 *
		 * @param string $hcap_csp_headers Content Security Policy (CSP) headers.
		 */
		$hcap_csp_headers = (array) apply_filters( 'hcap_csp_headers', $hcap_csp_headers );

		$headers[ $csp_key ] = implode( '; ', $hcap_csp_headers );

		return $headers;
	}

	/**
	 * Parse csp header.
	 *
	 * @param string $csp CSP header.
	 *
	 * @return array
	 */
	private function parse_csp( string $csp ): array {
		$csp_subheaders = explode( ';', $csp );
		$csp_arr        = [];

		foreach ( $csp_subheaders as $csp_subheader ) {
			$csp_subheader_arr = explode( ' ', trim( $csp_subheader ) );
			$key               = (string) array_shift( $csp_subheader_arr );
			$csp_arr[ $key ]   = $csp_subheader_arr;
		}

		unset( $csp_arr[''] );

		return array_filter( $csp_arr );
	}

	/**
	 * Merge csp headers.
	 *
	 * @param array $csp_arr1 CSP headers array 1.
	 * @param array $csp_arr2 CSP headers array 2.
	 *
	 * @return array
	 */
	private function merge_csp( array $csp_arr1, array $csp_arr2 ): array {
		$csp  = [];
		$keys = array_unique( array_merge( array_keys( $csp_arr1 ), array_keys( $csp_arr2 ) ) );

		foreach ( $keys as $key ) {
			$csp1        = $csp_arr1[ $key ] ?? [];
			$csp2        = $csp_arr2[ $key ] ?? [];
			$csp[ $key ] = array_unique( array_merge( $csp1, $csp2 ) );
		}

		return $csp;
	}

	/**
	 * Print inline styles.
	 *
	 * @return void
	 * @noinspection CssUnusedSymbol
	 */
	public function print_inline_styles(): void {
		$settings           = $this->settings();
		$div_logo_url       = HCAPTCHA_URL . '/assets/images/hcaptcha-div-logo.svg';
		$div_logo_white_url = HCAPTCHA_URL . '/assets/images/hcaptcha-div-logo-white.svg';
		$bg                 = $settings->get_custom_theme_background() ?: 'initial';

		$css = <<<CSS
	.h-captcha {
		position: relative;
		display: block;
		margin-bottom: 2rem;
		padding: 0;
		clear: both;
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
		background: url( $div_logo_url ) no-repeat;
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

	.h-captcha[data-theme="light"]::before,
	body.is-light-theme .h-captcha[data-theme="auto"]::before,
	.h-captcha[data-theme="auto"]::before {
		background-color: #fafafa;
		border: 1px solid #e0e0e0;
	}

	.h-captcha[data-theme="dark"]::before,
	body.is-dark-theme .h-captcha[data-theme="auto"]::before,
	html.wp-dark-mode-active .h-captcha[data-theme="auto"]::before,
	html.drdt-dark-mode .h-captcha[data-theme="auto"]::before {
		background-image: url( $div_logo_white_url );
		background-repeat: no-repeat;
		background-color: #333;
		border: 1px solid #f5f5f5;
	}

	.h-captcha[data-theme="custom"]::before {
		background-color: $bg;
	}

	.h-captcha[data-size="invisible"]::before {
		display: none;
	}

	.h-captcha iframe {
		position: relative;
	}

	div[style*="z-index: 2147483647"] div[style*="border-width: 11px"][style*="position: absolute"][style*="pointer-events: none"] {
		border-style: none;
	}
CSS;

		HCaptcha::css_display( $css );
	}

	/**
	 * Print styles to fit hcaptcha widget to the login form.
	 *
	 * @return void
	 * @noinspection CssUnusedSymbol
	 */
	public function login_head(): void {
		$css = <<<'CSS'
	@media (max-width: 349px) {
		.h-captcha {
			display: flex;
			justify-content: center;
		}
		.h-captcha[data-size="normal"] {
			scale: calc(270 / 303);
		    transform: translate(-20px, 0);
		}
	}

	@media (min-width: 350px) {
		body #login {
			width: 350px;
			box-sizing: content-box;
		}
	}
CSS;

		HCaptcha::css_display( $css );
	}

	/**
	 * Get API url.
	 *
	 * @return string
	 */
	public function get_api_url(): string {
		$api_host = trim( $this->settings()->get( 'api_host' ) ) ?: self::API_HOST;

		/**
		 * Filters the API host.
		 *
		 * @param string $api_host API host.
		 */
		$api_host = (string) apply_filters( 'hcap_api_host', $api_host );

		$api_host = $this->force_https( $api_host );

		return "$api_host/1/api.js";
	}

	/**
	 * Force https in the hostname.
	 *
	 * @param string $host Hostname. Could be with http|https scheme, or without it.
	 *
	 * @return string
	 */
	private function force_https( string $host ): string {
		$host = preg_replace( '#(http|https)://#', '', $host );

		// We need to add scheme here, otherwise wp_parse_url returns null.
		$host = (string) wp_parse_url( 'https://' . $host, PHP_URL_HOST );

		return 'https://' . $host;
	}

	/**
	 * Get API source url with params.
	 *
	 * @return string
	 */
	public function get_api_src(): string {
		$params = [
			'onload' => 'hCaptchaOnLoad',
			'render' => 'explicit',
		];

		$settings = $this->settings();

		if ( $settings->is_on( 'recaptcha_compat_off' ) ) {
			$params['recaptchacompat'] = 'off';
		}

		if ( $settings->is_on( 'custom_themes' ) && $settings->is_pro_or_general() ) {
			$params['custom'] = 'true';
		}

		$enterprise_params = [
			'asset_host' => 'assethost',
			'endpoint'   => 'endpoint',
			'host'       => 'host',
			'image_host' => 'imghost',
			'report_api' => 'reportapi',
			'sentry'     => 'sentry',
		];

		foreach ( $enterprise_params as $enterprise_param => $enterprise_arg ) {
			$value = trim( $settings->get( $enterprise_param ) );

			if ( $value ) {
				$params[ $enterprise_arg ] = rawurlencode( $this->force_https( $value ) );
			}
		}

		/**
		 * Filters the API source url with params.
		 *
		 * @param string $api_src API source url with params.
		 */
		return (string) apply_filters( 'hcap_api_src', add_query_arg( $params, $this->get_api_url() ) );
	}

	/**
	 * Get verify url.
	 *
	 * @return string
	 */
	public function get_verify_url(): string {
		$verify_host = trim( $this->settings()->get( 'backend' ) ) ?: self::VERIFY_HOST;

		/**
		 * Filters the verification host.
		 *
		 * @param string $verify_host Verification host.
		 */
		$verify_host = (string) apply_filters( 'hcap_verify_host', $verify_host );

		$verify_host = $this->force_https( $verify_host );

		return "$verify_host/siteverify";
	}

	/**
	 * Get check site config url.
	 *
	 * @return string
	 */
	public function get_check_site_config_url(): string {
		$verify_host = trim( $this->settings()->get( 'backend' ) ) ?: self::VERIFY_HOST;

		/** This filter is documented above. */
		$verify_host = (string) apply_filters( 'hcap_verify_host', $verify_host );

		$verify_host = $this->force_https( $verify_host );

		return "$verify_host/checksiteconfig";
	}

	/**
	 * Add the hCaptcha script to footer.
	 *
	 * @return void
	 */
	public function print_footer_scripts(): void {
		$status = $this->form_shown;

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

		wp_enqueue_script( self::WP_HOOKS_HANDLE );
		wp_enqueue_script(
			self::HANDLE,
			HCAPTCHA_URL . '/assets/js/apps/hcaptcha.js',
			[ self::WP_HOOKS_HANDLE ],
			HCAPTCHA_VERSION,
			true
		);

		$params   = [
			'sitekey' => $settings->get_site_key(),
			'theme'   => $settings->get_theme(),
			'size'    => $settings->get( 'size' ),
		];
		$language = $settings->get_language();

		// Fix auto-detection of hCaptcha language.
		$language = $language ?: HCaptcha::get_hcap_locale();

		if ( $language ) {
			$params['hl'] = $language;
		}

		$config_params = $settings->is_on( 'custom_themes' ) && $settings->is_pro_or_general()
			? $settings->get_config_params()
			: [];

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
	public function declare_wc_compatibility(): void {
		// @codeCoverageIgnoreStart
		if ( class_exists( FeaturesUtil::class ) ) {
			FeaturesUtil::declare_compatibility( 'custom_order_tables', constant( 'HCAPTCHA_FILE' ), true );
		}
		// @codeCoverageIgnoreEnd
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
	 */
	public function load_modules(): void {
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
			'Comment Form'                         => [
				[ 'wp_status', 'comment' ],
				'',
				WP\Comment::class,
			],
			'Login Form'                           => [
				[ 'wp_status', 'login' ],
				'',
				WP\Login::class,
			],
			'Lost Password Form'                   => [
				[ 'wp_status', 'lost_pass' ],
				'',
				WP\LostPassword::class,
			],
			'Post/Page Password Form'              => [
				[ 'wp_status', 'password_protected' ],
				'',
				PasswordProtected::class,
			],
			'Register Form'                        => [
				[ 'wp_status', 'register' ],
				'',
				WP\Register::class,
			],
			'ACF Extended Form'                    => [
				[ 'acfe_status', 'form' ],
				[ 'acf-extended-pro/acf-extended.php', 'acf-extended/acf-extended.php' ],
				ACFE\Form::class,
			],
			'Affiliates Login'                     => [
				[ 'affiliates_status', 'login' ],
				'affiliates/affiliates.php',
				Affiliates\Login::class,
			],
			'Affiliates Register'                  => [
				[ 'affiliates_status', 'register' ],
				'affiliates/affiliates.php',
				Affiliates\Register::class,
			],
			'Asgaros Form'                         => [
				[ 'asgaros_status', 'form' ],
				'asgaros-forum/asgaros-forum.php',
				Asgaros\Form::class,
			],
			'Avada Form'                           => [
				[ 'avada_status', 'form' ],
				'Avada',
				Avada\Form::class,
			],
			'Back In Stock Notifier Form'          => [
				[ 'back_in_stock_notifier_status', 'form' ],
				'back-in-stock-notifier-for-woocommerce/cwginstocknotifier.php',
				BackInStockNotifier\Form::class,
			],
			'bbPress Login Form'                   => [
				[ 'bbp_status', null ],
				'bbpress/bbpress.php',
				BBPress\Login::class,
			],
			'bbPress Lost Password Form'           => [
				[ 'bbp_status', null ],
				'bbpress/bbpress.php',
				BBPress\LostPassword::class,
			],
			'bbPress New Topic'                    => [
				[ 'bbp_status', 'new_topic' ],
				'bbpress/bbpress.php',
				BBPress\NewTopic::class,
			],
			'bbPress Register Form'                => [
				[ 'bbp_status', null ],
				'bbpress/bbpress.php',
				BBPress\Register::class,
			],
			'bbPress Reply'                        => [
				[ 'bbp_status', 'reply' ],
				'bbpress/bbpress.php',
				BBPress\Reply::class,
			],
			'Beaver Builder Contact Form'          => [
				[ 'beaver_builder_status', 'contact' ],
				'bb-plugin/fl-builder.php',
				BeaverBuilder\Contact::class,
			],
			'Beaver Builder Login Form'            => [
				[ 'beaver_builder_status', 'login' ],
				'bb-plugin/fl-builder.php',
				BeaverBuilder\Login::class,
			],
			'Brizy Form'                           => [
				[ 'brizy_status', 'form' ],
				'brizy/brizy.php',
				Brizy\Form::class,
			],
			'BuddyPress Create Group'              => [
				[ 'bp_status', 'create_group' ],
				'buddypress/bp-loader.php',
				BuddyPress\CreateGroup::class,
			],
			'BuddyPress Register'                  => [
				[ 'bp_status', 'registration' ],
				'buddypress/bp-loader.php',
				BuddyPress\Register::class,
			],
			'Classified Listing Contact'           => [
				[ 'classified_listing_status', 'contact' ],
				'classified-listing/classified-listing.php',
				ClassifiedListing\Contact::class,
			],
			'Classified Listing Login'             => [
				[ 'classified_listing_status', 'login' ],
				'classified-listing/classified-listing.php',
				ClassifiedListing\Login::class,
			],
			'Classified Listing Lost Password'     => [
				[ 'classified_listing_status', 'lost_pass' ],
				'classified-listing/classified-listing.php',
				ClassifiedListing\LostPassword::class,
			],
			'Classified Listing Register'          => [
				[ 'classified_listing_status', 'register' ],
				'classified-listing/classified-listing.php',
				ClassifiedListing\Register::class,
			],
			'CoBlocks Form'                        => [
				[ 'coblocks_status', 'form' ],
				'coblocks/class-coblocks.php',
				CoBlocks\Form::class,
			],
			'Colorlib Customizer Login'            => [
				[ 'colorlib_customizer_status', 'login' ],
				'colorlib-login-customizer/colorlib-login-customizer.php',
				ColorlibCustomizer\Login::class,
			],
			'Colorlib Customizer Lost Password'    => [
				[ 'colorlib_customizer_status', 'lost_pass' ],
				'colorlib-login-customizer/colorlib-login-customizer.php',
				ColorlibCustomizer\LostPassword::class,
			],
			'Colorlib Customizer Register'         => [
				[ 'colorlib_customizer_status', 'register' ],
				'colorlib-login-customizer/colorlib-login-customizer.php',
				ColorlibCustomizer\Register::class,
			],
			'Contact Form 7'                       => [
				[ 'cf7_status', null ],
				'contact-form-7/wp-contact-form-7.php',
				[ CF7::class, Admin::class ],
			],
			'Divi Comment Form'                    => [
				[ 'divi_status', 'comment' ],
				'Divi',
				[ Divi\Comment::class, WP\Comment::class ],
			],
			'Divi Contact Form'                    => [
				[ 'divi_status', 'contact' ],
				'Divi',
				Divi\Contact::class,
			],
			'Divi Email Optin Form'                => [
				[ 'divi_status', 'email_optin' ],
				'Divi',
				Divi\EmailOptin::class,
			],
			'Divi Login Form'                      => [
				[ 'divi_status', null ],
				'Divi',
				[ Divi\Login::class ],
			],
			'Divi Builder Comment Form'            => [
				[ 'divi_builder_status', 'comment' ],
				'divi-builder/divi-builder.php',
				[ Divi\Comment::class, WP\Comment::class ],
			],
			'Divi Builder Contact Form'            => [
				[ 'divi_builder_status', 'contact' ],
				'divi-builder/divi-builder.php',
				Divi\Contact::class,
			],
			'Divi Builder Email Optin Form'        => [
				[ 'divi_builder_status', 'email_optin' ],
				'divi-builder/divi-builder.php',
				Divi\EmailOptin::class,
			],
			'Divi Builder Login Form'              => [
				[ 'divi_builder_status', null ],
				'divi-builder/divi-builder.php',
				[ Divi\Login::class ],
			],
			'Download Manager'                     => [
				[ 'download_manager_status', 'button' ],
				'download-manager/download-manager.php',
				DownloadManager::class,
			],
			'Easy Digital Downloads Checkout'      => [
				[ 'easy_digital_downloads_status', 'checkout' ],
				'easy-digital-downloads/easy-digital-downloads.php',
				EasyDigitalDownloads\Checkout::class,
			],
			'Easy Digital Downloads Login'         => [
				[ 'easy_digital_downloads_status', 'login' ],
				'easy-digital-downloads/easy-digital-downloads.php',
				EasyDigitalDownloads\Login::class,
			],
			'Easy Digital Downloads Lost Password' => [
				[ 'easy_digital_downloads_status', 'lost_pass' ],
				'easy-digital-downloads/easy-digital-downloads.php',
				EasyDigitalDownloads\LostPassword::class,
			],
			'Easy Digital Downloads Register'      => [
				[ 'easy_digital_downloads_status', 'register' ],
				'easy-digital-downloads/easy-digital-downloads.php',
				EasyDigitalDownloads\Register::class,
			],
			'Elementor Pro Form'                   => [
				[ 'elementor_pro_status', 'form' ],
				'elementor-pro/elementor-pro.php',
				HCaptchaHandler::class,
			],
			'Elementor Pro Login'                  => [
				[ 'elementor_pro_status', null ],
				'elementor-pro/elementor-pro.php',
				ElementorPro\Login::class,
			],
			'Essential Addons Login'               => [
				[ 'essential_addons_status', 'login' ],
				'essential-addons-for-elementor-lite/essential_adons_elementor.php',
				EssentialAddons\Login::class,
			],
			'Essential Addons Register'            => [
				[ 'essential_addons_status', 'register' ],
				'essential-addons-for-elementor-lite/essential_adons_elementor.php',
				EssentialAddons\Register::class,
			],
			'Essential Blocks Form'                => [
				[ 'essential_blocks_status', 'form' ],
				'essential-blocks/essential-blocks.php',
				EssentialBlocks\Form::class,
			],
			'Extra Comment Form'                   => [
				[ 'extra_status', 'comment' ],
				'Extra',
				[ Divi\Comment::class, WP\Comment::class ],
			],
			'Extra Contact Form'                   => [
				[ 'extra_status', 'contact' ],
				'Extra',
				Divi\Contact::class,
			],
			'Extra Email Optin Form'               => [
				[ 'extra_status', 'email_optin' ],
				'Extra',
				Divi\EmailOptin::class,
			],
			'Extra Login Form'                     => [
				[ 'extra_status', null ],
				'Extra',
				[ Divi\Login::class ],
			],
			'Fluent Forms'                         => [
				[ 'fluent_status', 'form' ],
				'fluentform/fluentform.php',
				FluentForm\Form::class,
			],
			'Formidable Forms'                     => [
				[ 'formidable_forms_status', 'form' ],
				'formidable/formidable.php',
				FormidableForms\Form::class,
			],
			'Forminator'                           => [
				[ 'forminator_status', 'form' ],
				'forminator/forminator.php',
				Forminator\Form::class,
			],
			'GiveWP'                               => [
				[ 'give_wp_status', 'form' ],
				'give/give.php',
				GiveWP\Form::class,
			],
			'Gravity Forms'                        => [
				[ 'gravity_status', null ],
				'gravityforms/gravityforms.php',
				[ GravityForms\Form::class, GravityForms\Field::class ],
			],
			'HTML Forms'                           => [
				[ 'html_forms_status', 'form' ],
				'html-forms/html-forms.php',
				HTMLForms\Form::class,
			],
			'Jetpack'                              => [
				[ 'jetpack_status', 'contact' ],
				'jetpack/jetpack.php',
				Jetpack\Form::class,
			],
			'Kadence Form'                         => [
				[ 'kadence_status', 'form' ],
				'kadence-blocks/kadence-blocks.php',
				Kadence\Form::class,
			],
			'Kadence Advanced Form'                => [
				[ 'kadence_status', 'advanced_form' ],
				'kadence-blocks/kadence-blocks.php',
				Kadence\AdvancedForm::class,
			],
			'LearnDash Login Form'                 => [
				[ 'learn_dash_status', 'login' ],
				'sfwd-lms/sfwd_lms.php',
				LearnDash\Login::class,
			],
			'LearnDash Lost Password Form'         => [
				[ 'learn_dash_status', 'lost_pass' ],
				'sfwd-lms/sfwd_lms.php',
				LearnDash\LostPassword::class,
			],
			'LearnDash Register Form'              => [
				[ 'learn_dash_status', 'register' ],
				'sfwd-lms/sfwd_lms.php',
				LearnDash\Register::class,
			],
			'LearnPress Checkout'                  => [
				[ 'learn_press_status', 'checkout' ],
				'learnpress/learnpress.php',
				LearnPress\Checkout::class,
			],
			'LearnPress Login'                     => [
				[ 'learn_press_status', 'login' ],
				'learnpress/learnpress.php',
				LearnPress\Login::class,
			],
			'LearnPress Register'                  => [
				[ 'learn_press_status', 'register' ],
				'learnpress/learnpress.php',
				LearnPress\Register::class,
			],
			'Login/Signup Popup Login Form'        => [
				[ 'login_signup_popup_status', 'login' ],
				'easy-login-woocommerce/xoo-el-main.php',
				LoginSignupPopup\Login::class,
			],
			'Login/Signup Popup Register Form'     => [
				[ 'login_signup_popup_status', 'register' ],
				'easy-login-woocommerce/xoo-el-main.php',
				LoginSignupPopup\Register::class,
			],
			'MailChimp'                            => [
				[ 'mailchimp_status', 'form' ],
				'mailchimp-for-wp/mailchimp-for-wp.php',
				Mailchimp\Form::class,
			],
			'MailPoet'                             => [
				[ 'mailpoet_status', 'form' ],
				'mailpoet/mailpoet.php',
				MailPoet\Form::class,
			],
			'Maintenance Login'                    => [
				[ 'maintenance_status', 'login' ],
				'maintenance/maintenance.php',
				Maintenance\Login::class,
			],
			'MemberPress Login'                    => [
				[ 'memberpress_status', 'login' ],
				'memberpress/memberpress.php',
				MemberPress\Login::class,
			],
			'MemberPress Register'                 => [
				[ 'memberpress_status', 'register' ],
				'memberpress/memberpress.php',
				MemberPress\Register::class,
			],
			'Ninja Forms'                          => [
				[ 'ninja_status', 'form' ],
				'ninja-forms/ninja-forms.php',
				NF::class,
			],
			'Otter Blocks'                         => [
				[ 'otter_status', 'form' ],
				'otter-blocks/otter-blocks.php',
				Otter\Form::class,
			],
			'Paid Memberships Pro Checkout'        => [
				[ 'paid_memberships_pro_status', 'checkout' ],
				'paid-memberships-pro/paid-memberships-pro.php',
				PaidMembershipsPro\Checkout::class,
			],
			'Paid Memberships Pro Login'           => [
				[ 'paid_memberships_pro_status', null ],
				'paid-memberships-pro/paid-memberships-pro.php',
				PaidMembershipsPro\Login::class,
			],
			'Passster Protect'                     => [
				[ 'passster_status', 'protect' ],
				'content-protector/content-protector.php',
				Passster\Protect::class,
			],
			'Profile Builder Login'                => [
				[ 'profile_builder_status', 'login' ],
				'profile-builder/index.php',
				ProfileBuilder\Login::class,
			],
			'Profile Builder Register'             => [
				[ 'profile_builder_status', 'register' ],
				'profile-builder/index.php',
				ProfileBuilder\Register::class,
			],
			'Profile Builder Recover Password'     => [
				[ 'profile_builder_status', 'lost_pass' ],
				'profile-builder/index.php',
				ProfileBuilder\LostPassword::class,
			],
			'Quform'                               => [
				[ 'quform_status', 'form' ],
				'quform/quform.php',
				Quform::class,
			],
			'Sendinblue'                           => [
				[ 'sendinblue_status', 'form' ],
				'mailin/sendinblue.php',
				Sendinblue::class,
			],
			'Simple Basic Contact Form'            => [
				[ 'simple_basic_contact_form_status', 'form' ],
				'simple-basic-contact-form/simple-basic-contact-form.php',
				SimpleBasicContactForm\Form::class,
			],
			'Simple Download Monitor'              => [
				[ 'simple_download_monitor_status', 'form' ],
				'simple-download-monitor/main.php',
				SimpleDownloadMonitor\Form::class,
			],
			'Simple Membership Login'              => [
				[ 'simple_membership_status', 'login' ],
				'simple-membership/simple-wp-membership.php',
				SimpleMembership\Login::class,
			],
			'Simple Membership Register'           => [
				[ 'simple_membership_status', 'register' ],
				'simple-membership/simple-wp-membership.php',
				SimpleMembership\Register::class,
			],
			'Simple Membership Password Reset'     => [
				[ 'simple_membership_status', 'lost_pass' ],
				'simple-membership/simple-wp-membership.php',
				SimpleMembership\LostPassword::class,
			],
			'Spectra'                              => [
				[ 'spectra_status', 'form' ],
				'ultimate-addons-for-gutenberg/ultimate-addons-for-gutenberg.php',
				Spectra\Form::class,
			],
			'Subscriber'                           => [
				[ 'subscriber_status', 'form' ],
				'subscriber/subscriber.php',
				Subscriber\Form::class,
			],
			'Support Candy Form'                   => [
				[ 'supportcandy_status', 'form' ],
				'supportcandy/supportcandy.php',
				SupportCandy\Form::class,
			],
			'Theme My Login Login'                 => [
				[ 'theme_my_login_status', 'login' ],
				'theme-my-login/theme-my-login.php',
				ThemeMyLogin\Login::class,
			],
			'Theme My Login LostPassword'          => [
				[ 'theme_my_login_status', 'lost_pass' ],
				'theme-my-login/theme-my-login.php',
				ThemeMyLogin\LostPassword::class,
			],
			'Theme My Login Register'              => [
				[ 'theme_my_login_status', 'register' ],
				'theme-my-login/theme-my-login.php',
				ThemeMyLogin\Register::class,
			],
			'Tutor Checkout'                       => [
				[ 'tutor_status', 'checkout' ],
				'tutor/tutor.php',
				Tutor\Checkout::class,
			],
			'Tutor LMS Login'                      => [
				[ 'tutor_status', 'login' ],
				'tutor/tutor.php',
				Tutor\Login::class,
			],
			'Tutor LMS LostPassword'               => [
				[ 'tutor_status', 'lost_pass' ],
				'tutor/tutor.php',
				Tutor\LostPassword::class,
			],
			'Tutor LMS Register'                   => [
				[ 'tutor_status', 'register' ],
				'tutor/tutor.php',
				Tutor\Register::class,
			],
			'Ultimate Member Login'                => [
				[ 'ultimate_member_status', 'login' ],
				'ultimate-member/ultimate-member.php',
				UM\Login::class,
			],
			'Ultimate Member LostPassword'         => [
				[ 'ultimate_member_status', 'lost_pass' ],
				'ultimate-member/ultimate-member.php',
				UM\LostPassword::class,
			],
			'Ultimate Member Register'             => [
				[ 'ultimate_member_status', 'register' ],
				'ultimate-member/ultimate-member.php',
				UM\Register::class,
			],
			'UsersWP Forgot Password'              => [
				[ 'users_wp_status', 'forgot' ],
				'userswp/userswp.php',
				UsersWP\ForgotPassword::class,
			],
			'UsersWP Login'                        => [
				[ 'users_wp_status', 'login' ],
				'userswp/userswp.php',
				UsersWP\Login::class,
			],
			'UsersWP Register'                     => [
				[ 'users_wp_status', 'register' ],
				'userswp/userswp.php',
				UsersWP\Register::class,
			],
			'WooCommerce Checkout'                 => [
				[ 'woocommerce_status', 'checkout' ],
				'woocommerce/woocommerce.php',
				WC\Checkout::class,
			],
			'WooCommerce Login'                    => [
				[ 'woocommerce_status', 'login' ],
				'woocommerce/woocommerce.php',
				WC\Login::class,
			],
			'WooCommerce Lost Password'            => [
				[ 'woocommerce_status', 'lost_pass' ],
				'woocommerce/woocommerce.php',
				[ WP\LostPassword::class, WC\LostPassword::class ],
			],
			'WooCommerce Order Tracking'           => [
				[ 'woocommerce_status', 'order_tracking' ],
				'woocommerce/woocommerce.php',
				WC\OrderTracking::class,
			],
			'WooCommerce Register'                 => [
				[ 'woocommerce_status', 'register' ],
				'woocommerce/woocommerce.php',
				WC\Register::class,
			],
			'WooCommerce Wishlists'                => [
				[ 'woocommerce_wishlists_status', 'create_list' ],
				'woocommerce-wishlists/woocommerce-wishlists.php',
				CreateList::class,
			],
			'Wordfence Login'                      => [
				[ 'wordfence_status', null ],
				[ 'wordfence/wordfence.php', 'wordfence-login-security/wordfence-login-security.php' ],
				Wordfence\General::class,
			],
			'WP Job Openings'                      => [
				[ 'wp_job_openings_status', 'form' ],
				'wp-job-openings/wp-job-openings.php',
				WPJobOpenings\Form::class,
			],
			'WPForms'                              => [
				[ 'wpforms_status', null ],
				[ 'wpforms/wpforms.php', 'wpforms-lite/wpforms.php' ],
				WPForms\Form::class,
			],
			'wpDiscuz Comment'                     => [
				[ 'wpdiscuz_status', 'comment_form' ],
				'wpdiscuz/class.WpdiscuzCore.php',
				WPDiscuz\Comment::class,
			],
			'wpDiscuz Subscribe'                   => [
				[ 'wpdiscuz_status', 'subscribe_form' ],
				'wpdiscuz/class.WpdiscuzCore.php',
				WPDiscuz\Subscribe::class,
			],
			'wpForo New Topic'                     => [
				[ 'wpforo_status', 'new_topic' ],
				'wpforo/wpforo.php',
				WPForo\NewTopic::class,
			],
			'wpForo Reply'                         => [
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
			[ $option_name, $option_value ] = $module[0];

			$option = (array) $this->settings()->get( $option_name );

			if ( ! $this->plugin_or_theme_active( $module[1] ) ) {
				$this->settings()->set_field( $option_name, 'disabled', true );
				continue;
			}

			// If plugin/theme is active, load a class having the option_value specified or null.
			if ( $option_value && ! in_array( $option_value, $option, true ) ) {
				continue;
			}

			if ( ! $this->active ) {
				continue;
			}

			foreach ( (array) $module[2] as $component ) {
				if ( ! class_exists( $component, false ) ) {
					$this->loaded_classes[ $component ] = new $component();
				}
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
	public function plugin_or_theme_active( $plugin_or_theme_names ): bool {
		foreach ( (array) $plugin_or_theme_names as $plugin_or_theme_name ) {
			if ( '' === $plugin_or_theme_name ) {
				// WP Core is always active.
				return true;
			}

			if (
				false !== strpos( $plugin_or_theme_name, '.php' ) &&
				$this->is_plugin_active( $plugin_or_theme_name )
			) {
				// The plugin is active.
				return true;
			}

			if (
				false === strpos( $plugin_or_theme_name, '.php' ) &&
				get_template() === $plugin_or_theme_name
			) {
				// The theme is active.
				return true;
			}
		}

		return false;
	}

	/**
	 * Is plugin active.
	 * When network wide activated, check if the plugin is network active.
	 *
	 * @param string $plugin_name Plugin name.
	 *
	 * @return bool
	 */
	public function is_plugin_active( string $plugin_name ): bool {
		if ( is_multisite() ) {
			$tab          = $this->settings->get_tab( Integrations::class );
			$network_wide = $tab && $tab->is_network_wide();

			if ( $network_wide ) {
				return is_plugin_active_for_network( $plugin_name );
			}
		}

		return is_plugin_active( $plugin_name );
	}

	/**
	 * Load plugin text domain.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_default_textdomain();
		load_plugin_textdomain(
			'hcaptcha-for-forms-and-more',
			false,
			dirname( plugin_basename( HCAPTCHA_FILE ) ) . '/languages/'
		);
	}
}
