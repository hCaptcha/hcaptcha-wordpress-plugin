<?php
/**
 * General class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Settings;

use HCaptcha\Admin\Notifications;
use HCaptcha\Admin\OnboardingWizard;
use HCaptcha\AntiSpam\AntiSpam;
use HCaptcha\Helpers\API;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Helpers\Request;
use HCaptcha\Main;
use HCaptcha\MigrationWizard\DetectionResult;
use HCaptcha\MigrationWizard\MigrationWizard;
use KAGG\Settings\Abstracts\SettingsBase;

/**
 * Class General
 *
 * Settings page "General".
 */
class General extends PluginSettingsBase {

	/**
	 * Dialog scripts and style handle.
	 */
	public const DIALOG_HANDLE = 'kagg-dialog';

	/**
	 * Admin script handle.
	 */
	public const HANDLE = 'hcaptcha-general';

	/**
	 * Script localization object.
	 */
	public const OBJECT = 'HCaptchaGeneralObject';

	/**
	 * Check config ajax action.
	 */
	public const CHECK_CONFIG_ACTION = 'hcaptcha-general-check-config';

	/**
	 * Keys section id.
	 */
	public const SECTION_KEYS = 'keys';

	/**
	 * Appearance section id.
	 */
	public const SECTION_APPEARANCE = 'appearance';

	/**
	 * Custom section id.
	 */
	public const SECTION_CUSTOM = 'custom';

	/**
	 * Enterprise section id.
	 */
	public const SECTION_ENTERPRISE = 'enterprise';

	/**
	 * Content section id.
	 */
	public const SECTION_CONTENT = 'content';

	/**
	 * Another section id.
	 */
	public const SECTION_OTHER = 'other';

	/**
	 * Statistics section id.
	 */
	public const SECTION_STATISTICS = 'statistics';

	/**
	 * Live mode.
	 */
	public const MODE_LIVE = 'live';

	/**
	 * Test publisher mode.
	 */
	public const MODE_TEST_PUBLISHER = 'test:publisher';

	/**
	 * Test enterprise safe end user mode.
	 */
	public const MODE_TEST_ENTERPRISE_SAFE_END_USER = 'test:enterprise_safe_end_user';

	/**
	 * Test enterprise bot detected mode.
	 */
	public const MODE_TEST_ENTERPRISE_BOT_DETECTED = 'test:enterprise_bot_detected';

	/**
	 * Test publisher mode site key.
	 */
	public const MODE_TEST_PUBLISHER_SITE_KEY = '10000000-ffff-ffff-ffff-000000000001';

	/**
	 * Test enterprise safe end user mode site key.
	 */
	public const MODE_TEST_ENTERPRISE_SAFE_END_USER_SITE_KEY = '20000000-ffff-ffff-ffff-000000000002';

	/**
	 * Test enterprise bot detected mode site key.
	 */
	public const MODE_TEST_ENTERPRISE_BOT_DETECTED_SITE_KEY = '30000000-ffff-ffff-ffff-000000000003';

	/**
	 * Test secret key.
	 */
	public const MODE_TEST_SECRET_KEY = '0' . 'x' . '0000000000000000000000000000000000000000'; // phpcs:ignore Generic.Strings.UnnecessaryStringConcat.Found

	/**
	 * The 'check config' form id.
	 */
	public const CHECK_CONFIG_FORM_ID = 'check-config';

	/**
	 * Notifications class instance.
	 *
	 * @var Notifications|null
	 */
	protected ?Notifications $notifications = null;

	/**
	 * Get page title.
	 *
	 * @return string
	 */
	protected function page_title(): string {
		return __( 'General', 'hcaptcha-for-forms-and-more' );
	}

	/**
	 * Get section title.
	 *
	 * @return string
	 */
	protected function section_title(): string {
		return 'general';
	}

	/**
	 * Init class.
	 *
	 * @return void
	 */
	public function init(): void {
		new OnboardingWizard( $this );

		parent::init();
	}

	/**
	 * Init class hooks.
	 *
	 * @return void
	 */
	protected function init_hooks(): void {
		parent::init_hooks();

		$hcaptcha = hcaptcha();

		if ( wp_doing_ajax() ) {
			// We need ajax actions in the Notifications and Onboarding class.
			$this->init_notifications();
		} else {
			/**
			 * The current class loaded early on plugins_loaded.
			 * Init Notifications, Onboarding, and Auto Setup later, when the Settings class is ready.
			 * Also, we need to check if we are on the General screen.
			 */
			add_action( 'current_screen', [ $this, 'maybe_handle_onboarding_auto_setup' ], 5 );
			add_action( 'current_screen', [ $this, 'init_notifications' ] );
		}

		add_action( 'admin_head', [ $hcaptcha, 'print_inline_styles' ] );
		add_action( 'admin_print_footer_scripts', [ $hcaptcha, 'print_footer_scripts' ], 0 );

		add_filter( 'kagg_settings_fields', [ $this, 'settings_fields' ] );
		add_action( 'wp_ajax_' . self::CHECK_CONFIG_ACTION, [ $this, 'check_config' ] );

		add_filter( 'pre_update_option_' . $this->option_name(), [ $this, 'maybe_send_stats' ], 20, 2 );
		add_filter( 'pre_update_site_option_' . $this->option_name(), [ $this, 'maybe_send_stats' ], 20, 2 );
	}

	/**
	 * Apply onboarding automatic setup on the General page.
	 *
	 * @return void
	 */
	public function maybe_handle_onboarding_auto_setup(): void {
		if ( ! $this->is_options_screen() ) {
			return;
		}

		$auto_setup = Request::filter_input( INPUT_GET, OnboardingWizard::AUTO_SETUP_PARAM );

		if ( '1' !== $auto_setup ) {
			return;
		}

		if ( ! OnboardingWizard::verify_request( OnboardingWizard::AUTO_SETUP_ACTION ) ) {
			return;
		}

		$this->update_option( 'force', [ 'on' ] );
		$this->update_option( 'honeypot', [ 'on' ] );
		$this->update_option( 'set_min_submit_time', [ 'on' ] );

		if ( $this->should_enable_onboarding_antispam() ) {
			$this->update_option( 'antispam', [ 'on' ] );
		}

		$this->auto_migration();

		$this->update_option( OnboardingWizard::OPTION_NAME, 'step 8' );

		$url = $this->tab_url( $this );

		$this->redirect_after_onboarding_auto_setup( $url );
	}

	/**
	 * Apply the recommended migration wizard setup during onboarding auto-setup.
	 *
	 * @return void
	 */
	protected function auto_migration(): void {
		$migration_wizard = $this->create_migration_wizard();
		$scan_data        = $migration_wizard->scan();
		$surfaces         = $this->get_migration_surfaces( $scan_data );

		if ( empty( $surfaces ) ) {
			return;
		}

		$migration_wizard->apply( $surfaces );
	}

	/**
	 * Create a migration wizard instance.
	 *
	 * @return MigrationWizard
	 */
	protected function create_migration_wizard(): MigrationWizard {
		// @codeCoverageIgnoreStart
		return new MigrationWizard();
		// @codeCoverageIgnoreEnd
	}

