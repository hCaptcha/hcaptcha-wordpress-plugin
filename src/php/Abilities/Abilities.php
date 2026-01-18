<?php
/**
 * Abilities integration class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Abilities;

use HCaptcha\Admin\Events\Events;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Helpers\Utils;
use JsonException;
use WP_Error;

/**
 * Abilities API integration.
 *
 * Enables AI agents to interact with the hCaptcha plugin.
 */
class Abilities {
	/**
	 * Ability category slug.
	 */
	private const CATEGORY = 'hcaptcha';

	/**
	 * Threat snapshot schema version.
	 */
	private const THREAT_SNAPSHOT_SCHEMA_VERSION = '1.0';

	/**
	 * Option name for storing offender blocks.
	 */
	private const OPTION_OFFENDER_BLOCKS = 'hcaptcha_offender_blocks_v1';

	/**
	 * Ability name.
	 */
	private const ABILITY_GET_THREAT_SNAPSHOT = 'hcaptcha/get-threat-snapshot';

	/**
	 * Ability name.
	 */
	private const ABILITY_BLOCK_OFFENDERS = 'hcaptcha/block-offenders';

	/**
	 * Maximum number of top offenders to return in a threat snapshot.
	 */
	private const MAX_TOP_OFFENDERS = 100;

	/**
	 * Default number of top offenders to return in a threat snapshot.
	 */
	private const DEFAULT_TOP_OFFENDERS = 20;

	/**
	 * Default window duration for a threat snapshot in seconds.
	 */
	private const DEFAULT_WINDOW = 5 * MINUTE_IN_SECONDS;

	/**
	 * Class constructor.
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
		global $wp_version;

		if ( version_compare( $wp_version, '6.9', '<' ) ) {
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
		add_action( 'wp_abilities_api_categories_init', [ $this, 'register_categories' ] );
		add_action( 'wp_abilities_api_init', [ $this, 'register_abilities' ] );
		add_filter( 'hcap_blacklist_ip', [ $this, 'block_offender' ], -PHP_INT_MAX + 1, 2 );
	}

	/**
	 * Register Abilities API categories.
	 *
	 * @return void
	 */
	public function register_categories(): void {
		wp_register_ability_category(
			self::CATEGORY,
			[
				'label'       => __( 'hCaptcha', 'hcaptcha-for-forms-and-more' ),
				'description' => __( 'hCaptcha plugin abilities for AI agents.', 'hcaptcha-for-forms-and-more' ),
			]
		);
	}

