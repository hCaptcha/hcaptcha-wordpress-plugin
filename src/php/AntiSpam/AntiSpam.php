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
				$class_name = '\HCaptcha\AntiSpam\\' . ucfirst( $provider );

				return (
					class_exists( $class_name ) &&
					method_exists( $class_name, 'is_configured' ) &&
					$class_name::is_configured()
				);
			}
		);
	}
}
