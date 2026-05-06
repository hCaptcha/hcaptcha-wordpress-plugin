<?php
/**
 * BeaverBuilderDetector class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\MigrationWizard\Detectors;

use HCaptcha\MigrationWizard\DetectionResult;

/**
 * Class BeaverBuilderDetector.
 *
 * Detects reCAPTCHA usage configured in Beaver Builder contact forms.
 * Beaver Builder stores per-form settings in the '_fl_builder_data' post meta
 * as a serialized flat array of node objects, each containing a 'settings' object.
 */
class BeaverBuilderDetector extends AbstractDetector {

	/**
	 * Beaver Builder plugin slug.
	 */
	private const PLUGIN_SLUG = 'bb-plugin/fl-builder.php';

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
		return 'Beaver Builder';
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
		$keys = $this->get_recaptcha_keys();

		if ( [] === $keys ) {
			return [];
		}

		return [
			$this->build_result(
				'recaptcha',
				'beaver_builder_contact',
				DetectionResult::CONFIDENCE_HIGH,
				'Beaver Builder has reCAPTCHA keys configured in a contact form.'
			),
		];
	}

	/**
	 * Find reCAPTCHA keys stored in Beaver Builder post meta.
	 *
	 * Queries '_fl_builder_data' post meta for any page/post that has a
	 * contact-form module with non-empty recaptcha_site_key and recaptcha_secret_key.
	 *
	 * @return array{site_key: string, secret_key: string}|array{}
	 */
	private function get_recaptcha_keys(): array {
		global $wpdb;

		$has_key        = '%' . $wpdb->esc_like( 'recaptcha_site_key' ) . '%';
		$empty_site_key = '%' . $wpdb->esc_like( '"recaptcha_site_key";s:0:""' ) . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$meta_value = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT meta_value FROM $wpdb->postmeta WHERE meta_key = %s AND meta_value LIKE %s AND meta_value NOT LIKE %s LIMIT 1",
				'_fl_builder_data',
				$has_key,
				$empty_site_key
			)
		);

		if ( ! $meta_value ) {
			return [];
		}

		$data = maybe_unserialize( $meta_value );

		if ( ! is_array( $data ) ) {
			return [];
		}

		foreach ( $data as $node ) {
			if ( ! isset( $node->settings->recaptcha_site_key, $node->settings->recaptcha_secret_key ) ) {
				continue;
			}

			$site_key   = trim( (string) $node->settings->recaptcha_site_key );
			$secret_key = trim( (string) $node->settings->recaptcha_secret_key );

			if ( '' !== $site_key && '' !== $secret_key ) {
				return [
					'site_key'   => $site_key,
					'secret_key' => $secret_key,
				];
			}
		}

		return [];
	}
}
