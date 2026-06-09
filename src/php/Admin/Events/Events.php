<?php
/**
 * Events class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Admin\Events;

use Exception;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Migrations\Migrations;
use HCaptcha\Settings\General;
use HCaptcha\Settings\PluginSettingsBase;

/**
 * Class Events.
 */
class Events {

	/**
	 * Table name.
	 */
	public const TABLE_NAME = 'hcaptcha_events';

	/**
	 * Table created option name.
	 */
	public const TABLE_CREATED_OPTION_NAME = 'events_table_created';

	/**
	 * Served items limit.
	 */
	public const SERVED_LIMIT = 1000;

	/**
	 * Active event status.
	 */
	public const STATUS_ACTIVE = 'active';

	/**
	 * Trash event status.
	 */
	public const STATUS_TRASH = 'trash';

	/**
	 * Cleanup Trash action.
	 */
	public const CLEANUP_ACTION = 'hcap_cleanup_events_trash';

	/**
	 * Trash retention in days.
	 */
	public const TRASH_RETENTION_DAYS = 30;

	/**
	 * Verify request hook priority.
	 * Must be higher than \HCaptcha\AntiSpam\AntiSpam::VERIFY_REQUEST_PRIORITY
	 */
	public const VERIFY_REQUEST_PRIORITY = -1000;

	/**
	 * Saved flag.
	 *
	 * @var bool
	 */
	private bool $saved = false;

	/**
	 * Class constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		add_action( self::CLEANUP_ACTION, [ self::class, 'cleanup_trash' ] );

		if ( ! hcaptcha()->settings()->is_on( 'statistics' ) ) {
			return;
		}

		add_filter( 'hcap_verify_request', [ $this, 'save_event' ], self::VERIFY_REQUEST_PRIORITY, 3 );
	}

	/**
	 * Save event.
	 *
	 * @since 4.15.0 The `$error_codes` parameter was deprecated.
	 *
	 * @param string|null|mixed $result     The result of verification. The null means success.
	 * @param string[]          $deprecated Error code(s). Empty array on success.
	 * @param object            $error_info Error info. Contains error codes or empty array on success.
	 *
	 * @return string|null|mixed
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function save_event( $result, array $deprecated, object $error_info ) {
		global $wpdb;

		if ( $this->saved ) {
			return $result;
		}

		$this->saved = true;

		if ( ! ( is_string( $result ) || is_null( $result ) ) ) {
			return $result;
		}

		$settings   = hcaptcha()->settings();
		$ip         = '';
		$user_agent = '';
		$uuid       = '';

		if ( $settings->is_on( 'collect_ua' ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) : '';

			if ( $settings->is_on( 'anonymous' ) ) {
				$user_agent = wp_hash( $user_agent );
			}
		}

		if ( $settings->is_on( 'collect_ip' ) ) {
			$ip = (string) hcap_get_user_ip();

			if ( $settings->is_on( 'anonymous' ) ) {
				$ip = wp_hash( $ip );
			}
		}

		$info = HCaptcha::decode_id_info();

		if ( self::should_skip_event( $info ) ) {
			return $result;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$wpdb->prefix . self::TABLE_NAME,
			[
				'source'      => (string) wp_json_encode( $info['id']['source'] ),
				'form_id'     => sanitize_text_field( $info['id']['form_id'] ),
				'ip'          => $ip,
				'user_agent'  => $user_agent,
				'uuid'        => $uuid,
				'error_codes' => (string) wp_json_encode( $error_info->codes ?? [] ),
				'date_gmt'    => (string) gmdate( 'Y-m-d H:i:s' ),
			]
		);

		return $result;
	}

	/**
	 * Get events.
	 *
	 * @param array $args Arguments.
	 *
	 * @return array
	 */
	public static function get_events( array $args = [] ): array {
		global $wpdb;

		$args               = self::prepare_args( $args );
		$trash_schema_ready = self::is_trash_schema_ready();

		if ( ! self::table_exists() ) {
			return [
				'items' => [],
				'total' => 0,
			];
		}

		if ( ! $trash_schema_ready && self::STATUS_TRASH === $args['status'] ) {
			return [
				'items' => [],
				'total' => 0,
			];
		}

		$columns           = implode( ',', $args['columns'] );
		$columns           = $columns ?: '*';
		$table_name        = $wpdb->prefix . self::TABLE_NAME;
		$where             = self::get_where( $args );
		$where_date_nested = self::get_where_date_gmt_nested( $args );
		$orderby           = self::get_order_by( $args );
		$limit             = $args['limit'];

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$queries = [
			'START TRANSACTION',
			"SELECT COUNT(*)
				FROM $table_name
				WHERE $where",
			$wpdb->prepare(
				"SELECT $columns
						FROM $table_name
						WHERE $where_date_nested
						$orderby
						LIMIT %d",
				$limit
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			),
			'COMMIT',
		];

		$query_results = [];

		foreach ( $queries as $query ) {
			// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter
			$result          = $wpdb->query( $query );
			$query_results[] = $wpdb->last_result;

			if ( false === $result ) {
				$wpdb->query( 'ROLLBACK' );
				break;
			}
		}
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( false !== $result ) {
			$results = (array) $query_results[2];
			$total   = (int) $query_results[1][0]->{'COUNT(*)'};
		} else {
			$results = [];
			$total   = 0;
		}

		return [
			'items' => $results,
			'total' => $total,
		];
	}

