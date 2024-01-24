<?php
/**
 * General class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Settings;

use HCaptcha\Admin\Notifications;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Main;
use KAGG\Settings\Abstracts\SettingsBase;

/**
 * Class General
 *
 * Settings page "General".
 */
class General extends PluginSettingsBase {

	/**
	 * Admin script handle.
	 */
	const HANDLE = 'hcaptcha-general';

	/**
	 * Script localization object.
	 */
	const OBJECT = 'HCaptchaGeneralObject';

	/**
	 * Check config ajax action.
	 */
	const CHECK_CONFIG_ACTION = 'hcaptcha-general-check-config';

	/**
	 * Toggle section ajax action.
	 */
	const TOGGLE_SECTION_ACTION = 'hcaptcha-general-toggle-section';

	/**
	 * Keys section id.
	 */
	const SECTION_KEYS = 'keys';

	/**
	 * Appearance section id.
	 */
	const SECTION_APPEARANCE = 'appearance';

	/**
	 * Custom section id.
	 */
	const SECTION_CUSTOM = 'custom';

	/**
	 * Enterprise section id.
	 */
	const SECTION_ENTERPRISE = 'enterprise';

	/**
	 * Other section id.
	 */
	const SECTION_OTHER = 'other';

	/**
	 * Live mode.
	 */
	const MODE_LIVE = 'live';

	/**
	 * Test publisher mode.
	 */
	const MODE_TEST_PUBLISHER = 'test:publisher';

	/**
	 * Test enterprise safe end user mode.
	 */
	const MODE_TEST_ENTERPRISE_SAFE_END_USER = 'test:enterprise_safe_end_user';

	/**
	 * Test enterprise bot detected mode.
	 */
	const MODE_TEST_ENTERPRISE_BOT_DETECTED = 'test:enterprise_bot_detected';

	/**
	 * Test publisher mode site key.
	 */
	const MODE_TEST_PUBLISHER_SITE_KEY = '10000000-ffff-ffff-ffff-000000000001';

	/**
	 * Test enterprise safe end user mode site key.
	 */
	const MODE_TEST_ENTERPRISE_SAFE_END_USER_SITE_KEY = '20000000-ffff-ffff-ffff-000000000002';

	/**
	 * Test enterprise bot detected mode site key.
	 */
	const MODE_TEST_ENTERPRISE_BOT_DETECTED_SITE_KEY = '30000000-ffff-ffff-ffff-000000000003';

	/**
	 * User settings meta.
	 */
	const USER_SETTINGS_META = 'hcaptcha_user_settings';

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
	 */
	protected function init_hooks() {
		parent::init_hooks();

		$hcaptcha = hcaptcha();

		// Current class loaded early on plugins_loaded. Init Notifications later, when Settings class is ready.
		add_action( 'plugins_loaded', [ $this, 'init_notifications' ] );
		add_action( 'admin_head', [ $hcaptcha, 'print_inline_styles' ] );
		add_action( 'admin_print_footer_scripts', [ $hcaptcha, 'print_footer_scripts' ], 0 );

		add_filter( 'kagg_settings_fields', [ $this, 'settings_fields' ] );
		add_action( 'wp_ajax_' . self::CHECK_CONFIG_ACTION, [ $this, 'check_config' ] );
		add_action( 'wp_ajax_' . self::TOGGLE_SECTION_ACTION, [ $this, 'toggle_section' ] );
	}

	/**
	 * Init notifications.
	 *
	 * @return void
	 */
	public function init_notifications() {
		$this->notifications = new Notifications();
		$this->notifications->init();
	}