	/**
	 * Get migration surfaces suitable for onboarding auto-setup.
	 *
	 * @param array $scan_data Scan data returned by the migration wizard.
	 *
	 * @return array
	 */
	protected function get_migration_surfaces( array $scan_data ): array {
		$surfaces        = [];
		$already_enabled = (array) ( $scan_data['already_enabled'] ?? [] );

		foreach ( (array) ( $scan_data['results'] ?? [] ) as $result ) {
			if ( ! is_array( $result ) ) {
				continue;
			}

			$surface_id = $result['surface'] ?? '';

			if (
				empty( $result['is_migratable'] ) ||
				DetectionResult::CONFIDENCE_LOW === ( $result['confidence'] ?? '' ) ||
				in_array( $surface_id, $already_enabled, true )
			) {
				continue;
			}

			$surfaces[] = [
				'surface'               => $surface_id,
				'hcaptcha_option_key'   => $result['hcaptcha_option_key'] ?? '',
				'hcaptcha_option_value' => $result['hcaptcha_option_value'] ?? '',
			];
		}

		return $surfaces;
	}

	/**
	 * Whether onboarding automatic setup can enable the anti-spam check.
	 *
	 * @return bool
	 */
	protected function should_enable_onboarding_antispam(): bool {
		$provider = (string) $this->get( 'antispam_provider' );

		if ( ! $provider ) {
			return false;
		}

		return in_array( $provider, AntiSpam::get_configured_providers(), true );
	}

	/**
	 * Redirect after onboarding automatic setup changes are applied.
	 *
	 * @param string $url Redirect URL.
	 *
	 * @return void
	 */
	protected function redirect_after_onboarding_auto_setup( string $url ): void {
		// @codeCoverageIgnoreStart
		wp_safe_redirect( $url );

		exit;
		// @codeCoverageIgnoreEnd
	}

	/**
	 * Init notifications.
	 *
	 * @return void
	 */
	public function init_notifications(): void {
		if ( ! ( wp_doing_ajax() || $this->is_options_screen() ) ) {
			return;
		}

		$this->notifications = new Notifications();
		$this->notifications->init();
	}

