<?php
/**
 * The Integrations class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Settings;

use HCaptcha\Admin\OnboardingWizard;

use HCaptcha\AntiSpam\AntiSpam;
use HCaptcha\AntiSpam\Honeypot;
use HCaptcha\Dependencies\PluginDependencyManager;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Helpers\Request;
use HCaptcha\Helpers\Utils;
use KAGG\Settings\Abstracts\SettingsBase;
use Plugin_Upgrader;
use Theme_Upgrader;
use WP_Ajax_Upgrader_Skin;
use WP_Error;
use WP_Filesystem_Base;
use WP_Theme;

/**
 * Class Integrations
 *
 * Settings page "Integrations".
 */
class Integrations extends PluginSettingsBase {

	/**
	 * Dialog scripts and style handle.
	 */
	public const DIALOG_HANDLE = 'kagg-dialog';

	/**
	 * Admin script and style handle.
	 */
	public const HANDLE = 'hcaptcha-integrations';

	/**
	 * Script localization object.
	 */
	public const OBJECT = 'HCaptchaIntegrationsObject';

	/**
	 * Activate plugin ajax action.
	 */
	public const ACTIVATE_ACTION = 'hcaptcha-integrations-activate';

	/**
	 * Build activation plan ajax action.
	 */
	public const ACTIVATION_PLAN_ACTION = 'hcaptcha-integrations-activation-plan';

	/**
	 * Build deactivation plan ajax action.
	 */
	public const DEACTIVATION_PLAN_ACTION = 'hcaptcha-integrations-deactivation-plan';

	/**
	 * Header section id.
	 */
	public const SECTION_HEADER = 'header';

	/**
	 * Enabled section id.
	 */
	public const SECTION_ENABLED = 'enabled';

	/**
	 * Disabled section id.
	 */
	public const SECTION_DISABLED = 'disabled';

	/**
	 * Additional plugin dependencies, including fallbacks for missing plugin headers.
	 * Key is a plugin slug.
	 * Value is a plugin slug or an array of slugs.
	 */
	private const PLUGIN_DEPENDENCIES = [
		// phpcs:disable WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned, WordPress.Arrays.MultipleStatementAlignment.LongIndexSpaceBeforeDoubleArrow
		'Avada'                                                             => [
			'fusion-builder/fusion-builder.php',
			'fusion-core/fusion-core.php',
		],
		'blocksy'                                                           => [
			'blocksy-companion-pro/blocksy-companion.php',
			'blocksy-companion/blocksy-companion.php',
		],
		'acf-extended-pro/acf-extended.php'                                 => 'advanced-custom-fields-pro/acf.php',
		'back-in-stock-notifier-for-woocommerce/cwginstocknotifier.php'     => 'woocommerce/woocommerce.php',
		'customer-reviews-woocommerce/ivole.php'                            => 'woocommerce/woocommerce.php',
		'elementor-pro/elementor-pro.php'                                   => 'elementor/elementor.php',
		'essential-addons-elementor/essential_adons_elementor.php'          => 'elementor/elementor.php',
		'essential-addons-for-elementor-lite/essential_adons_elementor.php' => 'elementor/elementor.php',
		'fluentformpro/fluentformpro.php'                                   => 'fluentform/fluentform.php',
		'metform/metform.php'                                               => 'elementor/elementor.php',
		'sfwd-lms/sfwd_lms.php'                                             => 'learndash-hub/learndash-hub.php',
		'tutor-pro/tutor-pro.php'                                           => 'tutor/tutor.php',
		'ultimate-elementor/ultimate-elementor.php'                         => 'elementor/elementor.php',
		'woocommerce-germanized/woocommerce-germanized.php'                 => 'woocommerce/woocommerce.php',
		'woocommerce-paypal-payments/woocommerce-paypal-payments.php'       => 'woocommerce/woocommerce.php',
		'woocommerce-wishlists/woocommerce-wishlists.php'                   => 'woocommerce/woocommerce.php',
		// phpcs:enable WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned, WordPress.Arrays.MultipleStatementAlignment.LongIndexSpaceBeforeDoubleArrow
	];

	/**
	 * Install a plugin or theme.
	 *
	 * @var mixed
	 */
	protected $install;

	/**
	 * Entity name to install/activate/deactivate. Can be 'plugin' or 'theme'.
	 *
	 * @var string
	 */
	protected string $entity = '';

	/**
	 * Plugin trees.
	 *
	 * @var array
	 */
	protected array $plugin_trees = [];

	/**
	 * Installed plugins.
	 *
	 * @var array[]
	 */
	protected array $plugins = [];

	/**
	 * Installed themes.
	 *
	 * @var WP_Theme[]
	 */
	protected array $themes = [];

	/**
	 * All protected forms.
	 *
	 * @var array
	 */
	protected array $all_protected_forms = [];

	/**
	 * Get page title.
	 *
	 * @return string
	 */
	protected function page_title(): string {
		return __( 'Integrations', 'hcaptcha-for-forms-and-more' );
	}

	/**
	 * Get section title.
	 *
	 * @return string
	 */
	protected function section_title(): string {
		return 'integrations';
	}

	/**
	 * Init class.
	 *
	 * @return void
	 */
	public function init(): void {
		if ( ! function_exists( 'get_plugins' ) ) {
			// @codeCoverageIgnoreStart
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
			// @codeCoverageIgnoreEnd
		}

		$this->plugins = get_plugins();
		$this->themes  = wp_get_themes();

		new OnboardingWizard( $this );

		parent::init();
	}

	/**
	 * Init class hooks.
	 */
	protected function init_hooks(): void {
		parent::init_hooks();

		add_action( 'kagg_settings_header', [ $this, 'search_box' ] );
		add_action( 'wp_ajax_' . self::ACTIVATE_ACTION, [ $this, 'activate' ] );
		add_action( 'wp_ajax_' . self::ACTIVATION_PLAN_ACTION, [ $this, 'activation_plan' ] );
		add_action( 'wp_ajax_' . self::DEACTIVATION_PLAN_ACTION, [ $this, 'deactivation_plan' ] );
		add_action( 'after_switch_theme', [ $this, 'after_switch_theme_action' ], 0 );
		add_filter( 'hcaptcha_activate_plugins', [ $this, 'filter_activate_plugins' ], 0 );
	}

	/**
	 * After switch theme action.
	 * Do not allow redirect during Avada and Divi theme activation.
	 *
	 * @return void
	 */
	public function after_switch_theme_action(): void {
		if ( ! wp_doing_ajax() ) {
			return;
		}

		$action = Request::filter_input( INPUT_POST, 'action' );

		// Do not run checks when the Playground action is triggered.
		if ( self::ACTIVATE_ACTION === $action ) {
			$this->run_checks( self::ACTIVATE_ACTION );
		}

		// Do not allow redirect during Divi theme activation.
		remove_action( 'after_switch_theme', 'et_onboarding_trigger_redirect' );
		remove_action( 'after_switch_theme', 'avada_compat_switch_theme' );
		Utils::instance()->remove_action_regex( '/^Avada/', 'after_switch_theme' );
	}

