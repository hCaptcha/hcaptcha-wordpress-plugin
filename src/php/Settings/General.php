<?php
/**
 * General class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Settings;

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
	 * Other section id.
	 */
	const SECTION_OTHER = 'other';

	/**
	 * Get screen id.
	 *
	 * @return string
	 */
	public function screen_id() {
		return 'settings_page_hcaptcha';
	}

	/**
	 * Get option group.
	 *
	 * @return string
	 */
	protected function option_group() {
		return 'hcaptcha_group';
	}

	/**
	 * Get option page.
	 *
	 * @return string
	 */
	protected function option_page() {
		return 'hcaptcha';
	}

	/**
	 * Get option name.
	 *
	 * @return string
	 */
	protected function option_name() {
		return 'hcaptcha_settings';
	}

	/**
	 * Get page title.
	 *
	 * @return string
	 */
	protected function page_title() {
		return __( 'General', 'hcaptcha-for-forms-and-more' );
	}

	/**
	 * Get menu title.
	 *
	 * @return string
	 */
	protected function menu_title() {
		return __( 'hCaptcha', 'hcaptcha-for-forms-and-more' );
	}

	/**
	 * Get section title.
	 *
	 * @return string
	 */
	protected function section_title() {
		return 'general';
	}

	/**
	 * Init form fields.
	 */
	public function init_form_fields() {
		$this->form_fields = [
			'api_key'              => [
				'label'   => __( 'Site Key', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'text',
				'section' => self::SECTION_KEYS,
			],
			'secret_key'           => [
				'label'   => __( 'Secret Key', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'password',
				'section' => self::SECTION_KEYS,
			],
			'theme'                => [
				'label'   => __( 'Theme', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'select',
				'section' => self::SECTION_APPEARANCE,
				'options' => [
					'light' => __( 'Light', 'hcaptcha-for-forms-and-more' ),
					'dark'  => __( 'Dark', 'hcaptcha-for-forms-and-more' ),
				],
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
			],
			'language'             => [
				'label'   => __( 'Override Language Detection', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'select',
				'section' => self::SECTION_APPEARANCE,
				'options' => [
					''      => '--- Auto-Detect ---',
					'af'    => 'Afrikaans',
					'sq'    => 'Albanian',
					'am'    => 'Amharic',
					'ar'    => 'Arabic',
					'hy'    => 'Armenian',
					'az'    => 'Azerbaijani',
					'eu'    => 'Basque',
					'be'    => 'Belarusian',
					'bn'    => 'Bengali',
					'bg'    => 'Bulgarian',
					'bs'    => 'Bosnian',
					'my'    => 'Burmese',
					'ca'    => 'Catalan',
					'ceb'   => 'Cebuano',
					'zh'    => 'Chinese',
					'zh-CN' => 'Chinese Simplified',
					'zh-TW' => 'Chinese Traditional',
					'co'    => 'Corsican',
					'hr'    => 'Croatian',
					'cs'    => 'Czech',
					'da'    => 'Danish',
					'nl'    => 'Dutch',
					'en'    => 'English',
					'eo'    => 'Esperanto',
					'et'    => 'Estonian',
					'fa'    => 'Persian',
					'fi'    => 'Finnish',
					'fr'    => 'French',
					'fy'    => 'Frisian',
					'gd'    => 'Gaelic',
					'gl'    => 'Galacian',
					'ka'    => 'Georgian',
					'de'    => 'German',
					'el'    => 'Greek',
					'gu'    => 'Gujurati',
					'ht'    => 'Haitian',
					'ha'    => 'Hausa',
					'haw'   => 'Hawaiian',
					'he'    => 'Hebrew',
					'hi'    => 'Hindi',
					'hmn'   => 'Hmong',
					'hu'    => 'Hungarian',
					'is'    => 'Icelandic',
					'ig'    => 'Igbo',
					'id'    => 'Indonesian',
					'ga'    => 'Irish',
					'it'    => 'Italian',
					'ja'    => 'Japanese',
					'jw'    => 'Javanese',
					'kn'    => 'Kannada',
					'kk'    => 'Kazakh',
					'km'    => 'Khmer',
					'rw'    => 'Kinyarwanda',
					'ky'    => 'Kirghiz',
					'ko'    => 'Korean',
					'ku'    => 'Kurdish',
					'lo'    => 'Lao',
					'la'    => 'Latin',
					'lv'    => 'Latvian',
					'lt'    => 'Lithuanian',
					'lb'    => 'Luxembourgish',
					'mk'    => 'Macedonian',
					'mg'    => 'Malagasy',
					'ms'    => 'Malay',
					'ml'    => 'Malayalam',
					'mt'    => 'Maltese',
					'mi'    => 'Maori',
					'mr'    => 'Marathi',
					'mn'    => 'Mongolian',
					'ne'    => 'Nepali',
					'no'    => 'Norwegian',
					'ny'    => 'Nyanja',
					'or'    => 'Oriya',
					'pl'    => 'Polish',
					'pt'    => 'Portuguese',
					'ps'    => 'Pashto',
					'pa'    => 'Punjabi',
					'ro'    => 'Romanian',
					'ru'    => 'Russian',
					'sm'    => 'Samoan',
					'sn'    => 'Shona',
					'sd'    => 'Sindhi',
					'si'    => 'Singhalese',
					'sr'    => 'Serbian',
					'sk'    => 'Slovak',
					'sl'    => 'Slovenian',
					'so'    => 'Somani',
					'st'    => 'Southern Sotho',
					'es'    => 'Spanish',
					'su'    => 'Sundanese',
					'sw'    => 'Swahili',
					'sv'    => 'Swedish',
					'tl'    => 'Tagalog',
					'tg'    => 'Tajik',
					'ta'    => 'Tamil',
					'tt'    => 'Tatar',
					'te'    => 'Teluga',
					'th'    => 'Thai',
					'tr'    => 'Turkish',
					'tk'    => 'Turkmen',
					'ug'    => 'Uyghur',
					'uk'    => 'Ukrainian',
					'ur'    => 'Urdu',
					'uz'    => 'Uzbek',
					'vi'    => 'Vietnamese',
					'cy'    => 'Welsh',
					'xh'    => 'Xhosa',
					'yi'    => 'Yiddish',
					'yo'    => 'Yoruba',
					'zu'    => 'Zulu',
				],
				'helper'  => __(
					"By default, hCaptcha will automatically detect the user's locale and localize widgets accordingly.",
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
				/* translators: 1: hCaptcha Pro link. */
					__( 'Note: only works on %s site keys.', 'hcaptcha-for-forms-and-more' ),
					sprintf(
						'<a href="https://www.hcaptcha.com/pro" target="_blank">%s</a>',
						__( 'hCaptcha Pro', 'hcaptcha-for-forms-and-more' )
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
						'<a href="https://docs.hcaptcha.com/configuration/#hcaptcharendercontainer-params" target="_blank">%s</a>',
						__( 'parameters', 'hcaptcha-for-forms-and-more' )
					)
				),
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
			'whitelisted_ips'      => [
				'label'   => __( 'Whitelisted IPs', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'textarea',
				'section' => self::SECTION_OTHER,
				'helper'  => __( 'Do not show hCaptcha for listed IP addresses. Please specify one IP address per line.', 'hcaptcha-for-forms-and-more' ),
			],
		];
	}

	/**
	 * Section callback.
	 *
	 * @param array $arguments Section arguments.
	 */
	public function section_callback( $arguments ) {
		switch ( $arguments['id'] ) {
			case self::SECTION_KEYS:
				?>
				<h2>
					<?php echo esc_html( $this->page_title() ); ?>
				</h2>
				<p>
					<?php
					echo wp_kses_post(
						__(
							'To use <a href="https://www.hcaptcha.com/?r=wp" target="_blank">hCaptcha</a>, please register <a href="https://www.hcaptcha.com/signup-interstitial/?r=wp" target="_blank">here</a> to get your site and secret keys.',
							'hcaptcha-for-forms-and-more'
						)
					);
					?>
				</p>
				<?php
				$this->print_section_header( $arguments['id'], __( 'Keys', 'hcaptcha-for-forms-and-more' ) );
				break;
			case self::SECTION_APPEARANCE:
				$this->print_section_header( $arguments['id'], __( 'Appearance', 'hcaptcha-for-forms-and-more' ) );
				break;
			case self::SECTION_CUSTOM:
				$this->print_section_header( $arguments['id'], __( 'Custom', 'hcaptcha-for-forms-and-more' ) );
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
	private function print_section_header( $id, $title ) {
		?>
		<h3 class="hcaptcha-section-<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $title ); ?></h3>
		<?php
	}

	/**
	 * Enqueue class scripts.
	 *
	 * @todo Update with proper scripts and styles.
	 */
	public function admin_enqueue_scripts() {
		if ( ! $this->is_options_screen() ) {
			return;
		}

		wp_enqueue_script(
			self::HANDLE,
			constant( 'HCAPTCHA_URL' ) . "/assets/js/general/app$this->min_prefix.js",
			[],
			constant( 'HCAPTCHA_VERSION' ),
			true
		);

		wp_enqueue_style(
			self::HANDLE,
			constant( 'HCAPTCHA_URL' ) . "/assets/css/general$this->min_prefix.css",
			[],
			constant( 'HCAPTCHA_VERSION' )
		);
	}
}
