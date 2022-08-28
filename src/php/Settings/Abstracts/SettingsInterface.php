<?php
/**
 * SettingsInterface interface file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Settings\Abstracts;

/**
 * Interface SettingsInterface.
 */
interface SettingsInterface {
	/**
	 * Get plugin option.
	 *
	 * @param string $key         Setting name.
	 * @param mixed  $empty_value Empty value for this setting.
	 *
	 * @return string|array The value specified for the option or a default value for the option.
	 */
	public function get( $key, $empty_value = null );
}
