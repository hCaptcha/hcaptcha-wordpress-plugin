<?php
/**
 * SystemInfo class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Settings;

use HCaptcha\Migrations\Migrations;
use KAGG\Settings\Abstracts\SettingsBase;

/**
 * Class SystemInfo
 *
 * Settings page "SystemInfo".
 */
class SystemInfo extends PluginSettingsBase {

	/**
	 * Dialog scripts and style handle.
	 */
	public const DIALOG_HANDLE = 'kagg-dialog';

	/**
	 * Admin script handle.
	 */
	public const HANDLE = 'hcaptcha-system-info';

	/**
	 * Script localization object.
	 */
	public const OBJECT = 'HCaptchaSystemInfoObject';

	/**
	 * Data key length.
	 */
	private const DATA_KEY_LENGTH = 36;

	/**
	 * Get page title.
	 *
	 * @return string
	 */
	protected function page_title(): string {
		return __( 'System Info', 'hcaptcha-for-forms-and-more' );
	}

	/**
	 * Get section title.
	 *
	 * @return string
	 */
	protected function section_title(): string {
		return 'system-info';
	}

	/**
	 * Enqueue class scripts.
	 *
	 * @return void
	 */
	public function admin_enqueue_scripts(): void {
		wp_enqueue_script(
			self::DIALOG_HANDLE,
			constant( 'HCAPTCHA_URL' ) . "/assets/js/kagg-dialog$this->min_suffix.js",
			[],
			constant( 'HCAPTCHA_VERSION' ),
			true
		);

		wp_enqueue_style(
			self::DIALOG_HANDLE,
			constant( 'HCAPTCHA_URL' ) . "/assets/css/kagg-dialog$this->min_suffix.css",
			[],
			constant( 'HCAPTCHA_VERSION' )
		);

		wp_enqueue_script(
			self::HANDLE,
			constant( 'HCAPTCHA_URL' ) . "/assets/js/system-info$this->min_suffix.js",
			[ self::DIALOG_HANDLE ],
			constant( 'HCAPTCHA_VERSION' ),
			true
		);

		wp_localize_script(
			self::HANDLE,
			self::OBJECT,
			[
				'successMsg' => __( 'System info copied to the clipboard.', 'hcaptcha-for-forms-and-more' ),
				'errorMsg'   => __( 'Cannot copy info to the clipboard.', 'hcaptcha-for-forms-and-more' ),
				'OKBtnText'  => __( 'OK', 'hcaptcha-for-forms-and-more' ),
			]
		);

		wp_enqueue_style(
			self::HANDLE,
			constant( 'HCAPTCHA_URL' ) . "/assets/css/system-info$this->min_suffix.css",
			[ static::PREFIX . '-' . SettingsBase::HANDLE, self::DIALOG_HANDLE ],
			constant( 'HCAPTCHA_VERSION' )
		);
	}

	/**
	 * Section callback.
	 *
	 * @param array $arguments Section arguments.
	 */
	public function section_callback( array $arguments ): void {
		$this->print_header();

		?>
		<div id="hcaptcha-system-info-wrap">
			<span class="helper">
				<span class="helper-content"><?php esc_html_e( 'Copy system info to clipboard', 'hcaptcha-for-forms-and-more' ); ?></span>
			</span>
			<div class="dashicons-before dashicons-media-text" aria-hidden="true"></div>
			<label>
			<textarea
					id="hcaptcha-system-info"
					readonly><?php echo esc_textarea( $this->get_system_info() ); ?></textarea>
			</label>
		</div>
		<?php
	}

	/**
	 * Get system information.
	 *
	 * Based on a function from WPForms.
	 *
	 * @return string
	 */
	protected function get_system_info(): string {
		$data = $this->header( '### Begin System Info ###' );

		$data .= $this->hcaptcha_info();
		$data .= $this->integration_info();
		$data .= $this->site_info();
		$data .= $this->wp_info();
		$data .= $this->uploads_info();
		$data .= $this->plugins_info();
		$data .= $this->server_info();

		$data .= $this->header( '### End System Info ###' );

		return $data;
	}

