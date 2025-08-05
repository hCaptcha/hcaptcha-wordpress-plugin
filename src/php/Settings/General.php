<?php
/**
 * General class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Settings;

use HCaptcha\Admin\Notifications;
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
	 * Other section id.
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
	 * @var Notifications
	 */
	protected $notifications;

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
	 * Init class hooks.
	 *
	 * @return void
	 */
	protected function init_hooks(): void {
		parent::init_hooks();

		$hcaptcha = hcaptcha();

		if ( wp_doing_ajax() ) {
			// We need ajax actions in the Notifications class.
			$this->init_notifications();
		} else {
			// The current class loaded early on plugins_loaded.
			// Init Notifications later, when the Settings class is ready.
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
			],
			'secret_key'           => [
				'label'   => __( 'Secret Key', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'password',
				'section' => self::SECTION_KEYS,
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
				'default' => self::MODE_LIVE,
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
			'custom_prop'          => [
				'label'   => __( 'Property', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'select',
				'options' => [],
				'section' => self::SECTION_CUSTOM,
				'helper'  => __( 'Select custom theme property.', 'hcaptcha-for-forms-and-more' ),
			],
			'custom_value'         => [
				'label'   => __( 'Value', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'text',
				'section' => self::SECTION_CUSTOM,
				'helper'  => __( 'Set property value.', 'hcaptcha-for-forms-and-more' ),
			],
			'config_params'        => [
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
			'antispam'             => [
				'label'   => __( 'Enable anti-spam check', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'checkbox',
				'section' => self::SECTION_ANTISPAM,
				'options' => [
					'on' => __( 'Anti-spam check', 'hcaptcha-for-forms-and-more' ),
				],
				'helper'  => __( 'Enable anti-spam check of submitted forms.', 'hcaptcha-for-forms-and-more' ),
			],
			'antispam_provider'    => [
				'label'   => __( 'Anti-spam provider', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'select',
				'section' => self::SECTION_ANTISPAM,
				'options' => AntiSpam::get_supported_providers(),
				'helper'  => __( 'Select anti-spam provider.', 'hcaptcha-for-forms-and-more' ),
			],
			'blacklisted_ips'      => [
				'label'   => __( 'Denylisted IPs', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'textarea',
				'section' => self::SECTION_OTHER,
				'helper'  => __( 'Block form sending from listed IP addresses. Please specify one IP, range, or CIDR per line.', 'hcaptcha-for-forms-and-more' ),
			],
			'whitelisted_ips'      => [
				'label'   => __( 'Allowlisted IPs', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'textarea',
				'section' => self::SECTION_OTHER,
				'helper'  => __( 'Do not show hCaptcha for listed IP addresses. Please specify one IP, range, or CIDR per line.', 'hcaptcha-for-forms-and-more' ),
			],
			'off_when_logged_in'   => [
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
			'hide_login_errors'    => [
				'type'    => 'checkbox',
				'section' => self::SECTION_OTHER,
				'options' => [
					'on' => __( 'Hide Login Errors', 'hcaptcha-for-forms-and-more' ),
				],
				'helper'  => __( 'Avoid specifying errors like "invalid username" or "invalid password" to limit information exposure to attackers.', 'hcaptcha-for-forms-and-more' ),
			],
			'cleanup_on_uninstall' => [
				'type'    => 'checkbox',
				'section' => self::SECTION_OTHER,
				'options' => [
					'on' => __( 'Remove Data on Uninstall', 'hcaptcha-for-forms-and-more' ),
				],
				'default' => '',
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
			'login_limit'          => [
				'label'   => __( 'Login attempts before hCaptcha', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'number',
				'section' => self::SECTION_OTHER,
				'default' => 0,
				'min'     => 0,
				'helper'  => __( 'Maximum number of failed login attempts before showing hCaptcha.', 'hcaptcha-for-forms-and-more' ),
			],
			'login_interval'       => [
				'label'   => __( 'Failed login attempts interval, min', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'number',
				'section' => self::SECTION_OTHER,
				'default' => 15,
				'min'     => 1,
				'helper'  => __( 'Time interval in minutes when failed login attempts are counted.', 'hcaptcha-for-forms-and-more' ),
			],
			'delay'                => [
				'label'   => __( 'Delay showing hCaptcha, ms', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'number',
				'section' => self::SECTION_OTHER,
				'default' => -100,
				'min'     => -100,
				'step'    => 100,
				'helper'  => __( 'Delay time for loading the hCaptcha API script. Any negative value will prevent the API script from loading until user interaction: mouseenter, click, scroll or touch. This significantly improves Google Pagespeed Insights score.', 'hcaptcha-for-forms-and-more' ),
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

		// In Settings, a filter applied for mode.
		$mode = $settings->get_mode();

		if ( self::MODE_LIVE !== $mode ) {
			$this->form_fields['site_key']['disabled']   = true;
			$this->form_fields['secret_key']['disabled'] = true;
		}

		$config_params = $settings->get_config_params();
		$custom_theme  = $config_params['theme'] ?? [];
		$default_theme = $settings->get_default_theme();
		$custom_theme  = array_replace_recursive( $default_theme, $custom_theme );
		$custom_theme  = $this->flatten_array( $custom_theme );
		$options       = [];
		$custom_theme  = array_merge(
			[ esc_html__( '- Select Property -', 'hcaptcha-for-forms-and-more' ) => '' ],
			$custom_theme
		);

		foreach ( $custom_theme as $key => $value ) {
			$key_arr = explode( '--', $key );
			$level   = count( $key_arr ) - 1;
			$prefix  = $level ? str_repeat( '–', $level ) . ' ' : '';
			$option  = $prefix . ucfirst( end( $key_arr ) );

			$options[ $key . '=' . $value ] = $option;
		}

		$this->form_fields['custom_prop']['options'] = $options;

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
		$user                   = wp_get_current_user();
		$hcaptcha_user_settings = [];

		if ( $user ) {
			$hcaptcha_user_settings = get_user_meta( $user->ID, self::USER_SETTINGS_META, true );
		}

		$open     = $hcaptcha_user_settings['sections'][ $id ] ?? true;
		$disabled = '';
		$class    = '';
		$license  = hcaptcha()->settings()->get_license();

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

		$class .= $open ? '' : ' closed';
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
			constant( 'HCAPTCHA_URL' ) . "/assets/js/general$this->min_suffix.js",
			[ 'jquery', self::DIALOG_HANDLE ],
			constant( 'HCAPTCHA_VERSION' ),
			true
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
				'siteKey'                              => hcaptcha()->settings()->get( 'site_key' ),
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
			self::HANDLE,
			constant( 'HCAPTCHA_URL' ) . "/assets/css/general$this->min_suffix.css",
			[ static::PREFIX . '-' . SettingsBase::HANDLE, self::DIALOG_HANDLE ],
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

		$display = 'none';

		if ( 'invisible' === hcaptcha()->settings()->get( 'size' ) ) {
			$display = 'block';
		}

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
			$prefix = esc_html__( 'Site configuration error', 'hcaptcha-for-forms-and-more' );
			$prefix = $error ? $prefix . ': ' : $prefix . '.';
		}

		wp_send_json_error( $prefix . $error );
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
