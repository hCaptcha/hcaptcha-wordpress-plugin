<?php
/**
 * Migrations class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Migrations;

use HCaptcha\Admin\Events\Events;
use HCaptcha\Settings\PluginSettingsBase;

/**
 * Migrations class.
 */
class Migrations {

	/**
	 * Migrated versions options name.
	 */
	public const MIGRATED_VERSIONS_OPTION_NAME = 'hcaptcha_versions';

	/**
	 * Plugin version.
	 */
	private const PLUGIN_VERSION = HCAPTCHA_VERSION;

	/**
	 * Migration started status.
	 */
	public const STARTED = - 1;

	/**
	 * Migration failed status.
	 */
	public const FAILED = - 2;

	/**
	 * Plugin name.
	 */
	private const PLUGIN_NAME = 'hCaptcha Plugin';

	/**
	 * Migration constructor.
	 */
	public function __construct() {
		$this->init();
	}

	/**
	 * Init class.
	 *
	 * @return void
	 */
	public function init(): void {
		if ( ! $this->is_allowed() ) {
			return;
		}

		$this->init_hooks();
	}

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		add_action( 'plugins_loaded', [ $this, 'migrate' ], - PHP_INT_MAX );
	}

	/**
	 * Migrate.
	 *
	 * @return void
	 */
	public function migrate(): void {
		$migrated = (array) get_option( self::MIGRATED_VERSIONS_OPTION_NAME, [] );

		$this->check_plugin_update( $migrated );

		$migrations       = array_filter(
			get_class_methods( $this ),
			static function ( $migration ) {
				return false !== strpos( $migration, 'migrate_' );
			}
		);
		$upgrade_versions = [];

		foreach ( $migrations as $migration ) {
			$upgrade_version    = $this->get_upgrade_version( $migration );
			$upgrade_versions[] = $upgrade_version;

			if (
				( isset( $migrated[ $upgrade_version ] ) && $migrated[ $upgrade_version ] >= 0 ) ||
				version_compare( $upgrade_version, self::PLUGIN_VERSION, '>' )
			) {
				continue;
			}

			if ( ! isset( $migrated[ $upgrade_version ] ) ) {
				$migrated[ $upgrade_version ] = static::STARTED;

				$this->log( sprintf( 'Migration of %1$s to %2$s started.', self::PLUGIN_NAME, $upgrade_version ) );
			}

			// Run migration.
			$result = $this->{$migration}();

			// Some migration methods can be called several times to support AS action,
			// so do not log their completion here.
			if ( null === $result ) {
				// @codeCoverageIgnoreStart
				continue;
				// @codeCoverageIgnoreEnd
			}

			$migrated[ $upgrade_version ] = $result ? time() : static::FAILED;

			$this->log_migration_message( $result, $upgrade_version );
		}

		// Remove any keys that are not in the migrations list.
		$migrated = array_intersect_key( $migrated, array_flip( $upgrade_versions ) );

		// Store the current version.
		$migrated[ self::PLUGIN_VERSION ] = $migrated[ self::PLUGIN_VERSION ] ?? time();

		// Sort the array by version.
		uksort( $migrated, 'version_compare' );

		update_option( self::MIGRATED_VERSIONS_OPTION_NAME, $migrated );
	}

	/**
	 * Determine if migration is allowed.
	 */
	public function is_allowed(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['service-worker'] ) ) {
			return false;
		}

		return wp_doing_cron() || is_admin() || ( defined( 'WP_CLI' ) && constant( 'WP_CLI' ) );
	}

	/**
	 * Check if the plugin was updated.
	 *
	 * @param array $migrated Migrated versions.
	 *
	 * @return void
	 */
	private function check_plugin_update( array $migrated ): void {
		if ( isset( $migrated[ self::PLUGIN_VERSION ] ) ) {
			return;
		}

		// Send statistics on plugin update.
		add_action(
			'init',
			static function () {
				/**
				 * Send plugin statistics.
				 */
				do_action( 'hcap_send_plugin_stats' );
			}
		);
	}

	/**
	 * Get an upgrade version from the method name.
	 *
	 * @param string $method Method name.
	 *
	 * @return string
	 */
	private function get_upgrade_version( string $method ): string {
		// Find only the digits and underscores to get version number.
		if ( ! preg_match( '/(\d_?)+/', $method, $matches ) ) {
			// @codeCoverageIgnoreStart
			return '';
			// @codeCoverageIgnoreEnd
		}

		$raw_version = $matches[0];

		if ( strpos( $raw_version, '_' ) ) {
			// Modern notation: 3_10_0 means 3.10.0 version.

			// @codeCoverageIgnoreStart
			return str_replace( '_', '.', $raw_version );
			// @codeCoverageIgnoreEnd
		}

		// Legacy notation, with 1-digit subversion numbers: 360 means 3.6.0 version.
		return implode( '.', str_split( $raw_version ) );
	}

	/**
	 * Output message into log file.
	 *
	 * @param string $message Message to log.
	 *
	 * @return void
	 * @noinspection ForgottenDebugOutputInspection
	 */
	private function log( string $message ): void {
		if ( ! ( defined( 'WP_DEBUG' ) && constant( 'WP_DEBUG' ) ) ) {
			// @codeCoverageIgnoreStart
			return;
			// @codeCoverageIgnoreEnd
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( self::PLUGIN_NAME . ':  ' . $message );
	}

	/**
	 * Log migration message.
	 *
	 * @param bool   $migrated        Migration status.
	 * @param string $upgrade_version Upgrade version.
	 *
	 * @return void
	 */
	private function log_migration_message( bool $migrated, string $upgrade_version ): void {
		$message = $migrated ?
			sprintf( 'Migration of %1$s to %2$s completed.', self::PLUGIN_NAME, $upgrade_version ) :
			// @codeCoverageIgnoreStart
			sprintf( 'Migration of %1$s to %2$s failed.', self::PLUGIN_NAME, $upgrade_version );
		// @codeCoverageIgnoreEnd

		$this->log( $message );
	}

	/**
	 * Migrate to 2.0.0
	 *
	 * @return bool|null
	 * @noinspection PhpUnused
	 */
	protected function migrate_200(): ?bool {
		$options_map = [
			'hcaptcha_api_key'                     => 'site_key',
			'hcaptcha_secret_key'                  => 'secret_key',
			'hcaptcha_theme'                       => 'theme',
			'hcaptcha_size'                        => 'size',
			'hcaptcha_language'                    => 'language',
			'hcaptcha_off_when_logged_in'          => [ 'off_when_logged_in', 'on' ],
			'hcaptcha_recaptchacompat'             => [ 'recaptcha_compat_off', 'on' ],
			'hcaptcha_cmf_status'                  => [ 'wp_status', 'comment' ],
			'hcaptcha_lf_status'                   => [ 'wp_status', 'login' ],
			'hcaptcha_lpf_status'                  => [ 'wp_status', 'lost_pass' ],
			'hcaptcha_rf_status'                   => [ 'wp_status', 'register' ],
			'hcaptcha_bbp_new_topic_status'        => [ 'bbp_status', 'new_topic' ],
			'hcaptcha_bbp_reply_status'            => [ 'bbp_status', 'reply' ],
			'hcaptcha_bp_create_group_status'      => [ 'bp_status', 'create_group' ],
			'hcaptcha_bp_reg_status'               => [ 'bp_status', 'registration' ],
			'hcaptcha_cf7_status'                  => [ 'cf7_status', 'form' ],
			'hcaptcha_divi_cmf_status'             => [ 'divi_status', 'comment' ],
			'hcaptcha_divi_cf_status'              => [ 'divi_status', 'contact' ],
			'hcaptcha_divi_lf_status'              => [ 'divi_status', 'login' ],
			'hcaptcha_elementor__pro_form_status'  => [ 'elementor_pro_status', 'form' ],
			'hcaptcha_fluentform_status'           => [ 'fluent_status', 'form' ],
			'hcaptcha_gravityform_status'          => [ 'gravity_status', 'form' ],
			'hcaptcha_jetpack_cf_status'           => [ 'jetpack_status', 'contact' ],
			'hcaptcha_mc4wp_status'                => [ 'mailchimp_status', 'form' ],
			'hcaptcha_memberpress_register_status' => [ 'memberpress_status', 'register' ],
			'hcaptcha_nf_status'                   => [ 'ninja_status', 'form' ],
			'hcaptcha_subscribers_status'          => [ 'subscriber_status', 'form' ],
			'hcaptcha_um_login_status'             => [ 'ultimate_member_status', 'login' ],
			'hcaptcha_um_lost_pass_status'         => [ 'ultimate_member_status', 'lost_pass' ],
			'hcaptcha_um_register_status'          => [ 'ultimate_member_status', 'register' ],
			'hcaptcha_wc_checkout_status'          => [ 'woocommerce_status', 'checkout' ],
			'hcaptcha_wc_login_status'             => [ 'woocommerce_status', 'login' ],
			'hcaptcha_wc_lost_pass_status'         => [ 'woocommerce_status', 'lost_pass' ],
			'hcaptcha_wc_order_tracking_status'    => [ 'woocommerce_status', 'order_tracking' ],
			'hcaptcha_wc_reg_status'               => [ 'woocommerce_status', 'register' ],
			'hcaptcha_wc_wl_create_list_status'    => [ 'woocommerce_wishlists_status', 'create_list' ],
			'hcaptcha_wpforms_status'              => [ 'wpforms_status', 'lite' ],
			'hcaptcha_wpforms_pro_status'          => [ 'wpforms_status', 'pro' ],
			'hcaptcha_wpforo_new_topic_status'     => [ 'wpforo_status', 'new_topic' ],
			'hcaptcha_wpforo_reply_status'         => [ 'wpforo_status', 'reply' ],
		];

		$new_options = [];

		foreach ( $options_map as $old_option_name => $new_option_name ) {
			$old_option = get_option( $old_option_name, '' );

			if ( ! is_array( $new_option_name ) ) {
				$new_options[ $new_option_name ] = $old_option;
				continue;
			}

			[ $new_option_key, $new_option_value ] = $new_option_name;

			$new_options[ $new_option_key ] = $new_options[ $new_option_key ] ?? [];

			if ( 'on' === $old_option ) {
				$new_options[ $new_option_key ][] = $new_option_value;
			}
		}

		update_option( PluginSettingsBase::OPTION_NAME, $new_options );

		foreach ( array_keys( $options_map ) as $old_option_name ) {
			delete_option( $old_option_name );
		}

		return true;
	}

	/**
	 * Migrate to 3.6.0
	 *
	 * @return bool|null
	 * @noinspection PhpUnused
	 */
	protected function migrate_360(): ?bool {
		$option         = get_option( PluginSettingsBase::OPTION_NAME, [] );
		$wpforms_status = $option['wpforms_status'] ?? [];

		if ( empty( $wpforms_status ) ) {
			return true;
		}

		// Convert any WPForms status ('lite' or 'pro') to the status 'form'.
		$option['wpforms_status'] = [ 'form' ];

		update_option( PluginSettingsBase::OPTION_NAME, $option );

		return true;
	}

	/**
	 * Migrate to 4.0.0
	 *
	 * @return bool|null
	 * @noinspection PhpUnused
	 */
	protected function migrate_4_0_0(): ?bool {
		Events::create_table();

		add_action( 'plugins_loaded', [ $this, 'save_license_level' ] );

		return true;
	}

	/**
	 * Save license level in settings.
	 *
	 * @return void
	 */
	public function save_license_level(): void {
		// Check the license level.
		$result = hcap_check_site_config();

		if ( $result['error'] ?? false ) {
			return;
		}

		$pro               = $result['features']['custom_theme'] ?? false;
		$license           = $pro ? 'pro' : 'free';
		$option            = get_option( PluginSettingsBase::OPTION_NAME, [] );
		$option['license'] = $license;

		// Save license level in settings.
		update_option( PluginSettingsBase::OPTION_NAME, $option );
	}
}