	/**
	 * Register Abilities API abilities.
	 *
	 * @return void
	 */
	public function register_abilities(): void {
		// phpcs:disable WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned
		wp_register_ability(
			self::ABILITY_GET_THREAT_SNAPSHOT,
			[
				'label'               => __( 'Get Threat Snapshot', 'hcaptcha-for-forms-and-more' ),
				'description'         => __( 'Returns a threat snapshot for the requested time window.', 'hcaptcha-for-forms-and-more' ),
				'category'            => self::CATEGORY,
				'input_schema'        => [
					'type'                 => 'object',
					'properties'           => [
						'window' => [
							'type'        => 'string',
							'description' => __( 'Time window for the snapshot (e.g., 30s, 5m, 2h, 1d).', 'hcaptcha-for-forms-and-more' ),
							'pattern'     => '^\\d+(s|m|h|d)$',
							'default'     => '5m',
						],
						'top_n'  => [
							'type'        => 'integer',
							'description' => __( 'Number of top offenders to return.', 'hcaptcha-for-forms-and-more' ),
							'minimum'     => 1,
							'maximum'     => self::MAX_TOP_OFFENDERS,
							'default'     => self::DEFAULT_TOP_OFFENDERS,
						],
					],
					'required'             => [ 'window' ],
					'additionalProperties' => false,
				],
				'output_schema'       => [
					'type'                 => 'object',
					'properties'           => [
						'schema_version' => [
							'type'        => 'string',
							'description' => __( 'Threat snapshot schema version.', 'hcaptcha-for-forms-and-more' ),
						],
						'window'         => [
							'type'        => 'string',
							'description' => __( 'The requested time window.', 'hcaptcha-for-forms-and-more' ),
						],
						'window_seconds' => [
							'type'        => 'integer',
							'description' => __( 'Window duration in seconds.', 'hcaptcha-for-forms-and-more' ),
						],
						'generated_at'   => [
							'type'        => 'string',
							'format'      => 'date-time',
							'description' => __( 'Snapshot generation time (UTC).', 'hcaptcha-for-forms-and-more' ),
						],
						'metrics'        => [
							'type'                 => 'object',
							'properties'           => [
								'total'     => [ 'type' => 'integer' ],
								'failed'    => [ 'type' => 'integer' ],
								'fail_rate' => [ 'type' => 'number' ],
							],
							'required'             => [ 'total', 'failed', 'fail_rate' ],
							'additionalProperties' => false,
						],
						'signals'        => [
							'type'                 => 'object',
							'properties'           => [
								'attack_likelihood' => [ 'type' => 'string' ],
								'confidence'        => [ 'type' => 'string' ],
								'top_vectors'       => [
									'type'  => 'array',
									'items' => [ 'type' => 'string' ],
								],
							],
							'required'             => [ 'attack_likelihood', 'confidence', 'top_vectors' ],
							'additionalProperties' => false,
						],
						'breakdown'      => [
							'type'                 => 'object',
							'properties'           => [
								'errors'    => [
									'type'                 => 'object',
									'additionalProperties' => [ 'type' => 'integer' ],
								],
								'sources'   => [
									'type'  => 'array',
									'items' => [
										'type'                 => 'object',
										'properties'           => [
											'source'  => [ 'type' => 'string' ],
											'form_id' => [ 'type' => 'string' ],
											'count'   => [ 'type' => 'integer' ],
										],
										'required'             => [ 'source', 'form_id', 'count' ],
										'additionalProperties' => false,
									],
								],
								'offenders' => [
									'type'  => 'array',
									'items' => [
										'type'                 => 'object',
										'properties'           => [
											'type'        => [ 'type' => 'string' ],
											'offender_id' => [ 'type' => 'string' ],
											'count'       => [ 'type' => 'integer' ],
											'top_errors'  => [
												'type'     => 'array',
												'items'    => [ 'type' => 'string' ],
												'maxItems' => 3,
											],
											'top_sources' => [
												'type'     => 'array',
												'items'    => [ 'type' => 'string' ],
												'maxItems' => 3,
											],
										],
										'required'             => [ 'offender_id', 'count' ],
										'additionalProperties' => false,
									],
								],
							],
							'required'             => [ 'errors', 'sources', 'offenders' ],
							'additionalProperties' => false,
						],
					],
					'required'             => [
						'schema_version',
						'window',
						'window_seconds',
						'generated_at',
						'metrics',
						'signals',
						'breakdown',
					],
					'additionalProperties' => false,
				],
				'permission_callback' => [ $this, 'can_get_threat_snapshot' ],
				'execute_callback'    => [ $this, 'get_threat_snapshot' ],
				'meta'                => [
					'annotations'  => [
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					],
					'show_in_rest' => true,
				],
			]
		);
		// phpcs:enable WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned

		wp_register_ability(
			self::ABILITY_BLOCK_OFFENDERS,
			[
				'label'               => __( 'Block Offenders', 'hcaptcha-for-forms-and-more' ),
				'description'         => __( 'Blocks offender IDs returned by threat snapshots.', 'hcaptcha-for-forms-and-more' ),
				'category'            => self::CATEGORY,
				'input_schema'        => [
					'type'                 => 'object',
					'properties'           => [
						'offender_ids' => [
							'type'        => 'array',
							'description' => __( 'Offender IDs to block.', 'hcaptcha-for-forms-and-more' ),
							'items'       => [ 'type' => 'string' ],
							'minItems'    => 1,
							'maxItems'    => self::MAX_TOP_OFFENDERS,
						],
						'ttl_seconds'  => [
							'type'        => 'integer',
							'description' => __( 'Duration to block offenders for in seconds.', 'hcaptcha-for-forms-and-more' ),
							'minimum'     => MINUTE_IN_SECONDS,
							'maximum'     => YEAR_IN_SECONDS,
							'default'     => HOUR_IN_SECONDS,
						],
						'reason'       => [
							'type' => 'string',
						],
					],
					'required'             => [ 'offender_ids' ],
					'additionalProperties' => false,
				],
				'output_schema'       => [
					'type'                 => 'object',
					'properties'           => [
						'blocked'         => [
							'type'  => 'array',
							'items' => [ 'type' => 'string' ],
						],
						'already_blocked' => [
							'type'  => 'array',
							'items' => [ 'type' => 'string' ],
						],
						'effective_until' => [
							'type'   => 'string',
							'format' => 'date-time',
						],
					],
					'required'             => [ 'blocked', 'already_blocked', 'effective_until' ],
					'additionalProperties' => false,
				],
				'permission_callback' => [ $this, 'can_block_offenders' ],
				'execute_callback'    => [ $this, 'block_offenders' ],
				'meta'                => [
					'show_in_rest' => true,
					'annotations'  => [
						'readonly'    => false,
						'idempotent'  => true,
						'destructive' => true,
					],
				],
			]
		);
	}