	/**
	 * Filter the list of plugin to activate.
	 * Proceed with the special case for blocksy companion plugins.
	 * Companion plugins produce a fatal error when activated together.
	 *
	 * @param array|mixed $plugins    List of plugins.
	 * @param bool        $first_only Activate the first available plugin only.
	 *
	 * @return array
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function filter_activate_plugins( $plugins, bool $first_only = true ): array {
		$plugins = (array) $plugins;

		$companions = [
			'blocksy-companion-pro/blocksy-companion.php',
			'blocksy-companion/blocksy-companion.php',
		];

		if ( ! array_intersect( $plugins, $companions ) ) {
			return $plugins;
		}

		// Remove Companion plugins from the list to activate.
		$updated_plugins = array_diff( $plugins, $companions );

		foreach ( $companions as $companion ) {
			if ( hcaptcha()->is_plugin_active( $companion ) ) {
				// Do not activate Companion plugins if at least one of them is already active.
				return $updated_plugins;
			}
		}

		$installed_plugins = array_keys( $this->plugins );

		foreach ( $companions as $companion ) {
			if ( in_array( $companion, $installed_plugins, true ) ) {
				// Activate the first Companion plugin available.
				$updated_plugins[] = $companion;

				return $updated_plugins;
			}
		}

		return $plugins;
	}

	/**
	 * Init form fields.
	 *
	 * @return void
	 */
	public function init_form_fields(): void {
		$this->form_fields = [
			'show_antispam_coverage'         => [
				'type'    => 'checkbox',
				'section' => self::SECTION_HEADER,
				'options' => [
					'on' => __( 'Show Anti-Spam Indicators', 'hcaptcha-for-forms-and-more' ),
				],
				'helper'  => __( 'Shows icons for built-in antispam methods (Honeypot, Time check) for supported integrations, including inactive ones.', 'hcaptcha-for-forms-and-more' ),
			],
			'wp_status'                      => [
				'entity'  => 'core',
				'label'   => 'WP Core',
				'type'    => 'checkbox',
				'options' => [
					'comment'            => __( 'Comment Form', 'hcaptcha-for-forms-and-more' ),
					'login'              => __( 'Login Form', 'hcaptcha-for-forms-and-more' ),
					'lost_pass'          => __( 'Lost Password Form', 'hcaptcha-for-forms-and-more' ),
					'password_protected' => __( 'Post/Page Password Form', 'hcaptcha-for-forms-and-more' ),
					'register'           => __( 'Register Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'acfe_status'                    => [
				'label'   => 'ACF Extended',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'ACF Extended Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'affiliates_status'              => [
				'label'   => 'Affiliates',
				'type'    => 'checkbox',
				'options' => [
					'login'    => __( 'Affiliates Login Form', 'hcaptcha-for-forms-and-more' ),
					'register' => __( 'Affiliates Register Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'asgaros_status'                 => [
				'label'   => 'Asgaros',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'avada_status'                   => [
				'entity'  => 'theme',
				'label'   => 'Avada',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Avada Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'back_in_stock_notifier_status'  => [
				'label'   => 'Back In Stock Notifier',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Back In Stock Notifier Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'bbp_status'                     => [
				'label'   => 'bbPress',
				'type'    => 'checkbox',
				'options' => [
					'login'     => __( 'Login Form', 'hcaptcha-for-forms-and-more' ),
					'lost_pass' => __( 'Lost Password Form', 'hcaptcha-for-forms-and-more' ),
					'new_topic' => __( 'New Topic Form', 'hcaptcha-for-forms-and-more' ),
					'register'  => __( 'Register Form', 'hcaptcha-for-forms-and-more' ),
					'reply'     => __( 'Reply Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'beaver_builder_status'          => [
				'label'   => 'Beaver Builder',
				'logo'    => 'svg',
				'type'    => 'checkbox',
				'options' => [
					'contact' => __( 'Contact Form', 'hcaptcha-for-forms-and-more' ),
					'login'   => __( 'Login Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'blocksy_status'                 => [
				'label'   => 'blocksy',
				'entity'  => 'theme',
				'logo'    => 'svg',
				'type'    => 'checkbox',
				'options' => [
					'newsletter_subscribe' => __( 'Newsletter Subscribe (Free)', 'hcaptcha-for-forms-and-more' ),
					'product_review'       => __( 'Product Review (Pro)', 'hcaptcha-for-forms-and-more' ),
					'waitlist'             => __( 'Waitlist Form (Pro)', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'brizy_status'                   => [
				'label'   => 'Brizy',
				'logo'    => 'svg',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'bp_status'                      => [
				'label'   => 'BuddyPress',
				'logo'    => 'svg',
				'type'    => 'checkbox',
				'options' => [
					'create_group' => __( 'Create Group Form', 'hcaptcha-for-forms-and-more' ),
					'registration' => __( 'Register Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'classified_listing_status'      => [
				'label'   => 'Classified Listing',
				'type'    => 'checkbox',
				'options' => [
					'contact'   => __( 'Contact Form', 'hcaptcha-for-forms-and-more' ),
					'login'     => __( 'Login Form', 'hcaptcha-for-forms-and-more' ),
					'lost_pass' => __( 'Lost Password Form', 'hcaptcha-for-forms-and-more' ),
					'register'  => __( 'Register Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'coblocks_status'                => [
				'label'   => 'CoBlocks',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'colorlib_customizer_status'     => [
				'label'   => 'Colorlib Login Customizer',
				'type'    => 'checkbox',
				'options' => [
					'login'     => __( 'Login Form', 'hcaptcha-for-forms-and-more' ),
					'lost_pass' => __( 'Lost Password Form', 'hcaptcha-for-forms-and-more' ),
					'register'  => __( 'Register Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'cf7_status'                     => [
				'label'   => 'Contact Form 7',
				'logo'    => 'svg',
				'type'    => 'checkbox',
				'options' => [
					'form'        => __( 'Form Auto-Add', 'hcaptcha-for-forms-and-more' ),
					'embed'       => __( 'Form Embed', 'hcaptcha-for-forms-and-more' ),
					'live'        => __( 'Live Form in Admin', 'hcaptcha-for-forms-and-more' ),
					'replace_rsc' => __( 'Replace Really Simple CAPTCHA', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'customer_reviews_status'        => [
				'label'   => 'Customer Reviews',
				'logo'    => 'svg',
				'type'    => 'checkbox',
				'options' => [
					'q&a'    => __( 'Q&A Form', 'hcaptcha-for-forms-and-more' ),
					'review' => __( 'Review Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'divi_status'                    => [
				'entity'  => 'theme',
				'label'   => 'Divi',
				'type'    => 'checkbox',
				'options' => [
					'comment'     => __( 'Divi Comment Form', 'hcaptcha-for-forms-and-more' ),
					'contact'     => __( 'Divi Contact Form', 'hcaptcha-for-forms-and-more' ),
					'email_optin' => __( 'Divi Email Optin Form', 'hcaptcha-for-forms-and-more' ),
					'login'       => __( 'Divi Login Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'divi_builder_status'            => [
				'label'   => 'Divi Builder',
				'type'    => 'checkbox',
				'options' => [
					'comment'     => __( 'Divi Builder Comment Form', 'hcaptcha-for-forms-and-more' ),
					'contact'     => __( 'Divi Builder Contact Form', 'hcaptcha-for-forms-and-more' ),
					'email_optin' => __( 'Divi Builder Email Optin Form', 'hcaptcha-for-forms-and-more' ),
					'login'       => __( 'Divi Builder Login Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'download_manager_status'        => [
				'label'   => 'Download Manager',
				'type'    => 'checkbox',
				'options' => [
					'button' => __( 'Button', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'easy_digital_downloads_status'  => [
				'label'   => 'Easy Digital Downloads',
				'logo'    => 'svg',
				'type'    => 'checkbox',
				'options' => [
					'checkout'  => __( 'Checkout Form', 'hcaptcha-for-forms-and-more' ),
					'login'     => __( 'Login Form', 'hcaptcha-for-forms-and-more' ),
					'lost_pass' => __( 'Lost Password Form', 'hcaptcha-for-forms-and-more' ),
					'register'  => __( 'Register Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'elementor_pro_status'           => [
				'label'   => 'Elementor Pro',
				'logo'    => 'svg',
				'type'    => 'checkbox',
				'options' => [
					'form'  => __( 'Form', 'hcaptcha-for-forms-and-more' ),
					'login' => __( 'Login', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'essential_addons_status'        => [
				'label'   => 'Essential Addons',
				'type'    => 'checkbox',
				'options' => [
					'login'    => __( 'Login', 'hcaptcha-for-forms-and-more' ),
					'register' => __( 'Register', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'essential_blocks_status'        => [
				'label'   => 'Essential Blocks',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'events_manager_status'          => [
				'label'   => 'Events Manager',
				'logo'    => 'svg',
				'type'    => 'checkbox',
				'options' => [
					'booking' => __( 'Booking', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'extra_status'                   => [
				'entity'  => 'theme',
				'label'   => 'Extra',
				'logo'    => 'svg',
				'type'    => 'checkbox',
				'options' => [
					'comment'     => __( 'Extra Comment Form', 'hcaptcha-for-forms-and-more' ),
					'contact'     => __( 'Extra Contact Form', 'hcaptcha-for-forms-and-more' ),
					'email_optin' => __( 'Extra Email Optin Form', 'hcaptcha-for-forms-and-more' ),
					'login'       => __( 'Extra Login Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'fluent_status'                  => [
				'label'   => 'Fluent Forms',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'formidable_forms_status'        => [
				'label'   => 'Formidable Forms',
				'logo'    => 'svg',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'forminator_status'              => [
				'label'   => 'Forminator',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'give_wp_status'                 => [
				'label'   => 'GiveWP',
				'logo'    => 'svg',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'gravity_status'                 => [
				'label'   => 'Gravity Forms',
				'logo'    => 'svg',
				'type'    => 'checkbox',
				'options' => [
					'form'  => __( 'Form Auto-Add', 'hcaptcha-for-forms-and-more' ),
					'embed' => __( 'Form Embed', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'html_forms_status'              => [
				'label'   => 'HTML Forms',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'icegram_express_status'         => [
				'label'   => 'Icegram Express',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'jetpack_status'                 => [
				'label'   => 'Jetpack',
				'logo'    => 'svg',
				'type'    => 'checkbox',
				'options' => [
					'contact' => __( 'Contact Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'kadence_status'                 => [
				'label'   => 'Kadence',
				'logo'    => 'svg',
				'type'    => 'checkbox',
				'options' => [
					'form'          => __( 'Kadence Form', 'hcaptcha-for-forms-and-more' ),
					'advanced_form' => __( 'Kadence Advanced Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'learn_dash_status'              => [
				'label'   => 'LearnDash LMS',
				'logo'    => 'svg',
				'type'    => 'checkbox',
				'options' => [
					'login'     => __( 'Login Form', 'hcaptcha-for-forms-and-more' ),
					'lost_pass' => __( 'Lost Password Form', 'hcaptcha-for-forms-and-more' ),
					'register'  => __( 'Register Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'learn_press_status'             => [
				'label'   => 'LearnPress',
				'type'    => 'checkbox',
				'options' => [
					'checkout' => __( 'Checkout Form', 'hcaptcha-for-forms-and-more' ),
					'login'    => __( 'Login Form', 'hcaptcha-for-forms-and-more' ),
					'register' => __( 'Register Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'login_signup_popup_status'      => [
				'label'   => 'Login Signup Popup',
				'type'    => 'checkbox',
				'options' => [
					'login'    => __( 'Login Form', 'hcaptcha-for-forms-and-more' ),
					'register' => __( 'Register Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'mailchimp_status'               => [
				'label'   => 'Mailchimp for WP',
				'logo'    => 'svg',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'mailpoet_status'                => [
				'label'   => 'MailPoet',
				'logo'    => 'svg',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'maintenance_status'             => [
				'label'   => 'Maintenance',
				'type'    => 'checkbox',
				'options' => [
					'login' => __( 'Login Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'memberpress_status'             => [
				'label'   => 'MemberPress',
				'logo'    => 'svg',
				'type'    => 'checkbox',
				'options' => [
					'login'    => __( 'Login Form', 'hcaptcha-for-forms-and-more' ),
					'register' => __( 'Register Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'metform_status'                 => [
				'label'   => 'MetForm',
				'logo'    => 'svg',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'ninja_status'                   => [
				'label'   => 'Ninja Forms',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'otter_status'                   => [
				'label'   => 'Otter Blocks',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'passster_status'                => [
				'label'   => 'Passster',
				'type'    => 'checkbox',
				'options' => [
					'protect' => __( 'Protection Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'password_protected_status'      => [
				'label'   => 'Password Protected',
				'type'    => 'checkbox',
				'options' => [
					'protect' => __( 'Protection Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'profile_builder_status'         => [
				'label'   => 'Profile Builder',
				'type'    => 'checkbox',
				'options' => [
					'login'     => __( 'Login Form', 'hcaptcha-for-forms-and-more' ),
					'lost_pass' => __( 'Recover Password Form', 'hcaptcha-for-forms-and-more' ),
					'register'  => __( 'Register Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'quform_status'                  => [
				'label'   => 'Quform',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'sendinblue_status'              => [
				'label'   => 'Brevo',
				'logo'    => 'svg',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'simple_download_monitor_status' => [
				'label'   => 'Simple Download Monitor',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'simple_membership_status'       => [
				'label'   => 'Simple Membership',
				'type'    => 'checkbox',
				'options' => [
					'login'     => __( 'Login Form', 'hcaptcha-for-forms-and-more' ),
					'register'  => __( 'Register Form', 'hcaptcha-for-forms-and-more' ),
					'lost_pass' => __( 'Password Reset Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'spectra_status'                 => [
				'label'   => 'Spectra',
				'logo'    => 'svg',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'subscriber_status'              => [
				'label'   => 'Subscriber',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'supportcandy_status'            => [
				'label'   => 'Support Candy',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'theme_my_login_status'          => [
				'label'   => 'Theme My Login',
				'type'    => 'checkbox',
				'options' => [
					'login'     => __( 'Login Form', 'hcaptcha-for-forms-and-more' ),
					'lost_pass' => __( 'Lost Password Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'tutor_status'                   => [
				'label'   => 'Tutor LMS',
				'logo'    => 'svg',
				'type'    => 'checkbox',
				'options' => [
					'checkout'  => __( 'Checkout Form', 'hcaptcha-for-forms-and-more' ),
					'login'     => __( 'Login Form', 'hcaptcha-for-forms-and-more' ),
					'lost_pass' => __( 'Lost Password Form', 'hcaptcha-for-forms-and-more' ),
					'register'  => __( 'Register Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'ultimate_addons_status'         => [
				'label'   => 'Ultimate Addons',
				'logo'    => 'svg',
				'type'    => 'checkbox',
				'options' => [
					'login'    => __( 'Login Form', 'hcaptcha-for-forms-and-more' ),
					'register' => __( 'Register Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'ultimate_member_status'         => [
				'label'   => 'Ultimate Member',
				'type'    => 'checkbox',
				'options' => [
					'login'     => __( 'Login Form', 'hcaptcha-for-forms-and-more' ),
					'lost_pass' => __( 'Lost Password Form', 'hcaptcha-for-forms-and-more' ),
					'register'  => __( 'Register Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'users_wp_status'                => [
				'label'   => 'Users WP',
				'type'    => 'checkbox',
				'options' => [
					'forgot'   => __( 'Forgot Password Form', 'hcaptcha-for-forms-and-more' ),
					'login'    => __( 'Login Form', 'hcaptcha-for-forms-and-more' ),
					'register' => __( 'Register Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'woocommerce_status'             => [
				'label'   => 'WooCommerce',
				'type'    => 'checkbox',
				'options' => [
					'add_payment_method' => __( 'Add Payment Method Form', 'hcaptcha-for-forms-and-more' ),
					'checkout'           => __( 'Checkout Form', 'hcaptcha-for-forms-and-more' ),
					'login'              => __( 'Login Form', 'hcaptcha-for-forms-and-more' ),
					'lost_pass'          => __( 'Lost Password Form', 'hcaptcha-for-forms-and-more' ),
					'order_tracking'     => __( 'Order Tracking Form', 'hcaptcha-for-forms-and-more' ),
					'order_withdrawal'   => __( 'Order Withdrawal Form', 'hcaptcha-for-forms-and-more' ),
					'register'           => __( 'Register Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'woocommerce_germanized_status'  => [
				'label'   => 'WooCommerce Germanized',
				'type'    => 'checkbox',
				'options' => [
					'return_request' => __( 'Return Request Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'paypal_payments_status'         => [
				'label'   => 'WooCommerce PayPal Payments',
				'type'    => 'checkbox',
				'options' => [
					'button' => __( 'PayPal Button', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'woocommerce_wishlists_status'   => [
				'label'   => 'WooCommerce Wishlists',
				'type'    => 'checkbox',
				'options' => [
					'create_list' => __( 'Create List Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'wordfence_status'               => [
				'label'   => 'Wordfence',
				'logo'    => 'svg',
				'type'    => 'checkbox',
				'options' => [
					'login' => __( 'Login Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'wpforms_status'                 => [
				'label'   => 'WPForms',
				'type'    => 'checkbox',
				'options' => [
					'form'  => __( 'Form Auto-Add', 'hcaptcha-for-forms-and-more' ),
					'embed' => __( 'Form Embed', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'wpdiscuz_status'                => [
				'label'   => 'WPDiscuz',
				'type'    => 'checkbox',
				'options' => [
					'comment_form'   => __( 'Comment Form', 'hcaptcha-for-forms-and-more' ),
					'subscribe_form' => __( 'Subscribe Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'wpforo_status'                  => [
				'label'   => 'WPForo',
				'type'    => 'checkbox',
				'options' => [
					'new_topic' => __( 'New Topic Form', 'hcaptcha-for-forms-and-more' ),
					'reply'     => __( 'Reply Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'wp_job_openings_status'         => [
				'label'   => 'WP Job Openings',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
		];

		if ( is_multisite() ) {
			$this->form_fields['wp_status']['options']['signup']             = __( 'Signup Form', 'hcaptcha-for-forms-and-more' );
			$this->form_fields['theme_my_login_status']['options']['signup'] = __( 'Signup Form', 'hcaptcha-for-forms-and-more' );
		} else {
			$this->form_fields['theme_my_login_status']['options']['register'] = __( 'Register Form', 'hcaptcha-for-forms-and-more' );
		}
	}

	/**
	 * Get form fields for the command palette.
	 *
	 * @return array
	 */
	public function command_palette_form_fields(): array {
		$form_fields = $this->form_fields();

		return [ 'show_antispam_coverage' => $form_fields['show_antispam_coverage'] ];
	}

	/**
	 * Get form fields.
	 *
	 * @return array
	 */
	public function get_form_fields(): array {
		return $this->form_fields;
	}

	/**
	 * Get logo image.
	 *
	 * @param array $form_field Label.
	 *
	 * @return string
	 * @noinspection HtmlUnknownTarget
	 */
	private function logo( array $form_field ): string {
		$label     = $form_field['label'];
		$logo_type = $form_field['logo'] ?? 'png';
		$logo_file = sanitize_file_name( strtolower( $label ) . '.' . $logo_type );
		$entity    = $form_field['entity'] ?? 'plugin';

		$logo = sprintf(
			'<div class="hcaptcha-integrations-logo" data-installed="%1$s">' .
			'<img src="%2$s" alt="%3$s Logo" data-label="%3$s" data-entity="%4$s">' .
			'</div>',
			$form_field['installed'] ? 'true' : 'false',
			esc_url( constant( 'HCAPTCHA_URL' ) . "/assets/images/logo/$logo_file" ),
			$label,
			$entity
		);

		if ( 'theme' === $entity ) {
			$logo .= sprintf(
				'<div class="hcaptcha-integrations-entity">%1$s</div>',
				$entity
			);
		}

		return $logo;
	}

	/**
	 * Setup settings fields.
	 */
	public function setup_fields(): void {
		if ( ! $this->is_options_screen() ) {
			return;
		}

		$installed = $this->get_installed_entities();

		$this->setup_antispam_data( $installed );
		$this->setup_field_data( $installed );

		parent::setup_fields();
	}

	/**
	 * Show the settings page.
	 *
	 * @return void
	 */
	public function settings_page(): void {
		global $wp_settings_sections;

		if ( ! $this->is_any_antispam_enabled() ) {
			$page   = $this->get_active_tab()->option_page();
			$output = '<p>' . __( 'Enable Honeypot or Time check in the Anti-Spam tab to use indicators.', 'hcaptcha-for-forms-and-more' ) . '</p>';

			// Add output after the section.
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$wp_settings_sections[ $page ][ self::SECTION_HEADER ]['after_section'] = $output;
		}

		parent::settings_page();
	}

	/**
	 * Check whether one of the plugins or themes is installed.
	 *
	 * @param string|array $plugin_or_theme_names Plugin or theme names.
	 *
	 * @return bool
	 */
	protected function plugin_or_theme_installed( $plugin_or_theme_names ): bool {
		foreach ( (array) $plugin_or_theme_names as $plugin_or_theme_name ) {
			if ( '' === $plugin_or_theme_name ) {
				// WP Core is always installed.
				return true;
			}

			if (
				array_key_exists( $plugin_or_theme_name, $this->plugins ) &&
				false !== strpos( $plugin_or_theme_name, '.php' )
			) {
				// The plugin is installed.
				return true;
			}

			if (
				array_key_exists( $plugin_or_theme_name, $this->themes ) &&
				false === strpos( $plugin_or_theme_name, '.php' )
			) {
				// The theme is installed.
				return true;
			}
		}

		return false;
	}

	/**
	 * Sort fields. First, by enabled status, then by label.
	 *
	 * @param array $fields Fields.
	 *
	 * @return array
	 */
	public function sort_fields( array $fields ): array {
		uasort(
			$fields,
			static function ( $a, $b ) {
				$a_header = self::SECTION_HEADER === ( $a['section'] ?? '' );
				$b_header = self::SECTION_HEADER === ( $b['section'] ?? '' );

				if ( $a_header !== $b_header ) {
					return $b_header <=> $a_header;
				}

				$a_disabled = $a['disabled'] ?? false;
				$b_disabled = $b['disabled'] ?? false;

				$a_label = strtolower( $a['label'] ?? '' );
				$b_label = strtolower( $b['label'] ?? '' );

				if ( $a_disabled === $b_disabled ) {
					return $a_label <=> $b_label;
				}

				if ( ! $a_disabled && $b_disabled ) {
					return -1;
				}

				return 1;
			}
		);

		return $fields;
	}

	/**
	 * Show the search box.
	 *
	 * @return void
	 */
	public function search_box(): void {
		?>
		<div id="hcaptcha-integrations-search-wrap">
			<label for="hcaptcha-integrations-search"></label>
			<input
					type="search" id="hcaptcha-integrations-search"
					placeholder="<?php esc_html_e( 'Search integrations...', 'hcaptcha-for-forms-and-more' ); ?>">
		</div>
		<?php
	}

	/**
	 * Section callback.
	 *
	 * @param array $arguments Section arguments.
	 *
	 * @return void
	 * @noinspection HtmlUnknownTarget
	 */
	public function section_callback( array $arguments ): void {
		switch ( $arguments['id'] ) {
			case self::SECTION_HEADER:
				$this->print_header();

				?>
				<p>
					<?php esc_html_e( 'Manage integrations with popular plugins and themes such as Contact Form 7, Elementor Pro, WPForms, and more.', 'hcaptcha-for-forms-and-more' ); ?>
				</p>
				<p>
					<?php esc_html_e( 'You can activate and deactivate a plugin or theme by clicking on its logo.', 'hcaptcha-for-forms-and-more' ); ?>
				</p>
				<p>
					<?php
					$shortcode_url   = 'https://wordpress.org/plugins/hcaptcha-for-forms-and-more/#does%20the%20%5Bhcaptcha%5D%20shortcode%20have%20arguments%3F';
					$integration_url = 'https://github.com/hCaptcha/hcaptcha-wordpress-plugin/issues';

					echo wp_kses_post(
						sprintf(
						/* translators: 1: hCaptcha shortcode doc link, 2: integration doc link. */
							__( 'Don\'t see your plugin or theme here? Use the `[hcaptcha]` %1$s or %2$s.', 'hcaptcha-for-forms-and-more' ),
							sprintf(
								'<a href="%1$s" target="_blank">%2$s</a>',
								$shortcode_url,
								__( 'shortcode', 'hcaptcha-for-forms-and-more' )
							),
							sprintf(
								'<a href="%1$s" target="_blank">%2$s</a>',
								$integration_url,
								__( 'request an integration', 'hcaptcha-for-forms-and-more' )
							)
						)
					);
					?>
				</p>
				<?php

				break;
			case self::SECTION_ENABLED:
				$style = $this->is_any_antispam_enabled() && hcaptcha()->settings()->is_on( 'show_antispam_coverage' )
					? 'display: block;'
					: 'display: none;';

				?>
				<div id="hcaptcha-antispam-legend" class="hcaptcha-antispam-legend" style="<?php echo esc_attr( $style ); ?>">
					<?php
					echo wp_kses_post(
						sprintf(
							/* translators: 1: Honeypot icon, 2: Time check icon. */
							__( 'Antispam indicators: %1$s Honeypot &middot; %2$s Time check', 'hcaptcha-for-forms-and-more' ),
							'<i class="antispam-honeypot"></i>',
							'<i class="antispam-fst"></i>'
						)
					);
					?>
				</div>
				<hr class="hcaptcha-enabled-section">
				<h3><?php esc_html_e( 'Active plugins and themes', 'hcaptcha-for-forms-and-more' ); ?></h3>
				<?php

				break;
			case self::SECTION_DISABLED:
				$this->submit_button();

				?>
				<hr class="hcaptcha-disabled-section">
				<h3><?php esc_html_e( 'Inactive plugins and themes', 'hcaptcha-for-forms-and-more' ); ?></h3>
				<?php
				break;
		}
	}

	/**
	 * Enqueue class scripts.
	 *
	 * @return void
	 */
	public function admin_enqueue_scripts(): void {
		wp_enqueue_script(
			self::DIALOG_HANDLE,
			constant( 'HCAPTCHA_URL' ) . "/assets/js/kagg-dialog$this->min_suffix.js",
			[],
			constant( 'HCAPTCHA_VERSION' ),
			true
		);

		wp_enqueue_style(
			self::DIALOG_HANDLE,
			constant( 'HCAPTCHA_URL' ) . "/assets/css/kagg-dialog$this->min_suffix.css",
			[],
			constant( 'HCAPTCHA_VERSION' )
		);

		wp_enqueue_script(
			self::HANDLE,
			constant( 'HCAPTCHA_URL' ) . "/assets/js/integrations$this->min_suffix.js",
			[ 'jquery', self::DIALOG_HANDLE ],
			constant( 'HCAPTCHA_VERSION' ),
			true
		);

		$nonce            = Request::filter_input( INPUT_GET, 'nonce' );
		$suggest_activate = wp_verify_nonce( $nonce, self::ACTIVATE_ACTION )
			? Request::filter_input( INPUT_GET, 'suggest_activate' )
			: '';

		if ( $suggest_activate ) {
			$source = HCaptcha::get_status_source( $suggest_activate );

			if ( hcaptcha()->plugin_or_theme_active( $source ) ) {
				$suggest_activate = '';
			}
		}

		wp_localize_script(
			self::HANDLE,
			self::OBJECT,
			[
				'ajaxUrl'              => admin_url( 'admin-ajax.php' ),
				'action'               => self::ACTIVATE_ACTION,
				'nonce'                => wp_create_nonce( self::ACTIVATE_ACTION ),
				'activationPlanAction' => self::ACTIVATION_PLAN_ACTION,
				'activationPlanNonce'  => wp_create_nonce( self::ACTIVATION_PLAN_ACTION ),
				'planAction'           => self::DEACTIVATION_PLAN_ACTION,
				'planNonce'            => wp_create_nonce( self::DEACTIVATION_PLAN_ACTION ),
				/* translators: 1: Plugin name. */
				'installPluginMsg'     => __( 'Install and activate %s plugin?', 'hcaptcha-for-forms-and-more' ),
				/* translators: 1: Theme name. */
				'installThemeMsg'      => __( 'Install and activate %s theme?', 'hcaptcha-for-forms-and-more' ),
				/* translators: 1: Plugin name. */
				'activatePluginMsg'    => __( 'Activate %s plugin?', 'hcaptcha-for-forms-and-more' ),
				/* translators: 1: Plugin name. */
				'deactivatePluginMsg'  => __( 'Deactivate %s plugin?', 'hcaptcha-for-forms-and-more' ),
				/* translators: 1: Theme name. */
				'activateThemeMsg'     => __( 'Activate %s theme?', 'hcaptcha-for-forms-and-more' ),
				/* translators: 1: Theme name. */
				'deactivateThemeMsg'   => __( 'Deactivate %s theme?', 'hcaptcha-for-forms-and-more' ),
				'selectThemeMsg'       => __( 'Select theme to activate:', 'hcaptcha-for-forms-and-more' ),
				'onlyOneThemeMsg'      => __( 'Cannot deactivate the only theme on the site.', 'hcaptcha-for-forms-and-more' ),
				'dependenciesMsg'      => __( 'Also deactivate dependencies:', 'hcaptcha-for-forms-and-more' ),
				'deactivateAllMsg'     => __( 'Deactivate all', 'hcaptcha-for-forms-and-more' ),
				'loadingDepsMsg'       => __( 'Checking dependencies…', 'hcaptcha-for-forms-and-more' ),
				'suggestActivate'      => $suggest_activate,
				'suggestActivateMsg'   => __( 'Activate plugin or theme by clicking on its logo.', 'hcaptcha-for-forms-and-more' ),
				'unexpectedErrorMsg'   => __( 'Unexpected error.', 'hcaptcha-for-forms-and-more' ),
				'OKBtnText'            => __( 'OK', 'hcaptcha-for-forms-and-more' ),
				'CancelBtnText'        => __( 'Cancel', 'hcaptcha-for-forms-and-more' ),
				'themes'               => $this->get_themes(),
				'defaultTheme'         => $this->get_default_theme(),
			]
		);

		wp_enqueue_style(
			self::HANDLE,
			constant( 'HCAPTCHA_URL' ) . "/assets/css/integrations$this->min_suffix.css",
			[ static::PREFIX . '-' . SettingsBase::HANDLE, self::DIALOG_HANDLE ],
			constant( 'HCAPTCHA_VERSION' )
		);
	}

	/**
	 * Check if any antispam method (Honeypot or Time check) is enabled.
	 *
	 * @return bool
	 */
	private function is_any_antispam_enabled(): bool {
		$settings = hcaptcha()->settings();

		return $settings->is_on( 'honeypot' ) || $settings->is_on( 'set_min_submit_time' );
	}

	/**
	 * Ajax action to describe dependencies activated with a plugin or theme.
	 *
	 * @return void
	 * @noinspection PhpUnreachableStatementInspection
	 */
	public function activation_plan(): void {
		$this->run_checks( self::ACTIVATION_PLAN_ACTION );

		$this->entity = (string) Request::filter_input( INPUT_POST, 'entity' );
		$status       = str_replace( '-', '_', (string) Request::filter_input( INPUT_POST, 'status' ) );
		$entity_name  = $this->form_fields[ $status ]['label'] ?? '';

		if ( ! in_array( $this->entity, [ 'plugin', 'theme' ], true ) ) {
			wp_send_json_error(
				esc_html__( 'Unsupported integration entity.', 'hcaptcha-for-forms-and-more' )
			);

			return; // For testing purposes.
		}

		$permission_error = $this->get_activation_error( true, $entity_name, '' );

		if ( null !== $permission_error ) {
			wp_send_json_error( esc_html( $permission_error->get_error_message() ) );

			return; // For testing purposes.
		}

		$plan         = $this->build_activation_plan( $status, $entity_name );
		$plugin_names = array_column( $plan['items'], 'name' );
		$notice       = '';

		if ( $plugin_names ) {
			$notice = sprintf(
				/* translators: %s: comma-separated list of plugin names. */
				__( '%s will also be activated.', 'hcaptcha-for-forms-and-more' ),
				implode( ', ', $plugin_names )
			);
		}

		wp_send_json_success( [ 'notice' => esc_html( $notice ) ] );
	}

	/**
	 * Ajax action to build a plugin dependency deactivation plan.
	 *
	 * @return void
	 * @noinspection PhpUnreachableStatementInspection
	 */
	public function deactivation_plan(): void {
		$this->run_checks( self::DEACTIVATION_PLAN_ACTION );

		$this->entity = (string) Request::filter_input( INPUT_POST, 'entity' );
		$new_theme    = (string) Request::filter_input( INPUT_POST, 'newTheme' );
		$status       = str_replace( '-', '_', (string) Request::filter_input( INPUT_POST, 'status' ) );
		$entity_name  = $this->form_fields[ $status ]['label'] ?? '';

		if ( ! in_array( $this->entity, [ 'plugin', 'theme' ], true ) ) {
			wp_send_json_error(
				esc_html__( 'Unsupported integration entity.', 'hcaptcha-for-forms-and-more' )
			);

			return; // For testing purposes.
		}

		$permission_error = 'plugin' === $this->entity
			? $this->get_plugin_activation_error()
			: $this->get_theme_switch_error();

		if ( null !== $permission_error ) {
			wp_send_json_error( esc_html( $permission_error->get_error_message() ) );

			return; // For testing purposes.
		}

		$plugins = 'plugin' === $this->entity ? $this->get_status_plugins( $status ) : [];
		$plan    = $this->build_deactivation_plan( $plugins, $entity_name, $new_theme );

		if ( $plan['rootBlockedBy'] ) {
			$this->send_deactivation_blocked_error( $plan['rootBlockedBy'] );

			return; // For testing purposes.
		}

		wp_send_json_success( [ 'plan' => $plan ] );
	}

	/**
	 * Build the current activation plan.
	 *
	 * @param string $status      Integration status.
	 * @param string $entity_name Integration entity name.
	 *
	 * @return array
	 */
	protected function build_activation_plan( string $status, string $entity_name ): array {
		$manager       = $this->get_dependency_manager();
		$include_roots = 'theme' === $this->entity;
		$plugins       = $include_roots
			? $manager->get_additional_dependencies( $entity_name )
			: $this->filter_activate_plugins( $this->get_status_plugins( $status ) );

		return $manager->get_activation_plan(
			$plugins,
			$this->get_active_plugin_slugs(),
			$include_roots
		);
	}

	/**
	 * Ajax action to activate/deactivate the plugin / theme.
	 *
	 * @return void
	 * @noinspection PhpUnreachableStatementInspection
	 */
	public function activate(): void {
		$this->run_checks( self::ACTIVATE_ACTION );

		$this->install         = filter_input( INPUT_POST, 'install', FILTER_VALIDATE_BOOLEAN );
		$activate              = filter_input( INPUT_POST, 'activate', FILTER_VALIDATE_BOOLEAN );
		$this->entity          = filter_input( INPUT_POST, 'entity', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$new_theme             = filter_input( INPUT_POST, 'newTheme', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$status                = filter_input( INPUT_POST, 'status', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$status                = str_replace( '-', '_', $status );
		$entity_name           = $this->form_fields[ $status ]['label'] ?? '';
		$manage_dependencies   = filter_var(
			Request::filter_input( INPUT_POST, 'manageDependencies' ),
			FILTER_VALIDATE_BOOLEAN
		);
		$deactivate_all        = filter_var(
			Request::filter_input( INPUT_POST, 'deactivateAll' ),
			FILTER_VALIDATE_BOOLEAN
		);
		$selected_dependencies = (array) Request::filter_input( INPUT_POST, 'dependencies' );

		if ( ! in_array( $this->entity, [ 'plugin', 'theme' ], true ) ) {
			wp_send_json_error(
				esc_html__( 'Unsupported integration entity.', 'hcaptcha-for-forms-and-more' )
			);

			return; // For testing purposes.
		}

		$permission_error = $this->get_activation_error( $activate, $entity_name, $new_theme );

		if ( null !== $permission_error ) {
			wp_send_json_error( esc_html( $permission_error->get_error_message() ) );

			return; // For testing purposes.
		}

		header_remove( 'Location' );
		http_response_code( 200 );

		if ( 'plugin' === $this->entity ) {
			$entities = $this->get_status_plugins( $status );

			if ( ! $activate && $manage_dependencies ) {
				$plan = $this->build_deactivation_plan( $entities, $entity_name, $new_theme );

				if ( $plan['rootBlockedBy'] ) {
					$this->send_deactivation_blocked_error( $plan['rootBlockedBy'] );

					return; // For testing purposes.
				}

				$entities = $this->get_deactivation_plugins(
					$plan,
					$selected_dependencies,
					$deactivate_all,
					$this->get_current_theme_consumer()
				);
			}

			$this->process_plugins( $activate, $entities, $entity_name );
		}

		if ( 'theme' === $this->entity ) {
			$theme             = $activate ? $entity_name : $new_theme;
			$replacement_theme = $this->get_replacement_theme( $new_theme );

			if ( ! $activate && $manage_dependencies ) {
				$plan    = $this->build_deactivation_plan( [], $entity_name, $new_theme );
				$plugins = $this->get_deactivation_plugins(
					$plan,
					$selected_dependencies,
					$deactivate_all,
					$this->get_theme_consumer( $replacement_theme )
				);

				$this->process_theme( $theme, $plugins );

				return;
			}

			$this->process_theme( $theme );
		}
	}

	/**
	 * Get the theme that will replace the active theme.
	 *
	 * @param string $new_theme Requested replacement theme.
	 *
	 * @return string
	 */
	protected function get_replacement_theme( string $new_theme ): string {
		return $new_theme ?: $this->get_default_theme();
	}

	/**
	 * Get plugins assigned to an integration status.
	 *
	 * @param string $status Integration status.
	 *
	 * @return string[]
	 */
	protected function get_status_plugins( string $status ): array {
		$plugins = [];

		foreach ( hcaptcha()->modules as $module ) {
			if ( $module[0][0] === $status ) {
				$plugins = array_merge( $plugins, (array) $module[1] );
			}
		}

		return array_values( array_unique( $plugins ) );
	}

	/**
	 * Build the current deactivation plan.
	 *
	 * @param string[] $plugins     Root plugins.
	 * @param string   $entity_name Integration entity name.
	 * @param string   $new_theme   Theme that will become active.
	 *
	 * @return array
	 */
	protected function build_deactivation_plan(
		array $plugins,
		string $entity_name,
		string $new_theme
	): array {
		$manager        = $this->get_dependency_manager();
		$active_plugins = $this->get_active_plugin_slugs();
		$include_roots  = 'theme' === $this->entity;

		if ( $include_roots ) {
			$current_theme = $this->get_theme_consumer( '', $entity_name );
			$plugins       = $current_theme ? (array) reset( $current_theme ) : [];

			if ( ! $plugins ) {
				$plugins = $manager->get_additional_dependencies( $entity_name );
			}

			$external_consumers = $this->get_theme_consumer( $this->get_replacement_theme( $new_theme ) );
		} else {
			$external_consumers = $this->get_current_theme_consumer();
		}

		$plan               = $manager->get_deactivation_plan(
			$plugins,
			$active_plugins,
			$include_roots,
			$external_consumers
		);
		$permission_message = '';

		if ( $include_roots && $plan['items'] && null !== $this->get_plugin_activation_error() ) {
			$permission_message = __(
				'You are not allowed to deactivate plugins on this site.',
				'hcaptcha-for-forms-and-more'
			);

			foreach ( $plan['items'] as &$item ) {
				$item['disabled'] = true;
			}

			unset( $item );
		}

		foreach ( $plan['items'] as &$item ) {
			$item['reason'] = $item['requiredBy']
				? sprintf(
					/* translators: %s: comma-separated list of plugin or theme names. */
					__( 'Required by: %s', 'hcaptcha-for-forms-and-more' ),
					implode( ', ', $item['requiredBy'] )
				)
				: $permission_message;
		}

		unset( $item );

		return $plan;
	}

	/**
	 * Get plugins that can safely be deactivated from a plan.
	 *
	 * @param array    $plan               Deactivation plan.
	 * @param string[] $selected           Selected dependency plugins.
	 * @param bool     $deactivate_all     Deactivate every available dependency.
	 * @param array    $external_consumers Additional active dependency consumers.
	 *
	 * @return string[]
	 */
	protected function get_deactivation_plugins(
		array $plan,
		array $selected,
		bool $deactivate_all,
		array $external_consumers
	): array {
		if ( $deactivate_all ) {
			$selected = [];

			foreach ( $plan['items'] as $item ) {
				if ( empty( $item['disabled'] ) ) {
					$selected[] = $item['plugin'];
				}
			}
		}

		return $this->get_dependency_manager()->get_safe_deactivation_plugins(
			$plan,
			$selected,
			$this->get_active_plugin_slugs(),
			$external_consumers
		);
	}

	/**
	 * Get the dependency manager.
	 *
	 * @return PluginDependencyManager
	 */
	protected function get_dependency_manager(): PluginDependencyManager {
		return new PluginDependencyManager(
			$this->plugins,
			self::PLUGIN_DEPENDENCIES,
			function ( string $plugin ): array {
				return $this->get_plugin_data( $plugin );
			}
		);
	}

	/**
	 * Get active installed plugin files.
	 *
	 * @return string[]
	 */
	protected function get_active_plugin_slugs(): array {
		$active_plugins = [];
		$main           = hcaptcha();

		foreach ( array_keys( $this->plugins ) as $plugin ) {
			if ( $main->is_plugin_active( $plugin ) ) {
				$active_plugins[] = $plugin;
			}
		}

		return $active_plugins;
	}

	/**
	 * Get the active theme as a dependency consumer.
	 *
	 * @return array
	 */
	protected function get_current_theme_consumer(): array {
		return $this->get_theme_consumer( '' );
	}

	/**
	 * Get a theme and its configured plugin dependencies.
	 *
	 * @param string $theme         Theme stylesheet. Empty means the active theme.
	 * @param string $fallback_name Fallback theme name.
	 *
	 * @return array
	 */
	protected function get_theme_consumer( string $theme, string $fallback_name = '' ): array {
		$theme_object = $theme && isset( $this->themes[ $theme ] )
			? $this->themes[ $theme ]
			: wp_get_theme( $theme ?: null );
		$keys         = array_filter( [ $theme, $fallback_name ] );
		$name         = $fallback_name ?: $theme;

		if ( $theme_object instanceof WP_Theme ) {
			$keys[] = $theme_object->get_stylesheet();
			$keys[] = $theme_object->get_template();
			$keys[] = $theme_object->get( 'Name' );
			$name   = $theme_object->get( 'Name' ) ?: $name;
		}

		$dependencies = [];
		$manager      = $this->get_dependency_manager();

		foreach ( array_unique( array_filter( $keys ) ) as $key ) {
			$dependencies[] = $manager->get_additional_dependencies( (string) $key );
		}

		$dependencies = array_values( array_unique( array_merge( ...$dependencies ) ) );

		return $dependencies ? [ ( $name ?: $theme ) => $dependencies ] : [];
	}

	/**
	 * Send an error when a root plugin has active dependents.
	 *
	 * @param string[] $blocked_by Dependency consumer names.
	 *
	 * @return void
	 */
	protected function send_deactivation_blocked_error( array $blocked_by ): void {
		wp_send_json_error(
			esc_html(
				sprintf(
					/* translators: %s: comma-separated list of plugin or theme names. */
					__( 'Cannot deactivate this plugin because it is required by: %s.', 'hcaptcha-for-forms-and-more' ),
					implode( ', ', array_unique( $blocked_by ) )
				)
			)
		);
	}

	/**
	 * Get an activation permission error for the requested operation.
	 *
	 * @param bool   $activate    Whether to activate the entity.
	 * @param string $entity_name Entity name.
	 * @param string $new_theme   New theme slug.
	 *
	 * @return null|WP_Error Permission error, or null when activation is allowed.
	 */
	protected function get_activation_error( bool $activate, string $entity_name, string $new_theme ): ?WP_Error {
		if ( 'plugin' === $this->entity ) {
			return $this->get_plugin_activation_error();
		}

		$permission_error = $this->get_theme_switch_error();

		if ( null !== $permission_error ) {
			return $permission_error;
		}

		$theme   = $activate ? $entity_name : $new_theme;
		$theme   = $theme ?: $this->get_default_theme();
		$plugins = $this->get_dependency_manager()->get_additional_dependencies( $theme );

		return $plugins ? $this->get_plugin_activation_error() : null;
	}

	/**
	 * Activate/deactivate plugins.
	 *
	 * @param bool   $activate    Activate or deactivate.
	 * @param array  $plugins     Plugins to process.
	 * @param string $plugin_name Main plugin name to process.
	 *
	 * @return void
	 */
	protected function process_plugins( bool $activate, array $plugins, string $plugin_name ): void {
		if ( $activate ) {
			$result = $this->activate_plugins( $plugins );

			if ( is_wp_error( $result ) ) {
				$error_message = $result->get_error_message();
			} else {
				$error_message = '';
				$plugin_names  = $this->plugin_names_from_trees();

				if ( array_filter( $plugin_names ) ) {
					$message = $this->install
						? sprintf(
						/* translators: 1: Plugin name. */
							_n(
								'%s plugin is installed and activated.',
								'%s plugins are installed and activated.',
								count( $plugin_names ),
								'hcaptcha-for-forms-and-more'
							),
							implode( ', ', $plugin_names )
						)
						: sprintf(
						/* translators: 1: Plugin name. */
							_n(
								'%s plugin is activated.',
								'%s plugins are activated.',
								count( $plugin_names ),
								'hcaptcha-for-forms-and-more'
							),
							implode( ', ', $plugin_names )
						);

					$this->send_json_success( esc_html( $message ) );

					return; // For testing purposes.
				}
			}

			$message = $this->install
				? sprintf(
				/* translators: 1: Plugin name, 2: Error message. */
					__( 'Error installing and activating %1$s plugin: %2$s', 'hcaptcha-for-forms-and-more' ),
					$plugin_name,
					$error_message
				)
				: sprintf(
				/* translators: 1: Plugin name, 2: Error message. */
					__( 'Error activating %1$s plugin: %2$s', 'hcaptcha-for-forms-and-more' ),
					$plugin_name,
					$error_message
				);

			$this->send_json_error( esc_html( rtrim( $message, ': .' ) . '.' ) );

			return; // For testing purposes.
		}

		$this->deactivate_plugins( $plugins );
		$message = $this->get_plugins_deactivated_message( $plugins, $plugin_name );

		$this->send_json_success( esc_html( $message ) );
	}

	/**
	 * Get a message listing deactivated plugins.
	 *
	 * @param string[] $plugins      Plugin files.
	 * @param string   $fallback_name Fallback name when no plugin names are available.
	 *
	 * @return string
	 */
	protected function get_plugins_deactivated_message( array $plugins, string $fallback_name = '' ): string {
		$plugin_names = [];
		$manager      = $this->get_dependency_manager();

		foreach ( $plugins as $plugin ) {
			$plugin_names[] = $manager->get_plugin_name( $plugin );
		}

		$plugin_names = array_values( array_unique( array_filter( $plugin_names ) ) );

		if ( ! $plugin_names && $fallback_name ) {
			$plugin_names[] = $fallback_name;
		}

		if ( ! $plugin_names ) {
			return '';
		}

		return sprintf(
			/* translators: %s: comma-separated list of plugin names. */
			_n(
				'%s plugin is deactivated.',
				'%s plugins are deactivated.',
				count( $plugin_names ),
				'hcaptcha-for-forms-and-more'
			),
			implode( ', ', $plugin_names )
		);
	}

	/**
	 * Deactivate plugins and return a message describing the result.
	 *
	 * @param string[] $plugins Plugin files.
	 *
	 * @return string
	 */
	protected function deactivate_plugins_with_message( array $plugins ): string {
		if ( ! $plugins ) {
			return '';
		}

		$this->deactivate_plugins( $plugins );

		return $this->get_plugins_deactivated_message( $plugins );
	}

	/**
	 * Activate a theme.
	 *
	 * @param string   $theme              Theme name to process.
	 * @param string[] $deactivate_plugins Dependency plugins to deactivate after switching.
	 *
	 * @return void
	 */
	protected function process_theme( string $theme, array $deactivate_plugins = [] ): void {
		$permission_error = $this->get_theme_switch_error();

		if ( null !== $permission_error ) {
			$this->send_json_error( esc_html( $permission_error->get_error_message() ) );

			return; // For testing purposes.
		}

		// With Ctrl+Click, $theme is empty.
		$theme = $theme ?: $this->get_default_theme();

		if ( ! $theme ) {
			$message = sprintf(
			/* translators: 1: Theme name. */
				__( 'No default theme found.', 'hcaptcha-for-forms-and-more' ),
				$theme
			);

			$this->send_json_error( esc_html( $message ) );

			return; // For testing purposes.
		}

		$result = $this->activate_theme_dependencies( $theme );

		if ( null !== $result ) {
			$message = sprintf(
			/* translators: 1: Theme name, 2: Error message. */
				__( 'Error activating dependencies for %1$s theme: %2$s', 'hcaptcha-for-forms-and-more' ),
				$theme,
				$result->get_error_message()
			);

			$this->send_json_error( esc_html( $message ) );

			return; // For testing purposes.
		}

		$result = $this->activate_theme( $theme );

		if ( $result && is_wp_error( $result ) ) {
			$message = $this->install
				? sprintf(
				/* translators: 1: Theme name, 2: Error message. */
					__( 'Error installing and activating %1$s theme: %2$s', 'hcaptcha-for-forms-and-more' ),
					$theme,
					$result->get_error_message()
				)
				: sprintf(
				/* translators: 1: Theme name, 2: Error message. */
					__( 'Error activating %1$s theme: %2$s', 'hcaptcha-for-forms-and-more' ),
					$theme,
					$result->get_error_message()
				);

			$this->send_json_error( esc_html( $message ) );

			return; // For testing purposes.
		}

		$deactivation_message = $this->deactivate_plugins_with_message( $deactivate_plugins );

		$message = sprintf(
		/* translators: 1: Theme name. */
			__( '%s theme is activated.', 'hcaptcha-for-forms-and-more' ),
			wp_get_theme()->get( 'Name' ) ?? $theme
		);

		$plugin_names = $this->plugin_names_from_trees();

		if ( $plugin_names ) {
			$message .=
				' Also, dependent ' .
				sprintf(
				/* translators: 1: Plugin name. */
					_n(
						'%s plugin is activated.',
						'%s plugins are activated.',
						count( $plugin_names ),
						'hcaptcha-for-forms-and-more'
					),
					implode( ', ', $plugin_names )
				);
		}

		if ( $deactivation_message ) {
			$message .= ' ' . $deactivation_message;
		}

		$this->send_json_success( esc_html( $message ) );
	}

	/**
	 * Activate the dependencies for a theme.
	 *
	 * @param string $theme Theme name.
	 *
	 * @return null|WP_Error Permission or activation error, or null on success.
	 */
	protected function activate_theme_dependencies( string $theme ) {
		$plugins = $this->get_dependency_manager()->get_additional_dependencies( $theme );

		if ( ! $plugins ) {
			return null;
		}

		$permission_error = $this->get_plugin_activation_error();

		if ( null !== $permission_error ) {
			return $permission_error;
		}

		$result = $this->activate_plugins( $plugins, false );

		return is_wp_error( $result ) && $result->has_errors() ? $result : null;
	}

	/**
	 * Activate plugins.
	 *
	 * When we activate the first available plugin in the list only,
	 * we assume that Pro plugins are placed earlier in the list.
	 *
	 * @param array $plugins    Plugins to activate.
	 * @param bool  $first_only Activate the first available plugin only.
	 *
	 * @return null|true|WP_Error Null on success, WP_Error on failure. True if the plugin is already active.
	 */
	protected function activate_plugins( array $plugins, bool $first_only = true ) {
		/**
		 * Filter the list of plugin to activate.
		 *
		 * @param array $plugins    List of plugins.
		 * @param bool  $first_only Activate the first available plugin only.
		 */
		$plugins = apply_filters( 'hcaptcha_activate_plugins', $plugins, $first_only );
		$results = new WP_Error();

		foreach ( $plugins as $plugin ) {
			$this->build_plugins_tree( $plugin );

			$result = $this->activate_plugin_tree( $this->plugin_trees[ $plugin ] );

			if ( ! is_wp_error( $result ) ) {
				if ( $first_only ) {
					// Activate the first available plugin only.
					return $result;
				}

				continue;
			}

			$results->add( $result->get_error_code(), $result->get_error_message() );
		}

		return $results;
	}

	/**
	 * Activate plugins.
	 *
	 * @param array $node Node of the plugin tree.
	 *
	 * @return null|true|WP_Error Null on success, WP_Error on failure. True if the plugin is already active.
	 */
	protected function activate_plugin_tree( array &$node ) {
		if ( $node['children'] ) {
			foreach ( $node['children'] as & $child ) {
				$child['result'] = $this->activate_plugin_tree( $child );

				if ( is_wp_error( $child['result'] ) ) {
					return $child['result'];
				}
			}

			unset( $child );
		}

		$node['result'] = $this->maybe_activate_plugin( $node['plugin'] );

		return $node['result'];
	}

	/**
	 * Maybe activate the plugin.
	 *
	 * @param string $plugin Path to the plugin file relative to the plugins' directory.
	 *
	 * @return null|true|WP_Error Null on success, WP_Error on failure. True if the plugin is already active.
	 */
	protected function maybe_activate_plugin( string $plugin ) {
		$permission_error = $this->get_plugin_activation_error();

		if ( null !== $permission_error ) {
			return $permission_error;
		}

		// Always try to install a plugin, as some dependent plugins may require it.
		ob_start();
		$result = $this->install_plugin( $plugin );
		ob_end_clean();

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		ob_start();
		$result = $this->activate_plugin( $plugin );
		ob_end_clean();

		return $result;
	}

	/**
	 * Install plugin.
	 *
	 * @param string $plugin Path to the plugin file relative to the plugins' directory.
	 *
	 * @return null|WP_Error Null on success, WP_Error on failure.
	 */
	protected function install_plugin( string $plugin ): ?WP_Error {
		// Check if the plugin is already installed.
		if ( file_exists( constant( 'WP_PLUGIN_DIR' ) . '/' . $plugin ) ) {
			return null;
		}

		$plugin = trim( explode( '/', $plugin )[0] );

		if ( empty( $plugin ) ) {
			return new WP_Error( 'no_plugin_specified', __( 'No plugin specified.', 'hcaptcha-for-forms-and-more' ) );
		}

		if ( ! current_user_can( 'install_plugins' ) ) {
			return new WP_Error(
				'not_allowed',
				__( 'Sorry, you are not allowed to install plugins on this site.', 'hcaptcha-for-forms-and-more' )
			);
		}

		if ( ! class_exists( 'Plugin_Upgrader', false ) ) {
			// @codeCoverageIgnoreStart
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
			// @codeCoverageIgnoreEnd
		}

		if ( ! function_exists( 'plugins_api' ) ) {
			// @codeCoverageIgnoreStart
			require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
			// @codeCoverageIgnoreEnd
		}

		$api = plugins_api(
			'plugin_information',
			[
				'slug'   => $plugin,
				'fields' => [
					'sections' => false,
				],
			]
		);

		if ( is_wp_error( $api ) ) {
			return $api;
		}

		$skin     = new WP_Ajax_Upgrader_Skin();
		$upgrader = new Plugin_Upgrader( $skin );

		return $this->install_entity( $upgrader, $skin, $api->download_link );
	}

	/**
	 * Activate plugin.
	 *
	 * @param string $plugin Path to the plugin file relative to the plugins' directory.
	 *
	 * @return null|true|WP_Error Null on success, WP_Error on failure. True if the plugin is already active.
	 */
	protected function activate_plugin( string $plugin ) {
		if ( hcaptcha()->is_plugin_active( $plugin ) ) {
			return true;
		}

		$permission_error = $this->get_plugin_activation_error();

		if ( null !== $permission_error ) {
			return $permission_error;
		}

		$network_wide = is_multisite() && $this->is_network_wide();

		// Block redirects upon plugin activation.
		add_filter( 'wp_redirect', '__return_false' );

		$result = activate_plugin( $plugin, '', $network_wide );

		if ( null === $result ) {
			/**
			 * Fires after a plugin has been activated.
			 *
			 * @param string $plugin       Path to the plugin file relative to the plugins' directory.
			 * @param bool   $network_wide Whether to enable the plugin network-wide.
			 */
			do_action( 'hcaptcha_activated_plugin', $plugin, $network_wide );
		}

		return $result;
	}

	/**
	 * Get plugins' tree.
	 *
	 * @param string $plugin Plugin slug.
	 *
	 * @return array
	 */
	protected function build_plugins_tree( string $plugin ): array {
		if ( isset( $this->plugin_trees[ $plugin ] ) ) {
			return $this->plugin_trees[ $plugin ];
		}

		$dependencies = $this->plugin_dependencies( $plugin );
		$tree         = [
			'plugin'   => $plugin,
			'children' => [],
		];

		foreach ( $dependencies as $dependency ) {
			$tree['children'][] = $this->build_plugins_tree( $dependency );
		}

		$this->plugin_trees[ $plugin ] = $tree;

		return $tree;
	}

	/**
	 * Get plugin dependencies.
	 *
	 * @param string $plugin Plugin slug.
	 *
	 * @return array
	 */
	private function plugin_dependencies( string $plugin ): array {
		return $this->get_dependency_manager()->get_dependencies( $plugin );
	}

	/**
	 * Get plugin names from the tree.
	 *
	 * @return array
	 */
	protected function plugin_names_from_trees(): array {
		$plugin_names = [];

		foreach ( $this->plugin_trees as $node ) {
			$plugin_names[] = $this->plugin_names_from_tree( $node );
		}

		return array_unique( array_merge( [], ...$plugin_names ) );
	}

	/**
	 * Get plugin names from the tree.
	 *
	 * @param array $node Node of the plugin tree.
	 *
	 * @return array
	 */
	protected function plugin_names_from_tree( array $node ): array {
		$plugin_names = [];

		if ( $node['children'] ) {
			foreach ( $node['children'] as $child ) {
				$plugin_names[] = $this->plugin_names_from_tree( $child );
			}

			$plugin_names = array_merge( [], ...$plugin_names );
		}

		if ( isset( $node['result'] ) && is_wp_error( $node['result'] ) ) {
			return array_unique( array_merge( [], $plugin_names ) );
		}

		$status = '';

		foreach ( hcaptcha()->modules as $module ) {
			// Get the plugin name from modules only for a case when a single plugin mentioned there.
			if ( $module[1] === $node['plugin'] ) {
				$status = $module[0][0];

				break;
			}
		}

		$plugin_name = $this->form_fields[ $status ]['label'] ?? '';

		if ( ! $plugin_name ) {
			$plugin_data = $this->get_plugin_data( $node['plugin'] );
			$plugin_name = $plugin_data['Name'] ?? '';
		}

		return array_unique( array_merge( [ $plugin_name ], $plugin_names ) );
	}

	/**
	 * Deactivate plugins.
	 *
	 * @param array $plugins Plugins to deactivate.
	 *
	 * @return void
	 */
	protected function deactivate_plugins( array $plugins ): void {
		$network_wide = is_multisite() && $this->is_network_wide();

		deactivate_plugins( $plugins, true, $network_wide );
	}

	/**
	 * Activate theme.
	 *
	 * @param string $theme Theme to activate.
	 *
	 * @return null|true|WP_Error Null on success, WP_Error on failure.
	 */
	protected function activate_theme( string $theme ) {
		$permission_error = $this->get_theme_switch_error();

		if ( null !== $permission_error ) {
			return $permission_error;
		}

		if ( wp_get_theme()->get_stylesheet() === $theme ) {
			return true;
		}

		if ( $this->install ) {
			ob_start();

			$result = $this->install_theme( $theme );

			ob_end_clean();

			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		ob_start();
		switch_theme( $theme );
		ob_end_clean();

		return null;
	}

	/**
	 * Get a plugin activation permission error.
	 *
	 * @return null|WP_Error Permission error, or null when activation is allowed.
	 */
	protected function get_plugin_activation_error(): ?WP_Error {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return new WP_Error(
				'not_allowed',
				__(
					'You are not allowed to activate or deactivate plugins on this site.',
					'hcaptcha-for-forms-and-more'
				)
			);
		}

		if (
			is_multisite() && $this->is_network_wide() &&
			! current_user_can( 'manage_network_plugins' )
		) {
			return new WP_Error(
				'not_allowed',
				__( 'You are not allowed to manage plugins for this network.', 'hcaptcha-for-forms-and-more' )
			);
		}

		return null;
	}

	/**
	 * Get a theme switching permission error.
	 *
	 * @return null|WP_Error Permission error, or null when switching is allowed.
	 */
	protected function get_theme_switch_error(): ?WP_Error {
		if ( current_user_can( 'switch_themes' ) ) {
			return null;
		}

		return new WP_Error(
			'not_allowed',
			__( 'You are not allowed to switch themes on this site.', 'hcaptcha-for-forms-and-more' )
		);
	}

	/**
	 * Install theme.
	 *
	 * @param string $theme Theme to install.
	 *
	 * @return null|WP_Error Null on success, WP_Error on failure.
	 */
	protected function install_theme( string $theme ): ?WP_Error {
		$theme = trim( $theme );

		if ( empty( $theme ) ) {
			return new WP_Error( 'no_theme_specified', __( 'No theme specified.', 'hcaptcha-for-forms-and-more' ) );
		}

		if ( ! current_user_can( 'install_themes' ) ) {
			return new WP_Error(
				'not_allowed',
				__(
					'Sorry, you are not allowed to install themes on this site.',
					'hcaptcha-for-forms-and-more'
				)
			);
		}

		if ( ! class_exists( 'Theme_Upgrader', false ) ) {
			// @codeCoverageIgnoreStart
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
			require_once ABSPATH . 'wp-admin/includes/theme.php';
			// @codeCoverageIgnoreEnd
		}

		$api = themes_api(
			'theme_information',
			[
				'slug'   => $theme,
				'fields' => [ 'sections' => false ],
			]
		);

		if ( is_wp_error( $api ) ) {
			return $api;
		}

		$skin     = new WP_Ajax_Upgrader_Skin();
		$upgrader = new Theme_Upgrader( $skin );

		return $this->install_entity( $upgrader, $skin, $api->download_link );
	}

	/**
	 * Send JSON success.
	 *
	 * @param string $message Message.
	 *
	 * @return void
	 */
	private function send_json_success( string $message ): void {
		wp_send_json_success( $this->json_data( $message ) );
	}

	/**
	 * Send json error.
	 *
	 * @param string $message Message.
	 *
	 * @return void
	 */
	private function send_json_error( string $message ): void {
		wp_send_json_error( $this->json_data( $message ) );
	}

	/**
	 * Prepare json data.
	 *
	 * @param string $message Message.
	 *
	 * @return array
	 */
	protected function json_data( string $message ): array {
		$data          = [ 'message' => esc_html( $message ) ];
		$data['stati'] = $this->get_activation_stati();

		if ( 'theme' === $this->entity ) {
			$data['themes']       = $this->get_themes();
			$data['defaultTheme'] = $this->get_default_theme();
		}

		return $data;
	}

	/**
	 * Get activation stati of all integrated plugins and themes.
	 *
	 * @return array
	 */
	protected function get_activation_stati(): array {
		$stati = [];

		foreach ( hcaptcha()->modules as $module ) {
			$stati[ $module[0][0] ] = hcaptcha()->plugin_or_theme_active( $module[1] );
		}

		return $stati;
	}

	/**
	 * Get themes to switch (all themes, excluding the active one).
	 *
	 * @return array
	 */
	protected function get_themes(): array {
		$themes = array_map(
			static function ( $theme ) {
				return $theme->get( 'Name' );
			},
			$this->themes
		);

		unset( $themes[ wp_get_theme()->get_stylesheet() ] );

		asort( $themes );

		return $themes;
	}

	/**
	 * Get default theme.
	 *
	 * @return string
	 * @noinspection PhpVoidFunctionResultUsedInspection
	 */
	protected function get_default_theme(): string {
		$core_default_theme_obj = WP_Theme::get_core_default_theme();

		return $core_default_theme_obj ? $core_default_theme_obj->get_stylesheet() : '';
	}

	/**
	 * Install entity (plugin or theme).
	 *
	 * @param Plugin_Upgrader|Theme_Upgrader|object $upgrader      Upgrader instance.
	 * @param WP_Ajax_Upgrader_Skin|object          $skin          Upgrader skin instance.
	 * @param string                                $download_link Download link.
	 *
	 * @return WP_Error|null
	 */
	protected function install_entity( object $upgrader, object $skin, string $download_link ): ?WP_Error {
		$result = $upgrader->install( $download_link );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( is_wp_error( $skin->result ) ) {
			return $skin->result;
		}

		$skin_errors = $skin->get_errors();

		if ( $skin_errors && $skin_errors->has_errors() ) {
			return $skin_errors;
		}

		if ( is_null( $result ) ) {
			global $wp_filesystem;

			$status['errorCode']    = 'unable_to_connect_to_filesystem';
			$status['errorMessage'] = __(
				'Unable to connect to the filesystem. Please confirm your credentials.',
				'hcaptcha-for-forms-and-more'
			);

			// Pass through the error from WP_Filesystem if one was raised.
			if (
				$wp_filesystem instanceof WP_Filesystem_Base &&
				is_wp_error( $wp_filesystem->errors ) &&
				$wp_filesystem->errors->has_errors()
			) {
				$status['errorMessage'] = esc_html( $wp_filesystem->errors->get_error_message() );
			}

			return new WP_Error( $status['errorCode'], $status['errorMessage'] );
		}

		return null;
	}

	/**
	 * Wrapper for get_plugin_data.
	 * Check the plugin file for existence to avoid warnings.
	 *
	 * @param string $plugin    Plugin slug.
	 * @param bool   $markup    Optional. If the returned data should have HTML markup applied.
	 * @param bool   $translate Optional. If the returned data should be translated. Default true.
	 *
	 * @return array
	 */
	protected function get_plugin_data( string $plugin, bool $markup = true, bool $translate = true ): array {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			$this->load_plugin_data_api();
		}

		$plugin_file = $this->get_plugin_file( $plugin );

		if ( ! file_exists( $plugin_file ) ) {
			return [];
		}

		return get_plugin_data( $plugin_file, $markup, $translate );
	}

	/**
	 * Load WordPress plugin metadata functions.
	 *
	 * @codeCoverageIgnore WordPress core is outside the unit test environment.
	 */
	protected function load_plugin_data_api(): void {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	/**
	 * Get a plugin file from the plugin slug.
	 *
	 * @param string $plugin Plugin slug.
	 *
	 * @return string
	 */
	protected function get_plugin_file( string $plugin ): string {
		return constant( 'WP_PLUGIN_DIR' ) . '/' . $plugin;
	}

	/**
	 * Prepares antispam data for the given status and form field.
	 *
	 * @param string $status     The status identifier.
	 * @param array  $form_field The form field data.
	 *
	 * @return array The updated form field data containing antispam configurations.
	 */
	protected function prepare_antispam_data( string $status, array $form_field ): array {
		if ( ! $this->all_protected_forms ) {
			$this->all_protected_forms = array_merge( Honeypot::get_protected_forms(), AntiSpam::get_protected_forms() );
		}

		$settings = hcaptcha()->settings();

		foreach ( $this->all_protected_forms as $type => $protected_forms ) {
			if ( ! isset( $protected_forms[ $status ] ) ) {
				continue;
			}

			foreach ( $protected_forms[ $status ] as $form ) {
				if ( 'native' === $type || $settings->is( $status, $form ) ) {
					$form_field = $this->prepare_form_field_antispam_data( $form_field, $form, $type );
				}
			}
		}

		return $this->format_form_antispam_helpers( $form_field );
	}

	/**
	 * Prepare form field antispam data.
	 *
	 * @param array  $form_field Form field.
	 * @param string $form       Form name.
	 * @param string $type       Antispam type.
	 *
	 * @return array
	 */
	private function prepare_form_field_antispam_data( array $form_field, string $form, string $type ): array {
		$form_field['data'][ $form ]['antispam']         = '';
		$form_field['data'][ $form ][ "antispam-$type" ] = '';
		$form_field['helpers'][ $form ][]                = $type;

		return $form_field;
	}

	/**
	 * Format antispam helpers.
	 *
	 * @param array $form_field Form field.
	 *
	 * @return array
	 */
	private function format_form_antispam_helpers( array $form_field ): array {
		if ( ! isset( $form_field['helpers'] ) ) {
			return $form_field;
		}

		$helpers = [
			'honeypot' => __( 'hCaptcha honeypot', 'hcaptcha-for-forms-and-more' ),
			'fst'      => __( 'form submit time token', 'hcaptcha-for-forms-and-more' ),
			'native'   => __( 'native antispam service', 'hcaptcha-for-forms-and-more' ),
			'hcaptcha' => __( 'hCaptcha antispam service', 'hcaptcha-for-forms-and-more' ),
		];

		foreach ( $form_field['helpers'] as $form => $helper_arr ) {
			$helper_arr = array_map(
				static function ( $type ) use ( $helpers ) {
					return $helpers[ $type ];
				},
				$helper_arr
			);

			$helper = sprintf(
			/* translators: 1: form protection methods. */
				__( 'The form is protected by the %1$s.', 'hcaptcha-for-forms-and-more' ),
				Utils::list_array( $helper_arr )
			);

			$form_field['helpers'][ $form ] = $helper;
		}

		return $form_field;
	}

	/**
	 * Get installed plugins and themes.
	 *
	 * @return array
	 */
	protected function get_installed_entities(): array {
		$installed = [];

		foreach ( hcaptcha()->modules as $module ) {
			if ( $this->plugin_or_theme_installed( $module[1] ) ) {
				$installed[] = $module[0][0];
			}
		}

		return array_unique( $installed );
	}

	/**
	 * Setup antispam data.
	 *
	 * @param array $installed Installed entities.
	 *
	 * @return void
	 */
	private function setup_antispam_data( array $installed ): void {
		if ( ! $this->is_any_antispam_enabled() ) {
			$this->form_fields['show_antispam_coverage']['disabled'] = true;
		}

		foreach ( $this->form_fields as $status => &$form_field ) {
			if ( self::SECTION_HEADER === ( $form_field['section'] ?? '' ) ) {
				continue;
			}

			$form_field['installed'] = in_array( $status, $installed, true );
			$form_field['disabled']  = ( ! $form_field['installed'] ) || $form_field['disabled'];

			$form_field = $this->prepare_antispam_data( $status, $form_field );
		}

		unset( $form_field );
	}

	/**
	 * Setup field data.
	 *
	 * @param array $installed Installed entities.
	 *
	 * @return void
	 */
	protected function setup_field_data( array $installed ): void {
		$this->form_fields = $this->sort_fields( $this->form_fields );

		$this->setup_paypal_payments_dependency_notice();

		$prefix = self::PREFIX . '-' . $this->section_title() . '-';

		foreach ( $this->form_fields as $status => &$form_field ) {
			if ( self::SECTION_HEADER === ( $form_field['section'] ?? '' ) ) {
				continue;
			}

			$form_field['installed'] = in_array( $status, $installed, true );
			$form_field['section']   = $form_field['disabled'] ? self::SECTION_DISABLED : self::SECTION_ENABLED;

			if ( isset( $form_field['label'] ) ) {
				$form_field['label'] = $this->logo( $form_field );
			}

			$entity              = $form_field['entity'] ?? '';
			$theme               = 'theme' === $entity ? ' ' . $prefix . 'theme' : '';
			$form_field['class'] = str_replace( '_', '-', $prefix . $status . $theme );
		}

		unset( $form_field );
	}

	/**
	 * Setup WooCommerce PayPal Payments dependency notice.
	 *
	 * @return void
	 * @noinspection HtmlUnknownAnchorTarget
	 */
	private function setup_paypal_payments_dependency_notice(): void {
		if (
			! in_array( 'button', (array) $this->get( 'paypal_payments_status' ), true ) ||
			in_array( 'checkout', (array) $this->get( 'woocommerce_status' ), true )
		) {
			return;
		}

		$checkout_id = $this->get_checkbox_option_id( 'woocommerce_status', 'checkout' );

		if ( '' === $checkout_id ) {
			return;
		}

		$checkout_link = sprintf(
			'<a href="#%1$s">%2$s</a>',
			esc_attr( $checkout_id ),
			esc_html__( 'WooCommerce Checkout', 'hcaptcha-for-forms-and-more' )
		);

		$this->form_fields['paypal_payments_status']['supplemental'] = sprintf(
			/* translators: 1: WooCommerce Checkout integration link. */
			__(
				'Checkout PayPal buttons use WooCommerce Checkout hCaptcha. Enable %1$s to protect them.',
				'hcaptcha-for-forms-and-more'
			),
			$checkout_link
		);
	}

	/**
	 * Get checkbox option id.
	 *
	 * @param string $status Integration status.
	 * @param string $option Checkbox option.
	 *
	 * @return string
	 * @noinspection PhpSameParameterValueInspection
	 */
	private function get_checkbox_option_id( string $status, string $option ): string {
		$options = array_keys( $this->form_fields[ $status ]['options'] ?? [] );
		$index   = array_search( $option, $options, true );

		if ( false === $index ) {
			return '';
		}

		return sprintf( '%1$s_%2$d', $status, $index + 1 );
	}
}