	/**
	 * Get hCaptcha info.
	 *
	 * @return string
	 */
	private function hcaptcha_info(): string {
		$settings = hcaptcha()->settings();
		$data     = $this->header( '-- hCaptcha Info --' );

		$data .= $this->data( 'Version', constant( 'HCAPTCHA_VERSION' ) );

		// Keys section.
		$data .= $this->data( 'Site key', $this->is_empty( $settings->get_site_key() ) );
		$data .= $this->data( 'Secret key', $this->is_empty( $settings->get_secret_key() ) );

		// Appearance section.
		$data .= $this->data( 'Theme', $settings->get_theme() );
		$data .= $this->data( 'Size', $settings->get( 'size' ) );
		$data .= $this->data( 'Language', $settings->get( 'language' ) ?: 'Auto-detect' );
		$data .= $this->data( 'Mode', $settings->get_mode() );

		// Custom section.
		$data .= $this->data( 'Custom Themes', $this->is_on( 'custom_themes' ) );
		$data .= $this->data( 'Config Params', $this->is_empty( $settings->get( 'config_params' ) ) );

		// Enterprise section.
		$data .= $this->data( 'API Host', $settings->get( 'api_host' ) );
		$data .= $this->data( 'Asset Host', $settings->get( 'asset_host' ) );
		$data .= $this->data( 'Endpoint', $settings->get( 'endpoint' ) );
		$data .= $this->data( 'Host', $settings->get( 'host' ) );
		$data .= $this->data( 'Image Host', $settings->get( 'image_host' ) );
		$data .= $this->data( 'Report API', $settings->get( 'report_api' ) );
		$data .= $this->data( 'Sentry', $settings->get( 'sentry' ) );
		$data .= $this->data( 'Backend', $settings->get( 'backend' ) );

		// Other section.
		$data .= $this->data( 'Turn Off When Logged In', $this->is_on( 'off_when_logged_in' ) );
		$data .= $this->data( 'Disable reCAPTCHA Compatibility', $this->is_on( 'recaptcha_compat_off' ) );
		$data .= $this->data( 'Whitelisted IPs', $this->is_empty( $settings->get( 'whitelisted_ips' ) ) );
		$data .= $this->data( 'Login attempts before hCaptcha', $settings->get( 'login_limit' ) );
		$data .= $this->data( 'Failed login attempts interval, min', $settings->get( 'login_interval' ) );
		$data .= $this->data( 'Delay showing hCaptcha, ms', $settings->get( 'delay' ) );

		$migrations = get_option( Migrations::MIGRATED_VERSIONS_OPTION_NAME, [] );

		$data .= $this->data( 'Migrations' );

		$format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );

		foreach ( $migrations as $version => $timestamp ) {
			$value = Migrations::STARTED === $timestamp ? 'Started' : 0;
			$value = Migrations::FAILED === $timestamp ? 'Failed' : $value;
			$value = $value ?: gmdate( $format, $timestamp );

			$data .= $this->data( '  ' . $version, $value );
		}