	/**
	 * Init form fields.
	 *
	 * @return void
	 */
	public function init_form_fields(): void {
		$this->form_fields = [
			'site_key'             => [
				'label'        => __( 'Site Key', 'hcaptcha-for-forms-and-more' ),
				'type'         => 'text',
				'autocomplete' => 'nickname',
				'lp_ignore'    => 'true',
				'section'      => self::SECTION_KEYS,
				'helper'       => __( 'To fill out the site key, set Mode to Live.', 'hcaptcha-for-forms-and-more' ),
			],
			'secret_key'           => [
				'label'   => __( 'Secret Key', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'password',
				'section' => self::SECTION_KEYS,
				'helper'  => __( 'To fill out the secret key, set Mode to Live.', 'hcaptcha-for-forms-and-more' ),
			],
			'sample_hcaptcha'      => [
				'label'   => __( 'Active hCaptcha to Check Site Config', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'hcaptcha',
				'section' => self::SECTION_KEYS,
			],
			'check_config'         => [
				'label'   => __( 'Check Site Config', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'button',
				'text'    => __( 'Check', 'hcaptcha-for-forms-and-more' ),
				'section' => self::SECTION_KEYS,
			],
			'reset_notifications'  => [
				'label'   => __( 'Reset Notifications', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'button',
				'text'    => __( 'Reset', 'hcaptcha-for-forms-and-more' ),
				'section' => self::SECTION_KEYS,
			],
			'theme'                => [
				'label'   => __( 'Theme', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'select',
				'section' => self::SECTION_APPEARANCE,
				'options' => [
					'light' => __( 'Light', 'hcaptcha-for-forms-and-more' ),
					'dark'  => __( 'Dark', 'hcaptcha-for-forms-and-more' ),
					'auto'  => __( 'Auto', 'hcaptcha-for-forms-and-more' ),
				],
				'helper'  => __( 'Select hCaptcha theme.', 'hcaptcha-for-forms-and-more' ),
			],
			'size'                 => [
				'label'   => __( 'Size', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'select',
				'section' => self::SECTION_APPEARANCE,
				'options' => [
					'normal'    => __( 'Normal', 'hcaptcha-for-forms-and-more' ),
					'compact'   => __( 'Compact', 'hcaptcha-for-forms-and-more' ),
					'invisible' => __( 'Invisible', 'hcaptcha-for-forms-and-more' ),
				],
				'helper'  => __( 'Select hCaptcha size.', 'hcaptcha-for-forms-and-more' ),
			],
			'language'             => [
				'label'   => __( 'Language', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'select',
				'section' => self::SECTION_APPEARANCE,
				'options' => [
					''      => __( '--- Auto-Detect ---', 'hcaptcha-for-forms-and-more' ),
					'af'    => __( 'Afrikaans', 'hcaptcha-for-forms-and-more' ),
					'sq'    => __( 'Albanian', 'hcaptcha-for-forms-and-more' ),
					'am'    => __( 'Amharic', 'hcaptcha-for-forms-and-more' ),
					'ar'    => __( 'Arabic', 'hcaptcha-for-forms-and-more' ),
					'hy'    => __( 'Armenian', 'hcaptcha-for-forms-and-more' ),
					'az'    => __( 'Azerbaijani', 'hcaptcha-for-forms-and-more' ),
					'eu'    => __( 'Basque', 'hcaptcha-for-forms-and-more' ),
					'be'    => __( 'Belarusian', 'hcaptcha-for-forms-and-more' ),
					'bn'    => __( 'Bengali', 'hcaptcha-for-forms-and-more' ),
					'bg'    => __( 'Bulgarian', 'hcaptcha-for-forms-and-more' ),
					'bs'    => __( 'Bosnian', 'hcaptcha-for-forms-and-more' ),
					'my'    => __( 'Burmese', 'hcaptcha-for-forms-and-more' ),
					'ca'    => __( 'Catalan', 'hcaptcha-for-forms-and-more' ),
					'ceb'   => __( 'Cebuano', 'hcaptcha-for-forms-and-more' ),
					'zh'    => __( 'Chinese', 'hcaptcha-for-forms-and-more' ),
					'zh-CN' => __( 'Chinese Simplified', 'hcaptcha-for-forms-and-more' ),
					'zh-TW' => __( 'Chinese Traditional', 'hcaptcha-for-forms-and-more' ),
					'co'    => __( 'Corsican', 'hcaptcha-for-forms-and-more' ),
					'hr'    => __( 'Croatian', 'hcaptcha-for-forms-and-more' ),
					'cs'    => __( 'Czech', 'hcaptcha-for-forms-and-more' ),
					'da'    => __( 'Danish', 'hcaptcha-for-forms-and-more' ),
					'nl'    => __( 'Dutch', 'hcaptcha-for-forms-and-more' ),
					'en'    => __( 'English', 'hcaptcha-for-forms-and-more' ),
					'eo'    => __( 'Esperanto', 'hcaptcha-for-forms-and-more' ),
					'et'    => __( 'Estonian', 'hcaptcha-for-forms-and-more' ),
					'fa'    => __( 'Persian', 'hcaptcha-for-forms-and-more' ),
					'fi'    => __( 'Finnish', 'hcaptcha-for-forms-and-more' ),
					'fr'    => __( 'French', 'hcaptcha-for-forms-and-more' ),
					'fy'    => __( 'Frisian', 'hcaptcha-for-forms-and-more' ),
					'gd'    => __( 'Gaelic', 'hcaptcha-for-forms-and-more' ),
					'gl'    => __( 'Galician', 'hcaptcha-for-forms-and-more' ),
					'ka'    => __( 'Georgian', 'hcaptcha-for-forms-and-more' ),
					'de'    => __( 'German', 'hcaptcha-for-forms-and-more' ),
					'el'    => __( 'Greek', 'hcaptcha-for-forms-and-more' ),
					'gu'    => __( 'Gujarati', 'hcaptcha-for-forms-and-more' ),
					'ht'    => __( 'Haitian', 'hcaptcha-for-forms-and-more' ),
					'ha'    => __( 'Hausa', 'hcaptcha-for-forms-and-more' ),
					'haw'   => __( 'Hawaiian', 'hcaptcha-for-forms-and-more' ),
					'he'    => __( 'Hebrew', 'hcaptcha-for-forms-and-more' ),
					'hi'    => __( 'Hindi', 'hcaptcha-for-forms-and-more' ),
					'hmn'   => __( 'Hmong', 'hcaptcha-for-forms-and-more' ),
					'hu'    => __( 'Hungarian', 'hcaptcha-for-forms-and-more' ),
					'is'    => __( 'Icelandic', 'hcaptcha-for-forms-and-more' ),
					'ig'    => __( 'Igbo', 'hcaptcha-for-forms-and-more' ),
					'id'    => __( 'Indonesian', 'hcaptcha-for-forms-and-more' ),
					'ga'    => __( 'Irish', 'hcaptcha-for-forms-and-more' ),
					'it'    => __( 'Italian', 'hcaptcha-for-forms-and-more' ),
					'ja'    => __( 'Japanese', 'hcaptcha-for-forms-and-more' ),
					'jw'    => __( 'Javanese', 'hcaptcha-for-forms-and-more' ),
					'kn'    => __( 'Kannada', 'hcaptcha-for-forms-and-more' ),
					'kk'    => __( 'Kazakh', 'hcaptcha-for-forms-and-more' ),
					'km'    => __( 'Khmer', 'hcaptcha-for-forms-and-more' ),
					'rw'    => __( 'Kinyarwanda', 'hcaptcha-for-forms-and-more' ),
					'ky'    => __( 'Kirghiz', 'hcaptcha-for-forms-and-more' ),
					'ko'    => __( 'Korean', 'hcaptcha-for-forms-and-more' ),
					'ku'    => __( 'Kurdish', 'hcaptcha-for-forms-and-more' ),
					'lo'    => __( 'Lao', 'hcaptcha-for-forms-and-more' ),
					'la'    => __( 'Latin', 'hcaptcha-for-forms-and-more' ),
					'lv'    => __( 'Latvian', 'hcaptcha-for-forms-and-more' ),
					'lt'    => __( 'Lithuanian', 'hcaptcha-for-forms-and-more' ),
					'lb'    => __( 'Luxembourgish', 'hcaptcha-for-forms-and-more' ),
					'mk'    => __( 'Macedonian', 'hcaptcha-for-forms-and-more' ),
					'mg'    => __( 'Malagasy', 'hcaptcha-for-forms-and-more' ),
					'ms'    => __( 'Malay', 'hcaptcha-for-forms-and-more' ),
					'ml'    => __( 'Malayalam', 'hcaptcha-for-forms-and-more' ),
					'mt'    => __( 'Maltese', 'hcaptcha-for-forms-and-more' ),
					'mi'    => __( 'Maori', 'hcaptcha-for-forms-and-more' ),
					'mr'    => __( 'Marathi', 'hcaptcha-for-forms-and-more' ),
					'mn'    => __( 'Mongolian', 'hcaptcha-for-forms-and-more' ),
					'ne'    => __( 'Nepali', 'hcaptcha-for-forms-and-more' ),
					'no'    => __( 'Norwegian', 'hcaptcha-for-forms-and-more' ),
					'ny'    => __( 'Nyanja', 'hcaptcha-for-forms-and-more' ),
					'or'    => __( 'Oriya', 'hcaptcha-for-forms-and-more' ),
					'pl'    => __( 'Polish', 'hcaptcha-for-forms-and-more' ),
					'pt'    => __( 'Portuguese', 'hcaptcha-for-forms-and-more' ),
					'ps'    => __( 'Pashto', 'hcaptcha-for-forms-and-more' ),
					'pa'    => __( 'Punjabi', 'hcaptcha-for-forms-and-more' ),
					'ro'    => __( 'Romanian', 'hcaptcha-for-forms-and-more' ),
					'ru'    => __( 'Russian', 'hcaptcha-for-forms-and-more' ),
					'sm'    => __( 'Samoan', 'hcaptcha-for-forms-and-more' ),
					'sn'    => __( 'Shona', 'hcaptcha-for-forms-and-more' ),
					'sd'    => __( 'Sindhi', 'hcaptcha-for-forms-and-more' ),
					'si'    => __( 'Sinhala', 'hcaptcha-for-forms-and-more' ),
					'sr'    => __( 'Serbian', 'hcaptcha-for-forms-and-more' ),
					'sk'    => __( 'Slovak', 'hcaptcha-for-forms-and-more' ),
					'sl'    => __( 'Slovenian', 'hcaptcha-for-forms-and-more' ),
					'so'    => __( 'Somali', 'hcaptcha-for-forms-and-more' ),
					'st'    => __( 'Southern Sotho', 'hcaptcha-for-forms-and-more' ),
					'es'    => __( 'Spanish', 'hcaptcha-for-forms-and-more' ),
					'su'    => __( 'Sundanese', 'hcaptcha-for-forms-and-more' ),
					'sw'    => __( 'Swahili', 'hcaptcha-for-forms-and-more' ),
					'sv'    => __( 'Swedish', 'hcaptcha-for-forms-and-more' ),
					'tl'    => __( 'Tagalog', 'hcaptcha-for-forms-and-more' ),
					'tg'    => __( 'Tajik', 'hcaptcha-for-forms-and-more' ),
					'ta'    => __( 'Tamil', 'hcaptcha-for-forms-and-more' ),
					'tt'    => __( 'Tatar', 'hcaptcha-for-forms-and-more' ),
					'te'    => __( 'Telugu', 'hcaptcha-for-forms-and-more' ),
					'th'    => __( 'Thai', 'hcaptcha-for-forms-and-more' ),
					'tr'    => __( 'Turkish', 'hcaptcha-for-forms-and-more' ),
					'tk'    => __( 'Turkmen', 'hcaptcha-for-forms-and-more' ),
					'ug'    => __( 'Uyghur', 'hcaptcha-for-forms-and-more' ),
					'uk'    => __( 'Ukrainian', 'hcaptcha-for-forms-and-more' ),
					'ur'    => __( 'Urdu', 'hcaptcha-for-forms-and-more' ),
					'uz'    => __( 'Uzbek', 'hcaptcha-for-forms-and-more' ),
					'vi'    => __( 'Vietnamese', 'hcaptcha-for-forms-and-more' ),
					'cy'    => __( 'Welsh', 'hcaptcha-for-forms-and-more' ),
					'xh'    => __( 'Xhosa', 'hcaptcha-for-forms-and-more' ),
					'yi'    => __( 'Yiddish', 'hcaptcha-for-forms-and-more' ),
					'yo'    => __( 'Yoruba', 'hcaptcha-for-forms-and-more' ),
					'zu'    => __( 'Zulu', 'hcaptcha-for-forms-and-more' ),
				],
				'helper'  => __(
					"By default, hCaptcha will automatically detect the user's locale and localize widgets accordingly.",
					'hcaptcha-for-forms-and-more'
				),
			],
			'mode'                 => [
				'label'   => __( 'Mode', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'select',
				'section' => self::SECTION_APPEARANCE,
				// phpcs:disable WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned, WordPress.Arrays.MultipleStatementAlignment.LongIndexSpaceBeforeDoubleArrow
				'options' => [
					self::MODE_LIVE                          => __( 'Live', 'hcaptcha-for-forms-and-more' ),
					self::MODE_TEST_PUBLISHER                => __( 'Test: Publisher Account', 'hcaptcha-for-forms-and-more' ),
					self::MODE_TEST_ENTERPRISE_SAFE_END_USER => __( 'Test: Enterprise Account (Safe End User)', 'hcaptcha-for-forms-and-more' ),
					self::MODE_TEST_ENTERPRISE_BOT_DETECTED  => __( 'Test: Enterprise Account (Bot Detected)', 'hcaptcha-for-forms-and-more' ),
				],
				// phpcs:enable WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned, WordPress.Arrays.MultipleStatementAlignment.LongIndexSpaceBeforeDoubleArrow
				'default' => self::MODE_TEST_PUBLISHER,
				'helper'  => __(
					'Select live or test mode. In test mode, predefined keys are used.',
					'hcaptcha-for-forms-and-more'
				),
			],
			'force'                => [
				'label'   => __( 'Force hCaptcha', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'checkbox',
				'section' => self::SECTION_APPEARANCE,
				'options' => [
					'on' => __( 'Force', 'hcaptcha-for-forms-and-more' ),
				],
				'helper'  => __( 'Force hCaptcha check before submit.', 'hcaptcha-for-forms-and-more' ),
			],
			'menu_position'        => [
				'label'   => __( 'Tabs Menu Under Settings', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'checkbox',
				'section' => self::SECTION_APPEARANCE,
				'options' => [
					'on' => __( 'Tabs', 'hcaptcha-for-forms-and-more' ),
				],
				'helper'  => __( 'When on, the hCaptcha admin menu is placed under Settings.', 'hcaptcha-for-forms-and-more' ),
			],
			'custom_themes'        => [
				'label'   => __( 'Custom Themes', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'checkbox',
				'section' => self::SECTION_CUSTOM,
				'options' => [
					'on' => __( 'Enable Custom Themes', 'hcaptcha-for-forms-and-more' ),
				],
				'helper'  => sprintf(
					/* translators: 1: hCaptcha Pro link, 2: hCaptcha Enterprise link. */
					__( 'Note: only works on hCaptcha %1$s and %2$s site keys.', 'hcaptcha-for-forms-and-more' ),
					sprintf(
						'<a href="https://www.hcaptcha.com/pro?utm_source=wordpress&utm_medium=wpplugin&utm_campaign=upgrade" target="_blank">%s</a>',
						__( 'Pro', 'hcaptcha-for-forms-and-more' )
					),
					sprintf(
						'<a href="https://www.hcaptcha.com/enterprise?utm_source=wordpress&utm_medium=wpplugin&utm_campaign=upgrade" target="_blank">%s</a>',
						__( 'Enterprise', 'hcaptcha-for-forms-and-more' )
					)
				),
			],
			'config_params'        => [
				'label'   => __( 'Advanced Theme Editor', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'textarea',
				'section' => self::SECTION_CUSTOM,
			],
			'api_host'             => [
				'label'   => __( 'API Host', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'text',
				'section' => self::SECTION_ENTERPRISE,
				'default' => Main::API_HOST,
				'helper'  => __( 'See Enterprise docs.', 'hcaptcha-for-forms-and-more' ),
			],
			'asset_host'           => [
				'label'   => __( 'Asset Host', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'text',
				'section' => self::SECTION_ENTERPRISE,
				'helper'  => __( 'See Enterprise docs.', 'hcaptcha-for-forms-and-more' ),
			],
			'endpoint'             => [
				'label'   => __( 'Endpoint', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'text',
				'section' => self::SECTION_ENTERPRISE,
				'helper'  => __( 'See Enterprise docs.', 'hcaptcha-for-forms-and-more' ),
			],
			'host'                 => [
				'label'   => __( 'Host', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'text',
				'section' => self::SECTION_ENTERPRISE,
				'helper'  => __( 'See Enterprise docs.', 'hcaptcha-for-forms-and-more' ),
			],
			'image_host'           => [
				'label'   => __( 'Image Host', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'text',
				'section' => self::SECTION_ENTERPRISE,
				'helper'  => __( 'See Enterprise docs.', 'hcaptcha-for-forms-and-more' ),
			],
			'report_api'           => [
				'label'   => __( 'Report API', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'text',
				'section' => self::SECTION_ENTERPRISE,
				'helper'  => __( 'See Enterprise docs.', 'hcaptcha-for-forms-and-more' ),
			],
			'sentry'               => [
				'label'   => __( 'Sentry', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'text',
				'section' => self::SECTION_ENTERPRISE,
				'helper'  => __( 'See Enterprise docs.', 'hcaptcha-for-forms-and-more' ),
			],
			'backend'              => [
				'label'   => __( 'Backend', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'text',
				'section' => self::SECTION_ENTERPRISE,
				'default' => Main::VERIFY_HOST,
				'helper'  => __( 'See Enterprise docs.', 'hcaptcha-for-forms-and-more' ),
			],
			'protect_content'      => [
				'label'   => __( 'Content Settings', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'checkbox',
				'section' => self::SECTION_CONTENT,
				'options' => [
					'on' => __( 'Protect Content', 'hcaptcha-for-forms-and-more' ),
				],
				'helper'  => __( 'Protect site content from bots with hCaptcha.', 'hcaptcha-for-forms-and-more' ),
			],
			'protected_urls'       => [
				'label'   => __( 'Protected URLs', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'textarea',
				'section' => self::SECTION_CONTENT,
				'helper'  => __( 'Protect content of listed URLs. Please specify one URL per line. You may use regular expressions.', 'hcaptcha-for-forms-and-more' ),
			],
			'delay'                => [
				'label'   => __( 'Delay Showing hCaptcha, ms', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'number',
				'section' => self::SECTION_OTHER,
				'default' => -100,
				'min'     => -100,
				'step'    => 100,
				'helper'  => __( 'Delay time for loading the hCaptcha API script. Any negative value will prevent the API script from loading until user interaction: mouseenter, click, scroll or touch. This significantly improves Google Pagespeed Insights score.', 'hcaptcha-for-forms-and-more' ),
			],
			'off_when_logged_in'   => [
				'label'   => __( 'Other Settings', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'checkbox',
				'section' => self::SECTION_OTHER,
				'options' => [
					'on' => __( 'Turn Off When Logged In', 'hcaptcha-for-forms-and-more' ),
				],
				'helper'  => __( 'Do not show hCaptcha to logged-in users.', 'hcaptcha-for-forms-and-more' ),
			],
			'recaptcha_compat_off' => [
				'type'    => 'checkbox',
				'section' => self::SECTION_OTHER,
				'options' => [
					'on' => __( 'Disable reCAPTCHA Compatibility', 'hcaptcha-for-forms-and-more' ),
				],
				'helper'  => __( 'Use if including both hCaptcha and reCAPTCHA on the same page.', 'hcaptcha-for-forms-and-more' ),
			],
			'cleanup_on_uninstall' => [
				'type'    => 'checkbox',
				'section' => self::SECTION_OTHER,
				'options' => [
					'on' => __( 'Remove Data on Uninstall', 'hcaptcha-for-forms-and-more' ),
				],
				'helper'  => __( 'When enabled, all plugin data will be removed when uninstalling the plugin.', 'hcaptcha-for-forms-and-more' ),
			],
			self::NETWORK_WIDE     => [
				'type'    => 'checkbox',
				'section' => self::SECTION_OTHER,
				'options' => [
					'on' => __( 'Use network-wide settings', 'hcaptcha-for-forms-and-more' ),
				],
				'helper'  => __( 'On multisite, use same settings for all sites of the network.', 'hcaptcha-for-forms-and-more' ),
			],
			'statistics'           => [
				'label'   => __( 'Statistics', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'checkbox',
				'section' => self::SECTION_STATISTICS,
				'options' => [
					'on' => __( 'Enable Statistics', 'hcaptcha-for-forms-and-more' ),
				],
				'helper'  => __( 'By turning the statistics on, you agree to the collection of non-personal data to improve the plugin.', 'hcaptcha-for-forms-and-more' ),
			],
			'anonymous'            => [
				'type'    => 'checkbox',
				'section' => self::SECTION_STATISTICS,
				'options' => [
					'on' => __( 'Collect Anonymously', 'hcaptcha-for-forms-and-more' ),
				],
				'default' => 'on',
				'helper'  => __( 'Store collected IP and User Agent locally as hashed values to conform to GDPR requirements.', 'hcaptcha-for-forms-and-more' ),
			],
			'collect_ip'           => [
				'label'   => __( 'Collection', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'checkbox',
				'section' => self::SECTION_STATISTICS,
				'options' => [
					'on' => __( 'Collect IP', 'hcaptcha-for-forms-and-more' ),
				],
				'helper'  => __( 'Allow collecting of IP addresses from which forms were sent.', 'hcaptcha-for-forms-and-more' ),
			],
			'collect_ua'           => [
				'type'    => 'checkbox',
				'section' => self::SECTION_STATISTICS,
				'options' => [
					'on' => __( 'Collect User Agent', 'hcaptcha-for-forms-and-more' ),
				],
				'helper'  => __( 'Allow collecting of User Agent headers of users sending forms.', 'hcaptcha-for-forms-and-more' ),
			],
		];

		if ( ! is_multisite() ) {
			unset( $this->form_fields[ self::NETWORK_WIDE ] );
		}
	}

	/**
	 * Setup settings fields.
	 */
	public function setup_fields(): void {
		if ( ! $this->is_options_screen() ) {
			return;
		}

		$settings = hcaptcha()->settings();

		if ( ! $settings ) {
			// @codeCoverageIgnoreStart
			parent::setup_fields();

			return;
			// @codeCoverageIgnoreEnd
		}

		$license = $settings->get_license();

		if ( 'free' === $license ) {
			$this->form_fields['custom_themes']['disabled'] = true;
			$this->form_fields['api_host']['disabled']      = true;
			$this->form_fields['asset_host']['disabled']    = true;
			$this->form_fields['endpoint']['disabled']      = true;
			$this->form_fields['host']['disabled']          = true;
			$this->form_fields['image_host']['disabled']    = true;
			$this->form_fields['report_api']['disabled']    = true;
			$this->form_fields['sentry']['disabled']        = true;
			$this->form_fields['backend']['disabled']       = true;
		}

		parent::setup_fields();
	}

	/**
	 * Section callback.
	 *
	 * @param array $arguments Section arguments.
	 */
	public function section_callback( array $arguments ): void {
		switch ( $arguments['id'] ) {
			case self::SECTION_KEYS:
				$this->print_header();

				$this->notifications->show();
				$this->print_section_header( $arguments['id'], __( 'Keys', 'hcaptcha-for-forms-and-more' ) );
				break;
			case self::SECTION_APPEARANCE:
				$this->print_section_header( $arguments['id'], __( 'Appearance', 'hcaptcha-for-forms-and-more' ) );
				break;
			case self::SECTION_CUSTOM:
				$this->print_section_header( $arguments['id'], __( 'Custom', 'hcaptcha-for-forms-and-more' ) );
				break;
			case self::SECTION_ENTERPRISE:
				$this->print_section_header( $arguments['id'], __( 'Enterprise', 'hcaptcha-for-forms-and-more' ) );
				break;
			case self::SECTION_CONTENT:
				$this->print_section_header( $arguments['id'], __( 'Content', 'hcaptcha-for-forms-and-more' ) );
				break;
			case self::SECTION_OTHER:
				$this->print_section_header( $arguments['id'], __( 'Other', 'hcaptcha-for-forms-and-more' ) );
				break;
			case self::SECTION_STATISTICS:
				$this->print_section_header( $arguments['id'], __( 'Statistics', 'hcaptcha-for-forms-and-more' ) );
				break;
			default:
				break;
		}
	}

	/**
	 * Print section header.
	 *
	 * @param string $id    Section id.
	 * @param string $title Section title.
	 *
	 * @return void
	 */
	private function print_section_header( string $id, string $title ): void {
		$open     = $this->get_section_open_status( $id );
		$disabled = false;
		$settings = hcaptcha()->settings();
		$license  = $settings ? $settings->get_license() : 'free';

		switch ( $id ) {
			case self::SECTION_CUSTOM:
				if ( 'free' === $license ) {
					$open     = false;
					$disabled = true;

					$title .= ' - ' . __( 'hCaptcha Pro Required', 'hcaptcha-for-forms-and-more' );
				}
				break;
			case self::SECTION_ENTERPRISE:
				if ( 'free' === $license ) {
					$open     = false;
					$disabled = true;

					$title .= ' - ' . __( 'hCaptcha Enterprise Required', 'hcaptcha-for-forms-and-more' );
				}
				break;
			default:
				break;
		}

		$class = $open ? '' : ' closed';

		$class .= $disabled ? ' disabled' : '';

		?>
		<h3 class="togglable hcaptcha-section-<?php echo esc_attr( $id ); ?><?php echo esc_attr( $class ); ?>">
			<span class="hcaptcha-section-header-title">
				<?php echo esc_html( $title ); ?>
			</span>
			<span class="hcaptcha-section-header-toggle">
			</span>
		</h3>
		<?php
	}

	/**
	 * Enqueue class scripts.
	 *
	 * @return void
	 */
	public function admin_enqueue_scripts(): void {
		$settings       = hcaptcha()->settings();
		$script_version = constant( 'HCAPTCHA_VERSION' );
		$style_version  = constant( 'HCAPTCHA_VERSION' );

		if ( '' === $this->min_suffix ) {
			$script_mtime = filemtime( constant( 'HCAPTCHA_PATH' ) . '/assets/js/general.js' );
			$style_mtime  = filemtime( constant( 'HCAPTCHA_PATH' ) . '/assets/css/general.css' );

			$script_version .= false === $script_mtime ? '' : '-' . $script_mtime;
			$style_version  .= false === $style_mtime ? '' : '-' . $style_mtime;
		}

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
			constant( 'HCAPTCHA_URL' ) . "/assets/js/general$this->min_suffix.js",
			[ 'jquery', 'lodash', self::DIALOG_HANDLE ],
			$script_version,
			true
		);

		$check_config_notice =
			esc_html__( 'Credentials changed.', 'hcaptcha-for-forms-and-more' ) . '<br>' .
			esc_html__( 'Please complete hCaptcha and check the site config.', 'hcaptcha-for-forms-and-more' );

		wp_localize_script(
			self::HANDLE,
			self::OBJECT,
			[
				'ajaxUrl'                              => admin_url( 'admin-ajax.php' ),
				'checkConfigAction'                    => self::CHECK_CONFIG_ACTION,
				'checkConfigNonce'                     => wp_create_nonce( self::CHECK_CONFIG_ACTION ),
				'modeLive'                             => self::MODE_LIVE,
				'modeTestPublisher'                    => self::MODE_TEST_PUBLISHER,
				'modeTestEnterpriseSafeEndUser'        => self::MODE_TEST_ENTERPRISE_SAFE_END_USER,
				'modeTestEnterpriseBotDetected'        => self::MODE_TEST_ENTERPRISE_BOT_DETECTED,
				'siteKey'                              => $settings ? $settings->get( 'site_key' ) : '',
				'modeTestPublisherSiteKey'             => self::MODE_TEST_PUBLISHER_SITE_KEY,
				'modeTestEnterpriseSafeEndUserSiteKey' => self::MODE_TEST_ENTERPRISE_SAFE_END_USER_SITE_KEY,
				'modeTestEnterpriseBotDetectedSiteKey' => self::MODE_TEST_ENTERPRISE_BOT_DETECTED_SITE_KEY,
				'badJSONError'                         => __( 'Bad JSON', 'hcaptcha-for-forms-and-more' ),
				'validJSON'                            => __( 'Valid JSON', 'hcaptcha-for-forms-and-more' ),
				'invalidJSON'                          => __( 'Invalid JSON', 'hcaptcha-for-forms-and-more' ),
				'configMustBeObject'                   => __( 'Config Params must be a JSON object.', 'hcaptcha-for-forms-and-more' ),
				'unsavedChanges'                       => __( 'Unsaved changes', 'hcaptcha-for-forms-and-more' ),
				'lastValidPreview'                     => __( 'Showing the last valid config.', 'hcaptcha-for-forms-and-more' ),
				'activeState'                          => __( 'Active', 'hcaptcha-for-forms-and-more' ),
				'focusState'                           => __( 'Focus', 'hcaptcha-for-forms-and-more' ),
				'hoverState'                           => __( 'Hover', 'hcaptcha-for-forms-and-more' ),
				'mainState'                            => __( 'Main', 'hcaptcha-for-forms-and-more' ),
				'reportState'                          => __( 'Report', 'hcaptcha-for-forms-and-more' ),
				'selectedState'                        => __( 'Selected', 'hcaptcha-for-forms-and-more' ),
				'hexValue'                             => __( 'hex value', 'hcaptcha-for-forms-and-more' ),
				'checkConfigNotice'                    => $check_config_notice,
				'checkingConfigMsg'                    => __( 'Checking site config...', 'hcaptcha-for-forms-and-more' ),
				'completeHCaptchaTitle'                => __( 'Please complete the hCaptcha.', 'hcaptcha-for-forms-and-more' ),
				'completeHCaptchaContent'              => __( 'Before checking the site config, please complete the Active hCaptcha in the current section.', 'hcaptcha-for-forms-and-more' ),
				'OKBtnText'                            => __( 'OK', 'hcaptcha-for-forms-and-more' ),
				'configuredAntiSpamProviders'          => AntiSpam::get_configured_providers(),
			]
		);

		wp_enqueue_style(
			self::HANDLE,
			constant( 'HCAPTCHA_URL' ) . "/assets/css/general$this->min_suffix.css",
			[ static::PREFIX . '-' . SettingsBase::HANDLE, self::DIALOG_HANDLE ],
			$style_version
		);
	}

	/**
	 * Add custom hCaptcha field.
	 *
	 * @param array|mixed $fields Fields.
	 *
	 * @return array
	 */
	public function settings_fields( $fields ): array {
		$fields             = (array) $fields;
		$fields['hcaptcha'] = [ $this, 'print_hcaptcha_field' ];

		return $fields;
	}

	/**
	 * Output settings field.
	 *
	 * @param array $arguments Field arguments.
	 *
	 * @return void
	 */
	public function field_callback( array $arguments ): void {
		if ( 'config_params' !== ( $arguments['field_id'] ?? '' ) ) {
			parent::field_callback( $arguments );

			return;
		}

		$this->print_theme_editor_field( $arguments );
	}

	/**
	 * Print the advanced theme editor.
	 *
	 * @param array $arguments Field arguments.
	 *
	 * @return void
	 */
	protected function print_theme_editor_field( array $arguments ): void {
		$arguments    += [
			'disabled'    => false,
			'field_id'    => 'config_params',
			'placeholder' => '',
		];
		$settings      = hcaptcha()->settings();
		$license       = $settings ? $settings->get_license() : 'free';
		$preview_only  = 'free' === $license;
		$default_theme = $settings ? $settings->get_default_theme() : [];
		$default_json  = wp_json_encode( $default_theme );
		?>
		<div class="hcaptcha-theme-editor-launcher">
			<button
					type="button"
					class="button button-secondary"
					aria-controls="hcaptcha-theme-editor-window"
					aria-expanded="false"
					data-theme-editor-open>
				<?php esc_html_e( 'Open', 'hcaptcha-for-forms-and-more' ); ?>
			</button>
			<span class="hcaptcha-theme-editor-dirty" aria-live="polite" hidden></span>
		</div>

		<div
				class="hcaptcha-theme-editor"
				id="hcaptcha-theme-editor-window"
				role="dialog"
				aria-modal="false"
				aria-labelledby="hcaptcha-theme-editor-title"
				hidden
				data-theme-editor-preview-only="<?php echo esc_attr( $preview_only ? 'true' : 'false' ); ?>"
				data-default-theme="<?php echo esc_attr( (string) $default_json ); ?>">
			<div class="hcaptcha-theme-editor-header" data-theme-editor-drag-handle>
				<div class="hcaptcha-theme-editor-title">
					<img
							class="hcaptcha-theme-editor-icon"
							src="<?php echo esc_url( constant( 'HCAPTCHA_URL' ) . '/assets/images/hcaptcha-icon.svg' ); ?>"
							alt="">
					<div>
						<strong id="hcaptcha-theme-editor-title">
							<?php esc_html_e( 'Advanced Theme Editor', 'hcaptcha-for-forms-and-more' ); ?>
						</strong>
						<span><?php esc_html_e( 'Drag this bar to move the window', 'hcaptcha-for-forms-and-more' ); ?></span>
					</div>
				</div>
				<div class="hcaptcha-theme-editor-window-actions">
					<span class="hcaptcha-theme-editor-dirty" aria-live="polite" hidden></span>
					<button type="button" class="hcaptcha-theme-editor-close" data-theme-editor-close>
						<span class="screen-reader-text"><?php esc_html_e( 'Close theme editor', 'hcaptcha-for-forms-and-more' ); ?></span>
						<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
					</button>
				</div>
			</div>

			<?php if ( $preview_only ) : ?>
				<div class="hcaptcha-theme-editor-pro-notice" role="note">
					<span class="dashicons dashicons-warning" aria-hidden="true"></span>
					<p>
						<strong><?php esc_html_e( 'Preview only.', 'hcaptcha-for-forms-and-more' ); ?></strong>
						<?php esc_html_e( 'Custom themes require an hCaptcha Pro or Enterprise site key. You can explore every editor control, but changes are shown only in the preview and are not saved.', 'hcaptcha-for-forms-and-more' ); ?>
					</p>
				</div>
			<?php endif; ?>

			<div class="hcaptcha-theme-editor-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Editor mode', 'hcaptcha-for-forms-and-more' ); ?>">
				<button
						type="button"
						class="hcaptcha-theme-editor-tab is-active"
						id="hcaptcha-theme-editor-visual-tab"
						role="tab"
						aria-controls="hcaptcha-theme-editor-visual"
						aria-selected="true"
						data-theme-editor-tab="visual">
					<?php esc_html_e( 'Visual', 'hcaptcha-for-forms-and-more' ); ?>
				</button>
				<button
						type="button"
						class="hcaptcha-theme-editor-tab"
						id="hcaptcha-theme-editor-json-tab"
						role="tab"
						aria-controls="hcaptcha-theme-editor-json"
						aria-selected="false"
						data-theme-editor-tab="json">
					<?php esc_html_e( 'JSON', 'hcaptcha-for-forms-and-more' ); ?>
				</button>
			</div>

			<div class="hcaptcha-theme-editor-workspace">
				<div class="hcaptcha-theme-editor-panes">
					<div
							class="hcaptcha-theme-editor-pane is-active"
							id="hcaptcha-theme-editor-visual"
							role="tabpanel"
							aria-labelledby="hcaptcha-theme-editor-visual-tab"
							data-theme-editor-pane="visual">
						<nav class="hcaptcha-theme-editor-nav" aria-label="<?php esc_attr_e( 'Theme sections', 'hcaptcha-for-forms-and-more' ); ?>">
							<span class="hcaptcha-theme-editor-nav-label">
								<?php esc_html_e( 'Foundation', 'hcaptcha-for-forms-and-more' ); ?>
							</span>
							<button type="button" class="hcaptcha-theme-editor-nav-button is-active" data-theme-group="palette">
								<?php esc_html_e( 'Palette', 'hcaptcha-for-forms-and-more' ); ?>
							</button>
							<span class="hcaptcha-theme-editor-nav-label">
								<?php esc_html_e( 'Components', 'hcaptcha-for-forms-and-more' ); ?>
							</span>
							<span data-theme-editor-component-nav></span>
						</nav>

						<div class="hcaptcha-theme-editor-fields">
							<div class="hcaptcha-theme-editor-fields-header">
								<div>
									<strong data-theme-editor-group-title><?php esc_html_e( 'Palette', 'hcaptcha-for-forms-and-more' ); ?></strong>
									<p class="description" data-theme-editor-group-description></p>
								</div>
								<button type="button" class="button-link" data-theme-editor-reset-section>
									<?php esc_html_e( 'Reset section', 'hcaptcha-for-forms-and-more' ); ?>
								</button>
							</div>
							<label class="hcaptcha-theme-editor-mode" data-theme-editor-mode-field>
								<span><?php esc_html_e( 'Mode', 'hcaptcha-for-forms-and-more' ); ?></span>
								<select data-theme-editor-mode>
									<option value="light"><?php esc_html_e( 'Light', 'hcaptcha-for-forms-and-more' ); ?></option>
									<option value="dark"><?php esc_html_e( 'Dark', 'hcaptcha-for-forms-and-more' ); ?></option>
								</select>
							</label>
							<div data-theme-editor-fields></div>
						</div>
					</div>

					<div
							class="hcaptcha-theme-editor-pane"
							id="hcaptcha-theme-editor-json"
							role="tabpanel"
							aria-labelledby="hcaptcha-theme-editor-json-tab"
							data-theme-editor-pane="json"
							hidden>
						<div class="hcaptcha-theme-editor-json-header">
							<div>
								<strong><?php esc_html_e( 'Complete config', 'hcaptcha-for-forms-and-more' ); ?></strong>
								<p class="description">
									<?php esc_html_e( 'Unknown render parameters are preserved.', 'hcaptcha-for-forms-and-more' ); ?>
								</p>
							</div>
							<div class="hcaptcha-theme-editor-json-actions">
								<span class="hcaptcha-theme-editor-json-status is-valid" aria-live="polite">
									<?php esc_html_e( 'Valid JSON', 'hcaptcha-for-forms-and-more' ); ?>
								</span>
								<button type="button" class="button button-secondary" data-theme-editor-format>
									<?php esc_html_e( 'Format', 'hcaptcha-for-forms-and-more' ); ?>
								</button>
							</div>
						</div>
						<?php $this->print_textarea_field( $arguments ); ?>
						<p class="hcaptcha-theme-editor-json-error" role="alert"></p>
					</div>
				</div>

				<aside class="hcaptcha-theme-editor-preview" aria-label="<?php esc_attr_e( 'Theme preview', 'hcaptcha-for-forms-and-more' ); ?>">
					<div class="hcaptcha-theme-editor-preview-header">
						<strong><?php esc_html_e( 'Preview', 'hcaptcha-for-forms-and-more' ); ?></strong>
						<div class="hcaptcha-theme-editor-preview-background" aria-label="<?php esc_attr_e( 'Preview background', 'hcaptcha-for-forms-and-more' ); ?>">
							<button type="button" class="is-active" data-theme-preview-background="light">
								<?php esc_html_e( 'Light', 'hcaptcha-for-forms-and-more' ); ?>
							</button>
							<button type="button" data-theme-preview-background="dark">
								<?php esc_html_e( 'Dark', 'hcaptcha-for-forms-and-more' ); ?>
							</button>
						</div>
					</div>
					<div class="hcaptcha-theme-editor-preview-switch" role="tablist" aria-label="<?php esc_attr_e( 'Preview type', 'hcaptcha-for-forms-and-more' ); ?>">
						<button type="button" class="is-active" role="tab" aria-selected="true" data-theme-preview-view="widget">
							<?php esc_html_e( 'Widget', 'hcaptcha-for-forms-and-more' ); ?>
						</button>
						<button type="button" role="tab" aria-selected="false" data-theme-preview-view="challenge">
							<?php esc_html_e( 'Challenge', 'hcaptcha-for-forms-and-more' ); ?>
						</button>
					</div>
					<div class="hcaptcha-theme-editor-preview-stage" data-theme-editor-preview-stage>
						<div class="hcaptcha-theme-mock-widget" data-theme-preview-pane="widget">
							<span class="hcaptcha-theme-mock-checkbox" aria-hidden="true"></span>
							<span class="hcaptcha-theme-mock-widget-label"><?php esc_html_e( 'I am human', 'hcaptcha-for-forms-and-more' ); ?></span>
							<span class="hcaptcha-theme-mock-brand">
								<img
										class="hcaptcha-theme-mock-logo"
										data-theme-mock-logo="light"
										src="<?php echo esc_url( constant( 'HCAPTCHA_URL' ) . '/assets/images/hcaptcha-div-logo.svg' ); ?>"
										alt="">
								<img
										class="hcaptcha-theme-mock-logo"
										data-theme-mock-logo="dark"
										src="<?php echo esc_url( constant( 'HCAPTCHA_URL' ) . '/assets/images/hcaptcha-div-logo-white.svg' ); ?>"
										alt=""
										hidden>
								<small><?php esc_html_e( 'Privacy - Terms', 'hcaptcha-for-forms-and-more' ); ?></small>
							</span>
						</div>

						<div class="hcaptcha-theme-mock-challenge" data-theme-preview-pane="challenge" hidden>
							<div class="hcaptcha-theme-mock-prompt">
								<span><?php esc_html_e( 'Please click on the object that does not belong', 'hcaptcha-for-forms-and-more' ); ?></span>
							</div>
							<div class="hcaptcha-theme-mock-breadcrumbs" aria-hidden="true">
								<span class="is-active"></span><span></span><span></span>
							</div>
							<div class="hcaptcha-theme-mock-tasks" aria-hidden="true">
								<span></span><span class="is-selected"></span><span></span>
								<span></span><span class="is-hovered"></span><span></span>
								<span></span><span></span><span class="is-reported"></span>
							</div>
							<div class="hcaptcha-theme-mock-answer" aria-hidden="true">
								<span class="hcaptcha-theme-mock-radio"></span>
								<span class="hcaptcha-theme-mock-answer-text"><?php esc_html_e( 'Example answer', 'hcaptcha-for-forms-and-more' ); ?></span>
								<span class="hcaptcha-theme-mock-slider"><i></i></span>
							</div>
							<div class="hcaptcha-theme-mock-challenge-footer">
								<div class="hcaptcha-theme-mock-tools" aria-hidden="true">
									<span>↻</span><span>◉</span><span>⋮</span>
								</div>
								<button type="button" tabindex="-1" class="hcaptcha-theme-mock-skip">
									<?php esc_html_e( 'Skip', 'hcaptcha-for-forms-and-more' ); ?>
								</button>
								<button type="button" tabindex="-1" class="hcaptcha-theme-mock-verify">
									<?php esc_html_e( 'Verify', 'hcaptcha-for-forms-and-more' ); ?>
								</button>
							</div>
						</div>
					</div>
					<p class="description hcaptcha-theme-editor-preview-note" data-theme-editor-preview-note>
						<?php esc_html_e( 'Approximate challenge preview.', 'hcaptcha-for-forms-and-more' ); ?><br>
						<?php if ( $preview_only ) : ?>
							<?php esc_html_e( 'Changes are shown in this preview only.', 'hcaptcha-for-forms-and-more' ); ?>
						<?php else : ?>
							<?php esc_html_e( 'The real widget in Keys also updates live.', 'hcaptcha-for-forms-and-more' ); ?>
						<?php endif; ?>
					</p>
				</aside>
			</div>

			<div class="hcaptcha-theme-editor-footer">
				<div class="hcaptcha-theme-editor-footer-actions">
					<?php if ( ! $preview_only ) : ?>
						<button type="button" class="button button-secondary" data-theme-editor-show-sample>
							<?php esc_html_e( 'Show real hCaptcha', 'hcaptcha-for-forms-and-more' ); ?>
						</button>
					<?php endif; ?>
					<button type="button" class="button button-secondary" data-theme-editor-reset-theme>
						<?php esc_html_e( 'Reset theme', 'hcaptcha-for-forms-and-more' ); ?>
					</button>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Print hCaptcha field.
	 *
	 * @return void
	 */
	public function print_hcaptcha_field(): void {
		$args = [
			'id' => [
				'source'  => [ __CLASS__ ],
				'form_id' => self::CHECK_CONFIG_FORM_ID,
			],
		];

		HCaptcha::form_display( $args );

		$settings = hcaptcha()->settings();
		$size     = $settings ? $settings->get( 'size' ) : 'normal';
		$display  = 'invisible' === $size ? 'block' : 'none';

		?>
		<div id="hcaptcha-invisible-notice" style="display: <?php echo esc_attr( $display ); ?>">
			<p>
				<?php esc_html_e( 'hCaptcha is in invisible mode.', 'hcaptcha-for-forms-and-more' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Ajax action to check config.
	 *
	 * @return void
	 */
	public function check_config(): void {
		$this->run_checks( self::CHECK_CONFIG_ACTION );

		// Nonce is checked by check_ajax_referer() in run_checks().
		$ajax_mode       = Request::filter_input( INPUT_POST, 'mode' );
		$ajax_site_key   = Request::filter_input( INPUT_POST, 'siteKey' );
		$ajax_secret_key = Request::filter_input( INPUT_POST, 'secretKey' );

		add_filter(
			'hcap_mode',
			static function () use ( $ajax_mode ) {
				// @codeCoverageIgnoreStart
				return $ajax_mode;
				// @codeCoverageIgnoreEnd
			}
		);

		if ( self::MODE_LIVE === $ajax_mode ) {
			add_filter(
				'hcap_site_key',
				static function () use ( $ajax_site_key ) {
					// @codeCoverageIgnoreStart
					return $ajax_site_key;
					// @codeCoverageIgnoreEnd
				}
			);
			add_filter(
				'hcap_secret_key',
				static function () use ( $ajax_secret_key ) {
					// @codeCoverageIgnoreStart
					return $ajax_secret_key;
					// @codeCoverageIgnoreEnd
				}
			);
		}

		$result = hcap_check_site_config();

		if ( $result['error'] ?? false ) {
			$this->send_check_config_error( $result['error'] );
		}

		$pro     = $result['features']['custom_theme'] ?? false;
		$license = $pro ? 'pro' : 'free';

		$this->update_option( 'license', $license );

		// Nonce is checked by check_ajax_referer() in run_checks().
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$hcaptcha_response = isset( $_POST['h-captcha-response'] )
			? filter_var( wp_unslash( $_POST['h-captcha-response'] ), FILTER_SANITIZE_FULL_SPECIAL_CHARS )
			: '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		add_filter( 'hcap_check_honeypot_field', '__return_true' );
		add_filter( 'hcap_verify_fst_token', '__return_true' );

		$result = API::verify_request( $hcaptcha_response );

		if ( null !== $result ) {
			$this->send_check_config_error( $result, true );
		}

		wp_send_json_success(
			esc_html__( 'Site config is valid. Save your changes.', 'hcaptcha-for-forms-and-more' )
		);
	}

	/**
	 * Send stats if the key is switched on.
	 *
	 * @param mixed $value     New option value.
	 * @param mixed $old_value Old option value.
	 *
	 * @return mixed
	 */
	public function maybe_send_stats( $value, $old_value ) {
		$stats     = $value['statistics'][0] ?? '';
		$old_stats = $old_value['statistics'][0] ?? '';

		if ( 'on' === $stats && 'on' !== $old_stats ) {
			/**
			 * Statistics switch is turned on, send plugin statistics.
			 *
			 * @param bool $force_send Whether to force sending stats. Default is false.
			 */
			do_action( 'hcap_send_plugin_stats', true );
		}

		return $value;
	}

	/**
	 * Send check config error.
	 *
	 * @param string $error      Error message.
	 * @param bool   $raw_result Send a raw result.
	 *
	 * @return void
	 */
	private function send_check_config_error( string $error, bool $raw_result = false ): void {
		$prefix = '';

		if ( ! $raw_result ) {
			$prefix = __( 'Site configuration error', 'hcaptcha-for-forms-and-more' );
			$prefix = $error ? $prefix . ': ' : $prefix . '.';
		}

		wp_send_json_error( esc_html( $prefix . $error ) );
	}
}