	/**
	 * Filter the user IP to check if it is the offenders list.
	 * For denylisted IPs, any form submission fails.
	 *
	 * @param bool|mixed   $denylisted Whether IP is denylisted.
	 * @param string|false $client_ip  Client IP.
	 *
	 * @return bool
	 */
	public function block_offender( $denylisted, $client_ip ): bool {
		$denylisted = (bool) $denylisted;

		$offender_id = $this->get_offender_id( (string) $client_ip );

		return $this->is_offender_blocked( $offender_id ) ? true : $denylisted;
	}

	/**
	 * Permission callback for the `hcaptcha/get-threat-snapshot` ability.
	 *
	 * @return bool
	 */
	public function can_get_threat_snapshot(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Execute callback for the `hcaptcha/get-threat-snapshot` ability.
	 *
	 * @param mixed $input Ability input.
	 *
	 * @return array|WP_Error
	 */
	public function get_threat_snapshot( $input ) {
		$input  = (array) $input;
		$window = trim( (string) ( $input['window'] ?? '5m' ) );
		$top_n  = (int) ( $input['top_n'] ?? self::DEFAULT_TOP_OFFENDERS );
		$top_n  = max( 1, min( self::MAX_TOP_OFFENDERS, $top_n ) );

		if ( ! preg_match( '/^\d+([smhd])$/', $window ) ) {
			return new WP_Error(
				'hcaptcha_invalid_window',
				__( 'Invalid window. Expected a string like 30s, 5m, 3h, 1d.', 'hcaptcha-for-forms-and-more' )
			);
		}

		$seconds  = $this->window_to_seconds( $window );
		$now      = time();
		$to_gmt   = gmdate( 'Y-m-d H:i:s', $now );
		$from_gmt = gmdate( 'Y-m-d H:i:s', max( 0, $now - $seconds ) );

		$snapshot                   = $this->query_threats_snapshot( $from_gmt, $to_gmt, $top_n );
		$snapshot['schema_version'] = self::THREAT_SNAPSHOT_SCHEMA_VERSION;
		$snapshot['window']         = $window;
		$snapshot['window_seconds'] = $seconds;
		$snapshot['generated_at']   = gmdate( 'c' );

		return $snapshot;
	}

	/**
	 * Convert a window string (e.g., 5m) to seconds.
	 *
	 * @param string $window Window.
	 *
	 * @return int
	 */
	private function window_to_seconds( string $window ): int {
		preg_match( '/^(\d+)([smhd])$/', $window, $m );

		$amount = max( 0, (int) ( $m[1] ?? 0 ) );
		$unit   = $m[2] ?? '';

		switch ( $unit ) {
			case 's':
				return $amount;
			case 'm':
				return $amount * MINUTE_IN_SECONDS;
			case 'h':
				return $amount * HOUR_IN_SECONDS;
			case 'd':
				return $amount * DAY_IN_SECONDS;
			default:
				return self::DEFAULT_WINDOW;
		}
	}

	/**
	 * Query and aggregate threats snapshot from the events table.
	 *
	 * @param string $from_gmt From date (UTC), `Y-m-d H:i:s`.
	 * @param string $to_gmt   To date (UTC), `Y-m-d H:i:s`.
	 * @param int    $top_n    Number of top offenders to return.
	 *
	 * @return array<string, mixed>
	 */
	private function query_threats_snapshot( string $from_gmt, string $to_gmt, int $top_n ): array {
		global $wpdb;

		$empty = [
			'metrics'   => [
				'total'     => 0,
				'failed'    => 0,
				'fail_rate' => 0.0,
			],
			'signals'   => [
				'attack_likelihood' => 'low',
				'confidence'        => 'low',
				'top_vectors'       => [],
			],
			'breakdown' => [
				'errors'    => [],
				'sources'   => [],
				'offenders' => [],
			],
		];

		$table_name = $wpdb->prefix . Events::TABLE_NAME;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $table_name WHERE date_gmt BETWEEN %s AND %s",
				$from_gmt,
				$to_gmt
			)
		);

