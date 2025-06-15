<?php
/**
 * AntiSpam class file.
 *
 * @package hcaptcha-wp
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */

namespace HCaptcha\AntiSpam;

use HCaptcha\Admin\Events\Events;

/**
 * Class AntiSpam.
 */
class AntiSpam {
	/**
	 * Verify request hook priority.
	 * Must be lower than \HCaptcha\Admin\Events\Events::VERIFY_REQUEST_PRIORITY
	 */
	public const VERIFY_REQUEST_PRIORITY = Events::VERIFY_REQUEST_PRIORITY - 1000;

	/**
	 * Supported providers in [ 'id' => 'Name' ] format.
	 *
	 * @var array
	 */
	private const SUPPORTED_PROVIDERS = [
		'akismet' => 'Akismet',
	];

	/**
	 * List of protected forms in [ 'status' => [ 'option_1_key', 'option_2_key' ] ] format.
	 * Based on the definition in the \HCaptcha\Settings\Integrations::init_form_fields.
	 */
	private const PROTECTED_FORMS = [
		'akismet' => [
			'native'   => [
				'wp_status'               => [ 'comment' ],
				'cf7_status'              => [ 'form', 'embed' ],
				'elementor_pro_status'    => [ 'form' ],
				'fluent_status'           => [ 'form' ],
				'formidable_forms_status' => [ 'form' ],
				'forminator_status'       => [ 'form' ],
				'give_wp_status'          => [ 'form' ],
				'gravity_status'          => [ 'form', 'embed' ],
				'jetpack_status'          => [ 'contact' ],
				'ninja_status'            => [ 'form' ],
				'woocommerce_status'      => [ 'checkout' ],
				'wpforms_status'          => [ 'form' ],

			],
			'hcaptcha' => [
				'divi_status'         => [ 'contact' ],
				'divi_builder_status' => [ 'contact' ],
				'extra_status'        => [ 'contact' ],
				'quform_status'       => [ 'form' ],
			],
		],
	];

	/**
	 * AntiSpam provider.
	 *
	 * @var Akismet
	 */
	private $provider;

	/**
	 * The entry to check for spam.
	 *
	 * @var array
	 */
	private $entry;

	/**
	 * Constructor.
	 *
	 * @param array $entry Entry to check for spam.
	 *
	 * @return void
	 */
	public function __construct( array $entry ) {
		$entry = wp_parse_args(
			$entry,
			[
				'data'          => [],
				'name'          => null,
				'email'         => null,
				'form_date_gmt' => null,
			]
		);

		$this->entry = $entry;
	}

	/**
	 * Init class.
	 *
	 * @return void
	 */
	public function init(): void {
		$settings = hcaptcha()->settings();

		if ( ! $settings->is_on( 'antispam' ) ) {
			return;
		}

		$provider = new Akismet();

		if ( ! $provider::is_configured() ) {
			return;
		}

		$this->provider = $provider;

		$this->init_hooks();
	}

	/**
	 * Add hooks.
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		add_filter( 'hcap_verify_request', [ $this, 'verify_request_filter' ], self::VERIFY_REQUEST_PRIORITY, 3 );
	}

	/**
	 * Filters the result of request verification.
	 *
	 * @since 4.15.0 The `$error_codes` parameter was deprecated.
	 *
	 * @param string|null|mixed $result     The result of verification. The null means success.
	 * @param string[]          $deprecated Error code(s). Empty array on success.
	 * @param object            $error_info Error info. Contains error codes or empty array on success.
	 *
	 * @return string|null|mixed
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function verify_request_filter( $result, array $deprecated, object $error_info ) {
		static $verified, $antispam_result;

		if ( null !== $result ) {
			return $result;
		}

		if ( $verified ) {
			return $antispam_result;
		}

		$verified = true;

		$antispam_result = $this->provider->verify( $this->entry );

		if ( null !== $antispam_result ) {
			$result              = $antispam_result;
			$error_info->codes[] = 'spam';
		}

		return $result;
	}

	/**
	 * Get configured anti-spam providers.
	 *
	 * @param array $providers Provider slugs.
	 *
	 * @return array
	 */
	public static function get_configured_providers( array $providers ): array {
		return array_filter(
			$providers,
			static function ( $provider ) {
				return self::is_provider_configured( $provider );
			}
		);
	}

	/**
	 * Retrieves the list of supported providers.
	 *
	 * @return array
	 */
	public static function get_supported_providers(): array {
		return self::SUPPORTED_PROVIDERS;
	}

	/**
	 * Retrieves the protected forms list based on the current provider.
	 *
	 * @return array
	 */
	public static function get_protected_forms(): array {
		$antispam = hcaptcha()->settings()->get( 'antispam' );

		if ( ! $antispam ) {
			return [];
		}

		$provider = hcaptcha()->settings()->get( 'antispam_provider' );

		if ( ! self::is_provider_configured( $provider ) ) {
			return [];
		}

		return self::PROTECTED_FORMS[ $provider ] ?? [];
	}

	/**
	 * Is provider configured?
	 *
	 * @param string $provider Provider slug.
	 *
	 * @return bool
	 */
	private static function is_provider_configured( string $provider ): bool {
		$class_name = self::get_provider_classname( $provider );

		return (
			class_exists( $class_name ) &&
			method_exists( $class_name, 'is_configured' ) &&
			$class_name::is_configured()
		);
	}

	/**
	 * Get a provider class name.
	 *
	 * @param string $provider Provider slug.
	 *
	 * @return string
	 */
	private static function get_provider_classname( string $provider ): string {
		return __NAMESPACE__ . '\\' . ucfirst( $provider );
	}
}
