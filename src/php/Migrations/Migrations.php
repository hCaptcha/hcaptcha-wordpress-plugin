<?php
/**
 * Migration class file.
 *
 * @package hcaptcha-wp
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */

namespace HCaptcha\Migrations;

use ActionScheduler;
use ActionScheduler_Store;
use HCaptcha\Admin\Events\Events;
use HCaptcha\Helpers\HCaptcha;
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
	 * Migration completed status.
	 */
	public const COMPLETED = - 3;

	/**
	 * Priority of the plugins_loaded action to load Migrations.
	 */
	public const LOAD_PRIORITY = -PHP_INT_MAX;

	/**
	 * Plugin name.
	 */
	private const PLUGIN_NAME = 'hCaptcha Plugin';

	/**
	 * Action Scheduler group name.
	 */
	private const AS_GROUP = 'hcaptcha';

	/**
	 * Review trusted address headers option name.
	 */
	public const REVIEW_TRUSTED_ADDRESS_HEADERS_OPTION = 'review_trusted_address_headers';

	/**
	 * Whether a table check was done during the current request.
	 *
	 * @var bool
	 */
	private bool $tables_check_done = false;

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
	private function init(): void {
		if ( ! $this->is_allowed() ) {
			return;
		}

		$this->maybe_prepare_migration_option();
		$this->maybe_create_tables();
		$this->init_hooks();
	}

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		add_action( 'plugins_loaded', [ $this, 'migrate' ], self::LOAD_PRIORITY );
		add_action( 'plugins_loaded', [ $this, 'load_action_scheduler' ], -10 );

		add_action( 'async_migrate_4_11_0', [ $this, 'async_migrate_4_11_0' ] );
		add_action( 'async_migrate_5_0_0', [ $this, 'async_migrate_5_0_0' ] );
	}

	/**
	 * Load action scheduler.
	 *
	 * @return void
	 */
	public function load_action_scheduler(): void {
		require_once HCAPTCHA_PATH . '/vendor/woocommerce/action-scheduler/action-scheduler.php';
	}

	/**
	 * Migrate.
	 *
	 * @return void
	 */
	public function migrate(): void {
		$migrated             = (array) get_option( self::MIGRATED_VERSIONS_OPTION_NAME, [] );
		$plugin_major_version = explode( '-', self::PLUGIN_VERSION )[0];

		$this->check_plugin_update( $migrated, $plugin_major_version );

		$upgrade_versions = [];

		foreach ( $this->get_migrations() as $migration ) {
			$upgrade_version = $this->get_upgrade_version( $migration );

			if ( ! $upgrade_version ) {
				continue;
			}

			$upgrade_versions[] = $upgrade_version;

			if (
				( isset( $migrated[ $upgrade_version ] ) && $migrated[ $upgrade_version ] >= 0 ) ||
				version_compare( $upgrade_version, $plugin_major_version, '>' )
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

		// Set the current version update time if it does not exist.
		$current_version_migrated = $migrated[ $plugin_major_version ] ?? time();

		// Remove any keys that are not in the migration list.
		$migrated = array_intersect_key( $migrated, array_flip( $upgrade_versions ) );

		// Restore the current version update time.
		// Prevents updating the option on each request.
		$migrated[ $plugin_major_version ] = $current_version_migrated;

		// Sort the array by version.
		uksort( $migrated, 'version_compare' );

		update_option( self::MIGRATED_VERSIONS_OPTION_NAME, $migrated );
	}

	/**
	 * Determine if migration is allowed.
	 */
	private function is_allowed(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['service-worker'] ) ) {
			return false;
		}

		return (
			is_admin() ||
			wp_doing_cron() ||
			( defined( 'WP_CLI' ) && constant( 'WP_CLI' ) )
		);
	}

	/**
	 * Maybe prepare the migration option.
	 */
	private function maybe_prepare_migration_option(): void {
		$migrated = get_option( self::MIGRATED_VERSIONS_OPTION_NAME );

		// If the option is an array, it means that it is already prepared.
		if ( is_array( $migrated ) ) {
			return;
		}

		$migrated = [];

		foreach ( $this->get_migrations() as $migration ) {
			$upgrade_version = $this->get_upgrade_version( $migration );

			if ( ! $upgrade_version ) {
				continue;
			}

			// Mark migration as done.
			$migrated[ $upgrade_version ] = 0;
		}

		// Sort the array by version.
		uksort( $migrated, 'version_compare' );

		update_option( self::MIGRATED_VERSIONS_OPTION_NAME, $migrated );
	}

	/**
	 * Maybe create custom database tables.
	 *
	 * @return void
	 */
	private function maybe_create_tables(): void {
		if ( $this->tables_check_done ) {
			return;
		}

		Events::create_table();

		$this->tables_check_done = true;
	}

	/**
	 * Send plugin statistics.
	 *
	 * @return void
	 */
	public function send_plugin_stats(): void {
		/**
		 * Statistics switch is turned on, send plugin statistics.
		 *
		 * @param bool $force_send Whether to force sending stats. Default is false.
		 */
		do_action( 'hcap_send_plugin_stats' );
	}

	/**
	 * Check if the plugin was updated.
	 *
	 * @param array  $migrated             Migrated versions.
	 * @param string $plugin_major_version Plugin version without pre-release suffix.
	 *
	 * @return void
	 */
	private function check_plugin_update( array $migrated, string $plugin_major_version ): void {
		if ( isset( $migrated[ $plugin_major_version ] ) ) {
			return;
		}

		// Send statistics on plugin update.
		add_action( 'init', [ $this, 'send_plugin_stats' ] );
	}

	/**
	 * Get an upgrade version from the method name.
	 *
	 * @param string $method Method name.
	 *
	 * @return string
	 */
	private function get_upgrade_version( string $method ): string {
		// Find only the digits and underscores to get the version number.
		if ( ! preg_match( '/(\d_?)+/', $method, $matches ) ) {
			// @codeCoverageIgnoreStart
			return '';
			// @codeCoverageIgnoreEnd
		}

		$raw_version = $matches[0];

		if ( strpos( $raw_version, '_' ) ) {
			// Modern notation: 3_10_0 means the 3.10.0 version.

			// @codeCoverageIgnoreStart
			return str_replace( '_', '.', $raw_version );
			// @codeCoverageIgnoreEnd
		}

		// Legacy notation, with 1-digit subversion numbers: 360 means 3.6.0 version.
		return implode( '.', str_split( $raw_version ) );
	}

	/**
	 * Output the message into the log file.
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

		// These two lines is a precaution for a case if options in a new format already exist.
		$options = get_option( PluginSettingsBase::OPTION_NAME, [] );
		$options = array_merge( $new_options, $options );

		update_option( PluginSettingsBase::OPTION_NAME, $options );

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

		add_action( 'plugins_loaded', [ HCaptcha::class, 'save_license_level' ] );

		return true;
	}

	/**
	 * Migrate to 4.6.0
	 *
	 * @return bool|null
	 * @noinspection PhpUnused
	 */
	protected function migrate_4_6_0(): ?bool {
		$option         = get_option( PluginSettingsBase::OPTION_NAME, [] );
		$cf7_status_old = (array) ( $option['cf7_status'] ?? [] );
		$cf7_status_new = array_unique( array_merge( $cf7_status_old, [ 'live' ] ) );

		if ( $cf7_status_new !== $cf7_status_old ) {
			// Turn on CF7 Live Form in admin by default.
			$option['cf7_status'] = $cf7_status_new;

			update_option( PluginSettingsBase::OPTION_NAME, $option );
		}

		return true;
	}

	/**
	 * Migrate to 4.11.0
	 *
	 * @return bool|null
	 * @noinspection PhpUnused
	 */
	protected function migrate_4_11_0(): ?bool {
		return $this->run_async( __FUNCTION__ );
	}

	/**
	 * Async migration to 4.11.0.
	 *
	 * @return void
	 */
	public function async_migrate_4_11_0(): void {
		global $wpdb;

		$table_name = $wpdb->prefix . Events::TABLE_NAME;

		$this->add_index( $table_name, 'idx_date_source_form', 'date_gmt, source, form_id' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP INDEX hcaptcha_id on $table_name" );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$this->mark_completed();
	}

	/**
	 * Migrate to 5.0.0
	 *
	 * @return bool|null
	 * @noinspection PhpUnused
	 */
	protected function migrate_5_0_0(): ?bool {
		return $this->run_async( __FUNCTION__ );
	}

	/**
	 * Async migration to 5.0.0.
	 *
	 * @return void
	 */
	public function async_migrate_5_0_0(): void {
		$this->add_trusted_address_headers();
		$this->add_events_trash_folder();

		$this->mark_completed();
	}

	/**
	 * Run async action.
	 *
	 * @param string $method Method name.
	 * @param array  $args   Arguments.
	 *
	 * @return bool|null
	 * @noinspection PhpSameParameterValueInspection
	 */
	private function run_async( string $method, array $args = [] ): ?bool {
		$hook      = 'async_' . $method;
		$group     = self::AS_GROUP;
		$transient = $group . '_' . $hook;

		$status = (int) get_transient( $transient );

		if ( self::COMPLETED === $status ) {
			delete_transient( $transient );

			return true;
		}

		if ( ! $status ) {
			set_transient( $transient, self::STARTED );
		}

		add_action(
			'init',
			function () use ( $hook, $args, $group ) {
				$transient = $group . '_' . $hook;
				$status    = $this->create_as_action( $hook, $args, $group );

				if ( self::FAILED === $status ) {
					set_transient( $transient, $status );
				}
			},
			20
		);

		return null;
	}

	/**
	 * Add the trusted address headers option.
	 *
	 * @return void
	 */
	private function add_trusted_address_headers(): void {
		$option = get_option( PluginSettingsBase::OPTION_NAME, [] );

		if ( ! is_array( $option ) || array_key_exists( 'trusted_address_headers', $option ) ) {
			return;
		}

		$address_headers          = hcap_get_address_headers();
		$filtered_address_headers = [];

		if ( has_filter( 'hcap_trusted_address_headers' ) ) {
			// Migration has no reliable visitor request context, so do not use the current REMOTE_ADDR.
			$filtered_address_headers = hcap_filter_address_headers( $address_headers, '', $address_headers );
		}

		$option['trusted_address_headers'] = array_values(
			array_intersect( $filtered_address_headers, $address_headers )
		);

		if ( ! $option['trusted_address_headers'] ) {
			$option[ self::REVIEW_TRUSTED_ADDRESS_HEADERS_OPTION ] = 'on';
		}

		update_option( PluginSettingsBase::OPTION_NAME, $option );
	}

	/**
	 * Add support for the Trash Folder.
	 *
	 * @return void
	 */
	private function add_events_trash_folder(): void {
		global $wpdb;

		$table_name = $wpdb->prefix . Events::TABLE_NAME;

		Events::create_table();

		if ( ! Events::table_exists() ) {
			return;
		}

		$this->add_column(
			$table_name,
			'status',
			"VARCHAR(20) NOT NULL DEFAULT '" . Events::STATUS_ACTIVE . "' AFTER date_gmt"
		);
		$this->add_column(
			$table_name,
			'trashed_at_gmt',
			'DATETIME NULL AFTER status'
		);

		$this->add_index( $table_name, 'status_date_gmt', 'status, date_gmt' );
		$this->add_index( $table_name, 'status_source_form', 'status, source, form_id' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE $table_name SET status = %s WHERE status = ''",
				Events::STATUS_ACTIVE
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Create an AS action.
	 *
	 * @param string $hook  Hook name.
	 * @param array  $args  Hook arguments.
	 * @param string $group Group name.
	 *
	 * @return int Started or failed.
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	private function create_as_action( string $hook, array $args, string $group ): int {
		$actions = as_get_scheduled_actions(
			[
				'hook'   => $hook,
				'args'   => $args,
				'group'  => $group,
				'status' => [ // All statuses except completed.
					ActionScheduler_Store::STATUS_PENDING,
					ActionScheduler_Store::STATUS_RUNNING,
					ActionScheduler_Store::STATUS_FAILED,
					ActionScheduler_Store::STATUS_CANCELED,
				],
			]
		);

		if ( empty( $actions ) ) {
			// Plan the unique action.
			$action_id = as_enqueue_async_action( $hook, $args, $group, true );

			return $action_id ? self::STARTED : self::FAILED;
		}

		// Get the last action status.
		$last_action_id = max( array_map( 'intval', array_keys( $actions ) ) );
		$store          = ActionScheduler::store();
		$status         = $store ? $store->get_status( $last_action_id ) : ActionScheduler_Store::STATUS_FAILED;

		$started = in_array(
			$status,
			[
				ActionScheduler_Store::STATUS_PENDING,
				ActionScheduler_Store::STATUS_RUNNING,
			],
			true
		);

		return $started ? self::STARTED : self::FAILED;
	}

	/**
	 * Mark async migration as completed.
	 *
	 * @return void
	 */
	private function mark_completed(): void {
		$hook      = current_action();
		$group     = self::AS_GROUP;
		$transient = $group . '_' . $hook;

		set_transient( $transient, self::COMPLETED );
	}

	/**
	 * Add an index to a table.
	 *
	 * @param string $table_name Table.
	 * @param string $index_name Index name.
	 * @param string $key_part   Key part.
	 *
	 * @return void
	 * @noinspection PhpSameParameterValueInspection
	 */
	private function add_index( string $table_name, string $index_name, string $key_part ): void {
		global $wpdb;

		// Check id index already exists.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$result = $wpdb->get_var(
			"SELECT COUNT(1) IndexIsThere
					FROM INFORMATION_SCHEMA.STATISTICS
					WHERE table_schema = DATABASE()
      					AND table_name = '$table_name'
          				AND index_name = '$index_name'"
		);

		if ( '0' !== $result ) {
			return;
		}

		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.NoCaching

		// Change the column length for the wp_wpforms_entry_meta.type column to 255 and add an index.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$wpdb->query( "CREATE INDEX $index_name ON $table_name ( $key_part )" );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
	}

	/**
	 * Add a column to a table if it does not exist.
	 *
	 * @param string $table_name Table name.
	 * @param string $column     Column name.
	 * @param string $definition Column definition.
	 *
	 * @return void
	 */
	private function add_column( string $table_name, string $column, string $definition ): void {
		global $wpdb;

		if ( $this->column_exists( $table_name, $column ) ) {
			return;
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter
		$wpdb->query( "ALTER TABLE $table_name ADD COLUMN $column $definition" );
		// phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange
	}

	/**
	 * Check if a column exists.
	 *
	 * @param string $table_name Table name.
	 * @param string $column     Column name.
	 *
	 * @return bool
	 */
	private function column_exists( string $table_name, string $column ): bool {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$columns = $wpdb->get_results(
			$wpdb->prepare( "SHOW COLUMNS FROM $table_name LIKE %s", $column ),
			'ARRAY_N'
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter

		return ! empty( $columns );
	}

	/**
	 * Get migrations.
	 *
	 * @return string[]
	 */
	private function get_migrations(): array {
		return array_filter(
			get_class_methods( $this ),
			static function ( $migration ) {
				return 0 === strpos( $migration, 'migrate_' );
			}
		);
	}
}