		if ( null === $total ) {
			return $empty;
		}

		$total = (int) $total;

		$rows = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT source, form_id, ip, user_agent, error_codes
						FROM $table_name
						WHERE date_gmt BETWEEN %s AND %s
							AND error_codes <> %s",
				$from_gmt,
				$to_gmt,
				'[]'
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$failed_count = count( $rows );
		$fail_rate    = $total > 0 ? ( $failed_count / $total ) : 0.0;

		[ $error_counts, $sources, $offenders ] = $this->get_threat_details( $rows );

		usort(
			$sources,
			static function ( array $a, array $b ): int {
				if ( $a['count'] !== $b['count'] ) {
					return $b['count'] <=> $a['count'];
				}

				$cmp = strcmp( (string) $a['source'], (string) $b['source'] );

				if ( 0 !== $cmp ) {
					return $cmp;
				}

				return strcmp( (string) $a['form_id'], (string) $b['form_id'] );
			}
		);

		usort(
			$offenders,
			static function ( array $a, array $b ): int {
				if ( $a['count'] !== $b['count'] ) {
					return $b['count'] <=> $a['count'];
				}

				return strcmp( (string) $a['offender_id'], (string) $b['offender_id'] );
			}
		);

		$offenders = array_slice( $offenders, 0, max( 1, $top_n ) );

		foreach ( $offenders as &$offender ) {
			$error_map  = $this->sort_map_by_count_desc_then_key( $offender['_error_counts'] );
			$source_map = $this->sort_map_by_count_desc_then_key( $offender['_source_counts'] );

			unset( $offender['_error_counts'], $offender['_source_counts'] );

			$top_errors  = array_slice( array_keys( $error_map ), 0, 3 );
			$top_sources = array_slice( array_keys( $source_map ), 0, 3 );

			if ( $top_errors ) {
				$offender['top_errors'] = $top_errors;
			}

			if ( $top_sources ) {
				$offender['top_sources'] = $top_sources;
			}
		}

		unset( $offender );

		$top_vectors = array_slice( array_keys( $error_counts ), 0, 3 );

