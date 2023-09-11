<?php
/**
 * HCaptchaTestCase class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpLanguageLevelInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Unit;

use HCaptcha\Settings\Abstracts\SettingsBase;
use HCaptcha\Settings\General;
use Mockery;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use tad\FunctionMocker\FunctionMocker;
use WP_Mock;

/**
 * Class HCaptchaTestCase
 */
abstract class HCaptchaTestCase extends TestCase {

	/**
	 * Setup test
	 */
	public function setUp(): void { // phpcs:ignore PHPCompatibility.FunctionDeclarations.NewReturnTypeDeclarations.voidFound
		FunctionMocker::setUp();
		parent::setUp();
		WP_Mock::setUp();
	}

	/**
	 * End test
	 */
	public function tearDown(): void { // phpcs:ignore PHPCompatibility.FunctionDeclarations.NewReturnTypeDeclarations.voidFound
		WP_Mock::tearDown();
		Mockery::close();
		parent::tearDown();
		FunctionMocker::tearDown();
	}

	/**
	 * Get an object protected property.
	 *
	 * @param object $subject       Object.
	 * @param string $property_name Property name.
	 *
	 * @return mixed
	 *
	 * @throws ReflectionException Reflection exception.
	 */
	protected function get_protected_property( $subject, string $property_name ) {
		$property = ( new ReflectionClass( $subject ) )->getProperty( $property_name );
		$property->setAccessible( true );
		$value = $property->getValue( $subject );
		$property->setAccessible( false );

		return $value;
	}

	/**
	 * Set an object protected property.
	 *
	 * @param object $subject       Object.
	 * @param string $property_name Property name.
	 * @param mixed  $value         Property vale.
	 *
	 * @throws ReflectionException Reflection exception.
	 */
	protected function set_protected_property( $subject, string $property_name, $value ) {
		$property = ( new ReflectionClass( $subject ) )->getProperty( $property_name );
		$property->setAccessible( true );
		$property->setValue( $subject, $value );
		$property->setAccessible( false );
	}

	/**
	 * Set an object protected method accessibility.
	 *
	 * @param object $subject     Object.
	 * @param string $method_name Property name.
	 * @param bool   $accessible  Property vale.
	 *
	 * @return ReflectionMethod
	 *
	 * @throws ReflectionException Reflection exception.
	 */
	protected function set_method_accessibility( $subject, string $method_name, bool $accessible = true ): ReflectionMethod {
		$method = ( new ReflectionClass( $subject ) )->getMethod( $method_name );
		$method->setAccessible( $accessible );

		return $method;
	}

	/**
	 * Plucks a certain field out of each object or array in an array.
	 * Taken from WP Core.
	 *
	 * @param array|mixed $input_list List of objects or arrays.
	 * @param int|string  $field      Field from the object to place instead of the entire object.
	 * @param int|string  $index_key  Optional. Field from the object to use as keys for the new array.
	 *                                Default null.
	 *
	 * @return array Array of found values. If `$index_key` is set, an array of found values with keys
	 *               corresponding to `$index_key`. If `$index_key` is null, array keys from the original
	 *               `$input_list` will be preserved in the results.
	 */
	protected function wp_list_pluck( $input_list, $field, $index_key = null ): array {
		if ( ! is_array( $input_list ) ) {
			return [];
		}

		return $this->pluck( $input_list, $field, $index_key );
	}

	/**
	 * Plucks a certain field out of each element in the input array.
	 * Taken from WP Core.
	 *
	 * @param array      $input_list List of objects or arrays.
	 * @param int|string $field      Field to fetch from the object or array.
	 * @param int|string $index_key  Optional. Field from the element to use as keys for the new array.
	 *                               Default null.
	 *
	 * @return array Array of found values. If `$index_key` is set, an array of found values with keys
	 *               corresponding to `$index_key`. If `$index_key` is null, array keys from the original
	 *               `$list` will be preserved in the results.
	 */
	private function pluck( array $input_list, $field, $index_key = null ): array {
		$output   = $input_list;
		$new_list = [];

		if ( ! $index_key ) {
			/*
			 * This is simple. Could at some point wrap array_column()
			 * if we knew we had an array of arrays.
			 */
			foreach ( $output as $key => $value ) {
				if ( is_object( $value ) ) {
					$new_list[ $key ] = $value->$field;
				} elseif ( is_array( $value ) ) {
					$new_list[ $key ] = $value[ $field ];
				} else {
					// Error.
					return [];
				}
			}

			return $new_list;
		}

		/*
		 * When index_key is not set for a particular item, push the value
		 * to the end of the stack. This is how array_column() behaves.
		 */
		foreach ( $output as $value ) {
			if ( is_object( $value ) ) {
				if ( isset( $value->$index_key ) ) {
					$new_list[ $value->$index_key ] = $value->$field;
				} else {
					$new_list[] = $value->$field;
				}
			} elseif ( is_array( $value ) ) {
				if ( isset( $value[ $index_key ] ) ) {
					$new_list[ $value[ $index_key ] ] = $value[ $field ];
				} else {
					$new_list[] = $value[ $field ];
				}
			} else {
				// Error.
				return [];
			}
		}

		return $new_list;
	}

