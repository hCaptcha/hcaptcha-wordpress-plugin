<?php
/**
 * Events class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Admin\Events;

use HCaptcha\Helpers\HCaptcha;

/**
 * Class Events.
 */
class Events {

	/**
	 * Table name.
	 */
	const TABLE_NAME = 'hcaptcha_events';

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
	private function init_hooks() {
		if ( ! hcaptcha()->settings()->is_on( 'statistics' ) ) {
			return;
		}

		add_action( 'hcap_verify_request', [ $this, 'save_event' ], -PHP_INT_MAX, 2 );
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
		}

		if ( $settings->is_on( 'collect_ip' ) ) {
			$ip = (string) hcap_get_user_ip();
		}

		$info = HCaptcha::decode_id_info();

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

		$args = wp_parse_args(
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

		$columns    = implode( ',', $args['columns'] );
		$columns    = $columns ?: '*';
		$table_name = $wpdb->prefix . self::TABLE_NAME;
		$where_date = self::get_where_date( $args );
		$orderby    = self::get_order_by( $args );
		$offset     = absint( $args['offset'] );
		$limit      = absint( $args['limit'] );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = (array) $wpdb->get_results(
			$wpdb->prepare(
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT
						SQL_CALC_FOUND_ROWS
    					$columns
						FROM $table_name
						WHERE $where_date
						$orderby
						LIMIT %d, %d",
				$offset,
				$limit
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			)
		);

		$total = (int) $wpdb->get_var( 'SELECT FOUND_ROWS()' );

		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

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

		$args = wp_parse_args(
			$args,
			[
				'offset'  => 0,
				'limit'   => 20,
				'order'   => 'ASC',
				'orderby' => '',
				'dates'   => [],
			]
		);

		$table_name = $wpdb->prefix . self::TABLE_NAME;
		$where_date = self::get_where_date( $args );
		$orderby    = self::get_order_by( $args );
		$offset     = absint( $args['offset'] );
		$limit      = absint( $args['limit'] );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = (array) $wpdb->get_results(
			$wpdb->prepare(
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT
						SQL_CALC_FOUND_ROWS
    					source, form_id, COUNT(*) as served
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
	public static function create_table() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$table_name      = self::TABLE_NAME;

		$sql = "CREATE TABLE IF NOT EXISTS $wpdb->prefix$table_name (
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
		) $charset_collate;";

		dbDelta( $sql );
	}

	/**
	 * Get where date.
	 *
	 * @param array $args Arguments.
	 *
	 * @return string
	 */
	private static function get_where_date( array $args ): string {
		$dates = $args['dates'];

		if ( $dates ) {
			$dates[1] = $dates[1] ?? $dates[0];

			$dates[0] .= ' 00:00:00';
			$dates[1] .= ' 23:59:59';

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
	 * Get ODER BY / ORDER clause
	 *
	 * @param array $args Arguments.
	 *
	 * @return string
	 */
	private static function get_order_by( array $args ): string {
		$order = strtoupper( $args['order'] );
		$order = 'ASC' === $order ? '' : $order;

		return $args['orderby'] ? 'ORDER BY ' . $args['orderby'] . ' ' . $order : '';
	}
}