		return [
			'metrics'   => [
				'total'     => $total,
				'failed'    => $failed_count,
				'fail_rate' => number_format( $fail_rate, 2 ),
			],
			'signals'   => [
				'attack_likelihood' => $this->calculate_attack_likelihood( $total, $failed_count, $fail_rate ),
				'confidence'        => $this->calculate_confidence( $total ),
				'top_vectors'       => $top_vectors,
			],
			'breakdown' => [
				'errors'    => $error_counts,
				'sources'   => $sources,
				'offenders' => $offenders,
			],
		];
	}

	/**
	 * Get threat details from events.
	 *
	 * @param array $rows Events rows.
	 *
	 * @return array
	 */
	private function get_threat_details( array $rows ): array {
		$error_counts    = [];
		$source_counts   = [];
		$offender_totals = [];

		foreach ( $rows as $row ) {
			$error_codes = Utils::json_decode_arr( $row->error_codes );

			foreach ( $error_codes as $code ) {
				$code                  = (string) $code;
				$error_counts[ $code ] = ( $error_counts[ $code ] ?? 0 ) + 1;
			}

			$source = $this->parse_source_for_reporting( $row->source, $row->form_id );
			$label  = $source['label'];

			$source_counts = $this->update_source_counts( $source_counts, $label, $source );

			$ip = $row->ip;

			if ( '' === $ip ) {
				continue;
			}

			$offender_id = $this->get_offender_id( $ip );

			$offender_totals = $this->update_offender_totals( $offender_totals, $offender_id );

			foreach ( $error_codes as $code ) {
				$code = (string) $code;

				$offender_totals[ $offender_id ]['_error_counts'][ $code ] =
					( $offender_totals[ $offender_id ]['_error_counts'][ $code ] ?? 0 ) + 1;
			}

			$offender_totals[ $offender_id ]['_source_counts'][ $label ] =
				( $offender_totals[ $offender_id ]['_source_counts'][ $label ] ?? 0 ) + 1;
		}

		$error_counts = $this->sort_map_by_count_desc_then_key( $error_counts );
		$sources      = array_values( $source_counts );
		$offenders    = array_values( $offender_totals );

		return [ $error_counts, $sources, $offenders ];
	}

	/**
	 * Format the "source" field from the events table for reporting.
	 *
	 * @param string $source  Source JSON from DB.
	 * @param string $form_id Form ID from DB.
	 *
	 * @return array
	 */
	private function parse_source_for_reporting( string $source, string $form_id ): array {
		$source = HCaptcha::get_source_name( $source ) ?: 'Unknown';
		$label  = rtrim( $source . ':' . $form_id, ':' );

		return [
			'source'  => $source,
			'form_id' => $form_id,
			'label'   => $label,
		];
	}

	/**
	 * Update the source count for a given label.
	 *
	 * @param array  $source_counts Source counts.
	 * @param string $label         Label.
	 * @param array  $source        Source.
	 *
	 * @return array
	 */
	private function update_source_counts( array $source_counts, string $label, array $source ): array {
		if ( ! isset( $source_counts[ $label ] ) ) {
			$source_counts[ $label ] = [
				'source'  => $source['source'],
				'form_id' => $source['form_id'],
				'count'   => 0,
			];
		}

		++$source_counts[ $label ]['count'];

		return $source_counts;
	}

	/**
	 * Update the offender totals for a given offender ID.
	 *
	 * @param array  $offender_totals Offender totals.
	 * @param string $offender_id     Offender ID.
	 *
	 * @return array
	 */
	private function update_offender_totals( array $offender_totals, string $offender_id ): array {
		if ( ! isset( $offender_totals[ $offender_id ] ) ) {
			$offender_totals[ $offender_id ] = [
				'offender_id'    => $offender_id,
				'type'           => 'ip',
				'count'          => 0,
				'_error_counts'  => [],
				'_source_counts' => [],
			];
		}

		++$offender_totals[ $offender_id ]['count'];

		return $offender_totals;
	}

	/**
	 * Build a deterministic offender ID from an IP value.
	 *
	 * @param string $ip IP value (raw or already hashed, depending on settings).
	 *
	 * @return string
	 */
	private function get_offender_id( string $ip ): string {
		if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return wp_hash( $ip );
		}

		// Some event DB records may have already hashed IP value.
		return $ip;
	}

	/**
	 * Sort a string=>int map by value descending then key ascending.
	 *
	 * @param array<string,int> $map Map.
	 *
	 * @return array<string,int>
	 */
	private function sort_map_by_count_desc_then_key( array $map ): array {
		uksort(
			$map,
			static function ( string $a, string $b ) use ( $map ): int {
				$ca = $map[ $a ] ?? 0;
				$cb = $map[ $b ] ?? 0;

				if ( $ca === $cb ) {
					return strcmp( $a, $b );
				}

				return $cb <=> $ca;
			}
		);

		return $map;
	}

	/**
	 * Calculate attack likelihood.
	 *
	 * @param int   $total     Total events.
	 * @param int   $failed    Failed events.
	 * @param float $fail_rate Fail rate.
	 *
	 * @return string
	 */
	private function calculate_attack_likelihood( int $total, int $failed, float $fail_rate ): string {
		if ( $total < 20 ) {
			return 'low';
		}

		if ( $fail_rate >= 0.50 && $failed >= 10 ) {
			return 'high';
		}

		if ( $fail_rate >= 0.20 && $failed >= 5 ) {
			return 'medium';
		}

		return 'low';
	}

	/**
	 * Calculate confidence.
	 *
	 * @param int $total Total events.
	 *
	 * @return string
	 */
	private function calculate_confidence( int $total ): string {
		if ( $total >= 1000 ) {
			return 'high';
		}

		if ( $total >= 100 ) {
			return 'medium';
		}

		return 'low';
	}

	/**
	 * Can block offenders permission callback.
	 */
	public function can_block_offenders(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Execute callback for the `hcaptcha/block-offenders` ability.
	 *
	 * @param mixed $input Ability input.
	 *
	 * @return array
	 */
	public function block_offenders( $input ): array {
		$input = (array) $input;

		[ $offender_ids, $ttl_seconds, $reason ] = $this->prepare_block_offenders_input( $input );

		$now        = time();
		$expires_at = $now + $ttl_seconds;

		$blocked         = [];
		$already_blocked = [];

		$blocks = $this->get_offender_blocks();

		foreach ( $offender_ids as $offender_id ) {
			$offender_id = trim( (string) $offender_id );

			$existing = $blocks[ $offender_id ] ?? null;

			if ( is_array( $existing ) && (int) ( $existing['expires_at'] ?? 0 ) > $now ) {
				$already_blocked[] = $offender_id;
				continue;
			}

			$blocks[ $offender_id ] = [
				'expires_at' => $expires_at,
				'reason'     => $reason,
				'created_at' => $now,
			];

			$blocked[] = $offender_id;
		}

		$blocks = $this->prune_offender_blocks( $blocks, $now );
		$this->set_offender_blocks( $blocks );

		$effective_until_ts = 0;

		foreach ( array_merge( $blocked, $already_blocked ) as $id ) {
			$effective_until_ts = max( $effective_until_ts, (int) ( $blocks[ $id ]['expires_at'] ?? 0 ) );
		}

		$effective_until_ts = $effective_until_ts ?: $expires_at;

		sort( $blocked, SORT_STRING );
		sort( $already_blocked, SORT_STRING );

		return [
			'blocked'         => $blocked,
			'already_blocked' => $already_blocked,
			'effective_until' => gmdate( 'c', $effective_until_ts ),
		];
	}

	/**
	 * Prepare the input for the `block_offenders` callback.
	 *
	 * @param array $input Input.
	 *
	 * @return array
	 */
	private function prepare_block_offenders_input( array $input ): array {
		$offender_ids = isset( $input['offender_ids'] ) ? (array) $input['offender_ids'] : [];
		$ttl_seconds  = isset( $input['ttl_seconds'] ) ? (int) $input['ttl_seconds'] : HOUR_IN_SECONDS;
		$ttl_seconds  = max( MINUTE_IN_SECONDS, min( YEAR_IN_SECONDS, $ttl_seconds ) );
		$reason       = isset( $input['reason'] ) ? (string) $input['reason'] : 'blocked_by_ability';

		return [ $offender_ids, $ttl_seconds, $reason ];
	}

	/**
	 * Check if an offender is blocked.
	 *
	 * @param string $offender_id Offender ID.
	 *
	 * @return bool
	 */
	private function is_offender_blocked( string $offender_id ): bool {

		$entry = $this->get_offender_blocks()[ $offender_id ] ?? null;

		if ( ! is_array( $entry ) ) {
			return false;
		}

		return (int) ( $entry['expires_at'] ?? 0 ) > time();
	}

	/**
	 * Get the current offender blocks.
	 *
	 * @return array<string,array{expires_at:int,reason:string,created_at:int}>
	 */
	private function get_offender_blocks(): array {
		$raw = get_option( self::OPTION_OFFENDER_BLOCKS, [] );
		$raw = is_array( $raw ) ? $raw : [];

		$blocks = $this->prune_offender_blocks( $raw, time() );

		if ( $blocks !== $raw ) {
			$this->set_offender_blocks( $blocks );
		}

		return $blocks;
	}

	/**
	 * Prune expired offender blocks.
	 *
	 * @param array<string,mixed> $blocks Blocks.
	 * @param int                 $now    Current timestamp.
	 *
	 * @return array<string,array{expires_at:int,reason:string,created_at:int}>
	 */
	private function prune_offender_blocks( array $blocks, int $now ): array {
		$out = [];

		foreach ( $blocks as $offender_id => $entry ) {
			if ( $entry['expires_at'] <= $now ) {
				continue;
			}

			$out[ $offender_id ] = $entry;
		}

		ksort( $out, SORT_STRING );

		return $out;
	}

	/**
	 * Set the current offender blocks.
	 *
	 * @param array<string,array{expires_at:int,reason:string,created_at:int}> $blocks Blocks.
	 *
	 * @return void
	 */
	private function set_offender_blocks( array $blocks ): void {
		update_option( self::OPTION_OFFENDER_BLOCKS, $blocks, false );
	}
}
