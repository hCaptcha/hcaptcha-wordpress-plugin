<?php
/**
 * Events class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Admin\Events;

use Exception;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Settings\General;

/**
 * Class Events.
 */
class Events {

	/**
	 * Table name.
	 */
	public const TABLE_NAME = 'hcaptcha_events';

	/**
	 * Saved flag.
	 *
	 * @var bool
	 */
	private $saved;

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
		if ( ! hcaptcha()->settings()->is_on( 'statistics' ) ) {
			return;
		}

		add_filter( 'hcap_verify_request', [ $this, 'save_event' ], -PHP_INT_MAX, 2 );
	}

	/**
	 * Save event.
	 *
	 * @param string|null|mixed $result      The hCaptcha verification result.
	 * @param array             $error_codes Error codes.
	 *
	 * @return string|null|mixed
	 */
	public function save_event( $result, array $error_codes ) {
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

		if (
			[ General::class ] === $info['id']['source'] &&
			General::CHECK_CONFIG_FORM_ID === $info['id']['form_id']
		) {
			// Do not store events from the check config form.
			return $result;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$wpdb->prefix . self::TABLE_NAME,
			[
				'source'      => (string) wp_json_encode( $info['id']['source'] ),
				'form_id'     => $info['id']['form_id'],
				'ip'          => $ip,
				'user_agent'  => $user_agent,
				'uuid'        => $uuid,
				'error_codes' => (string) wp_json_encode( $error_codes ),
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

		$args          = wp_parse_args(
			$args,
			[
				'columns' => [],
				'offset'  => 0,
				'limit'   => 20,
				'order'   => 'ASC',
				'orderby' => '',
				'dates'   => [],
			]
		);
		$args['dates'] = $args['dates'] ?: self::get_default_dates();

		$columns           = implode( ',', $args['columns'] );
		$columns           = $columns ?: '*';
		$table_name        = $wpdb->prefix . self::TABLE_NAME;
		$where_date        = self::get_where_date_gmt( $args );
		$where_date_nested = self::get_where_date_gmt_nested( $args );
		$orderby           = self::get_order_by( $args );
		$limit             = absint( $args['limit'] );

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$queries = [
			'START TRANSACTION',
			"SELECT COUNT(*)
				FROM $table_name
				WHERE $where_date",
			$wpdb->prepare(
				"SELECT
    					$columns
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

		$args          = wp_parse_args(
			$args,
			[
				'offset'  => 0,
				'limit'   => 20,
				'order'   => 'ASC',
				'orderby' => '',
				'dates'   => [],
			]
		);
		$args['dates'] = $args['dates'] ?: self::get_default_dates();

		$table_name = $wpdb->prefix . self::TABLE_NAME;
		$where_date = self::get_where_date_gmt( $args );
		$orderby    = self::get_order_by( $args );
		$offset     = absint( $args['offset'] );
		$limit      = absint( $args['limit'] );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = (array) $wpdb->get_results(
			$wpdb->prepare(
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT
						SQL_CALC_FOUND_ROWS
    					id, source, form_id, COUNT(*) as served
						FROM $table_name
						WHERE $where_date
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

		$where = '1=0';

		foreach ( $results as $result ) {
			$source  = esc_sql( $result->source );
			$form_id = esc_sql( $result->form_id );

			$where .= " OR (source='$source' AND form_id='$form_id')";
		}

		$where = "($where) AND " . $where_date;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$served = (array) $wpdb->get_results(
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"SELECT date_gmt FROM $table_name WHERE $where"
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
	 * Create table.
	 *
	 * @return void
	 */
	public static function create_table(): void {
		global $wpdb;

		$table_name = self::TABLE_NAME;

		if ( self::table_exists( $wpdb->prefix . $table_name ) ) {
			// @codeCoverageIgnoreStart
			return;
			// @codeCoverageIgnoreEnd
		}

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
		    PRIMARY KEY (id),
		    KEY source (source),
		    KEY form_id (form_id),
		    KEY hcaptcha_id (source, form_id),
		    KEY ip (ip),
		    KEY uuid (uuid),
		    KEY date_gmt (date_gmt)
		) $charset_collate";

		dbDelta( $sql );
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

		if ( $dates ) {
			$dates[1] = $dates[1] ?? $dates[0];

			$dates[0] .= ' 00:00:00';
			$dates[1] .= ' 23:59:59';

			foreach ( $dates as &$date ) {
				$date = wp_date( 'Y-m-d H:i:s', strtotime( $date ) );
			}

			unset( $date );

			$where_date = sprintf(
				"date_gmt BETWEEN '%s' AND '%s'",
				esc_sql( $dates[0] ),
				esc_sql( $dates[1] )
			);
		} else {
			$where_date = '1=1';
		}

		return $where_date;
	}

	/**
	 * Get where date GMT with nested request to optimize.
	 *
	 * @param array $args Arguments.
	 *
	 * @return string
	 */
	public static function get_where_date_gmt_nested( array $args ): string {
		global $wpdb;

		$dates = $args['dates'];

		if ( $dates ) {
			$dates[1] = $dates[1] ?? $dates[0];

			$dates[0] .= ' 00:00:00';
			$dates[1] .= ' 23:59:59';

			foreach ( $dates as &$date ) {
				$date = wp_date( 'Y-m-d H:i:s', strtotime( $date ) );
			}

			unset( $date );

			$table_name = $wpdb->prefix . self::TABLE_NAME;
			$offset     = absint( $args['offset'] );

			$where_date = sprintf(
				"date_gmt BETWEEN '%s' AND '%s'
						AND date_gmt <= (
							SELECT date_gmt
							FROM %s
							WHERE date_gmt BETWEEN '%s' AND '%s'
							ORDER BY date_gmt DESC
							LIMIT %d, 1
						)
						",
				esc_sql( $dates[0] ),
				esc_sql( $dates[1] ),
				$table_name,
				esc_sql( $dates[0] ),
				esc_sql( $dates[1] ),
				$offset
			);
		} else {
			$where_date = '1=1';
		}

		return $where_date;
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
		$orderby = 'name' === $orderby ? 'source' : $orderby;
		$order   = strtoupper( $args['order'] );
		$order   = 'ASC' === $order ? '' : $order;

		return $orderby ? 'ORDER BY ' . $orderby . ' ' . $order : '';
	}

	/**
	 * Get default dates.
	 *
	 * @return array
	 */
	private static function get_default_dates(): array {
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
	 * Check if the database table exists and cache the result.
	 *
	 * @param string $table_name Table name. Can have SQL wildcard.
	 *
	 * @return bool
	 */
	private static function table_exists( string $table_name ): bool {
		foreach ( self::get_existing_tables( $table_name ) as $existing_table ) {
			if ( self::wildcard_match( $table_name, $existing_table ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get the list of existing tables and cache the result.
	 *
	 * @param string $table_name Table name. Can have SQL wildcard.
	 *
	 * @return array List of table names.
	 */
	private static function get_existing_tables( string $table_name ): array {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$tables = $wpdb->get_results(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ),
			'ARRAY_N'
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery

		return ! empty( $tables ) ? wp_list_pluck( $tables, 0 ) : [];
	}

	/**
	 * Wildcard match.
	 * Works as MySQL LIKE match.
	 *
	 * @param string $pattern Pattern.
	 * @param string $subject String to search into.
	 *
	 * @return false|int
	 */
	private static function wildcard_match( string $pattern, string $subject ) {
		$regex = str_replace(
			[ '%', '_' ], // MySQL wildcard chars.
			[ '.*', '.' ],  // Regexp chars.
			preg_quote( $pattern, '/' )
		);

		return preg_match( '/^' . $regex . '$/is', $subject );
	}
}
