<?php
/**
 * Integrations class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Settings;

use KAGG\Settings\Abstracts\SettingsBase;
use WP_Error;
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
	 * Enabled section id.
	 */
	public const SECTION_ENABLED = 'enabled';

	/**
	 * Disabled section id.
	 */
	public const SECTION_DISABLED = 'disabled';

	/**
	 * Plugin dependencies not specified in their headers.
	 * Key is a plugin slug.
	 * Value is a plugin slug or an array of slugs.
	 */
	private const PLUGIN_DEPENDENCIES = [
		// phpcs:disable WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned, WordPress.Arrays.MultipleStatementAlignment.LongIndexSpaceBeforeDoubleArrow
		'Avada'                                                             => [
			'fusion-builder/fusion-builder.php',
			'fusion-core/fusion-core.php',
		],
		'acf-extended-pro/acf-extended.php'                                 => 'advanced-custom-fields-pro/acf.php',
		'back-in-stock-notifier-for-woocommerce/cwginstocknotifier.php'     => 'woocommerce/woocommerce.php',
		'elementor-pro/elementor-pro.php'                                   => 'elementor/elementor.php',
		'essential-addons-for-elementor-lite/essential_adons_elementor.php' => 'elementor/elementor.php',
		'sfwd-lms/sfwd_lms.php'                                             => 'learndash-hub/learndash-hub.php',
		'woocommerce-wishlists/woocommerce-wishlists.php'                   => 'woocommerce/woocommerce.php',
		// phpcs:enable WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned, WordPress.Arrays.MultipleStatementAlignment.LongIndexSpaceBeforeDoubleArrow
	];

	/**
	 * Entity name to activate/deactivate. Can be 'plugin' or 'theme'.
	 *
	 * @var string
	 */
	protected $entity = '';

	/**
	 * Plugins tree.
	 *
	 * @var array
	 */
	protected $plugins_tree;

	/**
	 * Installed plugins.
	 *
	 * @var array[]
	 */
	protected $plugins;

	/**
	 * Installed themes.
	 *
	 * @var WP_Theme[]
	 */
	protected $themes;

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

		parent::init();
	}

	/**
	 * Init class hooks.
	 */
	protected function init_hooks(): void {
		parent::init_hooks();

		add_action( 'kagg_settings_header', [ $this, 'search_box' ] );
		add_action( 'wp_ajax_' . self::ACTIVATE_ACTION, [ $this, 'activate' ] );
	}

	/**
	 * Activated plugin action.
	 * Do not allow redirect during plugin activation.
	 *
	 * @return void
	 */
	public function activated_plugin_action(): void {
		remove_action( 'activated_plugin', 'Brizy_Admin_GettingStarted::redirectAfterActivation' );
	}

	/**
	 * Init form fields.
	 *
	 * @return void
	 */
	public function init_form_fields(): void {
		$this->form_fields = [
			'wp_status'                        => [
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
			'acfe_status'                      => [
				'label'   => 'ACF Extended',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'ACF Extended Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'affiliates_status'                => [
				'label'   => 'Affiliates',
				'type'    => 'checkbox',
				'options' => [
					'login'    => __( 'Affiliates Login Form', 'hcaptcha-for-forms-and-more' ),
					'register' => __( 'Affiliates Register Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'asgaros_status'                   => [
				'label'   => 'Asgaros',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'avada_status'                     => [
				'entity'  => 'theme',
				'label'   => 'Avada',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Avada Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'back_in_stock_notifier_status'    => [
				'label'   => 'Back In Stock Notifier',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Back In Stock Notifier Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'bbp_status'                       => [
				'label'   => 'bbPress',
				'type'    => 'checkbox',
				'options' => [
					'new_topic' => __( 'New Topic Form', 'hcaptcha-for-forms-and-more' ),
					'reply'     => __( 'Reply Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'beaver_builder_status'            => [
				'label'   => 'Beaver Builder',
				'logo'    => 'svg',
				'type'    => 'checkbox',
				'options' => [
					'contact' => __( 'Contact Form', 'hcaptcha-for-forms-and-more' ),
					'login'   => __( 'Login Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'brizy_status'                     => [
				'label'   => 'Brizy',
				'logo'    => 'svg',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'bp_status'                        => [
				'label'   => 'BuddyPress',
				'logo'    => 'svg',
				'type'    => 'checkbox',
				'options' => [
					'create_group' => __( 'Create Group Form', 'hcaptcha-for-forms-and-more' ),
					'registration' => __( 'Register Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'classified_listing_status'        => [
				'label'   => 'Classified Listing',
				'type'    => 'checkbox',
				'options' => [
					'contact'   => __( 'Contact Form', 'hcaptcha-for-forms-and-more' ),
					'login'     => __( 'Login Form', 'hcaptcha-for-forms-and-more' ),
					'lost_pass' => __( 'Lost Password Form', 'hcaptcha-for-forms-and-more' ),
					'register'  => __( 'Register Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'coblocks_status'                  => [
				'label'   => 'CoBlocks',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'colorlib_customizer_status'       => [
				'label'   => 'Colorlib Login Customizer',
				'type'    => 'checkbox',
				'options' => [
					'login'     => __( 'Login Form', 'hcaptcha-for-forms-and-more' ),
					'lost_pass' => __( 'Lost Password Form', 'hcaptcha-for-forms-and-more' ),
					'register'  => __( 'Register Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'cf7_status'                       => [
				'label'   => 'Contact Form 7',
				'logo'    => 'svg',
				'type'    => 'checkbox',
				'options' => [
					'form'  => __( 'Form Auto-Add', 'hcaptcha-for-forms-and-more' ),
					'embed' => __( 'Form Embed', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'divi_status'                      => [
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
			'download_manager_status'          => [
				'label'   => 'Download Manager',
				'type'    => 'checkbox',
				'options' => [
					'button' => __( 'Button', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'easy_digital_downloads_status'    => [
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
			'elementor_pro_status'             => [
				'label'   => 'Elementor Pro',
				'logo'    => 'svg',
				'type'    => 'checkbox',
				'options' => [
					'form'  => __( 'Form', 'hcaptcha-for-forms-and-more' ),
					'login' => __( 'Login', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'essential_addons_status'          => [
				'label'   => 'Essential Addons',
				'type'    => 'checkbox',
				'options' => [
					'login'    => __( 'Login', 'hcaptcha-for-forms-and-more' ),
					'register' => __( 'Register', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'essential_blocks_status'          => [
				'label'   => 'Essential Blocks',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'fluent_status'                    => [
				'label'   => 'Fluent Forms',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'formidable_forms_status'          => [
				'label'   => 'Formidable Forms',
				'logo'    => 'svg',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'forminator_status'                => [
				'label'   => 'Forminator',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'give_wp_status'                   => [
				'label'   => 'GiveWP',
				'logo'    => 'svg',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'gravity_status'                   => [
				'label'   => 'Gravity Forms',
				'logo'    => 'svg',
				'type'    => 'checkbox',
				'options' => [
					'form'  => __( 'Form Auto-Add', 'hcaptcha-for-forms-and-more' ),
					'embed' => __( 'Form Embed', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'html_forms_status'                => [
				'label'   => 'HTML Forms',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'jetpack_status'                   => [
				'label'   => 'Jetpack',
				'logo'    => 'svg',
				'type'    => 'checkbox',
				'options' => [
					'contact' => __( 'Contact Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'kadence_status'                   => [
				'label'   => 'Kadence',
				'logo'    => 'svg',
				'type'    => 'checkbox',
				'options' => [
					'form'          => __( 'Kadence Form', 'hcaptcha-for-forms-and-more' ),
					'advanced_form' => __( 'Kadence Advanced Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'learn_dash_status'                => [
				'label'   => 'LearnDash LMS',
				'logo'    => 'svg',
				'type'    => 'checkbox',
				'options' => [
					'login'     => __( 'Login Form', 'hcaptcha-for-forms-and-more' ),
					'lost_pass' => __( 'Lost Password Form', 'hcaptcha-for-forms-and-more' ),
					'register'  => __( 'Register Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'login_signup_popup_status'        => [
				'label'   => 'Login Signup Popup',
				'type'    => 'checkbox',
				'options' => [
					'login'    => __( 'Login Form', 'hcaptcha-for-forms-and-more' ),
					'register' => __( 'Register Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'mailchimp_status'                 => [
				'label'   => 'Mailchimp for WP',
				'logo'    => 'svg',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'mailpoet_status'                  => [
				'label'   => 'MailPoet',
				'logo'    => 'svg',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'memberpress_status'               => [
				'label'   => 'MemberPress',
				'logo'    => 'svg',
				'type'    => 'checkbox',
				'options' => [
					'login'    => __( 'Login Form', 'hcaptcha-for-forms-and-more' ),
					'register' => __( 'Register Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'ninja_status'                     => [
				'label'   => 'Ninja Forms',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'otter_status'                     => [
				'label'   => 'Otter Blocks',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'paid_memberships_pro_status'      => [
				'label'   => 'Paid Memberships Pro',
				'logo'    => 'svg',
				'type'    => 'checkbox',
				'options' => [
					'checkout' => __( 'Checkout Form', 'hcaptcha-for-forms-and-more' ),
					'login'    => __( 'Login Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'passster_status'                  => [
				'label'   => 'Passster',
				'type'    => 'checkbox',
				'options' => [
					'protect' => __( 'Protection Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'profile_builder_status'           => [
				'label'   => 'Profile Builder',
				'type'    => 'checkbox',
				'options' => [
					'login'     => __( 'Login Form', 'hcaptcha-for-forms-and-more' ),
					'lost_pass' => __( 'Recover Password Form', 'hcaptcha-for-forms-and-more' ),
					'register'  => __( 'Register Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'quform_status'                    => [
				'label'   => 'Quform',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'sendinblue_status'                => [
				'label'   => 'Brevo',
				'logo'    => 'svg',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'simple_basic_contact_form_status' => [
				'label'   => 'Simple Basic Contact Form',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'simple_download_monitor_status'   => [
				'label'   => 'Simple Download Monitor',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'spectra_status'                   => [
				'label'   => 'Spectra',
				'logo'    => 'svg',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'subscriber_status'                => [
				'label'   => 'Subscriber',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'supportcandy_status'              => [
				'label'   => 'Support Candy',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'theme_my_login_status'            => [
				'label'   => 'Theme My Login',
				'type'    => 'checkbox',
				'options' => [
					'login'     => __( 'Login Form', 'hcaptcha-for-forms-and-more' ),
					'lost_pass' => __( 'Lost Password Form', 'hcaptcha-for-forms-and-more' ),
					'register'  => __( 'Register Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'ultimate_member_status'           => [
				'label'   => 'Ultimate Member',
				'type'    => 'checkbox',
				'options' => [
					'login'     => __( 'Login Form', 'hcaptcha-for-forms-and-more' ),
					'lost_pass' => __( 'Lost Password Form', 'hcaptcha-for-forms-and-more' ),
					'register'  => __( 'Register Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'users_wp_status'                  => [
				'label'   => 'Users WP',
				'type'    => 'checkbox',
				'options' => [
					'forgot'   => __( 'Forgot Password Form', 'hcaptcha-for-forms-and-more' ),
					'login'    => __( 'Login Form', 'hcaptcha-for-forms-and-more' ),
					'register' => __( 'Register Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'woocommerce_status'               => [
				'label'   => 'WooCommerce',
				'type'    => 'checkbox',
				'options' => [
					'checkout'       => __( 'Checkout Form', 'hcaptcha-for-forms-and-more' ),
					'login'          => __( 'Login Form', 'hcaptcha-for-forms-and-more' ),
					'lost_pass'      => __( 'Lost Password Form', 'hcaptcha-for-forms-and-more' ),
					'order_tracking' => __( 'Order Tracking Form', 'hcaptcha-for-forms-and-more' ),
					'register'       => __( 'Register Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'woocommerce_wishlists_status'     => [
				'label'   => 'WooCommerce Wishlists',
				'type'    => 'checkbox',
				'options' => [
					'create_list' => __( 'Create List Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'wordfence_status'                 => [
				'label'   => 'Wordfence',
				'logo'    => 'svg',
				'type'    => 'checkbox',
				'options' => [
					'login' => __( 'Login Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'wpforms_status'                   => [
				'label'   => 'WPForms',
				'type'    => 'checkbox',
				'options' => [
					'form'  => __( 'Form Auto-Add', 'hcaptcha-for-forms-and-more' ),
					'embed' => __( 'Form Embed', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'wpdiscuz_status'                  => [
				'label'   => 'WPDiscuz',
				'type'    => 'checkbox',
				'options' => [
					'comment_form'   => __( 'Comment Form', 'hcaptcha-for-forms-and-more' ),
					'subscribe_form' => __( 'Subscribe Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'wpforo_status'                    => [
				'label'   => 'WPForo',
				'type'    => 'checkbox',
				'options' => [
					'new_topic' => __( 'New Topic Form', 'hcaptcha-for-forms-and-more' ),
					'reply'     => __( 'Reply Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'wp_job_openings_status'           => [
				'label'   => 'WP Job Openings',
				'type'    => 'checkbox',
				'options' => [
					'form' => __( 'Form', 'hcaptcha-for-forms-and-more' ),
				],
			],
		];
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

		return sprintf(
			'<div class="hcaptcha-integrations-logo" data-installed="%1$s">' .
			'<img src="%2$s" alt="%3$s Logo" data-label="%3$s" data-entity="%4$s">' .
			'</div>',
			$form_field['installed'] ? '1' : '0',
			esc_url( constant( 'HCAPTCHA_URL' ) . "/assets/images/logo/$logo_file" ),
			$label,
			$entity
		);
	}

	/**
	 * Setup settings fields.
	 */
	public function setup_fields(): void {
		if ( ! $this->is_options_screen() ) {
			return;
		}

		$installed = [];

		foreach ( hcaptcha()->modules as $module ) {
			if ( $this->plugin_or_theme_installed( $module[1] ) ) {
				$installed[] = $module[0][0];
			}
		}

		$installed = array_unique( $installed );

		$this->form_fields = $this->sort_fields( $this->form_fields );

		foreach ( $this->form_fields as $status => &$form_field ) {
			$form_field['installed'] = in_array( $status, $installed, true );
			$form_field['section']   = ( ! $form_field['installed'] ) || $form_field['disabled']
				? self::SECTION_DISABLED
				: self::SECTION_ENABLED;

			if ( isset( $form_field['label'] ) ) {
				$form_field['label'] = $this->logo( $form_field );
			}
		}

		unset( $form_field );

		parent::setup_fields();
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
	 * Show search box.
	 *
	 * @return void
	 */
	public function search_box(): void {
		?>
		<div id="hcaptcha-integrations-search-wrap">
			<label for="hcaptcha-integrations-search"></label>
			<input
					type="search" id="hcaptcha-integrations-search"
					placeholder="<?php esc_html_e( 'Search plugins and themes...', 'hcaptcha-for-forms-and-more' ); ?>">
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
		if ( self::SECTION_DISABLED === $arguments['id'] ) {
			$this->submit_button();

			?>
			<hr class="hcaptcha-disabled-section">
			<h3><?php esc_html_e( 'Inactive plugins and themes', 'hcaptcha-for-forms-and-more' ); ?></h3>
			<?php

			return;
		}

		$this->print_header();

		?>
		<div id="hcaptcha-message"></div>
		<p>
			<?php esc_html_e( 'Manage integrations with popular plugins such as Contact Form 7, WPForms, Gravity Forms, and more.', 'hcaptcha-for-forms-and-more' ); ?>
		</p>
		<p>
			<?php esc_html_e( 'You can activate and deactivate a plugin by clicking on its logo.', 'hcaptcha-for-forms-and-more' ); ?>
		</p>
		<p>
			<?php
			$shortcode_url   = 'https://wordpress.org/plugins/hcaptcha-for-forms-and-more/#does%20the%20%5Bhcaptcha%5D%20shortcode%20have%20arguments%3F';
			$integration_url = 'https://github.com/hCaptcha/hcaptcha-wordpress-plugin/issues';

			echo wp_kses_post(
				sprintf(
				/* translators: 1: hCaptcha shortcode doc link, 2: integration doc link. */
					__( 'Don\'t see your plugin here? Use the `[hcaptcha]` %1$s or %2$s.', 'hcaptcha-for-forms-and-more' ),
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
		<h3><?php esc_html_e( 'Active plugins and themes', 'hcaptcha-for-forms-and-more' ); ?></h3>
		<?php
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

		wp_localize_script(
			self::HANDLE,
			self::OBJECT,
			[
				'ajaxUrl'            => admin_url( 'admin-ajax.php' ),
				'action'             => self::ACTIVATE_ACTION,
				'nonce'              => wp_create_nonce( self::ACTIVATE_ACTION ),
				/* translators: 1: Plugin name. */
				'activateMsg'        => __( 'Activate %s plugin?', 'hcaptcha-for-forms-and-more' ),
				/* translators: 1: Plugin name. */
				'deactivateMsg'      => __( 'Deactivate %s plugin?', 'hcaptcha-for-forms-and-more' ),
				/* translators: 1: Plugin name. */
				'installMsg'         => __( 'Please install %s plugin manually.', 'hcaptcha-for-forms-and-more' ),
				/* translators: 1: Theme name. */
				'activateThemeMsg'   => __( 'Activate %s theme?', 'hcaptcha-for-forms-and-more' ),
				/* translators: 1: Theme name. */
				'deactivateThemeMsg' => __( 'Deactivate %s theme?', 'hcaptcha-for-forms-and-more' ),
				/* translators: 1: Theme name. */
				'installThemeMsg'    => __( 'Please install %s theme manually.', 'hcaptcha-for-forms-and-more' ),
				'selectThemeMsg'     => __( 'Select theme to activate:', 'hcaptcha-for-forms-and-more' ),
				'onlyOneThemeMsg'    => __( 'Cannot deactivate the only theme on the site.', 'hcaptcha-for-forms-and-more' ),
				'unexpectedErrorMsg' => __( 'Unexpected error.', 'hcaptcha-for-forms-and-more' ),
				'OKBtnText'          => __( 'OK', 'hcaptcha-for-forms-and-more' ),
				'CancelBtnText'      => __( 'Cancel', 'hcaptcha-for-forms-and-more' ),
				'themes'             => $this->get_themes(),
				'defaultTheme'       => $this->get_default_theme(),
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
	 * Ajax action to activate/deactivate plugin/theme.
	 *
	 * @return void
	 */
	public function activate(): void {
		$this->run_checks( self::ACTIVATE_ACTION );

		$activate     = filter_input( INPUT_POST, 'activate', FILTER_VALIDATE_BOOLEAN );
		$this->entity = filter_input( INPUT_POST, 'entity', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$new_theme    = filter_input( INPUT_POST, 'newTheme', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$status       = filter_input( INPUT_POST, 'status', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$status       = str_replace( '-', '_', $status );
		$entity_name  = $this->form_fields[ $status ]['label'] ?? '';

		header_remove( 'Location' );
		http_response_code( 200 );

		if ( 'plugin' === $this->entity ) {
			$entities = [];

			foreach ( hcaptcha()->modules as $module ) {
				if ( $module[0][0] === $status ) {
					$entities[] = (array) $module[1];
				}
			}

			$entities = array_unique( array_merge( [], ...$entities ) );

			$this->process_plugins( $activate, $entities, $entity_name );
		}

		if ( 'theme' === $this->entity ) {
			$theme = $activate ? $entity_name : $new_theme;

			$this->process_theme( $theme );
		}
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
			if ( ! $this->activate_plugins( $plugins ) ) {
				$message = sprintf(
				/* translators: 1: Plugin name. */
					__( 'Error activating %s plugin.', 'hcaptcha-for-forms-and-more' ),
					$plugin_name
				);

				$this->send_json_error( esc_html( $message ) );
			}

			$plugin_names = $this->plugin_names_from_tree( $this->plugins_tree );
			$message      = sprintf(
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
		}

		deactivate_plugins( $plugins );

		$message = sprintf(
		/* translators: 1: Plugin name. */
			__( '%s plugin is deactivated.', 'hcaptcha-for-forms-and-more' ),
			$plugin_name
		);

		$this->send_json_success( esc_html( $message ) );
	}

	/**
	 * Activate a theme.
	 *
	 * @param string $theme Theme name to process.
	 *
	 * @return void
	 */
	protected function process_theme( string $theme ): void {
		$plugins      = self::PLUGIN_DEPENDENCIES[ $theme ] ?? [];
		$plugin_names = [];

		/**
		 * Activate dependent plugins before activating the theme.
		 * The activate_plugins() function will activate the first available plugin only.
		 * That is why we should cycle through the list of dependencies.
		 */
		foreach ( $plugins as $plugin ) {
			$this->activate_plugins( [ $plugin ] );
			$plugin_names[] = $this->plugin_names_from_tree( $this->plugins_tree );
		}

		$plugin_names = array_merge( [], ...$plugin_names );

		if ( ! $this->activate_theme( $theme ) ) {
			$message = sprintf(
			/* translators: 1: Theme name. */
				__( 'Error activating %s theme.', 'hcaptcha-for-forms-and-more' ),
				$theme
			);

			$this->send_json_error( esc_html( $message ) );
		}

		$message = sprintf(
		/* translators: 1: Theme name. */
			__( '%s theme is activated.', 'hcaptcha-for-forms-and-more' ),
			$theme
		);

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

		$this->send_json_success( esc_html( $message ) );
	}

	/**
	 * Activate plugins.
	 *
	 * We activate the first available plugin in the list only,
	 * assuming that Pro plugins are placed earlier in the list.
	 *
	 * @param array $plugins Plugins to activate.
	 *
	 * @return bool
	 */
	protected function activate_plugins( array $plugins ): bool {
		foreach ( $plugins as $plugin ) {
			$this->plugins_tree = $this->build_plugins_tree( $plugin );
			$result             = $this->activate_plugin_tree( $this->plugins_tree );

			if ( ! is_wp_error( $result ) ) {
				// Activate the first available plugin only.
				return true;
			}
		}

		return false;
	}

	/**
	 * Activate plugins.
	 *
	 * @param array $node Node of the plugin tree.
	 *
	 * @return null|true|WP_Error
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

		$node['result'] = $this->activate_plugin( $node['plugin'] );

		return $node['result'];
	}

	/**
	 * Activate plugin.
	 *
	 * @param string $plugin Path to the plugin file relative to the plugins' directory.
	 *
	 * @return null|true|WP_Error Null on success, WP_Error on failure. True if the plugin is already active.
	 */
	protected function activate_plugin( string $plugin ) {

		if ( is_plugin_active( $plugin ) ) {
			return true;
		}

		// Do not allow redirect during plugin activation.
		add_action( 'activated_plugin', [ $this, 'activated_plugin_action' ], PHP_INT_MIN );

		ob_start();
		// Null on success, WP_Error on failure.
		$result = activate_plugin( $plugin );
		ob_end_clean();

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
		$dependencies = $this->plugin_dependencies( $plugin );
		$tree         = [
			'plugin'   => $plugin,
			'children' => [],
		];

		foreach ( $dependencies as $dependency ) {
			$tree['children'][] = $this->build_plugins_tree( $dependency );
		}

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
		$plugin_headers   = get_plugin_data( constant( 'WP_PLUGIN_DIR' ) . '/' . $plugin );
		$requires_plugins = $plugin_headers['RequiresPlugins'] ?? '';
		$wp_dependencies  = $this->plugin_dirs_to_slugs(
			array_filter( array_map( 'trim', explode( ',', $requires_plugins ) ) )
		);
		$dependencies     = (array) ( self::PLUGIN_DEPENDENCIES[ $plugin ] ?? [] );

		return array_merge( $wp_dependencies, $dependencies );
	}

	/**
	 * Convert plugin directories to slugs.
	 *
	 * @param array $dirs Plugin directories.
	 *
	 * @return array
	 */
	protected function plugin_dirs_to_slugs( array $dirs ): array {
		if ( ! $dirs ) {
			return $dirs;
		}

		$slugs = array_keys( $this->plugins );

		foreach ( $dirs as &$dir ) {
			$slug = preg_grep( "#^$dir/#", $slugs );

			if ( $slug ) {
				$dir = reset( $slug );
			}
		}

		return $dirs;
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

		if ( null !== $node['result'] ) {
			return array_unique( array_merge( [], $plugin_names ) );
		}

		$status = '';

		foreach ( hcaptcha()->modules as $module ) {
			if ( $module[1] === $node['plugin'] ) {
				$status = $module[0][0];

				break;
			}
		}

		$plugin_name = $this->form_fields[ $status ]['label'] ?? '';

		if ( ! $plugin_name ) {
			$plugin_data = get_plugin_data( constant( 'WP_PLUGIN_DIR' ) . '/' . $node['plugin'] );
			$plugin_name = $plugin_data['Name'] ?? '';
		}

		return array_unique( array_merge( [ $plugin_name ], $plugin_names ) );
	}

	/**
	 * Activate theme.
	 *
	 * @param string $theme Theme to activate.
	 *
	 * @return bool
	 */
	protected function activate_theme( string $theme ): bool {
		if ( ! wp_get_theme( $theme )->exists() ) {
			return false;
		}

		ob_start();

		switch_theme( $theme );

		ob_end_clean();

		return true;
	}

	/**
	 * Send json success.
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
	public function get_themes(): array {
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
	 */
	public function get_default_theme(): string {
		$core_default_theme_obj = WP_Theme::get_core_default_theme();

		return $core_default_theme_obj ? $core_default_theme_obj->get_stylesheet() : '';
	}
}
