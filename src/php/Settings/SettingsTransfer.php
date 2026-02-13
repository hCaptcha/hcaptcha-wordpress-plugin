<?php
/**
 * Shared logic for exporting and importing settings (Ajax and WP-CLI).
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Settings;

use HCaptcha\Helpers\HCaptcha;
use WP_Error;

/**
 * Class SettingsTransfer
 */
class SettingsTransfer {
	/**
	 * Schema version for import/export payloads.
	 */
	public const SCHEMA_VERSION = '1.0';

	/**
	 * Build export payload.
	 *
	 * @param bool $include_keys Whether to include keys block.
	 *
	 * @return array
	 */
	public function build_export_payload( bool $include_keys = false ): array {
		$settings = get_option( PluginSettingsBase::OPTION_NAME, [] );

		$keys = [];

		if ( $include_keys ) {
			$keys['site_key']   = $settings['site_key'] ?? '';
			$keys['secret_key'] = $settings['secret_key'] ?? '';
		}

		// Remove keys from settings to avoid duplication and accidental exposure.
		unset( $settings['site_key'], $settings['secret_key'] );

		$data = [
			'meta'     => [
				'plugin'         => hcaptcha()->settings()->get_plugin_name(),
				'plugin_version' => constant( 'HCAPTCHA_VERSION' ),
				'schema_version' => self::SCHEMA_VERSION,
				'exported_at'    => gmdate( 'c' ),
			],
			'settings' => $settings,
		];

		if ( $include_keys ) {
			$data['keys'] = $keys;
		}

		return $data;
	}

	/**
	 * Validate import payload structure and meta.
	 *
	 * @param mixed $payload Decoded JSON payload.
	 *
	 * @return WP_Error|null
	 */
	public function validate_import_payload( $payload ): ?WP_Error {
		$plugin = $payload['meta']['plugin'] ?? '';
		$schema = $payload['meta']['schema_version'] ?? '';

		if ( hcaptcha()->settings()->get_plugin_name() !== $plugin || self::SCHEMA_VERSION !== $schema ) {
			return new WP_Error(
				'plugin_mismatch',
				__( 'Unsupported settings format.', 'hcaptcha-for-forms-and-more' )
			);
		}

		return null;
	}

	/**
	 * Apply import payload using admin sanitization path.
	 *
	 * @param array $payload    Validated payload.
	 * @param bool  $allow_keys Whether to allow importing keys block.
	 * @param bool  $dry_run    Do not write settings when true.
	 *
	 * @return WP_Error|string|null
	 */
	public function apply_import_payload( array $payload, bool $allow_keys = false, bool $dry_run = false ) {
		$result = $this->validate_import_payload( $payload );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$settings = (array) ( $payload['settings'] ?? [] );

		if ( isset( $payload['keys'] ) && $allow_keys ) {
			$settings['site_key']   = $payload['keys']['site_key'] ?? '';
			$settings['secret_key'] = $payload['keys']['secret_key'] ?? '';
		}

		if ( $dry_run ) {
			return null;
		}

		$old_settings = get_option( PluginSettingsBase::OPTION_NAME, [] );

		// Use General settings page sanitization rules to prepare value like the admin UI does.
		$general  = hcaptcha()->settings()->get_tab( General::class );
		$prepared = $general->pre_update_option_filter( $settings, $old_settings );

		if ( $prepared === $old_settings ) {
			// Nothing changed; return a success message.
			return __( 'Current and imported hCaptcha settings are the same.', 'hcaptcha-for-forms-and-more' );
		}

		$updated = update_option( PluginSettingsBase::OPTION_NAME, $prepared );

		if ( ! $updated ) {
			return new WP_Error(
				'save_failed',
				__( 'Failed to save settings.', 'hcaptcha-for-forms-and-more' )
			);
		}

		hcaptcha()->settings()->init();
		HCaptcha::save_license_level();

		return null;
	}
}
