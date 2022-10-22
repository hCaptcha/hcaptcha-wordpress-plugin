<?php
/**
 * Migrations class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Migrations;

/**
 * Migrations class.
 */
class Migrations {

	/**
	 * Migrated versions options name.
	 */
	const MIGRATED_VERSIONS_OPTION_NAME = 'hcaptcha_versions';

	/**
	 * Plugin version.
	 */
	const PLUGIN_VERSION = HCAPTCHA_VERSION;

	/**
	 * Migration started status.
	 */
	const STARTED = - 1;

	/**
	 * Migration failed status.
	 */
	const FAILED = - 2;

	/**
	 * Plugin name.
	 */
	const PLUGIN_NAME = 'hCaptcha Plugin';

	/**
	 * Migration constructor.
	 */
	public function __construct() {
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
	private function init_hooks() {
		add_action( 'plugins_loaded', [ $this, 'migrate' ], - PHP_INT_MAX );
	}

	/**
	 * Migrate.
	 *
	 * @return void
	 */
	public function migrate() {
		$migrated = get_option( self::MIGRATED_VERSIONS_OPTION_NAME, [] );

		$migrations = array_filter(
			get_class_methods( $this ),
			static function ( $migration ) {
				return false !== strpos( $migration, 'migrate_' );
			}
		);

		foreach ( $migrations as $migration ) {
			$upgrade_version = $this->get_upgrade_version( $migration );

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
				continue;
			}

			$migrated[ $upgrade_version ] = $result ? time() : static::FAILED;

			$message = $result ?
				sprintf( 'Migration of %1$s to %2$s completed.', self::PLUGIN_NAME, $upgrade_version ) :
				sprintf( 'Migration of %1$s to %2$s failed.', self::PLUGIN_NAME, $upgrade_version );

			$this->log( $message );
		}

		update_option( self::MIGRATED_VERSIONS_OPTION_NAME, $migrated );
	}

	/**
	 * Determine if migration is allowed.
	 */
	private function is_allowed() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['service-worker'] ) ) {
			return false;
		}

		return ( defined( 'DOING_CRON' ) && DOING_CRON ) || is_admin();
	}

	/**
	 * Get upgrade version from the method name.
	 *
	 * @param string $method Method name.
	 *
	 * @return string
	 */
	private function get_upgrade_version( $method ) {
		// Find only the digits to get version number.
		if ( ! preg_match( '/\d+/', $method, $matches ) ) {
			return '';
		}

		return implode( '.', str_split( $matches[0] ) );
	}

	/**
	 * Output message into log file.
	 *
	 * @param string $message Message to log.
	 * @param mixed  $item    Item.
	 *
	 * @return void
	 * @noinspection ForgottenDebugOutputInspection
	 * @noinspection PhpSameParameterValueInspection
	 */
	private function log( $message, $item = null ) {
		if ( ! ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ) {
			return;
		}

		if ( null !== $item ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
			$message .= ' ' . print_r( $item, true );
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( self::PLUGIN_NAME . ':  ' . $message );
	}

	/**
	 * Migrate to 2.0.0
	 *
	 * @return bool|null
	 * @noinspection PhpUnusedPrivateMethodInspection
	 * @noinspection MultiAssignmentUsageInspection
	 */
	private function migrate_200() {
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

			list( $new_option_key, $new_option_value ) = $new_option_name;

			$new_options[ $new_option_key ] = isset( $new_options[ $new_option_key ] ) ?
				$new_options[ $new_option_key ] :
				[];

			if ( 'on' === $old_option ) {
				$new_options[ $new_option_key ][] = $new_option_value;
			}
		}

		update_option( 'hcaptcha_settings', $new_options );

		foreach ( array_keys( $options_map ) as $old_option_name ) {
			delete_option( $old_option_name );
		}

		return true;
	}
}