	/**
	 * Get test settings.
	 *
	 * @return array
	 */
	protected function get_test_settings(): array {
		return [
			'wp_status'                    =>
				[
					0 => 'lost_pass',
					1 => 'password_protected',
					2 => 'register',
				],
			'acfe_status'                  =>
				[ 0 => 'form' ],
			'avada_status'                 =>
				[ 0 => 'form' ],
			'bbp_status'                   =>
				[
					0 => 'new_topic',
					1 => 'reply',
				],
			'beaver_builder_status'        =>
				[
					0 => 'contact',
					1 => 'login',
				],
			'bp_status'                    =>
				[
					0 => 'create_group',
					1 => 'registration',
				],
			'cf7_status'                   =>
				[ 0 => 'form' ],
			'divi_status'                  =>
				[
					0 => 'comment',
					1 => 'contact',
					2 => 'login',
				],
			'download_manager_status'      =>
				[ 0 => 'button' ],
			'elementor_pro_status'         =>
				[ 0 => 'form' ],
			'fluent_status'                =>
				[ 0 => 'form' ],
			'forminator_status'            =>
				[],
			'give_wp_status'               =>
				[ 0 => 'form' ],
			'gravity_status'               =>
				[ 0 => 'form' ],
			'jetpack_status'               =>
				[ 0 => 'contact' ],
			'kadence_status'               =>
				[ 0 => 'form' ],
			'mailchimp_status'             =>
				[ 0 => 'form' ],
			'memberpress_status'           =>
				[ 0 => 'register' ],
			'ninja_status'                 =>
				[ 0 => 'form' ],
			'otter_status'                 =>
				[ 0 => 'form' ],
			'quform_status'                =>
				[ 0 => 'form' ],
			'sendinblue_status'            =>
				[ 0 => 'form' ],
			'subscriber_status'            =>
				[ 0 => 'form' ],
			'ultimate_member_status'       =>
				[
					0 => 'login',
					1 => 'lost_pass',
					2 => 'register',
				],
			'woocommerce_status'           =>
				[
					0 => 'checkout',
					1 => 'login',
					2 => 'lost_pass',
					3 => 'order_tracking',
					4 => 'register',
				],
			'woocommerce_wishlists_status' =>
				[ 0 => 'create_list' ],
			'wpforms_status'               =>
				[
					0 => 'lite',
					1 => 'pro',
				],
			'wpdiscuz_status'              =>
				[ 0 => 'comment_form' ],
			'wpforo_status'                =>
				[
					0 => 'new_topic',
					1 => 'reply',
				],
			'woocommerce_wishlist_status'  =>
				[ 0 => 'create_list' ],
			'api_key'                      => 'some API key',
			'secret_key'                   => 'some secret key',
			'theme'                        => 'light',
			'size'                         => 'normal',
			'language'                     => 'en',
			'custom_themes'                =>
				[],
			'off_when_logged_in'           =>
				[],
			'recaptcha_compat_off'         =>
				[],
			'custom_theme_params'          => '{
   "theme": "dark",
   "size": "compact"
}',
			'config_params'                => '',
			'whitelisted_ips'              => '4444444.777.2
220.45.45.1
',
			'_network_wide'                => [],
			'mode'                         => 'test:publisher',
			'site_key'                     => 'some site key',
			'delay'                        => '0',
			'login_limit'                  => '2',
			'login_interval'               => '15',
		];
	}

	/**
	 * Get test form fields.
	 *
	 * @return array|array[]
	 */
	protected function get_test_form_fields(): array {
		$test_general_form_fields      = $this->get_test_general_form_fields();
		$test_integrations_form_fields = $this->get_test_integrations_form_fields();

		$test_form_fields = array_merge( $test_general_form_fields, $test_integrations_form_fields );

		array_walk( $test_form_fields, [ $this, 'set_defaults' ] );

		return $test_form_fields;
	}

	/**
	 * Set default required properties for each field.
	 *
	 * @param array  $field Settings field.
	 * @param string $id    Settings field id.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 */
	protected function set_defaults( array &$field, string $id ) {
		$field = array_merge(
			[
				'default'  => '',
				'disabled' => false,
				'field_id' => '',
				'label'    => '',
				'section'  => '',
				'title'    => '',
			],
			$field
		);
	}

	/**
	 * Get test form fields of General class.
	 *
	 * @return array
	 */
	protected function get_test_general_form_fields(): array {
		$form_fields = [
			'site_key'                 => [
				'label'   => 'Site Key',
				'type'    => 'text',
				'section' => General::SECTION_KEYS,
			],
			'secret_key'               => [
				'label'   => 'Secret Key',
				'type'    => 'password',
				'section' => General::SECTION_KEYS,
			],
			'sample_hcaptcha'          => [
				'label'   => 'Sample hCaptcha',
				'type'    => 'hcaptcha',
				'section' => General::SECTION_KEYS,
			],
			'check_config'             => [
				'label'   => 'Check Site Config',
				'type'    => 'button',
				'text'    => 'Check',
				'section' => General::SECTION_KEYS,
			],
			'reset_notifications'      => [
				'label'   => 'Reset Notifications',
				'type'    => 'button',
				'text'    => 'Reset',
				'section' => General::SECTION_KEYS,
			],
			'theme'                    => [
				'label'   => 'Theme',
				'type'    => 'select',
				'section' => General::SECTION_APPEARANCE,
				'options' => [
					'light' => 'Light',
					'dark'  => 'Dark',
				],
				'helper'  => 'Select hCaptcha theme.',
			],
			'size'                     => [
				'label'   => 'Size',
				'type'    => 'select',
				'section' => General::SECTION_APPEARANCE,
				'options' => [
					'normal'    => 'Normal',
					'compact'   => 'Compact',
					'invisible' => 'Invisible',
				],
				'helper'  => 'Select hCaptcha size.',
			],
			'language'                 => [
				'label'   => 'Language',
				'type'    => 'select',
				'section' => General::SECTION_APPEARANCE,
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
				'helper'  => "By default, hCaptcha will automatically detect the user's locale and localize widgets accordingly.",
			],
			'mode'                     => [
				'label'   => 'Mode',
				'type'    => 'select',
				'section' => General::SECTION_APPEARANCE,
				// phpcs:disable WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned, WordPress.Arrays.MultipleStatementAlignment.LongIndexSpaceBeforeDoubleArrow
				'options' => [
					General::MODE_LIVE                          => 'Live',
					General::MODE_TEST_PUBLISHER                => 'Test: Publisher Account',
					General::MODE_TEST_ENTERPRISE_SAFE_END_USER => 'Test: Enterprise Account (Safe End User)',
					General::MODE_TEST_ENTERPRISE_BOT_DETECTED  => 'Test: Enterprise Account (Bot Detected)',
				],
				// phpcs:enable WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned, WordPress.Arrays.MultipleStatementAlignment.LongIndexSpaceBeforeDoubleArrow
				'default' => General::MODE_LIVE,
				'helper'  => 'Select live or test mode. In test mode, predefined keys are used.',
			],
			'custom_themes'            => [
				'label'   => 'Custom Themes',
				'type'    => 'checkbox',
				'section' => General::SECTION_CUSTOM,
				'options' => [
					'on' => 'Enable Custom Themes',
				],
				'helper'  => sprintf(
				/* translators: 1: hCaptcha Pro link, 2: hCaptcha Enterprise link. */
					'Note: only works on hCaptcha %1$s and %2$s site keys.',
					sprintf(
						'<a href="https://www.hcaptcha.com/pro?utm_source=wordpress&utm_medium=wpplugin&utm_campaign=upgrade" target="_blank">%s</a>',
						'Pro'
					),
					sprintf(
						'<a href="https://www.hcaptcha.com/enterprise?utm_source=wordpress&utm_medium=wpplugin&utm_campaign=upgrade" target="_blank">%s</a>',
						'Enterprise'
					)
				),
			],
			'config_params'            => [
				'label'   => 'Config Params',
				'type'    => 'textarea',
				'section' => General::SECTION_CUSTOM,
				'helper'  => sprintf(
				/* translators: 1: hCaptcha render params doc link. */
					'hCaptcha render %s (optional). Must be a valid JSON.',
					sprintf(
						'<a href="https://docs.hcaptcha.com/configuration/#hcaptcharendercontainer-params?utm_source=wordpress&utm_medium=wpplugin&utm_campaign=docs" target="_blank">%s</a>',
						'parameters'
					)
				),
			],
			'off_when_logged_in'       => [
				'label'   => 'Other Settings',
				'type'    => 'checkbox',
				'section' => General::SECTION_OTHER,
				'options' => [
					'on' => 'Turn Off When Logged In',
				],
				'helper'  => 'Do not show hCaptcha to logged-in users.',
			],
			'recaptcha_compat_off'     => [
				'type'    => 'checkbox',
				'section' => General::SECTION_OTHER,
				'options' => [
					'on' => 'Disable reCAPTCHA Compatibility',
				],
				'helper'  => 'Use if including both hCaptcha and reCAPTCHA on the same page.',
			],
			SettingsBase::NETWORK_WIDE => [
				'type'    => 'checkbox',
				'section' => General::SECTION_OTHER,
				'options' => [
					'on' => 'Use network-wide settings',
				],
				'helper'  => 'On multisite, use same settings for all sites of the network.',
			],
			'whitelisted_ips'          => [
				'label'   => 'Whitelisted IPs',
				'type'    => 'textarea',
				'section' => General::SECTION_OTHER,
				'helper'  => 'Do not show hCaptcha for listed IP addresses. Please specify one IP address per line.',
			],
			'login_limit'              => [
				'label'   => 'Login attempts before hCaptcha',
				'type'    => 'number',
				'section' => General::SECTION_OTHER,
				'default' => 0,
				'min'     => 0,
				'helper'  => 'Maximum number of failed login attempts before showing hCaptcha.',
			],
			'login_interval'           => [
				'label'   => 'Failed login attempts interval, min',
				'type'    => 'number',
				'section' => General::SECTION_OTHER,
				'default' => 15,
				'min'     => 1,
				'helper'  => 'Time interval in minutes when failed login attempts are counted.',
			],
			'delay'                    => [
				'label'   => 'Delay showing hCaptcha, ms',
				'type'    => 'number',
				'section' => General::SECTION_OTHER,
				'default' => - 100,
				'min'     => - 100,
				'step'    => 100,
				'helper'  => 'Delay time for loading the hCaptcha API script. Any negative value will prevent the API script from loading until user interaction: mouseenter, click, scroll or touch. This significantly improves Google Pagespeed Insights score.',
			],
		];

		if ( ! ( function_exists( 'is_multisite' ) && is_multisite() ) ) {
			unset( $form_fields[ SettingsBase::NETWORK_WIDE ] );
		}

		return $form_fields;
	}

	/**
	 * Get test form fields of Integrations class.
	 *
	 * @return array
	 */
	protected function get_test_integrations_form_fields(): array {
		return [
			'wp_status'                     =>
				[
					'label'   => 'WP Core',
					'type'    => 'checkbox',
					'options' =>
						[
							'comment'            => 'Comment Form',
							'login'              => 'Login Form',
							'lost_pass'          => 'Lost Password Form',
							'password_protected' => 'Post/Page Password Form',
							'register'           => 'Register Form',
						],
				],
			'acfe_status'                   =>
				[
					'label'   => 'ACF Extended',
					'type'    => 'checkbox',
					'options' =>
						[
							'form' => 'ACF Extended Form',
						],
				],
			'asgaros_status'                => [
				'label'   => 'Asgaros',
				'type'    => 'checkbox',
				'options' => [
					'form' => 'Form',
				],
			],
			'avada_status'                  =>
				[
					'label'   => 'Avada',
					'type'    => 'checkbox',
					'options' =>
						[
							'form' => 'Avada Form',
						],
				],
			'back_in_stock_notifier_status' => [
				'label'   => 'Back In Stock Notifier',
				'type'    => 'checkbox',
				'options' => [
					'form' => 'Back In Stock Notifier Form',
				],
			],
			'bbp_status'                    =>
				[
					'label'   => 'bbPress',
					'type'    => 'checkbox',
					'options' =>
						[
							'new_topic' => 'New Topic Form',
							'reply'     => 'Reply Form',
						],
				],
			'beaver_builder_status'         =>
				[
					'label'   => 'Beaver Builder',
					'type'    => 'checkbox',
					'options' =>
						[
							'contact' => 'Contact Form',
							'login'   => 'Login Form',
						],
				],
			'brizy_status'                  => [
				'label'   => 'Brizy',
				'type'    => 'checkbox',
				'options' => [
					'form' => 'Form',
				],
			],
			'bp_status'                     =>
				[
					'label'   => 'BuddyPress',
					'type'    => 'checkbox',
					'options' =>
						[
							'create_group' => 'Create Group Form',
							'registration' => 'Registration Form',
						],
				],
			'classified_listing_status'     => [
				'label'   => 'Classified Listing',
				'type'    => 'checkbox',
				'options' => [
					'contact'   => 'Contact Form',
					'login'     => 'Login Form',
					'lost_pass' => 'Lost Password Form',
					'register'  => 'Register Form',
				],
			],
			'colorlib_customizer_status'    => [
				'label'   => 'Colorlib Login Customizer',
				'type'    => 'checkbox',
				'options' => [
					'login'     => 'Login Form',
					'lost_pass' => 'Lost Password Form',
					'register'  => 'Register Form',
				],
			],
			'cf7_status'                    =>
				[
					'label'   => 'Contact Form 7',
					'type'    => 'checkbox',
					'options' =>
						[
							'form' => 'Form',
						],
				],
			'divi_status'                   =>
				[
					'label'   => 'Divi',
					'type'    => 'checkbox',
					'options' =>
						[
							'comment'     => 'Divi Comment Form',
							'contact'     => 'Divi Contact Form',
							'email_optin' => 'Divi Email Optin Form',
							'login'       => 'Divi Login Form',
						],
				],
			'download_manager_status'       =>
				[
					'label'   => 'Download Manager',
					'type'    => 'checkbox',
					'options' =>
						[
							'button' => 'Button',
						],
				],
			'easy_digital_downloads_status' => [
				'label'   => 'Easy Digital Downloads',
				'type'    => 'checkbox',
				'options' => [
					'checkout' => 'Checkout Form',
				],
			],
			'elementor_pro_status'          =>
				[
					'label'   => 'Elementor Pro',
					'type'    => 'checkbox',
					'options' =>
						[
							'form' => 'Form',
						],
				],
			'fluent_status'                 =>
				[
					'label'   => 'Fluent Forms',
					'type'    => 'checkbox',
					'options' =>
						[
							'form' => 'Form',
						],
				],
			'formidable_forms_status'       => [
				'label'   => 'Formidable Forms',
				'type'    => 'checkbox',
				'options' => [
					'form' => 'Form',
				],
			],
			'forminator_status'             =>
				[
					'label'   => 'Forminator',
					'type'    => 'checkbox',
					'options' =>
						[
							'form' => 'Form',
						],
				],
			'give_wp_status'                => [
				'label'   => 'GiveWP',
				'type'    => 'checkbox',
				'options' => [
					'form' => 'Form',
				],
			],
			'gravity_status'                =>
				[
					'label'   => 'Gravity Forms',
					'type'    => 'checkbox',
					'options' =>
						[
							'form' => 'Form',
						],
				],
			'jetpack_status'                =>
				[
					'label'   => 'Jetpack',
					'type'    => 'checkbox',
					'options' =>
						[
							'contact' => 'Contact Form',
						],
				],
			'kadence_status'                =>
				[
					'label'   => 'Kadence',
					'type'    => 'checkbox',
					'options' =>
						[
							'form'          => 'Kadence Form',
							'advanced_form' => 'Kadence Advanced Form',
						],
				],
			'mailchimp_status'              =>
				[
					'label'   => 'Mailchimp for WP',
					'type'    => 'checkbox',
					'options' =>
						[
							'form' => 'Form',
						],
				],
			'memberpress_status'            =>
				[
					'label'   => 'MemberPress',
					'type'    => 'checkbox',
					'options' =>
						[
							'login'    => 'Login Form',
							'register' => 'Registration Form',
						],
				],
			'ninja_status'                  =>
				[
					'label'   => 'Ninja Forms',
					'type'    => 'checkbox',
					'options' =>
						[
							'form' => 'Form',
						],
				],
			'otter_status'                  =>
				[
					'label'   => 'Otter Blocks',
					'type'    => 'checkbox',
					'options' =>
						[
							'form' => 'Form',
						],
				],
			'paid_memberships_pro_status'   =>
				[
					'label'   => 'Paid Memberships Pro',
					'type'    => 'checkbox',
					'options' => [
						'checkout' => 'Checkout Form',
						'login'    => 'Login Form',
					],
				],
			'passster_status'               => [
				'label'   => 'Passster',
				'type'    => 'checkbox',
				'options' => [
					'protect' => 'Protection Form',
				],
			],
			'profile_builder_status'        => [
				'label'   => 'Profile Builder',
				'type'    => 'checkbox',
				'options' => [
					'login'     => 'Login Form',
					'lost_pass' => 'Recover Password Form',
					'register'  => 'Register Form',
				],
			],
			'quform_status'                 =>
				[
					'label'   => 'Quform',
					'type'    => 'checkbox',
					'options' =>
						[
							'form' => 'Form',
						],
				],
			'sendinblue_status'             =>
				[
					'label'   => 'Sendinblue',
					'type'    => 'checkbox',
					'options' =>
						[
							'form' => 'Form',
						],
				],
			'subscriber_status'             =>
				[
					'label'   => 'Subscriber',
					'type'    => 'checkbox',
					'options' =>
						[
							'form' => 'Form',
						],
				],
			'supportcandy_status'           => [
				'label'   => 'Support Candy',
				'type'    => 'checkbox',
				'options' => [
					'form' => 'Form',
				],
			],
			'theme_my_login_status'         => [
				'label'   => 'Theme My Login',
				'type'    => 'checkbox',
				'options' => [
					'login'     => 'Login Form',
					'lost_pass' => 'Lost Password Form',
					'register'  => 'Register Form',
				],
			],
			'ultimate_member_status'        =>
				[
					'label'   => 'Ultimate Member',
					'type'    => 'checkbox',
					'options' =>
						[
							'login'     => 'Login Form',
							'lost_pass' => 'Lost Password Form',
							'register'  => 'Register Form',
						],
				],
			'users_wp_status'               => [
				'label'   => 'Users WP',
				'type'    => 'checkbox',
				'options' => [
					'forgot'   => 'Forgot Password Form',
					'login'    => 'Login Form',
					'register' => 'Register Form',
				],
			],
			'woocommerce_status'            =>
				[
					'label'   => 'WooCommerce',
					'type'    => 'checkbox',
					'options' =>
						[
							'checkout'       => 'Checkout Form',
							'login'          => 'Login Form',
							'lost_pass'      => 'Lost Password Form',
							'order_tracking' => 'Order Tracking Form',
							'register'       => 'Registration Form',
						],
				],
			'woocommerce_wishlists_status'  =>
				[
					'label'   => 'WooCommerce Wishlists',
					'type'    => 'checkbox',
					'options' =>
						[
							'create_list' => 'Create List Form',
						],
				],
			'wordfence_status'              => [
				'label'   => 'Wordfence',
				'type'    => 'checkbox',
				'options' => [
					'login' => 'Login Form',
				],
			],
			'wpforms_status'                =>
				[
					'label'   => 'WPForms',
					'type'    => 'checkbox',
					'options' =>
						[
							'lite' => 'Lite',
							'pro'  => 'Pro',
						],
				],
			'wpdiscuz_status'               =>
				[
					'label'   => 'WPDiscuz',
					'type'    => 'checkbox',
					'options' =>
						[
							'comment_form'   => 'Comment Form',
							'subscribe_form' => 'Subscribe Form',
						],
				],
			'wpforo_status'                 =>
				[
					'label'   => 'WPForo',
					'type'    => 'checkbox',
					'options' =>
						[
							'new_topic' => 'New Topic Form',
							'reply'     => 'Reply Form',
						],
				],
		];
	}
}
