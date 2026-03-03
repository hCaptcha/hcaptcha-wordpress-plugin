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
	 * Toggle section ajax action.
	 */
	public const TOGGLE_SECTION_ACTION = 'hcaptcha-general-toggle-section';

	/**
	 * Check IPs ajax action.
	 */
	public const CHECK_IPS_ACTION = 'hcaptcha-general-check-ips';

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
	 * AntiSpam section id.
	 */
	public const SECTION_ANTISPAM = 'antispam';

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
	 * User settings meta.
	 */
	public const USER_SETTINGS_META = 'hcaptcha_user_settings';

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
			// The current class loaded early on plugins_loaded.
			// Init Notifications and Onboarding later, when the Settings class is ready.
			// Also, we need to check if we are on the General screen.
			add_action( 'current_screen', [ $this, 'init_notifications' ] );
		}

		add_action( 'admin_head', [ $hcaptcha, 'print_inline_styles' ] );
		add_action( 'admin_print_footer_scripts', [ $hcaptcha, 'print_footer_scripts' ], 0 );

		add_filter( 'kagg_settings_fields', [ $this, 'settings_fields' ] );
		add_action( 'wp_ajax_' . self::CHECK_CONFIG_ACTION, [ $this, 'check_config' ] );
		add_action( 'wp_ajax_' . self::CHECK_IPS_ACTION, [ $this, 'check_ips' ] );
		add_action( 'wp_ajax_' . self::TOGGLE_SECTION_ACTION, [ $this, 'toggle_section' ] );

		add_filter( 'pre_update_option_' . $this->option_name(), [ $this, 'maybe_send_stats' ], 20, 2 );
		add_filter( 'pre_update_site_option_' . $this->option_name(), [ $this, 'maybe_send_stats' ], 20, 2 );

		add_filter( 'pre_update_option_' . $this->option_name(), [ $this, 'maybe_load_maxmind_db' ], 20, 2 );
		add_filter( 'pre_update_site_option_' . $this->option_name(), [ $this, 'maybe_load_maxmind_db' ], 20, 2 );
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
			'site_key'              => [
				'label'        => __( 'Site Key', 'hcaptcha-for-forms-and-more' ),
				'type'         => 'text',
				'autocomplete' => 'nickname',
				'lp_ignore'    => 'true',
				'section'      => self::SECTION_KEYS,
				'helper'       => __( 'To fill out the site key, set Mode to Live.', 'hcaptcha-for-forms-and-more' ),
			],
			'secret_key'            => [
				'label'   => __( 'Secret Key', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'password',
				'section' => self::SECTION_KEYS,
				'helper'  => __( 'To fill out the secret key, set Mode to Live.', 'hcaptcha-for-forms-and-more' ),
			],
			'sample_hcaptcha'       => [
				'label'   => __( 'Active hCaptcha to Check Site Config', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'hcaptcha',
				'section' => self::SECTION_KEYS,
			],
			'check_config'          => [
				'label'   => __( 'Check Site Config', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'button',
				'text'    => __( 'Check', 'hcaptcha-for-forms-and-more' ),
				'section' => self::SECTION_KEYS,
			],
			'reset_notifications'   => [
				'label'   => __( 'Reset Notifications', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'button',
				'text'    => __( 'Reset', 'hcaptcha-for-forms-and-more' ),
				'section' => self::SECTION_KEYS,
			],
			'theme'                 => [
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
			'size'                  => [
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
			'language'              => [
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
			'mode'                  => [
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
			'force'                 => [
				'label'   => __( 'Force hCaptcha', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'checkbox',
				'section' => self::SECTION_APPEARANCE,
				'options' => [
					'on' => __( 'Force', 'hcaptcha-for-forms-and-more' ),
				],
				'helper'  => __( 'Force hCaptcha check before submit.', 'hcaptcha-for-forms-and-more' ),
			],
			'menu_position'         => [
				'label'   => __( 'Tabs Menu Under Settings', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'checkbox',
				'section' => self::SECTION_APPEARANCE,
				'options' => [
					'on' => __( 'Tabs', 'hcaptcha-for-forms-and-more' ),
				],
				'helper'  => __( 'When on, the hCaptcha admin menu is placed under Settings.', 'hcaptcha-for-forms-and-more' ),
			],
			'custom_themes'         => [
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
			'custom_prop'           => [
				'label'   => __( 'Property', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'select',
				'options' => [],
				'section' => self::SECTION_CUSTOM,
				'helper'  => __( 'Select custom theme property.', 'hcaptcha-for-forms-and-more' ),
			],
			'custom_value'          => [
				'label'   => __( 'Value', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'text',
				'section' => self::SECTION_CUSTOM,
				'helper'  => __( 'Set property value.', 'hcaptcha-for-forms-and-more' ),
			],
			'config_params'         => [
				'label'   => __( 'Config Params', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'textarea',
				'section' => self::SECTION_CUSTOM,
				'helper'  => sprintf(
				/* translators: 1: hCaptcha render params doc link. */
					__( 'hCaptcha render %s (optional). Must be a valid JSON.', 'hcaptcha-for-forms-and-more' ),
					sprintf(
						'<a href="https://docs.hcaptcha.com/configuration/#hcaptcharendercontainer-params?utm_source=wordpress&utm_medium=wpplugin&utm_campaign=docs" target="_blank">%s</a>',
						__( 'parameters', 'hcaptcha-for-forms-and-more' )
					)
				),
			],
			'api_host'              => [
				'label'   => __( 'API Host', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'text',
				'section' => self::SECTION_ENTERPRISE,
				'default' => Main::API_HOST,
				'helper'  => __( 'See Enterprise docs.', 'hcaptcha-for-forms-and-more' ),
			],
			'asset_host'            => [
				'label'   => __( 'Asset Host', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'text',
				'section' => self::SECTION_ENTERPRISE,
				'helper'  => __( 'See Enterprise docs.', 'hcaptcha-for-forms-and-more' ),
			],
			'endpoint'              => [
				'label'   => __( 'Endpoint', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'text',
				'section' => self::SECTION_ENTERPRISE,
				'helper'  => __( 'See Enterprise docs.', 'hcaptcha-for-forms-and-more' ),
			],
			'host'                  => [
				'label'   => __( 'Host', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'text',
				'section' => self::SECTION_ENTERPRISE,
				'helper'  => __( 'See Enterprise docs.', 'hcaptcha-for-forms-and-more' ),
			],
			'image_host'            => [
				'label'   => __( 'Image Host', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'text',
				'section' => self::SECTION_ENTERPRISE,
				'helper'  => __( 'See Enterprise docs.', 'hcaptcha-for-forms-and-more' ),
			],
			'report_api'            => [
				'label'   => __( 'Report API', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'text',
				'section' => self::SECTION_ENTERPRISE,
				'helper'  => __( 'See Enterprise docs.', 'hcaptcha-for-forms-and-more' ),
			],
			'sentry'                => [
				'label'   => __( 'Sentry', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'text',
				'section' => self::SECTION_ENTERPRISE,
				'helper'  => __( 'See Enterprise docs.', 'hcaptcha-for-forms-and-more' ),
			],
			'backend'               => [
				'label'   => __( 'Backend', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'text',
				'section' => self::SECTION_ENTERPRISE,
				'default' => Main::VERIFY_HOST,
				'helper'  => __( 'See Enterprise docs.', 'hcaptcha-for-forms-and-more' ),
			],
			'protect_content'       => [
				'label'   => __( 'Content Settings', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'checkbox',
				'section' => self::SECTION_CONTENT,
				'options' => [
					'on' => __( 'Protect Content', 'hcaptcha-for-forms-and-more' ),
				],
				'helper'  => __( 'Protect site content from bots with hCaptcha.', 'hcaptcha-for-forms-and-more' ),
			],
			'protected_urls'        => [
				'label'   => __( 'Protected URLs', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'textarea',
				'section' => self::SECTION_CONTENT,
				'helper'  => __( 'Protect content of listed URLs. Please specify one URL per line. You may use regular expressions.', 'hcaptcha-for-forms-and-more' ),
			],
			'set_min_submit_time'   => [
				'label'   => __( 'Token and Honeypot', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'checkbox',
				'section' => self::SECTION_ANTISPAM,
				'options' => [
					'on' => __( 'Set Minimum Time', 'hcaptcha-for-forms-and-more' ),
				],
				'helper'  => __( 'Set a minimum amount of time a user must spend on a form before submitting.', 'hcaptcha-for-forms-and-more' ),
			],
			'min_submit_time'       => [
				'label'   => __( 'Minimum Time to Submit the Form, sec', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'number',
				'section' => self::SECTION_ANTISPAM,
				'default' => 2,
				'min'     => 1,
				'helper'  => __( 'Set a minimum amount of time a user must spend on a form before submitting.', 'hcaptcha-for-forms-and-more' ),
			],
			'honeypot'              => [
				'type'    => 'checkbox',
				'section' => self::SECTION_ANTISPAM,
				'options' => [
					'on' => __( 'Enable Honeypot Field', 'hcaptcha-for-forms-and-more' ),
				],
				'helper'  => __( 'Add a honeypot field to submitted forms for early bot prevention.', 'hcaptcha-for-forms-and-more' ),
			],
			'antispam'              => [
				'label'   => __( 'Anti-Spam Check', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'checkbox',
				'section' => self::SECTION_ANTISPAM,
				'options' => [
					'on' => __( 'Enable Anti-Spam Check', 'hcaptcha-for-forms-and-more' ),
				],
				'helper'  => __( 'Enable anti-spam check of submitted forms.', 'hcaptcha-for-forms-and-more' ),
			],
			'antispam_provider'     => [
				'label'   => __( 'Anti-Spam Provider', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'select',
				'section' => self::SECTION_ANTISPAM,
				'options' => AntiSpam::get_supported_providers(),
				'helper'  => __( 'Select anti-spam provider.', 'hcaptcha-for-forms-and-more' ),
			],
			'blacklisted_ips'       => [
				'label'   => __( 'Denylisted IPs', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'textarea',
				'section' => self::SECTION_OTHER,
				'helper'  => __( 'Block form sending from listed IP addresses. Please specify one IP, range, or CIDR per line.', 'hcaptcha-for-forms-and-more' ),
			],
			'whitelisted_ips'       => [
				'label'   => __( 'Allowlisted IPs', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'textarea',
				'section' => self::SECTION_OTHER,
				'helper'  => __( 'Do not show hCaptcha for listed IP addresses. Please specify one IP, range, or CIDR per line.', 'hcaptcha-for-forms-and-more' ),
			],
			'blacklisted_countries' => [
				'label'   => __( 'Denylisted Countries', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'multiple',
				'options' => [],
				'section' => self::SECTION_OTHER,
				'helper'  => __( 'Block form sending from selected countries.', 'hcaptcha-for-forms-and-more' ),
			],
			'whitelisted_countries' => [
				'label'   => __( 'Allowlisted Countries', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'multiple',
				'options' => [],
				'section' => self::SECTION_OTHER,
				'helper'  => __( 'Do not show hCaptcha for users from selected countries.', 'hcaptcha-for-forms-and-more' ),
			],
			'delay'                 => [
				'label'   => __( 'Delay Showing hCaptcha, ms', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'number',
				'section' => self::SECTION_OTHER,
				'default' => -100,
				'min'     => -100,
				'step'    => 100,
				'helper'  => __( 'Delay time for loading the hCaptcha API script. Any negative value will prevent the API script from loading until user interaction: mouseenter, click, scroll or touch. This significantly improves Google Pagespeed Insights score.', 'hcaptcha-for-forms-and-more' ),
			],
			'maxmind_key'           => [
				'label'        => __( 'MaxMind License Key', 'hcaptcha-for-forms-and-more' ),
				'type'         => 'password',
				'autocomplete' => 'off',
				'section'      => self::SECTION_OTHER,
				'helper'       => __( 'Needed to automatically download the GeoLite2 Country database for country allowlist/denylist checks.', 'hcaptcha-for-forms-and-more' ),
			],
			'login_limit'           => [
				'label'   => __( 'Login Attempts Before hCaptcha', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'number',
				'section' => self::SECTION_OTHER,
				'default' => 0,
				'min'     => 0,
				'helper'  => __( 'Maximum number of failed login attempts before showing hCaptcha.', 'hcaptcha-for-forms-and-more' ),
			],
			'login_interval'        => [
				'label'   => __( 'Failed Login Attempts Interval, min', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'number',
				'section' => self::SECTION_OTHER,
				'default' => 15,
				'min'     => 1,
				'helper'  => __( 'Time interval in minutes when failed login attempts are counted.', 'hcaptcha-for-forms-and-more' ),
			],
			'off_when_logged_in'    => [
				'type'    => 'checkbox',
				'section' => self::SECTION_OTHER,
				'options' => [
					'on' => __( 'Turn Off When Logged In', 'hcaptcha-for-forms-and-more' ),
				],
				'helper'  => __( 'Do not show hCaptcha to logged-in users.', 'hcaptcha-for-forms-and-more' ),
			],
			'recaptcha_compat_off'  => [
				'type'    => 'checkbox',
				'section' => self::SECTION_OTHER,
				'options' => [
					'on' => __( 'Disable reCAPTCHA Compatibility', 'hcaptcha-for-forms-and-more' ),
				],
				'helper'  => __( 'Use if including both hCaptcha and reCAPTCHA on the same page.', 'hcaptcha-for-forms-and-more' ),
			],
			'hide_login_errors'     => [
				'type'    => 'checkbox',
				'section' => self::SECTION_OTHER,
				'options' => [
					'on' => __( 'Hide Login Errors', 'hcaptcha-for-forms-and-more' ),
				],
				'helper'  => __( 'Avoid specifying errors like "invalid username" or "invalid password" to limit information exposure to attackers.', 'hcaptcha-for-forms-and-more' ),
			],
			'cleanup_on_uninstall'  => [
				'type'    => 'checkbox',
				'section' => self::SECTION_OTHER,
				'options' => [
					'on' => __( 'Remove Data on Uninstall', 'hcaptcha-for-forms-and-more' ),
				],
				'helper'  => __( 'When enabled, all plugin data will be removed when uninstalling the plugin.', 'hcaptcha-for-forms-and-more' ),
			],
			self::NETWORK_WIDE      => [
				'type'    => 'checkbox',
				'section' => self::SECTION_OTHER,
				'options' => [
					'on' => __( 'Use network-wide settings', 'hcaptcha-for-forms-and-more' ),
				],
				'helper'  => __( 'On multisite, use same settings for all sites of the network.', 'hcaptcha-for-forms-and-more' ),
			],
			'statistics'            => [
				'label'   => __( 'Statistics', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'checkbox',
				'section' => self::SECTION_STATISTICS,
				'options' => [
					'on' => __( 'Enable Statistics', 'hcaptcha-for-forms-and-more' ),
				],
				'helper'  => __( 'By turning the statistics on, you agree to the collection of non-personal data to improve the plugin.', 'hcaptcha-for-forms-and-more' ),
			],
			'anonymous'             => [
				'type'    => 'checkbox',
				'section' => self::SECTION_STATISTICS,
				'options' => [
					'on' => __( 'Collect Anonymously', 'hcaptcha-for-forms-and-more' ),
				],
				'default' => 'on',
				'helper'  => __( 'Store collected IP and User Agent locally as hashed values to conform to GDPR requirements.', 'hcaptcha-for-forms-and-more' ),
			],
			'collect_ip'            => [
				'label'   => __( 'Collection', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'checkbox',
				'section' => self::SECTION_STATISTICS,
				'options' => [
					'on' => __( 'Collect IP', 'hcaptcha-for-forms-and-more' ),
				],
				'helper'  => __( 'Allow collecting of IP addresses from which forms were sent.', 'hcaptcha-for-forms-and-more' ),
			],
			'collect_ua'            => [
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

		if ( ! AntiSpam::get_supported_providers() ) {
			unset( $this->form_fields['antispam'], $this->form_fields['antispam_provider'] );
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

		$config_params = $settings->get_config_params();
		$custom_theme  = $config_params['theme'] ?? [];
		$default_theme = $settings->get_default_theme();
		$custom_theme  = array_replace_recursive( $default_theme, $custom_theme );
		$custom_theme  = $this->flatten_array( $custom_theme );
		$custom_theme  = array_merge(
			[ esc_html__( '- Select Property -', 'hcaptcha-for-forms-and-more' ) => '' ],
			$custom_theme
		);
		$options       = [];

		foreach ( $custom_theme as $key => $value ) {
			$key_arr = explode( '--', $key );
			$level   = count( $key_arr ) - 1;
			$prefix  = $level ? str_repeat( '–', $level ) . ' ' : '';
			$option  = $prefix . ucfirst( end( $key_arr ) );

			$options[ $key . '=' . $value ] = $option;
		}

		$this->form_fields['custom_prop']['options'] = $options;

		$country_names = $this->get_country_names();
		$maxmind_key   = $settings->get( 'maxmind_key' );

		$this->form_fields['blacklisted_countries']['options'] = $country_names;
		$this->form_fields['whitelisted_countries']['options'] = $country_names;

		if ( '' === $maxmind_key ) {
			$this->form_fields['blacklisted_countries']['disabled'] = true;
			$this->form_fields['whitelisted_countries']['disabled'] = true;
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

				?>
				<div id="hcaptcha-message"></div>
				<?php

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
			case self::SECTION_ANTISPAM:
				$this->print_section_header( $arguments['id'], __( 'Anti-spam', 'hcaptcha-for-forms-and-more' ) );
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
		$disabled = '';
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
		<h3 class="hcaptcha-section-<?php echo esc_attr( $id ); ?><?php echo esc_attr( $class ); ?>">
			<span class="hcaptcha-section-header-title">
				<?php echo esc_html( $title ); ?>
			</span>
			<span class="hcaptcha-section-header-toggle">
			</span>
		</h3>
		<?php
	}

	/**
	 * Get section open status.
	 *
	 * @param string $id Section id.
	 *
	 * @return bool
	 */
	private function get_section_open_status( string $id ): bool {
		$user                   = wp_get_current_user();
		$hcaptcha_user_settings = [];

		if ( $user ) {
			$hcaptcha_user_settings = get_user_meta( $user->ID, self::USER_SETTINGS_META, true );
		}

		return (bool) ( $hcaptcha_user_settings['sections'][ $id ] ?? true );
	}

	/**
	 * Enqueue class scripts.
	 *
	 * @return void
	 */
	public function admin_enqueue_scripts(): void {
		$settings                     = hcaptcha()->settings();
		$maxmind_key                  = $settings ? $settings->get( 'maxmind_key' ) : '';
		$countries_search_placeholder = $maxmind_key
			? __( 'Search countries...', 'hcaptcha-for-forms-and-more' )
			: __( 'Set MaxMind License Key first', 'hcaptcha-for-forms-and-more' );

		$choices_handle   = self::HANDLE . '-choices';
		$countries_handle = self::HANDLE . '-countries';

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
			constant( 'HCAPTCHA_VERSION' ),
			true
		);

		wp_enqueue_script(
			$choices_handle,
			constant( 'HCAPTCHA_URL' ) . '/assets/lib/choices/choices.min.js',
			[],
			'v11.2.0',
			true
		);

		wp_enqueue_script(
			$countries_handle,
			constant( 'HCAPTCHA_URL' ) . '/assets/js/general-countries.js',
			[ 'jquery', $choices_handle ],
			constant( 'HCAPTCHA_VERSION' ),
			true
		);

		wp_localize_script(
			$countries_handle,
			'HCaptchaGeneralCountriesObject',
			[
				'searchPlaceholder' => $countries_search_placeholder,
				'searchAriaLabel'   => __( 'Search countries', 'hcaptcha-for-forms-and-more' ),
			]
		);

		$check_config_notice =
			esc_html__( 'Credentials changed.', 'hcaptcha-for-forms-and-more' ) . "\n" .
			esc_html__( 'Please complete hCaptcha and check the site config.', 'hcaptcha-for-forms-and-more' );

		/* translators: 1: Provider name. */
		$provider_error = __( '%1$s anti-spam provider is not configured.', 'hcaptcha-for-forms-and-more' );

		wp_localize_script(
			self::HANDLE,
			self::OBJECT,
			[
				'ajaxUrl'                              => admin_url( 'admin-ajax.php' ),
				'checkConfigAction'                    => self::CHECK_CONFIG_ACTION,
				'checkConfigNonce'                     => wp_create_nonce( self::CHECK_CONFIG_ACTION ),
				'checkIPsAction'                       => self::CHECK_IPS_ACTION,
				'checkIPsNonce'                        => wp_create_nonce( self::CHECK_IPS_ACTION ),
				'toggleSectionAction'                  => self::TOGGLE_SECTION_ACTION,
				'toggleSectionNonce'                   => wp_create_nonce( self::TOGGLE_SECTION_ACTION ),
				'modeLive'                             => self::MODE_LIVE,
				'modeTestPublisher'                    => self::MODE_TEST_PUBLISHER,
				'modeTestEnterpriseSafeEndUser'        => self::MODE_TEST_ENTERPRISE_SAFE_END_USER,
				'modeTestEnterpriseBotDetected'        => self::MODE_TEST_ENTERPRISE_BOT_DETECTED,
				'siteKey'                              => $settings ? $settings->get( 'site_key' ) : '',
				'modeTestPublisherSiteKey'             => self::MODE_TEST_PUBLISHER_SITE_KEY,
				'modeTestEnterpriseSafeEndUserSiteKey' => self::MODE_TEST_ENTERPRISE_SAFE_END_USER_SITE_KEY,
				'modeTestEnterpriseBotDetectedSiteKey' => self::MODE_TEST_ENTERPRISE_BOT_DETECTED_SITE_KEY,
				'badJSONError'                         => __( 'Bad JSON', 'hcaptcha-for-forms-and-more' ),
				'checkConfigNotice'                    => $check_config_notice,
				'checkingConfigMsg'                    => __( 'Checking site config...', 'hcaptcha-for-forms-and-more' ),
				'completeHCaptchaTitle'                => __( 'Please complete the hCaptcha.', 'hcaptcha-for-forms-and-more' ),
				'completeHCaptchaContent'              => __( 'Before checking the site config, please complete the Active hCaptcha in the current section.', 'hcaptcha-for-forms-and-more' ),
				'OKBtnText'                            => __( 'OK', 'hcaptcha-for-forms-and-more' ),
				'configuredAntiSpamProviders'          => AntiSpam::get_configured_providers(),
				'configuredAntiSpamProviderError'      => $provider_error,
			]
		);

		wp_enqueue_style(
			$choices_handle,
			constant( 'HCAPTCHA_URL' ) . '/assets/lib/choices/choices.min.css',
			[],
			'v11.2.0'
		);

		wp_enqueue_style(
			self::HANDLE,
			constant( 'HCAPTCHA_URL' ) . "/assets/css/general$this->min_suffix.css",
			[ static::PREFIX . '-' . SettingsBase::HANDLE, self::DIALOG_HANDLE, $choices_handle ],
			constant( 'HCAPTCHA_VERSION' )
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
	 * Ajax action to check IPs.
	 *
	 * @return void
	 */
	public function check_ips(): void {
		$this->run_checks( self::CHECK_IPS_ACTION );

		// Nonce is checked by check_ajax_referer() in run_checks().
		$ips     = Request::filter_input( INPUT_POST, 'ips' );
		$ips_arr = explode( ' ', $ips );

		foreach ( $ips_arr as $key => $ip ) {
			$ip = trim( $ip );

			if ( ! $this->is_valid_ip_or_range( $ip ) ) {
				wp_send_json_error(
					esc_html__( 'Invalid IP or CIDR range:', 'hcaptcha-for-forms-and-more' ) .
					' ' . esc_html( $ip )
				);

				// For testing purposes.
				return;
			}

			$ips_arr[ $key ] = $ip;
		}

		wp_send_json_success();
	}

	/**
	 * Ajax action to toggle a section.
	 *
	 * @return void
	 */
	public function toggle_section(): void {
		$this->run_checks( self::TOGGLE_SECTION_ACTION );

		// Nonce is checked by check_ajax_referer() in run_checks().
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$section = isset( $_POST['section'] ) ? sanitize_text_field( wp_unslash( $_POST['section'] ) ) : '';
		$status  = isset( $_POST['status'] )
			? filter_input( INPUT_POST, 'status', FILTER_VALIDATE_BOOLEAN )
			: false;
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$user    = wp_get_current_user();
		$user_id = $user->ID ?? 0;

		if ( ! $user_id ) {
			wp_send_json_error( esc_html__( 'Cannot save section status.', 'hcaptcha-for-forms-and-more' ) );

			return; // For testing purposes.
		}

		$hcaptcha_user_settings = array_filter(
			(array) get_user_meta( $user_id, self::USER_SETTINGS_META, true )
		);

		$hcaptcha_user_settings['sections'][ $section ] = (bool) $status;

		update_user_meta( $user_id, self::USER_SETTINGS_META, $hcaptcha_user_settings );

		wp_send_json_success();
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
			 */
			do_action( 'hcap_send_plugin_stats' );
		}

		return $value;
	}

	/**
	 * Load maxmind db if country settings are changed.
	 *
	 * @param mixed $value     New option value.
	 * @param mixed $old_value Old option value.
	 *
	 * @return mixed
	 */
	public function maybe_load_maxmind_db( $value, $old_value ) {
		$maxmind_key     = $value['maxmind_key'] ?? [];
		$old_maxmind_key = $old_value['maxmind_key'] ?? [];

		if ( $maxmind_key && $maxmind_key !== $old_maxmind_key ) {
			/**
			 * Statistics switch is turned on, send plugin statistics.
			 *
			 * @param string $maxmind_key Maxmind key.
			 */
			do_action( 'hcap_load_maxmind_db', $maxmind_key );
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

	/**
	 * Flatten array.
	 *
	 * @param array $arr Multidimensional array.
	 *
	 * @return array
	 */
	private function flatten_array( array $arr ): array {
		static $level = [], $result = [];

		foreach ( $arr as $key => $value ) {
			$level[] = $key;

			if ( is_array( $value ) ) {
				$result[] = [ implode( '--', $level ) => '' ];
				$result[] = $this->flatten_array( $value );

				array_pop( $level );
				continue;
			}

			$result[] = [ implode( '--', $level ) => $value ];

			array_pop( $level );
		}

		return array_merge( [], ...$result );
	}

	/**
	 * Get country names.
	 *
	 * @return array
	 */
	private function get_country_names(): array {
		static $country_names;

		if ( $country_names ) {
			return $country_names;
		}

		// Country codes are according ISO 3166-1 alpha-2.
		$country_names = [
			'AD' => __( 'Andorra', 'hcaptcha-for-forms-and-more' ),
			'AE' => __( 'United Arab Emirates', 'hcaptcha-for-forms-and-more' ),
			'AF' => __( 'Afghanistan', 'hcaptcha-for-forms-and-more' ),
			'AG' => __( 'Antigua and Barbuda', 'hcaptcha-for-forms-and-more' ),
			'AI' => __( 'Anguilla', 'hcaptcha-for-forms-and-more' ),
			'AL' => __( 'Albania', 'hcaptcha-for-forms-and-more' ),
			'AM' => __( 'Armenia', 'hcaptcha-for-forms-and-more' ),
			'AO' => __( 'Angola', 'hcaptcha-for-forms-and-more' ),
			'AR' => __( 'Argentina', 'hcaptcha-for-forms-and-more' ),
			'AS' => __( 'American Samoa', 'hcaptcha-for-forms-and-more' ),
			'AT' => __( 'Austria', 'hcaptcha-for-forms-and-more' ),
			'AU' => __( 'Australia', 'hcaptcha-for-forms-and-more' ),
			'AW' => __( 'Aruba', 'hcaptcha-for-forms-and-more' ),
			'AX' => __( 'Aland Islands', 'hcaptcha-for-forms-and-more' ),
			'AZ' => __( 'Azerbaijan', 'hcaptcha-for-forms-and-more' ),
			'BA' => __( 'Bosnia and Herzegovina', 'hcaptcha-for-forms-and-more' ),
			'BB' => __( 'Barbados', 'hcaptcha-for-forms-and-more' ),
			'BD' => __( 'Bangladesh', 'hcaptcha-for-forms-and-more' ),
			'BE' => __( 'Belgium', 'hcaptcha-for-forms-and-more' ),
			'BF' => __( 'Burkina Faso', 'hcaptcha-for-forms-and-more' ),
			'BG' => __( 'Bulgaria', 'hcaptcha-for-forms-and-more' ),
			'BH' => __( 'Bahrain', 'hcaptcha-for-forms-and-more' ),
			'BI' => __( 'Burundi', 'hcaptcha-for-forms-and-more' ),
			'BJ' => __( 'Benin', 'hcaptcha-for-forms-and-more' ),
			'BL' => __( 'St. Barthelemy', 'hcaptcha-for-forms-and-more' ),
			'BM' => __( 'Bermuda', 'hcaptcha-for-forms-and-more' ),
			'BN' => __( 'Brunei', 'hcaptcha-for-forms-and-more' ),
			'BO' => __( 'Bolivia', 'hcaptcha-for-forms-and-more' ),
			'BQ' => __( 'Bonaire, Sint Eustatius and Saba', 'hcaptcha-for-forms-and-more' ),
			'BR' => __( 'Brazil', 'hcaptcha-for-forms-and-more' ),
			'BS' => __( 'Bahamas', 'hcaptcha-for-forms-and-more' ),
			'BT' => __( 'Bhutan', 'hcaptcha-for-forms-and-more' ),
			'BW' => __( 'Botswana', 'hcaptcha-for-forms-and-more' ),
			'BY' => __( 'Belarus', 'hcaptcha-for-forms-and-more' ),
			'BZ' => __( 'Belize', 'hcaptcha-for-forms-and-more' ),
			'CA' => __( 'Canada', 'hcaptcha-for-forms-and-more' ),
			'CC' => __( 'Cocos (Keeling) Islands', 'hcaptcha-for-forms-and-more' ),
			'CD' => __( 'Congo (DRC)', 'hcaptcha-for-forms-and-more' ),
			'CF' => __( 'Central African Republic', 'hcaptcha-for-forms-and-more' ),
			'CG' => __( 'Congo', 'hcaptcha-for-forms-and-more' ),
			'CH' => __( 'Switzerland', 'hcaptcha-for-forms-and-more' ),
			'CI' => __( 'Cote d\'Ivoire', 'hcaptcha-for-forms-and-more' ),
			'CK' => __( 'Cook Islands', 'hcaptcha-for-forms-and-more' ),
			'CL' => __( 'Chile', 'hcaptcha-for-forms-and-more' ),
			'CM' => __( 'Cameroon', 'hcaptcha-for-forms-and-more' ),
			'CN' => __( 'China', 'hcaptcha-for-forms-and-more' ),
			'CO' => __( 'Colombia', 'hcaptcha-for-forms-and-more' ),
			'CR' => __( 'Costa Rica', 'hcaptcha-for-forms-and-more' ),
			'CU' => __( 'Cuba', 'hcaptcha-for-forms-and-more' ),
			'CV' => __( 'Cabo Verde', 'hcaptcha-for-forms-and-more' ),
			'CW' => __( 'Curacao', 'hcaptcha-for-forms-and-more' ),
			'CX' => __( 'Christmas Island', 'hcaptcha-for-forms-and-more' ),
			'CY' => __( 'Cyprus', 'hcaptcha-for-forms-and-more' ),
			'CZ' => __( 'Czechia', 'hcaptcha-for-forms-and-more' ),
			'DE' => __( 'Germany', 'hcaptcha-for-forms-and-more' ),
			'DJ' => __( 'Djibouti', 'hcaptcha-for-forms-and-more' ),
			'DK' => __( 'Denmark', 'hcaptcha-for-forms-and-more' ),
			'DM' => __( 'Dominica', 'hcaptcha-for-forms-and-more' ),
			'DO' => __( 'Dominican Republic', 'hcaptcha-for-forms-and-more' ),
			'DZ' => __( 'Algeria', 'hcaptcha-for-forms-and-more' ),
			'EC' => __( 'Ecuador', 'hcaptcha-for-forms-and-more' ),
			'EE' => __( 'Estonia', 'hcaptcha-for-forms-and-more' ),
			'EG' => __( 'Egypt', 'hcaptcha-for-forms-and-more' ),
			'ER' => __( 'Eritrea', 'hcaptcha-for-forms-and-more' ),
			'ES' => __( 'Spain', 'hcaptcha-for-forms-and-more' ),
			'ET' => __( 'Ethiopia', 'hcaptcha-for-forms-and-more' ),
			'FI' => __( 'Finland', 'hcaptcha-for-forms-and-more' ),
			'FJ' => __( 'Fiji', 'hcaptcha-for-forms-and-more' ),
			'FK' => __( 'Falkland Islands', 'hcaptcha-for-forms-and-more' ),
			'FM' => __( 'Micronesia', 'hcaptcha-for-forms-and-more' ),
			'FO' => __( 'Faroe Islands', 'hcaptcha-for-forms-and-more' ),
			'FR' => __( 'France', 'hcaptcha-for-forms-and-more' ),
			'GA' => __( 'Gabon', 'hcaptcha-for-forms-and-more' ),
			'GB' => __( 'United Kingdom', 'hcaptcha-for-forms-and-more' ),
			'GD' => __( 'Grenada', 'hcaptcha-for-forms-and-more' ),
			'GE' => __( 'Georgia', 'hcaptcha-for-forms-and-more' ),
			'GF' => __( 'French Guiana', 'hcaptcha-for-forms-and-more' ),
			'GG' => __( 'Guernsey', 'hcaptcha-for-forms-and-more' ),
			'GH' => __( 'Ghana', 'hcaptcha-for-forms-and-more' ),
			'GI' => __( 'Gibraltar', 'hcaptcha-for-forms-and-more' ),
			'GL' => __( 'Greenland', 'hcaptcha-for-forms-and-more' ),
			'GM' => __( 'Gambia', 'hcaptcha-for-forms-and-more' ),
			'GN' => __( 'Guinea', 'hcaptcha-for-forms-and-more' ),
			'GP' => __( 'Guadeloupe', 'hcaptcha-for-forms-and-more' ),
			'GQ' => __( 'Equatorial Guinea', 'hcaptcha-for-forms-and-more' ),
			'GR' => __( 'Greece', 'hcaptcha-for-forms-and-more' ),
			'GT' => __( 'Guatemala', 'hcaptcha-for-forms-and-more' ),
			'GU' => __( 'Guam', 'hcaptcha-for-forms-and-more' ),
			'GW' => __( 'Guinea-Bissau', 'hcaptcha-for-forms-and-more' ),
			'GY' => __( 'Guyana', 'hcaptcha-for-forms-and-more' ),
			'HK' => __( 'Hong Kong SAR', 'hcaptcha-for-forms-and-more' ),
			'HN' => __( 'Honduras', 'hcaptcha-for-forms-and-more' ),
			'HR' => __( 'Croatia', 'hcaptcha-for-forms-and-more' ),
			'HT' => __( 'Haiti', 'hcaptcha-for-forms-and-more' ),
			'HU' => __( 'Hungary', 'hcaptcha-for-forms-and-more' ),
			'ID' => __( 'Indonesia', 'hcaptcha-for-forms-and-more' ),
			'IE' => __( 'Ireland', 'hcaptcha-for-forms-and-more' ),
			'IL' => __( 'Israel', 'hcaptcha-for-forms-and-more' ),
			'IM' => __( 'Isle of Man', 'hcaptcha-for-forms-and-more' ),
			'IN' => __( 'India', 'hcaptcha-for-forms-and-more' ),
			'IO' => __( 'British Indian Ocean Territory', 'hcaptcha-for-forms-and-more' ),
			'IQ' => __( 'Iraq', 'hcaptcha-for-forms-and-more' ),
			'IR' => __( 'Iran', 'hcaptcha-for-forms-and-more' ),
			'IS' => __( 'Iceland', 'hcaptcha-for-forms-and-more' ),
			'IT' => __( 'Italy', 'hcaptcha-for-forms-and-more' ),
			'JE' => __( 'Jersey', 'hcaptcha-for-forms-and-more' ),
			'JM' => __( 'Jamaica', 'hcaptcha-for-forms-and-more' ),
			'JO' => __( 'Jordan', 'hcaptcha-for-forms-and-more' ),
			'JP' => __( 'Japan', 'hcaptcha-for-forms-and-more' ),
			'KE' => __( 'Kenya', 'hcaptcha-for-forms-and-more' ),
			'KG' => __( 'Kyrgyzstan', 'hcaptcha-for-forms-and-more' ),
			'KH' => __( 'Cambodia', 'hcaptcha-for-forms-and-more' ),
			'KI' => __( 'Kiribati', 'hcaptcha-for-forms-and-more' ),
			'KM' => __( 'Comoros', 'hcaptcha-for-forms-and-more' ),
			'KN' => __( 'St. Kitts and Nevis', 'hcaptcha-for-forms-and-more' ),
			'KP' => __( 'North Korea', 'hcaptcha-for-forms-and-more' ),
			'KR' => __( 'Korea', 'hcaptcha-for-forms-and-more' ),
			'KW' => __( 'Kuwait', 'hcaptcha-for-forms-and-more' ),
			'KY' => __( 'Cayman Islands', 'hcaptcha-for-forms-and-more' ),
			'KZ' => __( 'Kazakhstan', 'hcaptcha-for-forms-and-more' ),
			'LA' => __( 'Laos', 'hcaptcha-for-forms-and-more' ),
			'LB' => __( 'Lebanon', 'hcaptcha-for-forms-and-more' ),
			'LC' => __( 'St. Lucia', 'hcaptcha-for-forms-and-more' ),
			'LI' => __( 'Liechtenstein', 'hcaptcha-for-forms-and-more' ),
			'LK' => __( 'Sri Lanka', 'hcaptcha-for-forms-and-more' ),
			'LR' => __( 'Liberia', 'hcaptcha-for-forms-and-more' ),
			'LS' => __( 'Lesotho', 'hcaptcha-for-forms-and-more' ),
			'LT' => __( 'Lithuania', 'hcaptcha-for-forms-and-more' ),
			'LU' => __( 'Luxembourg', 'hcaptcha-for-forms-and-more' ),
			'LV' => __( 'Latvia', 'hcaptcha-for-forms-and-more' ),
			'LY' => __( 'Libya', 'hcaptcha-for-forms-and-more' ),
			'MA' => __( 'Morocco', 'hcaptcha-for-forms-and-more' ),
			'MC' => __( 'Monaco', 'hcaptcha-for-forms-and-more' ),
			'MD' => __( 'Moldova', 'hcaptcha-for-forms-and-more' ),
			'ME' => __( 'Montenegro', 'hcaptcha-for-forms-and-more' ),
			'MF' => __( 'St. Martin', 'hcaptcha-for-forms-and-more' ),
			'MG' => __( 'Madagascar', 'hcaptcha-for-forms-and-more' ),
			'MH' => __( 'Marshall Islands', 'hcaptcha-for-forms-and-more' ),
			'MK' => __( 'North Macedonia', 'hcaptcha-for-forms-and-more' ),
			'ML' => __( 'Mali', 'hcaptcha-for-forms-and-more' ),
			'MM' => __( 'Myanmar', 'hcaptcha-for-forms-and-more' ),
			'MN' => __( 'Mongolia', 'hcaptcha-for-forms-and-more' ),
			'MO' => __( 'Macao SAR', 'hcaptcha-for-forms-and-more' ),
			'MP' => __( 'Northern Mariana Islands', 'hcaptcha-for-forms-and-more' ),
			'MQ' => __( 'Martinique', 'hcaptcha-for-forms-and-more' ),
			'MR' => __( 'Mauritania', 'hcaptcha-for-forms-and-more' ),
			'MS' => __( 'Montserrat', 'hcaptcha-for-forms-and-more' ),
			'MT' => __( 'Malta', 'hcaptcha-for-forms-and-more' ),
			'MU' => __( 'Mauritius', 'hcaptcha-for-forms-and-more' ),
			'MV' => __( 'Maldives', 'hcaptcha-for-forms-and-more' ),
			'MW' => __( 'Malawi', 'hcaptcha-for-forms-and-more' ),
			'MX' => __( 'Mexico', 'hcaptcha-for-forms-and-more' ),
			'MY' => __( 'Malaysia', 'hcaptcha-for-forms-and-more' ),
			'MZ' => __( 'Mozambique', 'hcaptcha-for-forms-and-more' ),
			'NA' => __( 'Namibia', 'hcaptcha-for-forms-and-more' ),
			'NC' => __( 'New Caledonia', 'hcaptcha-for-forms-and-more' ),
			'NE' => __( 'Niger', 'hcaptcha-for-forms-and-more' ),
			'NF' => __( 'Norfolk Island', 'hcaptcha-for-forms-and-more' ),
			'NG' => __( 'Nigeria', 'hcaptcha-for-forms-and-more' ),
			'NI' => __( 'Nicaragua', 'hcaptcha-for-forms-and-more' ),
			'NL' => __( 'Netherlands', 'hcaptcha-for-forms-and-more' ),
			'NO' => __( 'Norway', 'hcaptcha-for-forms-and-more' ),
			'NP' => __( 'Nepal', 'hcaptcha-for-forms-and-more' ),
			'NR' => __( 'Nauru', 'hcaptcha-for-forms-and-more' ),
			'NU' => __( 'Niue', 'hcaptcha-for-forms-and-more' ),
			'NZ' => __( 'New Zealand', 'hcaptcha-for-forms-and-more' ),
			'OM' => __( 'Oman', 'hcaptcha-for-forms-and-more' ),
			'PA' => __( 'Panama', 'hcaptcha-for-forms-and-more' ),
			'PE' => __( 'Peru', 'hcaptcha-for-forms-and-more' ),
			'PF' => __( 'French Polynesia', 'hcaptcha-for-forms-and-more' ),
			'PG' => __( 'Papua New Guinea', 'hcaptcha-for-forms-and-more' ),
			'PH' => __( 'Philippines', 'hcaptcha-for-forms-and-more' ),
			'PK' => __( 'Pakistan', 'hcaptcha-for-forms-and-more' ),
			'PL' => __( 'Poland', 'hcaptcha-for-forms-and-more' ),
			'PM' => __( 'St. Pierre and Miquelon', 'hcaptcha-for-forms-and-more' ),
			'PN' => __( 'Pitcairn Islands', 'hcaptcha-for-forms-and-more' ),
			'PR' => __( 'Puerto Rico', 'hcaptcha-for-forms-and-more' ),
			'PS' => __( 'Palestinian Authority', 'hcaptcha-for-forms-and-more' ),
			'PT' => __( 'Portugal', 'hcaptcha-for-forms-and-more' ),
			'PW' => __( 'Palau', 'hcaptcha-for-forms-and-more' ),
			'PY' => __( 'Paraguay', 'hcaptcha-for-forms-and-more' ),
			'QA' => __( 'Qatar', 'hcaptcha-for-forms-and-more' ),
			'RE' => __( 'Reunion', 'hcaptcha-for-forms-and-more' ),
			'RO' => __( 'Romania', 'hcaptcha-for-forms-and-more' ),
			'RS' => __( 'Serbia', 'hcaptcha-for-forms-and-more' ),
			'RU' => __( 'Russia', 'hcaptcha-for-forms-and-more' ),
			'RW' => __( 'Rwanda', 'hcaptcha-for-forms-and-more' ),
			'SA' => __( 'Saudi Arabia', 'hcaptcha-for-forms-and-more' ),
			'SB' => __( 'Solomon Islands', 'hcaptcha-for-forms-and-more' ),
			'SC' => __( 'Seychelles', 'hcaptcha-for-forms-and-more' ),
			'SD' => __( 'Sudan', 'hcaptcha-for-forms-and-more' ),
			'SE' => __( 'Sweden', 'hcaptcha-for-forms-and-more' ),
			'SG' => __( 'Singapore', 'hcaptcha-for-forms-and-more' ),
			'SH' => __( 'St Helena, Ascension, Tristan da Cunha', 'hcaptcha-for-forms-and-more' ),
			'SI' => __( 'Slovenia', 'hcaptcha-for-forms-and-more' ),
			'SJ' => __( 'Svalbard and Jan Mayen', 'hcaptcha-for-forms-and-more' ),
			'SK' => __( 'Slovakia', 'hcaptcha-for-forms-and-more' ),
			'SL' => __( 'Sierra Leone', 'hcaptcha-for-forms-and-more' ),
			'SM' => __( 'San Marino', 'hcaptcha-for-forms-and-more' ),
			'SN' => __( 'Senegal', 'hcaptcha-for-forms-and-more' ),
			'SO' => __( 'Somalia', 'hcaptcha-for-forms-and-more' ),
			'SR' => __( 'Suriname', 'hcaptcha-for-forms-and-more' ),
			'SS' => __( 'South Sudan', 'hcaptcha-for-forms-and-more' ),
			'ST' => __( 'Sao Tome and Principe', 'hcaptcha-for-forms-and-more' ),
			'SV' => __( 'El Salvador', 'hcaptcha-for-forms-and-more' ),
			'SX' => __( 'Sint Maarten', 'hcaptcha-for-forms-and-more' ),
			'SY' => __( 'Syria', 'hcaptcha-for-forms-and-more' ),
			'SZ' => __( 'Eswatini', 'hcaptcha-for-forms-and-more' ),
			'TC' => __( 'Turks and Caicos Islands', 'hcaptcha-for-forms-and-more' ),
			'TD' => __( 'Chad', 'hcaptcha-for-forms-and-more' ),
			'TG' => __( 'Togo', 'hcaptcha-for-forms-and-more' ),
			'TH' => __( 'Thailand', 'hcaptcha-for-forms-and-more' ),
			'TJ' => __( 'Tajikistan', 'hcaptcha-for-forms-and-more' ),
			'TK' => __( 'Tokelau', 'hcaptcha-for-forms-and-more' ),
			'TL' => __( 'Timor-Leste', 'hcaptcha-for-forms-and-more' ),
			'TM' => __( 'Turkmenistan', 'hcaptcha-for-forms-and-more' ),
			'TN' => __( 'Tunisia', 'hcaptcha-for-forms-and-more' ),
			'TO' => __( 'Tonga', 'hcaptcha-for-forms-and-more' ),
			'TR' => __( 'Turkiye', 'hcaptcha-for-forms-and-more' ),
			'TT' => __( 'Trinidad and Tobago', 'hcaptcha-for-forms-and-more' ),
			'TV' => __( 'Tuvalu', 'hcaptcha-for-forms-and-more' ),
			'TW' => __( 'Taiwan', 'hcaptcha-for-forms-and-more' ),
			'TZ' => __( 'Tanzania', 'hcaptcha-for-forms-and-more' ),
			'UA' => __( 'Ukraine', 'hcaptcha-for-forms-and-more' ),
			'UG' => __( 'Uganda', 'hcaptcha-for-forms-and-more' ),
			'UM' => __( 'U.S. Outlying Islands', 'hcaptcha-for-forms-and-more' ),
			'US' => __( 'United States', 'hcaptcha-for-forms-and-more' ),
			'UY' => __( 'Uruguay', 'hcaptcha-for-forms-and-more' ),
			'UZ' => __( 'Uzbekistan', 'hcaptcha-for-forms-and-more' ),
			'VA' => __( 'Vatican City', 'hcaptcha-for-forms-and-more' ),
			'VC' => __( 'St. Vincent and Grenadines', 'hcaptcha-for-forms-and-more' ),
			'VE' => __( 'Venezuela', 'hcaptcha-for-forms-and-more' ),
			'VG' => __( 'British Virgin Islands', 'hcaptcha-for-forms-and-more' ),
			'VI' => __( 'U.S. Virgin Islands', 'hcaptcha-for-forms-and-more' ),
			'VN' => __( 'Vietnam', 'hcaptcha-for-forms-and-more' ),
			'VU' => __( 'Vanuatu', 'hcaptcha-for-forms-and-more' ),
			'WF' => __( 'Wallis and Futuna', 'hcaptcha-for-forms-and-more' ),
			'WS' => __( 'Samoa', 'hcaptcha-for-forms-and-more' ),
			'XK' => __( 'Kosovo', 'hcaptcha-for-forms-and-more' ),
			'YE' => __( 'Yemen', 'hcaptcha-for-forms-and-more' ),
			'YT' => __( 'Mayotte', 'hcaptcha-for-forms-and-more' ),
			'ZA' => __( 'South Africa', 'hcaptcha-for-forms-and-more' ),
			'ZM' => __( 'Zambia', 'hcaptcha-for-forms-and-more' ),
			'ZW' => __( 'Zimbabwe', 'hcaptcha-for-forms-and-more' ),
		];

		return $country_names;
	}

	/**
	 * Validate IP or CIDR range.
	 *
	 * @param string $input Input to validate.
	 *
	 * @return bool
	 */
	private function is_valid_ip_or_range( string $input ): bool {
		$input = trim( $input );

		// Check for a single IP (IPv4 or IPv6).
		if ( filter_var( $input, FILTER_VALIDATE_IP ) ) {
			return true;
		}

		// Check CIDR-range.
		if ( strpos( $input, '/' ) !== false ) {
			[ $ip, $prefix ] = explode( '/', $input, 2 );

			// Check that the prefix is correct.
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) && filter_var( $prefix, FILTER_VALIDATE_INT ) !== false ) {
				$prefix = (int) $prefix;

				if (
					( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) && $prefix >= 0 && $prefix <= 32 ) ||
					( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) && $prefix >= 0 && $prefix <= 128 )
				) {
					return true;
				}
			}

			return false;
		}

		// Check the range of 'IP-IP' type.
		if ( strpos( $input, '-' ) !== false ) {
			[ $ip_start, $ip_end ] = explode( '-', $input, 2 );

			$ip_start = trim( $ip_start );
			$ip_end   = trim( $ip_end );

			if ( filter_var( $ip_start, FILTER_VALIDATE_IP ) && filter_var( $ip_end, FILTER_VALIDATE_IP ) ) {
				// Make sure that both IPs are of the same type (IPv4/IPv6).
				if (
					( filter_var( $ip_start, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) && filter_var( $ip_end, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) ||
					( filter_var( $ip_start, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) && filter_var( $ip_end, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) )
				) {
					return true;
				}
			}
		}

		return false;
	}
}
