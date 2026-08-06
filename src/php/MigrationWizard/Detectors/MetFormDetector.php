<?php
/**
 * MetFormDetector class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\MigrationWizard\Detectors;

use HCaptcha\MigrationWizard\DetectionResult;

/**
 * Class MetFormDetector.
 *
 * Detects reCAPTCHA usage configured in MetForm.
 */
class MetFormDetector extends AbstractDetector {

	/**
	 * MetForm plugin slug.
	 */
	private const PLUGIN_SLUG = 'metform/metform.php';

	/**
	 * MetForm global settings option.
	 */
	private const SETTINGS_OPTION = 'metform_option__settings';

	/**
	 * MetForm form settings meta key.
	 */
	private const FORM_SETTINGS_META = 'metform_form__form_setting';

	/**
	 * MetForm post type.
	 */
	private const POST_TYPE = 'metform-form';

	/**
	 * ReCAPTCHA key pairs: [ site key, secret key ].
	 */
	private const KEY_PAIRS = [
		[ 'mf_recaptcha_site_key', 'mf_recaptcha_secret_key' ],
		[ 'mf_recaptcha_site_key_v3', 'mf_recaptcha_secret_key_v3' ],
	];

	/**
	 * Get the source plugin slug.
	 *
	 * @return string
	 */
	public function get_source_plugin(): string {
		return self::PLUGIN_SLUG;
	}

	/**
	 * Get the source plugin display name.
	 *
	 * @return string
	 */
	public function get_source_name(): string {
		return 'MetForm';
	}

	/**
	 * Check if this detector is applicable.
	 *
	 * @return bool
	 */
	public function is_applicable(): bool {
		return $this->is_plugin_active( self::PLUGIN_SLUG );
	}

	/**
	 * Run detection.
	 *
	 * @return DetectionResult[]
	 */
	public function detect(): array {
		$global_settings = get_option( self::SETTINGS_OPTION, [] );
		$global_settings = is_array( $global_settings ) ? $global_settings : [];

		if ( ! $this->has_recaptcha_keys( $global_settings ) && ! $this->has_form_recaptcha( $global_settings ) ) {
			return [];
		}

		return [
			$this->build_result(
				'recaptcha',
				'metform_form',
				DetectionResult::CONFIDENCE_HIGH,
				'MetForm has reCAPTCHA keys configured. Forms may use reCAPTCHA.'
			),
		];
	}

	/**
	 * Check whether any MetForm form has reCAPTCHA keys configured.
	 *
	 * @param array $global_settings Global MetForm settings.
	 *
	 * @return bool
	 */
	private function has_form_recaptcha( array $global_settings ): bool {
		$form_ids = get_posts(
			[
				'post_type'   => self::POST_TYPE,
				'post_status' => 'any',
				'numberposts' => -1,
				'fields'      => 'ids',
			]
		);

		foreach ( $form_ids as $form_id ) {
			$form_settings = get_post_meta( $form_id, self::FORM_SETTINGS_META, true );

			if ( ! is_array( $form_settings ) ) {
				continue;
			}

			$settings = $global_settings ? array_merge( $form_settings, $global_settings ) : $form_settings;

			if ( $this->has_recaptcha_keys( $settings ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check whether settings contain a complete reCAPTCHA key pair.
	 *
	 * @param array $settings MetForm settings.
	 *
	 * @return bool
	 */
	private function has_recaptcha_keys( array $settings ): bool {
		foreach ( self::KEY_PAIRS as $pair ) {
			$site_key   = trim( (string) ( $settings[ $pair[0] ] ?? '' ) );
			$secret_key = trim( (string) ( $settings[ $pair[1] ] ?? '' ) );

			if ( '' !== $site_key && '' !== $secret_key ) {
				return true;
			}
		}

		return false;
	}
}
