<?php
/**
 * PluginSettingsBase class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Settings;

use HCaptcha\Settings\Abstracts\SettingsBase;

/**
 * Class PluginSettingsBase
 *
 * Extends general SettingsBase suitable for any plugin with current plugin related methods.
 */
abstract class PluginSettingsBase extends SettingsBase {

	/**
	 * Get plugin base name.
	 *
	 * @return string
	 */
	protected function plugin_basename() {
		return plugin_basename( constant( 'HCAPTCHA_FILE' ) );
	}

	/**
	 * Get plugin url.
	 *
	 * @return string
	 */
	protected function plugin_url() {
		return constant( 'HCAPTCHA_URL' );
	}

	/**
	 * Get plugin version.
	 *
	 * @return string
	 */
	protected function plugin_version() {
		return constant( 'HCAPTCHA_VERSION' );
	}

	/**
	 * Get settings link label.
	 *
	 * @return string
	 */
	protected function settings_link_label() {
		return __( 'hCaptcha Settings', 'hcaptcha-for-forms-and-more' );
	}

	/**
	 * Get settings link text.
	 *
	 * @return string
	 */
	protected function settings_link_text() {
		return __( 'Settings', 'hcaptcha-for-forms-and-more' );
	}

	/**
	 * Get text domain.
	 *
	 * @return string
	 */
	protected function text_domain() {
		return 'hcaptcha-for-forms-and-more';
	}
}