	/**
	 * Init form fields.
	 */
	public function init_form_fields() {
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
				'helper'  => __( 'See Enterprise docs.' ),
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
			self::NETWORK_WIDE     => [
				'type'    => 'checkbox',
				'section' => self::SECTION_OTHER,
				'options' => [
					'on' => __( 'Use network-wide settings', 'hcaptcha-for-forms-and-more' ),
				],
				'helper'  => __( 'On multisite, use same settings for all sites of the network.', 'hcaptcha-for-forms-and-more' ),
			],
			'whitelisted_ips'      => [
				'label'   => __( 'Whitelisted IPs', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'textarea',
				'section' => self::SECTION_OTHER,
				'helper'  => __( 'Do not show hCaptcha for listed IP addresses. Please specify one IP address per line.', 'hcaptcha-for-forms-and-more' ),
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
		];

		if ( ! is_multisite() ) {
			unset( $this->form_fields[ self::NETWORK_WIDE ] );
		}
	}

	/**
	 * Setup settings fields.
	 */
	public function setup_fields() {
		if ( ! $this->is_options_screen() ) {
			return;
		}

		// In Settings, a filter applied for mode.
		$mode = hcaptcha()->settings()->get_mode();

		if ( self::MODE_LIVE !== $mode ) {
			$this->form_fields['site_key']['disabled']   = true;
			$this->form_fields['secret_key']['disabled'] = true;
		}

		parent::setup_fields();
	}

	/**
	 * Section callback.
	 *
	 * @param array $arguments Section arguments.
	 */
	public function section_callback( array $arguments ) {
		switch ( $arguments['id'] ) {
			case self::SECTION_KEYS:
				?>
				<h2>
					<?php echo esc_html( $this->page_title() ); ?>
				</h2>
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
			case self::SECTION_OTHER:
				$this->print_section_header( $arguments['id'], __( 'Other', 'hcaptcha-for-forms-and-more' ) );
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
	private function print_section_header( string $id, string $title ) {
		$user                   = wp_get_current_user();
		$hcaptcha_user_settings = [];

		if ( $user ) {
			$hcaptcha_user_settings = get_user_meta( $user->ID, self::USER_SETTINGS_META, true );
		}

		$open  = $hcaptcha_user_settings['sections'][ $id ] ?? true;
		$class = $open ? '' : ' closed';

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
	 */
	public function admin_enqueue_scripts() {
		wp_enqueue_script(
			self::HANDLE,
			constant( 'HCAPTCHA_URL' ) . "/assets/js/general$this->min_prefix.js",
			[ 'jquery' ],
			constant( 'HCAPTCHA_VERSION' ),
			true
		);

		$check_config_notice =
			esc_html__( 'Credentials changed.', 'hcaptcha-for-forms-and-more' ) . "\n" .
			esc_html__( 'Please complete hCaptcha and check the site config.', 'hcaptcha-for-forms-and-more' );

		wp_localize_script(
			self::HANDLE,
			self::OBJECT,
			[
				'ajaxUrl'                              => admin_url( 'admin-ajax.php' ),
				'checkConfigAction'                    => self::CHECK_CONFIG_ACTION,
				'checkConfigNonce'                     => wp_create_nonce( self::CHECK_CONFIG_ACTION ),
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
				'checkConfigNotice'                    => $check_config_notice,
			]
		);

		wp_enqueue_style(
			self::HANDLE,
			constant( 'HCAPTCHA_URL' ) . "/assets/css/general$this->min_prefix.css",
			[ static::PREFIX . '-' . SettingsBase::HANDLE ],
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
	public function print_hcaptcha_field() {
		HCaptcha::form_display();

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
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function check_config() {
		$this->run_checks( self::CHECK_CONFIG_ACTION );

		// Nonce is checked by check_ajax_referer() in run_checks().
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$ajax_mode       = isset( $_POST['mode'] ) ? sanitize_text_field( wp_unslash( $_POST['mode'] ) ) : '';
		$ajax_site_key   = isset( $_POST['siteKey'] ) ? sanitize_text_field( wp_unslash( $_POST['siteKey'] ) ) : '';
		$ajax_secret_key = isset( $_POST['secretKey'] ) ? sanitize_text_field( wp_unslash( $_POST['secretKey'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		add_filter(
			'hcap_mode',
			static function ( $mode ) use ( $ajax_mode ) {
				return $ajax_mode;
			}
		);

		if ( self::MODE_LIVE === $ajax_mode ) {
			add_filter(
				'hcap_site_key',
				static function ( $site_key ) use ( $ajax_site_key ) {
					return $ajax_site_key;
				}
			);
			add_filter(
				'hcap_secret_key',
				static function ( $secret_key ) use ( $ajax_secret_key ) {
					return $ajax_secret_key;
				}
			);
		}

		$settings = hcaptcha()->settings();
		$params   = [
			'host'    => (string) wp_parse_url( site_url(), PHP_URL_HOST ),
			'sitekey' => $settings->get_site_key(),
			'sc'      => 1,
			'swa'     => 1,
			'spst'    => 0,
		];
		$url      = add_query_arg( $params, hcaptcha()->get_check_site_config_url() );

		$raw_response = wp_remote_post( $url );

		$raw_body = wp_remote_retrieve_body( $raw_response );

		if ( empty( $raw_body ) ) {
			$this->send_check_config_error( __( 'Cannot communicate with hCaptcha server.', 'hcaptcha-for-forms-and-more' ) );
		}

		$body = json_decode( $raw_body, true );

		if ( ! $body ) {
			$this->send_check_config_error( __( 'Cannot decode hCaptcha server response.', 'hcaptcha-for-forms-and-more' ) );
		}

		if ( empty( $body['pass'] ) ) {
			$error = $body['error'] ? (string) $body['error'] : '';
			$error = $error ? ': ' . $error : '';

			$this->send_check_config_error( $error );
		}

		// Nonce is checked by check_ajax_referer() in run_checks().
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$hcaptcha_response = isset( $_POST['h-captcha-response'] ) ?
			filter_var( wp_unslash( $_POST['h-captcha-response'] ), FILTER_SANITIZE_FULL_SPECIAL_CHARS ) :
			'';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$result = hcaptcha_request_verify( $hcaptcha_response );

		if ( null !== $result ) {
			$this->send_check_config_error( $result, true );
		}

		wp_send_json_success(
			esc_html__( 'Site config is valid.', 'hcaptcha-for-forms-and-more' )
		);
	}

	/**
	 * Ajax action to toggle a section.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function toggle_section() {
		$this->run_checks( self::TOGGLE_SECTION_ACTION );

		// Nonce is checked by check_ajax_referer() in run_checks().
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$section = isset( $_POST['section'] ) ? sanitize_text_field( wp_unslash( $_POST['section'] ) ) : '';
		$status  = isset( $_POST['status'] ) ?
			filter_input( INPUT_POST, 'status', FILTER_VALIDATE_BOOL ) :
			false;
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$user = wp_get_current_user();

		if ( ! $user ) {
			wp_send_json_error( esc_html__( 'Cannot save section status.', 'hcaptcha-for-forms-and-more' ) );
		}

		$hcaptcha_user_settings = array_filter(
			(array) get_user_meta( $user->ID, self::USER_SETTINGS_META, true )
		);

		$hcaptcha_user_settings['sections'][ $section ] = (bool) $status;

		update_user_meta( $user->ID, self::USER_SETTINGS_META, $hcaptcha_user_settings );

		wp_send_json_success();
	}

	/**
	 * Check ajax call.
	 *
	 * @param string $action Action.
	 *
	 * @return void
	 */
	private function run_checks( string $action ) {
		// Run a security check.
		if ( ! check_ajax_referer( $action, 'nonce', false ) ) {
			wp_send_json_error( esc_html__( 'Your session has expired. Please reload the page.', 'hcaptcha-for-forms-and-more' ) );
		}

		// Check for permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'You are not allowed to perform this action.', 'hcaptcha-for-forms-and-more' ) );
		}
	}

	/**
	 * Send check config error.
	 *
	 * @param string $error      Error message.
	 * @param bool   $raw_result Send a raw result.
	 *
	 * @return void
	 */
	private function send_check_config_error( string $error, $raw_result = false ) {
		$prefix = $raw_result ? '' : esc_html__( 'Site configuration error: ', 'hcaptcha-for-forms-and-more' );

		wp_send_json_error(
			$prefix . $error
		);
	}
}