	/**
	 * Get forms.
	 *
	 * @param array $args Arguments.
	 *
	 * @return array
	 */
	public static function get_forms( array $args = [] ): array {
		global $wpdb;

		$args               = self::prepare_args( $args );
		$trash_schema_ready = self::is_trash_schema_ready();

		if ( ! self::table_exists() ) {
			return [
				'items'  => [],
				'total'  => 0,
				'served' => [],
			];
		}

		if ( ! $trash_schema_ready && self::STATUS_TRASH === $args['status'] ) {
			return [
				'items'  => [],
				'total'  => 0,
				'served' => [],
			];
		}

		$table_name = $wpdb->prefix . self::TABLE_NAME;
		$where      = self::get_where( $args );
		$orderby    = self::get_order_by( $args );
		$offset     = $args['offset'];
		$limit      = $args['limit'];
		$force_key  = $trash_schema_ready ? 'status_date_gmt' : 'date_gmt';

		// We need to collect id also to distinguish rows on the Forms page.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$results = (array) $wpdb->get_results(
			$wpdb->prepare(
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT SQL_CALC_FOUND_ROWS MIN(id) AS id, source, form_id, COUNT(*) as served
						FROM $table_name
						WHERE $where
						GROUP BY source, form_id
						$orderby
						LIMIT %d, %d",
				$offset,
				$limit
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			)
		);

		$total = (int) $wpdb->get_var( 'SELECT FOUND_ROWS()' );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		$forms_where = '1=0';

		foreach ( $results as $result ) {
			$source  = esc_sql( $result->source );
			$form_id = esc_sql( $result->form_id );

			$forms_where .= " OR (source='$source' AND form_id='$form_id')";
		}

		$where        = "($forms_where) AND $where";
		$served_limit = self::SERVED_LIMIT;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$served = (array) $wpdb->get_results(
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			"SELECT date_gmt
					FROM $table_name FORCE INDEX ($force_key)
					WHERE $where
					ORDER BY date_gmt
					LIMIT $served_limit"
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return [
			'items'  => $results,
			'total'  => $total,
			'served' => $served,
		];
	}

