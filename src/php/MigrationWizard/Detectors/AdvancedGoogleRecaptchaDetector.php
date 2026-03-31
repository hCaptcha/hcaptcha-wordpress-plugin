<?php
/**
 * AdvancedGoogleRecaptchaDetector class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\MigrationWizard\Detectors;

use HCaptcha\MigrationWizard\DetectionResult;

/**
 * Class AdvancedGoogleRecaptchaDetector.
 *
 * Detects reCAPTCHA usage from the "Advanced Google reCAPTCHA" plugin.
 * Plugin slug: advanced-google-recaptcha/advanced-google-recaptcha.php.
 * Options stored in: 'wpcaptcha_options'.
 */
class AdvancedGoogleRecaptchaDetector extends AbstractDetector {

	/**
	 * Plugin option name.
	 */
	private const OPTION_NAME = 'wpcaptcha_options';

	/**
	 * Surface map: a plugin option key => normalized surface id.
	 */
	private const SURFACE_MAP = [
		'captcha_show_wp_comment'       => 'wp_comment',
		'captcha_show_login'            => 'wp_login',
		'captcha_show_wp_lost_password' => 'wp_lost_password',
		'captcha_show_wp_registration'  => 'wp_register',
		'captcha_show_bp_registration'  => 'buddypress_registration',
		'captcha_show_edd_registration' => 'edd_register',
		'captcha_show_woo_checkout'     => 'wc_checkout',
		'captcha_show_woo_registration' => 'wc_register',
	];

	/**
	 * Get the source plugin slug.
	 *
	 * @return string
	 */
	public function get_source_plugin(): string {
		return 'advanced-google-recaptcha/advanced-google-recaptcha.php';
	}

	/**
	 * Get the source plugin display name.
	 *
	 * @return string
	 */
	public function get_source_name(): string {
		return 'Advanced Google reCAPTCHA';
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

		$captcha_type = $options['captcha'] ?? 'disabled';

		if ( ! in_array( $captcha_type, [ 'recaptchav2', 'recaptchav3' ], true ) ) {
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
