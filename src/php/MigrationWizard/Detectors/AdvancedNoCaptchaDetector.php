<?php
/**
 * AdvancedNoCaptchaDetector class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\MigrationWizard\Detectors;

use HCaptcha\MigrationWizard\DetectionResult;

/**
 * Class AdvancedNoCaptchaDetector.
 *
 * Detects reCAPTCHA usage from the "Advanced noCaptcha & invisible Captcha" plugin.
 * Plugin slug: advanced-nocaptcha-recaptcha/advanced-nocaptcha-recaptcha.php.
 * Options stored in: 'anr_admin_options'.
 */
class AdvancedNoCaptchaDetector extends AbstractDetector {

	/**
	 * Plugin option name.
	 */
	private const OPTION_NAME = 'c4wp_admin_options';

	/**
	 * Surface map: enabled_forms value => normalized surface id.
	 */
	private const SURFACE_MAP = [
		'login'            => 'wp_login',
		'registration'     => 'wp_register',
		'lost_password'    => 'wp_lost_password',
		'comment'          => 'wp_comment',
		'wc_login'         => 'wc_login',
		'wc_registration'  => 'wc_register',
		'wc_checkout'      => 'wc_checkout',
		'wc_lost_password' => 'wc_lost_password',
		'bp_register'      => 'buddypress_register',
		'bbp_topic'        => 'bbpress_new_topic',
		'bbp_reply'        => 'bbpress_reply',
	];

	/**
	 * Get the source plugin slug.
	 *
	 * @return string
	 */
	public function get_source_plugin(): string {
		return 'advanced-nocaptcha-recaptcha/advanced-nocaptcha-recaptcha.php';
	}

	/**
	 * Get the source plugin display name.
	 *
	 * @return string
	 */
	public function get_source_name(): string {
		return 'Advanced noCaptcha & invisible Captcha';
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

		$enabled_forms = isset( $options['enabled_forms'] ) ? (array) $options['enabled_forms'] : [];

		foreach ( self::SURFACE_MAP as $form_key => $surface_id ) {
			if ( in_array( $form_key, $enabled_forms, true ) ) {
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
