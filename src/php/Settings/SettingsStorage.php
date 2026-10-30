<?php
/**
 * SettingsStorage class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Settings;

use KAGG\Settings\Abstracts\SettingsBase;

/**
 * Persist maintenance field changes without submitting a settings form or changing its scope.
 */
class SettingsStorage {

	/**
	 * Exact maintenance write currently passing through native option filters.
	 *
	 * @var array|null
	 */
	private static ?array $write = null;

	/**
	 * Whether the persisted network-wide scope is active, even before Main initializes.
	 *
	 * @return bool
	 */
	public static function is_network_wide(): bool {
		return is_multisite() && [ 'on' ] === array_filter(
			(array) get_site_option( PluginSettingsBase::OPTION_NAME . SettingsBase::NETWORK_WIDE, [] )
		);
	}

	/**
	 * Read the selected stored scope without consulting cached settings tabs.
	 *
	 * @param bool|null $network Network scope, or null for the active scope.
	 *
	 * @return array
	 */
	public static function get( ?bool $network = null ): array {
		$network = is_multisite() && ( $network ?? self::is_network_wide() );
		$value   = $network ? get_site_option( PluginSettingsBase::OPTION_NAME, [] ) : get_option( PluginSettingsBase::OPTION_NAME, [] );

		return is_array( $value ) ? $value : [];
	}

	/**
	 * Apply only maintenance-owned field deltas to one existing storage scope.
	 *
	 * @param array     $changes Changed fields.
	 * @param string[]  $remove  Removed fields.
	 * @param bool|null $network Network scope, or null for the active scope.
	 *
	 * @return bool
	 */
	public static function update( array $changes, array $remove = [], ?bool $network = null ): bool {
		$network = is_multisite() && ( $network ?? self::is_network_wide() );
		$value   = self::get( $network );

		unset( $changes[ SettingsBase::NETWORK_WIDE ] );

		foreach ( $remove as $key ) {
			if ( SettingsBase::NETWORK_WIDE !== $key ) {
				unset( $value[ $key ] );
			}
		}

		$value    = array_merge( $value, $changes );
		$previous = self::$write;

		self::$write = [
			'hook'       => ( $network ? 'pre_update_site_option_' : 'pre_update_option_' ) . PluginSettingsBase::OPTION_NAME,
			'blog_id'    => get_current_blog_id(),
			'network_id' => get_current_network_id(),
			'value'      => $value,
		];

		try {
			return $network ? update_site_option( PluginSettingsBase::OPTION_NAME, $value ) : update_option( PluginSettingsBase::OPTION_NAME, $value );
		} finally {
			self::$write = $previous;
		}
	}

	/**
	 * Identify this helper's exact write without exposing a mutable bypass flag.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  Filtered option value.
	 *
	 * @return bool
	 */
	public static function is_internal_write( string $option, $value ): bool {
		return null !== self::$write &&
			PluginSettingsBase::OPTION_NAME === $option &&
			current_filter() === self::$write['hook'] &&
			get_current_blog_id() === self::$write['blog_id'] &&
			get_current_network_id() === self::$write['network_id'] &&
			self::$write['value'] === $value;
	}
}