	/**
	 * Get event counts grouped by status.
	 *
	 * @param array $args Arguments.
	 *
	 * @return array
	 */
	public static function get_status_counts( array $args = [] ): array {
		global $wpdb;

		$args = self::prepare_args( $args );

		$counts = [
			self::STATUS_ACTIVE => 0,
			self::STATUS_TRASH  => 0,
		];

		if ( ! self::table_exists() ) {
			return $counts;
		}

		$table_name = $wpdb->prefix . self::TABLE_NAME;
		$where_date = self::get_where_date_gmt( $args );

		if ( ! self::is_trash_schema_ready() ) {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$counts[ self::STATUS_ACTIVE ] = (int) $wpdb->get_var(
				"SELECT COUNT(*)
						FROM $table_name
						WHERE $where_date"
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

			return $counts;
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT status, COUNT(*) as total
						FROM $table_name
						WHERE $where_date
							AND status IN (%s, %s)
						GROUP BY status",
				self::STATUS_ACTIVE,
				self::STATUS_TRASH
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		foreach ( $results as $result ) {
			$counts[ $result->status ] = (int) $result->total;
		}

		return $counts;
	}

	/**
	 * Create the table.
	 *
	 * @param bool $force Whether to ignore the stored table-created marker.
	 *
	 * @return void
	 */
	public static function create_table( bool $force = false ): void {
		global $wpdb;

		if ( ! $force && self::table_exists() ) {
			return;
		}

		if ( self::database_table_exists() ) {
			self::mark_table_created();

			return;
		}

		if ( $force ) {
			self::unmark_table_created();
		}

		$table_name = self::TABLE_NAME;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $wpdb->prefix$table_name (
		    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		    source      VARCHAR(256)    NOT NULL,
		    form_id     VARCHAR(20)     NOT NULL,
		    ip          VARCHAR(39)     NOT NULL,
		    user_agent  VARCHAR(256)    NOT NULL,
		    uuid        VARCHAR(36)     NOT NULL,
		    error_codes VARCHAR(256)    NOT NULL,
		    date_gmt    DATETIME        NOT NULL,
		    status      VARCHAR(20)     NOT NULL DEFAULT 'active',
		    trashed_at_gmt DATETIME     NULL,
		    PRIMARY KEY (id),
		    KEY source (source),
		    KEY form_id (form_id),
		    KEY hcaptcha_id (source, form_id),
		    KEY ip (ip),
		    KEY uuid (uuid),
		    KEY date_gmt (date_gmt),
		    KEY status_date_gmt (status, date_gmt),
		    KEY status_source_form (status, source, form_id)
		) $charset_collate";

		dbDelta( $sql );

		if ( self::database_table_exists() ) {
			self::mark_table_created();
		}
	}

	/**
	 * Whether the Events table is marked as created.
	 *
	 * @return bool
	 */
	public static function table_exists(): bool {
		$settings = hcaptcha()->settings();
		$raw      = $settings ? $settings->get_raw_settings() : get_option( PluginSettingsBase::OPTION_NAME, [] );
		$raw      = is_array( $raw ) ? $raw : [];

		return 'on' === ( $raw[ self::TABLE_CREATED_OPTION_NAME ] ?? '' );
	}

	/**
	 * Whether the Events table exists in the database.
	 *
	 * @return bool
	 */
	private static function database_table_exists(): bool {
		global $wpdb;

		$table_name = $wpdb->esc_like( $wpdb->prefix . self::TABLE_NAME );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$tables = $wpdb->get_results(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ),
			'ARRAY_N'
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return ! empty( $tables );
	}

	/**
	 * Mark the Events table as created.
	 *
	 * @return void
	 */
	private static function mark_table_created(): void {
		$option = get_option( PluginSettingsBase::OPTION_NAME, [] );
		$option = is_array( $option ) ? $option : [];

		$option[ self::TABLE_CREATED_OPTION_NAME ] = 'on';

		update_option( PluginSettingsBase::OPTION_NAME, $option );
	}

	/**
	 * Unmark the Events table as created.
	 *
	 * @return void
	 */
	private static function unmark_table_created(): void {
		$option = get_option( PluginSettingsBase::OPTION_NAME, [] );

		if ( ! is_array( $option ) || ! array_key_exists( self::TABLE_CREATED_OPTION_NAME, $option ) ) {
			return;
		}

		unset( $option[ self::TABLE_CREATED_OPTION_NAME ] );

		update_option( PluginSettingsBase::OPTION_NAME, $option );
	}

	/**
	 * Whether an event should be skipped.
	 *
	 * @param array $info Decoded hCaptcha ID info.
	 *
	 * @return bool
	 */
	private static function should_skip_event( array $info ): bool {
		return (
			(
				[ General::class ] === $info['id']['source'] &&
				General::CHECK_CONFIG_FORM_ID === $info['id']['form_id']
			) ||
			! self::table_exists()
		);
	}

	/**
	 * Cleanup trashed events older than the retention window.
	 *
	 * @return void
	 */
	public static function cleanup_trash(): void {
		global $wpdb;

		$settings = hcaptcha()->settings();

		if (
			! $settings ||
			! $settings->is_on( 'statistics' ) ||
			! self::table_exists() ||
			! self::is_trash_schema_ready()
		) {
			return;
		}

		$table_name = $wpdb->prefix . self::TABLE_NAME;
		$date_gmt   = gmdate(
			'Y-m-d H:i:s',
			time() - self::TRASH_RETENTION_DAYS * constant( 'DAY_IN_SECONDS' )
		);

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM $table_name
					WHERE status = %s
						AND trashed_at_gmt IS NOT NULL
						AND trashed_at_gmt < %s",
				self::STATUS_TRASH,
				$date_gmt
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Get where date GMT.
	 *
	 * @param array $args Arguments.
	 *
	 * @return string
	 */
	public static function get_where_date_gmt( array $args ): string {
		$dates = $args['dates'];

		if ( ! $dates ) {
			return '1=1';
		}

		$dates = self::prepare_gmt_dates( $dates );

		return sprintf(
			"date_gmt BETWEEN '%s' AND '%s'",
			esc_sql( $dates[0] ),
			esc_sql( $dates[1] )
		);
	}

	/**
	 * Get where date GMT with a nested request to optimize.
	 *
	 * @param array $args Arguments.
	 *
	 * @return string
	 */
	public static function get_where_date_gmt_nested( array $args ): string {
		global $wpdb;

		$dates = $args['dates'];

		if ( ! $dates ) {
			return '1=1';
		}

		$dates               = self::prepare_gmt_dates( $dates );
		$table_name          = $wpdb->prefix . self::TABLE_NAME;
		$order               = $args['order'];
		$offset              = $args['offset'];
		$compare             = 'DESC' === $order ? '<=' : '>=';
		$trash_schema_ready  = self::is_trash_schema_ready();
		$status_outer_where  = '';
		$status_nested_where = '';

		if ( $trash_schema_ready ) {
			$status              = esc_sql( $args['status'] );
			$status_outer_where  = "AND status = '$status'";
			$status_nested_where = "AND status = '$status'";
		}

		return sprintf(
			"date_gmt BETWEEN '%s' AND '%s'
					%s
					AND date_gmt %s (
						SELECT date_gmt
						FROM %s
						WHERE date_gmt BETWEEN '%s' AND '%s'
							%s
						ORDER BY date_gmt %s
						LIMIT %d, 1
					)
					",
			esc_sql( $dates[0] ),
			esc_sql( $dates[1] ),
			$status_outer_where,
			$compare,
			$table_name,
			esc_sql( $dates[0] ),
			esc_sql( $dates[1] ),
			$status_nested_where,
			$order,
			$offset
		);
	}

	/**
	 * Get WHERE clause.
	 *
	 * @param array $args Arguments.
	 *
	 * @return string
	 */
	private static function get_where( array $args ): string {
		if ( ! self::is_trash_schema_ready() ) {
			return self::get_where_date_gmt( $args );
		}

		return self::get_where_date_gmt( $args ) . ' AND ' . self::get_where_status( $args );
	}

	/**
	 * Whether the Events table has the Trash Folder schema.
	 *
	 * @return bool
	 */
	public static function is_trash_schema_ready(): bool {
		$migrated_versions = (array) get_option( Migrations::MIGRATED_VERSIONS_OPTION_NAME, [] );

		return 0 <= (int) ( $migrated_versions['5.0.0'] ?? 0 );
	}

	/**
	 * Get the WHERE status clause.
	 *
	 * @param array $args Arguments.
	 *
	 * @return string
	 */
	private static function get_where_status( array $args ): string {
		return sprintf( "status = '%s'", esc_sql( $args['status'] ) );
	}

	/**
	 * Prepare dates.
	 *
	 * @param array $dates Dates.
	 *
	 * @return array
	 */
	public static function prepare_gmt_dates( array $dates ): array {
		$dates[1] = $dates[1] ?? $dates[0];

		$dates[0] .= ' 00:00:00';
		$dates[1] .= ' 23:59:59';

		foreach ( $dates as &$date ) {
			$date = get_gmt_from_date( $date );
		}

		unset( $date );

		return $dates;
	}

	/**
	 * Get ORDER BY / ORDER clause
	 *
	 * @param array $args Arguments.
	 *
	 * @return string
	 */
	private static function get_order_by( array $args ): string {
		$orderby = $args['orderby'];
		$order   = $args['order'];
		$order   = 'ASC' === $order ? '' : $order;

		return $orderby ? 'ORDER BY ' . $orderby . ' ' . $order : '';
	}

	/**
	 * Get default dates.
	 *
	 * @return array
	 */
	public static function get_default_dates(): array {
		$end_date   = date_create_immutable( 'now', wp_timezone() );
		$start_date = $end_date;

		try {
			$start_date = $start_date->modify( '-30 day' );
		} catch ( Exception $e ) {
			$start_date = $end_date;
		}

		$start_date = $start_date->setTime( 0, 0 );
		$end_date   = $end_date->setTime( 23, 59, 59 );
		$format     = 'Y-m-d';

		return [ $start_date->format( $format ), $end_date->format( $format ) ];
	}

	/**
	 * Prepare arguments.
	 *
	 * @param array $args Arguments.
	 *
	 * @return array
	 */
	private static function prepare_args( array $args ): array {
		$args = wp_parse_args(
			$args,
			[
				'columns' => [],
				'offset'  => 0,
				'limit'   => 20,
				'order'   => 'ASC',
				'orderby' => '',
				'dates'   => [],
				'status'  => self::STATUS_ACTIVE,
			]
		);

		$args['columns'] = array_map( 'strtolower', $args['columns'] );
		$args['offset']  = absint( $args['offset'] );
		$args['limit']   = max( 1, absint( $args['limit'] ) );
		$order           = strtoupper( $args['order'] );
		$args['order']   = in_array( $order, [ 'ASC', 'DESC' ], true ) ? $order : 'ASC';
		$orderby         = strtolower( $args['orderby'] );
		$args['orderby'] = in_array( $orderby, $args['columns'], true ) ? $orderby : '';
		$dates           = (array) $args['dates'];
		$args['dates']   = $dates ?: self::get_default_dates();
		$status          = sanitize_key( $args['status'] );
		$args['status']  = in_array( $status, [ self::STATUS_ACTIVE, self::STATUS_TRASH ], true )
			? $status
			: self::STATUS_ACTIVE;

		return $args;
	}
}
