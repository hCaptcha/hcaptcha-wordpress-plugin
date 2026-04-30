<?php
/**
 * ACFEDetector class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\MigrationWizard\Detectors;

use HCaptcha\MigrationWizard\DetectionResult;

/**
 * Class ACFEDetector.
 *
 * Detects reCAPTCHA usage configured in ACF Extended (ACFE).
 */
class ACFEDetector extends AbstractDetector {

	/**
	 * ACF Extended plugin slugs.
	 */
	private const PLUGIN_SLUGS = [
		'acf-extended-pro/acf-extended.php',
		'acf-extended/acf-extended.php',
	];

	/**
	 * ACF Extended surface IDs to detect.
	 */
	private const SURFACES = [
		'acfe_form',
	];

	/**
	 * Get the source plugin slug.
	 *
	 * @return string
	 */
	public function get_source_plugin(): string {
		foreach ( self::PLUGIN_SLUGS as $slug ) {
			if ( $this->is_plugin_active( $slug ) ) {
				return $slug;
			}
		}

		return self::PLUGIN_SLUGS[0];
	}

	/**
	 * Get the source plugin display name.
	 *
	 * @return string
	 */
	public function get_source_name(): string {
		return 'ACF Extended';
	}

	/**
	 * Check if this detector is applicable.
	 *
	 * @return bool
	 */
	public function is_applicable(): bool {
		foreach ( self::PLUGIN_SLUGS as $slug ) {
			if ( $this->is_plugin_active( $slug ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Run detection.
	 *
	 * @return DetectionResult[]
	 */
	public function detect(): array {
		$results = [];

		$site_key   = $this->get_setting( 'acfe/field/recaptcha/site_key' );
		$secret_key = $this->get_setting( 'acfe/field/recaptcha/secret_key' );

		if ( empty( $site_key ) || empty( $secret_key ) ) {
			return $results;
		}

		foreach ( self::SURFACES as $surface_id ) {
			$results[] = $this->build_result(
				'recaptcha',
				$surface_id,
				DetectionResult::CONFIDENCE_HIGH,
				'ACF Extended has reCAPTCHA keys configured. ACFE forms may use reCAPTCHA.'
			);
		}

		return $results;
	}

	/**
	 * Get ACFE setting.
	 *
	 * @param string $name Setting name.
	 *
	 * @return string
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	private function get_setting( string $name ): string {
		// Try ACF setting first if ACF is active.
		if ( function_exists( 'acf_get_setting' ) ) {
			$value = acf_get_setting( $name );

			if ( ! empty( $value ) && is_string( $value ) ) {
				return trim( $value );
			}
		}

		// Fallback to option.
		$settings = get_option( 'acfe_settings', [] );

		if ( ! is_array( $settings ) ) {
			return '';
		}

		// ACFE saves keys in acfe_settings as 'field/recaptcha/site_key' (without acfe/ prefix).
		$key = str_replace( 'acfe/', '', $name );

		// Handle nested keys like 'field/recaptcha/site_key'.
		$value = $this->get_nested_value( $settings, $key );

		return is_string( $value ) ? trim( $value ) : '';
	}

	/**
	 * Get nested value from an array using a slash-separated key.
	 *
	 * @param array  $data Array.
	 * @param string $key   Slash-separated key.
	 * @return mixed
	 */
	private function get_nested_value( array $data, string $key ) {
		$keys = explode( '/', $key );

		foreach ( $keys as $inner_key ) {
			if ( ! isset( $data[ $inner_key ] ) ) {
				return null;
			}
			$data = $data[ $inner_key ];
		}

		return $data;
	}
}
