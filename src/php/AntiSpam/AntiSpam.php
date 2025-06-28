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
	 * Name is for displaying on the General page.
	 *
	 * @var array
	 */
	private const SUPPORTED_PROVIDERS = [];

	/**
	 * List of protected forms in [ 'status' => [ 'option_1_key', 'option_2_key' ] ] format.
	 * Based on the definition in the \HCaptcha\Settings\Integrations::init_form_fields.
	 */
	private const PROTECTED_FORMS = [
		'antispam_provider' => [
			'native'   => [],
			'hcaptcha' => [
				'avada_status'        => [ 'form' ],
				'coblocks_status'     => [ 'form' ],
				'divi_status'         => [ 'contact' ],
				'divi_builder_status' => [ 'contact' ],
				'extra_status'        => [ 'contact' ],
				'quform_status'       => [ 'form' ],
				'kadence_status'      => [ 'form', 'advanced_form' ],
				'spectra_status'      => [ 'form' ],
			],
		],
	];

	/**
	 * AntiSpam provider.
	 *
	 * @var ProviderBase
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

		$provider_id = $settings->get( 'antispam_provider' );

		if ( ! self::is_provider_configured( $provider_id ) ) {
			return;
		}

		$provider_classname = self::get_provider_classname( $provider_id );
		$this->provider     = new $provider_classname();

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
	 * Retrieves the protected forms list based on the current provider.
	 *
	 * @return array
	 */
	public static function get_protected_forms(): array {
		$antispam = hcaptcha()->settings()->get( 'antispam' );

		if ( ! $antispam ) {
			return [];
		}

		$provider_id = hcaptcha()->settings()->get( 'antispam_provider' );

		if ( ! self::is_provider_configured( $provider_id ) ) {
			return [];
		}

		return self::PROTECTED_FORMS[ $provider_id ] ?? [];
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
	 * Get configured anti-spam providers.
	 *
	 * @return array
	 */
	public static function get_configured_providers(): array {
		static $configured_providers;

		if ( null === $configured_providers ) {
			$configured_providers = array_filter(
				array_keys( self::get_supported_providers() ),
				static function ( $provider ) {
					return self::is_provider_configured( $provider );
				}
			);
		}

		return $configured_providers;
	}

	/**
	 * Is provider supported?
	 *
	 * @param string $provider_id Provider ID.
	 *
	 * @return bool
	 */
	private static function is_provider_supported( string $provider_id ): bool {
		return isset( self::SUPPORTED_PROVIDERS[ $provider_id ] );
	}

	/**
	 * Is the provider supported and configured?
	 *
	 * @param string $provider_id Provider ID.
	 *
	 * @return bool
	 */
	private static function is_provider_configured( string $provider_id ): bool {
		$class_name = self::get_provider_classname( $provider_id );

		return (
			self::is_provider_supported( $provider_id ) &&
			class_exists( $class_name ) &&
			method_exists( $class_name, 'is_configured' ) &&
			$class_name::is_configured()
		);
	}

	/**
	 * Get a provider class name.
	 *
	 * @param string $provider_id Provider ID.
	 *
	 * @return string
	 */
	private static function get_provider_classname( string $provider_id ): string {
		return __NAMESPACE__ . '\\' . ucfirst( $provider_id );
	}
}
