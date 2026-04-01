<?php
/**
 * GoogleCaptchaDetector class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\MigrationWizard\Detectors;

use HCaptcha\MigrationWizard\DetectionResult;

/**
 * Class GoogleCaptchaDetector.
 *
 * Detects reCAPTCHA usage from the "reCaptcha by BestWebSoft" plugin.
 * Plugin slug: google-captcha/google-captcha.php.
 * Options stored in: 'gglcptch_options'.
 */
class GoogleCaptchaDetector extends AbstractDetector {

	/**
	 * Plugin option name.
	 */
	private const OPTION_NAME = 'gglcptch_options';

	/**
	 * Surface map: a plugin option key => normalized surface id.
	 */
	private const SURFACE_MAP = [
		'login_form'        => 'wp_login',
		'registration_form' => 'wp_register',
		'reset_pwd_form'    => 'wp_lost_password',
		'password_form'     => 'wp_password_protected',
		'comments_form'     => 'wp_comment',
		'contact_form'      => 'cf7_form',
	];

	/**
	 * Get the source plugin slug.
	 *
	 * @return string
	 */
	public function get_source_plugin(): string {
		return 'google-captcha/google-captcha.php';
	}

	/**
	 * Get the source plugin display name.
	 *
	 * @return string
	 */
	public function get_source_name(): string {
		return 'reCaptcha by BestWebSoft';
	}

	/**
	 * Check if this detector is applicable.
	 *
	 * @return bool
	 */
	public function is_applicable(): bool {
		return $this->is_plugin_active( $this->get_source_plugin() );
	}

	/**
	 * Run detection.
	 *
	 * @return DetectionResult[]
	 */
	public function detect(): array {
		$options = get_option( self::OPTION_NAME, [] );
		$results = [];

		if ( ! is_array( $options ) || empty( $options ) ) {
			return $results;
		}

		foreach ( self::SURFACE_MAP as $option_key => $surface_id ) {
			if ( ! empty( $options[ $option_key ] ) && 1 === (int) $options[ $option_key ] ) {
				$results[] = $this->build_result(
					'recaptcha',
					$surface_id,
					DetectionResult::CONFIDENCE_HIGH
				);
			}
		}

		return $results;
	}
}
