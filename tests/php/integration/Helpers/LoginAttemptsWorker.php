<?php
/**
 * Independent login-attempt storage worker.
 *
 * @package HCaptcha\Tests
 */

use HCaptcha\Helpers\LoginAttempts;

// phpcs:disable Universal.Files.SeparateFunctionsFromOO.Mixed -- Standalone worker combines its adapter and bootstrap functions.

const HCAPTCHA_WORKER_TIMEOUT = 15;

$config_json = getenv( 'HCAPTCHA_LOGIN_ATTEMPTS_WORKER' );
$config      = json_decode( (string) $config_json, true );

if ( ! is_array( $config ) ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Standalone CLI worker error output.
	fwrite( STDERR, "Invalid worker configuration.\n" );
	exit( 2 );
}

$GLOBALS['hcaptcha_login_attempts_worker_config'] = $config;

if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}

if ( ! function_exists( 'wp_salt' ) ) {
	/**
	 * Return the parent WordPress process's nonce salt.
	 *
	 * @param string $scheme Salt scheme.
	 *
	 * @return string
	 */
	function wp_salt( string $scheme = 'auth' ): string {
		return (string) $GLOBALS['hcaptcha_login_attempts_worker_config']['salt'];
	}
}

if ( ! function_exists( 'wp_cache_delete' ) ) {
	/**
	 * Exercise cross-process option-cache invalidation when requested.
	 *
	 * @param string $key   Cache key.
	 * @param string $group Cache group.
	 *
	 * @return bool
	 */
	function wp_cache_delete( string $key, string $group = '' ): bool {
		$cache_dir = (string) ( $GLOBALS['hcaptcha_login_attempts_worker_config']['cache_dir'] ?? '' );

		if ( '' === $cache_dir ) {
			return true;
		}

		$marker = $cache_dir . DIRECTORY_SEPARATOR . hash( 'sha256', $group . '|' . $key );

		if ( is_file( $marker ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Isolated test-worker data.
			unlink( $marker );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Isolated test-worker log.
		file_put_contents( $cache_dir . DIRECTORY_SEPARATOR . 'deletions.log', $key . "\n", FILE_APPEND | LOCK_EX );

		return true;
	}
}

require_once (string) $config['plugin_root'] . '/vendor/autoload.php';

/**
 * Minimal real-MySQL wpdb-compatible adapter for an independent PHP process.
 */
final class LoginAttemptsWorkerDatabase {

	/**
	 * Options table name.
	 *
	 * @var string
	 */
	public string $options;

	/**
	 * Last database error.
	 *
	 * @var string
	 */
	public string $last_error = '';

	/**
	 * Database connection.
	 *
	 * @var mysqli
	 */
	private mysqli $connection;

	/**
	 * Worker configuration.
	 *
	 * @var array
	 */
	private array $config;

	/**
	 * Constructor.
	 *
	 * @param array $config Worker configuration.
	 *
	 * @throws RuntimeException When the worker cannot connect to the database.
	 */
	public function __construct( array $config ) {
		$this->config  = $config;
		$this->options = (string) $config['options_table'];

		[ $host, $port, $socket ] = $this->parse_host( (string) $config['db_host'] );

		// phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysqli_report -- Isolated concurrency test worker.
		mysqli_report( MYSQLI_REPORT_OFF );
		// phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysqli_init -- Isolated concurrency test worker.
		$connection = mysqli_init();

		if (
			false === $connection ||
			! $connection->real_connect(
				$host,
				(string) $config['db_user'],
				(string) $config['db_password'],
				(string) $config['db_name'],
				$port,
				$socket
			)
		) {
			throw new RuntimeException( 'Cannot connect concurrency worker to the test database.' );
		}

		$this->connection = $connection;
	}

	/**
	 * Prepare the placeholders used by LoginAttempts.
	 *
	 * @param string $query SQL query.
	 * @param mixed  ...$args Placeholder arguments.
	 *
	 * @return string
	 * @throws RuntimeException When placeholder arguments are invalid.
	 */
	public function prepare( string $query, ...$args ): string {
		$index = 0;

		$prepared = preg_replace_callback(
			'/%[sd]/',
			function ( array $matches ) use ( $args, &$index ): string {
				if ( ! array_key_exists( $index, $args ) ) {
					throw new RuntimeException( 'Missing SQL placeholder argument.' );
				}

				$value = $args[ $index++ ];

				if ( '%d' === $matches[0] ) {
					return (string) (int) $value;
				}

				return "'" . $this->connection->real_escape_string( (string) $value ) . "'";
			},
			$query
		);

		if ( null === $prepared || count( $args ) !== $index ) {
			throw new RuntimeException( 'Invalid SQL placeholder arguments.' );
		}

		return $prepared;
	}

	/**
	 * Execute a mutating query.
	 *
	 * @param string $query SQL query.
	 *
	 * @return int|false
	 */
	public function query( string $query ) {
		$this->wait_before_query( $query );

		$result           = $this->connection->query( $query );
		$this->last_error = $this->connection->error;

		if ( false === $result ) {
			return false;
		}

		$this->signal_after_query( $query );

		return $this->connection->affected_rows;
	}

	/**
	 * Return the first column of the first selected row.
	 *
	 * @param string $query SQL query.
	 *
	 * @return string|null
	 */
	public function get_var( string $query ): ?string {
		$result           = $this->connection->query( $query );
		$this->last_error = $this->connection->error;

		if ( false === $result ) {
			return null;
		}

		$row = $result->fetch_row();
		$result->free();

		return null === $row ? null : (string) $row[0];
	}

	/**
	 * Parse a standard WordPress database host value.
	 *
	 * @param string $db_host Database host.
	 *
	 * @return array{0: string, 1: int, 2: string|null}
	 */
	private function parse_host( string $db_host ): array {
		$host   = $db_host;
		$port   = 0;
		$socket = null;

		if ( preg_match( '/^(.+):(\d+)$/', $db_host, $matches ) ) {
			$host = $matches[1];
			$port = (int) $matches[2];
		} elseif ( preg_match( '/^(.+):(.+)$/', $db_host, $matches ) ) {
			$host   = $matches[1];
			$socket = $matches[2];
		}

		return [ $host, $port, $socket ];
	}

	/**
	 * Wait for deterministic query-order markers.
	 *
	 * @param string $query SQL query.
	 *
	 * @return void
	 */
	private function wait_before_query( string $query ): void {
		$interleave = (array) ( $this->config['interleave'] ?? [] );
		$pattern    = (string) ( $interleave['wait_before_pattern'] ?? '' );

		if ( '' === $pattern || ! preg_match( $pattern, $query ) ) {
			return;
		}

		login_attempts_worker_wait_for_files( (array) ( $interleave['wait_for'] ?? [] ) );
	}

	/**
	 * Signal completion of a selected database mutation.
	 *
	 * @param string $query SQL query.
	 *
	 * @return void
	 */
	private function signal_after_query( string $query ): void {
		$interleave = (array) ( $this->config['interleave'] ?? [] );
		$pattern    = (string) ( $interleave['signal_after_pattern'] ?? '' );
		$file       = (string) ( $interleave['signal_file'] ?? '' );

		if ( '' === $pattern || '' === $file || ! preg_match( $pattern, $query ) ) {
			return;
		}

		login_attempts_worker_write( $file, [ 'signaled' => true ] );
	}
}

/**
 * Write a JSON barrier or result marker.
 *
 * @param string $file File path.
 * @param array  $data Marker data.
 *
 * @return void
 * @throws RuntimeException When the marker cannot be written.
 */
function login_attempts_worker_write( string $file, array $data ): void {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents, WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Isolated test-worker marker; WordPress is intentionally not bootstrapped.
	if ( false === file_put_contents( $file, (string) json_encode( $data ), LOCK_EX ) ) {
		throw new RuntimeException( 'Cannot write concurrency marker.' );
	}
}

/**
 * Wait until every deterministic barrier marker exists.
 *
 * @param string[] $files Marker files.
 *
 * @return void
 * @throws RuntimeException When the barrier times out.
 */
function login_attempts_worker_wait_for_files( array $files ): void {
	$deadline = microtime( true ) + HCAPTCHA_WORKER_TIMEOUT;

	while ( array_filter( $files, static fn( string $file ): bool => ! is_file( $file ) ) ) {
		if ( microtime( true ) >= $deadline ) {
			throw new RuntimeException( 'Timed out at concurrency barrier.' );
		}

		usleep( 1000 );
	}
}

try {
	// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Independent worker supplies its own wpdb-compatible connection.
	$wpdb      = new LoginAttemptsWorkerDatabase( $config );
	$store     = new LoginAttempts();
	$operation = (string) $config['operation'];
	$address   = (string) $config['address'];
	$now       = (int) $config['now'];
	$ready     = [];
	$token     = null;

	if ( 'read-increment' === $operation ) {
		$ready['observed'] = $store->read( $address, $now );
	} elseif ( 'reset' === $operation ) {
		$token          = $store->get_reset_token( $address, $now );
		$ready['token'] = null !== $token;
	}

	login_attempts_worker_write( (string) $config['ready_file'], $ready );
	login_attempts_worker_wait_for_files( [ (string) $config['release_file'] ] );

	if ( 'increment' === $operation || 'read-increment' === $operation ) {
		$result = $store->increment( $address, $now, (int) $config['ttl'] );
	} elseif ( 'reset' === $operation ) {
		$store->reset( $address, $token );
		$result = null;
	} else {
		throw new RuntimeException( 'Unknown concurrency worker operation.' );
	}

	login_attempts_worker_write(
		(string) $config['result_file'],
		[
			'result'     => $result,
			'last_error' => $wpdb->last_error,
		]
	);
} catch ( Throwable $throwable ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Standalone CLI worker error output.
	fwrite( STDERR, $throwable->getMessage() . "\n" );
	exit( 1 );
}
