<?php
/**
 * Events class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Admin;

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
		self::create_table();
		$this->init_hooks();
	}

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	private function init_hooks() {
		add_action( 'hcap_verify_request', [ $this, 'save_event' ], - PHP_INT_MAX, 2 );
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

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) : '';
		$info       = HCaptcha::decode_id_info();
		$ip         = (string) hcap_get_user_ip();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$wpdb->prefix . self::TABLE_NAME,
			[
				'source'      => (string) wp_json_encode( $info['id']['source'] ),
				'form_id'     => $info['id']['form_id'],
				'ip'          => $ip,
				'user_agent'  => $user_agent,
				'uuid'        => '',
				'error_codes' => (string) wp_json_encode( $error_codes ),
				'date_gmt'    => (string) gmdate( 'Y-m-d H:i:s' ),
			]
		);

		return $result;
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

		$sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}$table_name (
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
		) {$charset_collate};";

		dbDelta( $sql );
	}
}