		return $data;
	}

	/**
	 * Get integration info.
	 *
	 * @return string
	 */
	public function integration_info(): string {
		[ $integration_fields, $integration_settings ] = $this->get_integrations();

		$disabled = false;

		$data = $this->header( '--- Active plugins and themes ---' );

		foreach ( $integration_fields as $field_key => $field ) {
			if ( $field['disabled'] !== $disabled ) {
				$disabled = true;

				$data .= $this->header( '--- Inactive plugins and themes ---' );
			}

			$data .= $this->data( $field['label'] );

			foreach ( $field['options'] as $option_key => $option ) {
				$setting = isset( $integration_settings[ $field_key ] ) ? (array) $integration_settings[ $field_key ] : [];
				$value   = in_array( $option_key, $setting, true ) ? 'On' : 'Off';

				$data .= $this->data( '  ' . $option, $value );
			}
		}

		return $data;
	}

	/**
	 * Get integrations.
	 *
	 * @return array
	 */
	public function get_integrations(): array {
		$tabs = hcaptcha()->settings()->get_tabs();

		$tabs = array_filter(
			$tabs,
			static function ( $tab ) {
				return is_a( $tab, Integrations::class );
			}
		);

		if ( ! $tabs ) {
			return [];
		}

		$integrations_obj = array_shift( $tabs );
		$fields           = $integrations_obj->form_fields();
		$fields           = $integrations_obj->sort_fields( $fields );
		$settings         = $integrations_obj->settings;

		return [ $fields, $settings ];
	}

	/**
	 * Get Site info.
	 *
	 * @return string
	 */
	private function site_info(): string {
		$data = $this->header( '-- Site Info --' );

		$data .= $this->data( 'Site URL', site_url() );
		$data .= $this->data( 'Home URL', home_url() );
		$data .= $this->data( 'Multisite', is_multisite() ? 'Yes' : 'No' );

		return $data;
	}

	/**
	 * Get WordPress Configuration info.
	 *
	 * @return string
	 * @noinspection NestedTernaryOperatorInspection
	 */
	private function wp_info(): string {
		global $wpdb;

		$theme_data = wp_get_theme();
		$theme      = $theme_data->get( 'Name' ) . ' ' . $theme_data->get( 'Version' );

		$data = $this->header( '-- WordPress Configuration --' );

		$data .= $this->data( 'Version', get_bloginfo( 'version' ) );
		$data .= $this->data( 'Language', get_locale() );
		$data .= $this->data( 'User Language', get_user_locale() );
		$data .= $this->data( 'Permalink Structure', get_option( 'permalink_structure' ) ?: 'Default' );
		$data .= $this->data( 'Active Theme', $theme );
		$data .= $this->data( 'Show On Front', get_option( 'show_on_front' ) );

		// Only show page specs if the front page is set to 'page'.
		if ( get_option( 'show_on_front' ) === 'page' ) {
			$front_page_id = get_option( 'page_on_front' );
			$blog_page_id  = get_option( 'page_for_posts' );
			$front_page    = $front_page_id ? get_the_title( $front_page_id ) . ' (#' . $front_page_id . ')' : 'Unset';
			$blog_page     = $blog_page_id ? get_the_title( $blog_page_id ) . ' (#' . $blog_page_id . ')' : 'Unset';

			$data .= $this->data( 'Page On Front', $front_page );
			$data .= $this->data( 'Page For Posts', $blog_page );
		}

		$data .= $this->data( 'ABSPATH', constant( 'ABSPATH' ) );
		$data .= $this->data( 'Table Prefix', 'Length: ' . strlen( $wpdb->prefix ) . '   Status: ' . ( strlen( $wpdb->prefix ) > 16 ? 'ERROR: Too long' : 'Acceptable' ) );
		$data .= $this->data( 'WP_DEBUG', defined( 'WP_DEBUG' ) ? constant( 'WP_DEBUG' ) ? 'Enabled' : 'Disabled' : 'Not set' );
		$data .= $this->data( 'Memory Limit', constant( 'WP_MEMORY_LIMIT' ) );
		$data .= $this->data( 'Registered Post Stati', implode( ', ', get_post_stati() ) );
		$data .= $this->data(
			'Revisions',
			constant( 'WP_POST_REVISIONS' ) ?
				constant( 'WP_POST_REVISIONS' ) > 1 ?
					'Limited to ' . constant( 'WP_POST_REVISIONS' ) :
					'Enabled' :
				'Disabled'
		);

		return $data;
	}

	/**
	 * Get Uploads/Constants info.
	 *
	 * @return string
	 * @noinspection NestedTernaryOperatorInspection
	 */
	private function uploads_info(): string {
		$data = $this->header( '-- WordPress Uploads/Constants --' );

		$data .= $this->data( 'WP_CONTENT_DIR', defined( 'WP_CONTENT_DIR' ) ? constant( 'WP_CONTENT_DIR' ) ?: 'Disabled' : 'Not set' );
		$data .= $this->data( 'WP_CONTENT_URL', defined( 'WP_CONTENT_URL' ) ? constant( 'WP_CONTENT_URL' ) ?: 'Disabled' : 'Not set' );
		$data .= $this->data( 'UPLOADS', defined( 'UPLOADS' ) ? constant( 'UPLOADS' ) ?: 'Disabled' : 'Not set' );

		$uploads_dir = wp_upload_dir();

		$data .= $this->data( 'wp_uploads_dir() path', $uploads_dir['path'] );
		$data .= $this->data( 'wp_uploads_dir() url', $uploads_dir['url'] );
		$data .= $this->data( 'wp_uploads_dir() basedir', $uploads_dir['basedir'] );
		$data .= $this->data( 'wp_uploads_dir() baseurl', $uploads_dir['baseurl'] );

		return $data;
	}

	/**
	 * Get Plugins info.
	 *
	 * @return string
	 */
	private function plugins_info(): string {
		// Get plugins that have an update.
		$data = $this->mu_plugins();

		$data .= $this->installed_plugins();
		$data .= $this->multisite_plugins();

		return $data;
	}

	/**
	 * Get MU Plugins info.
	 *
	 * @return string
	 */
	private function mu_plugins(): string {
		$data = '';

		// Must-use plugins.
		// Note: MU plugins can't show updates!
		$mu_plugins = get_mu_plugins();

		if ( ! empty( $mu_plugins ) && count( $mu_plugins ) > 0 ) {
			$data = $this->header( '-- Must-Use Plugins --' );

			$key_length = $this->get_max_key_length( $mu_plugins, 'Name' );

			foreach ( $mu_plugins as $plugin_data ) {
				$data .= $this->data( $plugin_data['Name'], $plugin_data['Version'], $key_length );
			}
		}

		return $data;
	}

	/**
	 * Get Installed Plugins info.
	 *
	 * @return string
	 */
	private function installed_plugins(): string {
		$updates = get_plugin_updates();

		// WordPress active plugins.
		$data = $this->header( '-- WordPress Active Plugins --' );

		$plugins        = get_plugins();
		$active_plugins = get_option( 'active_plugins', [] );

		$key_length = $this->get_max_key_length( $plugins, 'Name' );

		foreach ( $plugins as $plugin_path => $plugin ) {
			if ( ! in_array( $plugin_path, $active_plugins, true ) ) {
				continue;
			}

			$update = array_key_exists( $plugin_path, $updates ) ? ' (needs update - ' . $updates[ $plugin_path ]->update->new_version . ')' : '';

			$data .= $this->data( $plugin['Name'], $plugin['Version'] . $update, $key_length );
		}

		// WordPress inactive plugins.
		$data .= $this->header( '-- WordPress Inactive Plugins --' );

		foreach ( $plugins as $plugin_path => $plugin ) {
			if ( in_array( $plugin_path, $active_plugins, true ) ) {
				continue;
			}

			$update = array_key_exists( $plugin_path, $updates ) ? ' (needs update - ' . $updates[ $plugin_path ]->update->new_version . ')' : '';

			$data .= $this->data( $plugin['Name'], $plugin['Version'] . $update, $key_length );
		}

		return $data;
	}

	/**
	 * Get Multisite Plugins info.
	 *
	 * @return string
	 */
	protected function multisite_plugins(): string {
		$data = '';

		if ( ! is_multisite() ) {
			return $data;
		}

		$updates = get_plugin_updates();

		// WordPress Multisite active plugins.
		$data = $this->header( '-- Network Active Plugins --' );

		$plugins        = wp_get_active_network_plugins();
		$active_plugins = get_site_option( 'active_sitewide_plugins', [] );
		$plugin_data    = [];

		foreach ( $plugins as $plugin_path ) {
			$plugin_base = plugin_basename( $plugin_path );

			if ( ! array_key_exists( $plugin_base, $active_plugins ) ) {
				continue;
			}

			$plugin                                 = get_plugin_data( $plugin_path );
			$plugin_data[ $plugin_path ]['Name']    = $plugin['Name'];
			$plugin_data[ $plugin_path ]['Version'] = $plugin['Version'];
		}

		$key_length = $this->get_max_key_length( $plugins, 'Name' );

		foreach ( $plugin_data as $plugin_path => $plugin_datum ) {
			$update = array_key_exists( $plugin_path, $updates ) ? ' (needs update - ' . $updates[ $plugin_path ]->update->new_version . ')' : '';

			$data .= $this->data( $plugin_datum['Name'], $plugin_datum['Version'] . $update, $key_length );
		}

		return $data;
	}

	/**
	 * Get Server info.
	 *
	 * @return string
	 */
	private function server_info(): string {
		global $wpdb;

		// Server configuration (really just versions).
		$data = $this->header( '-- Webserver Configuration --' );

		$data .= $this->data( 'PHP Version', constant( 'PHP_VERSION' ) );
		$data .= $this->data( 'MySQL Version', $wpdb->db_version() );
		$data .= $this->data( 'Webserver Info', isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : '' );

		// PHP configs... now we're getting to the important stuff.
		$data .= $this->header( '-- PHP Configuration --' );
		$data .= $this->data( 'Memory Limit', ini_get( 'memory_limit' ) );
		$data .= $this->data( 'Upload Max Size', ini_get( 'upload_max_filesize' ) );
		$data .= $this->data( 'Post Max Size', ini_get( 'post_max_size' ) );
		$data .= $this->data( 'Upload Max Filesize', ini_get( 'upload_max_filesize' ) );
		$data .= $this->data( 'Time Limit', ini_get( 'max_execution_time' ) );
		$data .= $this->data( 'Max Input Vars', ini_get( 'max_input_vars' ) );
		$data .= $this->data( 'Display Errors', ( ini_get( 'display_errors' ) ? 'On (' . ini_get( 'display_errors' ) . ')' : 'N/A' ) );

		// PHP extensions and such.
		$data .= $this->header( '-- PHP Extensions --' );
		$data .= $this->data( 'cURL', ( function_exists( 'curl_init' ) ? 'Supported' : 'Not Supported' ) );
		$data .= $this->data( 'fsockopen', ( function_exists( 'fsockopen' ) ? 'Supported' : 'Not Supported' ) );
		$data .= $this->data( 'SOAP Client', ( class_exists( 'SoapClient', false ) ? 'Installed' : 'Not Installed' ) );
		$data .= $this->data( 'Suhosin', ( extension_loaded( 'suhosin' ) ? 'Installed' : 'Not Installed' ) );

		// Session stuff.
		$data .= $this->header( '-- Session Configuration --' );
		$data .= $this->data( 'Session', isset( $_SESSION ) ? 'Enabled' : 'Disabled' );

		// The rest of this is only relevant if session is enabled.
		if ( isset( $_SESSION ) ) {
			$data .= $this->data( 'Session Name', esc_html( ini_get( 'session.name' ) ) );
			$data .= $this->data( 'Cookie Path', esc_html( ini_get( 'session.cookie_path' ) ) );
			$data .= $this->data( 'Save Path', esc_html( ini_get( 'session.save_path' ) ) );
			$data .= $this->data( 'Use Cookies', ( ini_get( 'session.use_cookies' ) ? 'On' : 'Off' ) );
			$data .= $this->data( 'Use Only Cookies', ( ini_get( 'session.use_only_cookies' ) ? 'On' : 'Off' ) );
		}

		return $data;
	}

	/**
	 * Get header.
	 *
	 * @param string $header Header.
	 *
	 * @return string
	 */
	private function header( string $header ): string {
		return "\n" . $header . "\n\n";
	}

	/**
	 * Get data string.
	 *
	 * @param string $key            Data key.
	 * @param string $value          Data value.
	 * @param int    $max_key_length Max key length.
	 *
	 * @return string
	 */
	private function data( string $key, string $value = '', int $max_key_length = 0 ): string {
		$length = $max_key_length ? max( $max_key_length, self::DATA_KEY_LENGTH ) : self::DATA_KEY_LENGTH;

		$length += 2;

		return $this->mb_str_pad( $key . ': ', $length ) . $value . "\n";
	}

	/**
	 * Get max key length.
	 *
	 * @param array  $data Data.
	 * @param string $key  Key.
	 *
	 * @return int
	 * @noinspection PhpSameParameterValueInspection
	 */
	private function get_max_key_length( array $data, string $key ): int {
		return array_reduce(
			$data,
			static function ( $carry, $item ) use ( $key ) {
				$length = isset( $item[ $key ] ) ? mb_strlen( $item[ $key ] ) : 0;

				return max( $carry, $length );
			},
			0
		);
	}

	/**
	 * Multibyte str_pad.
	 *
	 * @param string $s          A string.
	 * @param int    $length     Desired length.
	 * @param string $pad_string Padding character.
	 *
	 * @return string
	 * @noinspection PhpSameParameterValueInspection
	 */
	private function mb_str_pad( string $s, int $length, string $pad_string = ' ' ): string {
		$pad_string = mb_substr( $pad_string, 0, 1 );
		$times      = max( 0, $length - mb_strlen( $s ) );

		return $s . str_repeat( $pad_string, $times );
	}

	/**
	 * Return whether data is empty.
	 *
	 * @param mixed $data Data.
	 *
	 * @return string
	 */
	private function is_empty( $data ): string {
		return empty( $data ) ? 'Not set' : 'Set';
	}

	/**
	 * Return whether option value is 'on' or just non-empty.
	 *
	 * @param string $key Setting name.
	 *
	 * @return string
	 */
	private function is_on( string $key ): string {
		return hcaptcha()->settings()->is_on( $key ) ? 'On' : 'Off';
	}
}
