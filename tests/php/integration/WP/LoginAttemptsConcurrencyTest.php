<?php
/**
 * LoginAttemptsConcurrencyTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\WP;

use FilesystemIterator;
use HCaptcha\Helpers\LoginAttempts;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use HCaptcha\WP\Login;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Symfony\Component\Process\Process;
use WP_Error;
use WP_User;

/**
 * Test cross-process failed-login accounting.
 */
class LoginAttemptsConcurrencyTest extends HCaptchaWPTestCase {

	/**
	 * Worker barrier timeout in seconds.
	 */
	private const TIMEOUT = 20;

	/**
	 * Clear shared login state after each case.
	 */
	public function tearDown(): void {
		delete_option( 'hcaptcha_settings' );
		LoginAttempts::delete_all();
		self::commit_transaction();

		parent::tearDown();
	}

	/**
	 * Test same- and different-address increments from independent PHP workers.
	 *
	 * Every worker deliberately reads the old value before the release barrier.
	 * This reproduces the former stale read/write window while the production
	 * increment remains one atomic database operation.
	 *
	 * @param bool $persistent_cache Whether to exercise shared cache invalidation.
	 *
	 * @dataProvider dp_storage_modes
	 */
	public function test_concurrent_failures_retain_every_increment_and_enforce_threshold( bool $persistent_cache ): void {
		$address       = '198.51.100.90';
		$other_address = $this->address_in_distinct_slot( $address );
		$now           = time();
		$jobs          = [];

		self::assertNotSame( $this->option_name( $address ), $this->option_name( $other_address ) );

		for ( $index = 0; $index < 4; ++$index ) {
			$jobs[] = [
				'operation' => 'read-increment',
				'address'   => $address,
				'now'       => $now,
				'ttl'       => 15 * MINUTE_IN_SECONDS,
			];
		}

		for ( $index = 0; $index < 3; ++$index ) {
			$jobs[] = [
				'operation' => 'read-increment',
				'address'   => $other_address,
				'now'       => $now,
				'ttl'       => 15 * MINUTE_IN_SECONDS,
			];
		}

		$run      = $this->run_workers( $jobs, $persistent_cache );
		$attempts = new LoginAttempts();

		foreach ( $run['ready'] as $ready ) {
			self::assertSame( 0, $ready['observed'] );
		}

		foreach ( $run['results'] as $id => $result ) {
			self::assertNotSame(
				LoginAttempts::MAX_FAILURES,
				$result['result'],
				'Worker ' . $id . ' could not store the increment: ' . $result['last_error']
			);
		}

		self::assertSame( 4, $attempts->read( $address, $now ), 'Worker results: ' . wp_json_encode( $run['results'] ) );
		self::assertSame( 3, $attempts->read( $other_address, $now ), 'Worker results: ' . wp_json_encode( $run['results'] ) );
		$this->assert_cache_invalidated( $run, [ $address, $other_address ], $persistent_cache );

		update_option(
			'hcaptcha_settings',
			[
				'login_limit'    => 4,
				'login_interval' => 15,
			]
		);
		hcaptcha()->init_hooks();

		$subject = new Login();
		$this->set_protected_property( $subject, 'ip', $address );

		// Four already in-flight requests observed zero. Once they finish, the
		// next unsolved request is rejected using current shared state.
		self::assertInstanceOf( WP_Error::class, $subject->login_base_verify( new WP_User( 1 ), 'password' ) );
	}

	/**
	 * Test atomic expiry rollover and saturation alongside another address.
	 *
	 * @param bool $persistent_cache Whether to exercise shared cache invalidation.
	 *
	 * @dataProvider dp_storage_modes
	 */
	public function test_concurrent_expiry_and_saturation_are_bounded( bool $persistent_cache ): void {
		$expired_address   = '198.51.100.91';
		$saturated_address = $this->address_in_distinct_slot( $expired_address );
		$now               = time();
		$jobs              = [];

		self::assertNotSame( $this->option_name( $expired_address ), $this->option_name( $saturated_address ) );
		$this->seed_record( $expired_address, 37, $now - 1 );
		$this->seed_record( $saturated_address, LoginAttempts::MAX_FAILURES - 2, $now + HOUR_IN_SECONDS );

		foreach ( [ $expired_address, $expired_address, $expired_address, $saturated_address, $saturated_address, $saturated_address ] as $address ) {
			$jobs[] = [
				'operation' => 'increment',
				'address'   => $address,
				'now'       => $now,
				'ttl'       => MINUTE_IN_SECONDS,
			];
		}

		$run      = $this->run_workers( $jobs, $persistent_cache );
		$attempts = new LoginAttempts();

		self::assertSame( 3, $attempts->read( $expired_address, $now ) );
		self::assertSame( LoginAttempts::MAX_FAILURES, $attempts->read( $saturated_address, $now ) );
		$this->assert_cache_invalidated( $run, [ $expired_address, $saturated_address ], $persistent_cache );
	}

	/**
	 * Test a successful-login reset cannot erase later concurrent failures.
	 *
	 * The worker database adapter holds the reset DELETE until every increment
	 * has committed. This deterministic ordering would end at zero with an
	 * unconditional address DELETE; the reset token leaves all increments intact.
	 *
	 * @param bool $persistent_cache Whether to exercise shared cache invalidation.
	 *
	 * @dataProvider dp_storage_modes
	 */
	public function test_concurrent_success_reset_uses_compare_and_swap( bool $persistent_cache ): void {
		$address       = '198.51.100.92';
		$other_address = $this->address_in_distinct_slot( $address );
		$now           = time();

		self::assertNotSame( $this->option_name( $address ), $this->option_name( $other_address ) );
		$this->seed_record( $address, 2, $now + HOUR_IN_SECONDS );
		$this->seed_record( $other_address, 1, $now + HOUR_IN_SECONDS );

		$jobs = [
			[
				'id'         => 'failure-1',
				'operation'  => 'read-increment',
				'address'    => $address,
				'now'        => $now,
				'ttl'        => MINUTE_IN_SECONDS,
				'interleave' => [
					'signal_after_pattern' => '/^INSERT /i',
					'signal'               => 'failure-1-written',
				],
			],
			[
				'id'         => 'failure-2',
				'operation'  => 'read-increment',
				'address'    => $address,
				'now'        => $now,
				'ttl'        => MINUTE_IN_SECONDS,
				'interleave' => [
					'signal_after_pattern' => '/^INSERT /i',
					'signal'               => 'failure-2-written',
				],
			],
			[
				'id'         => 'other-failure',
				'operation'  => 'read-increment',
				'address'    => $other_address,
				'now'        => $now,
				'ttl'        => MINUTE_IN_SECONDS,
				'interleave' => [
					'signal_after_pattern' => '/^INSERT /i',
					'signal'               => 'other-failure-written',
				],
			],
			[
				'id'         => 'success-reset',
				'operation'  => 'reset',
				'address'    => $address,
				'now'        => $now,
				'ttl'        => MINUTE_IN_SECONDS,
				'interleave' => [
					'wait_before_pattern' => '/^DELETE /i',
					'wait_for'            => [ 'failure-1-written', 'failure-2-written', 'other-failure-written' ],
				],
			],
		];

		$run      = $this->run_workers( $jobs, $persistent_cache );
		$attempts = new LoginAttempts();

		self::assertSame( 2, $run['ready']['failure-1']['observed'] );
		self::assertSame( 2, $run['ready']['failure-2']['observed'] );
		self::assertTrue( $run['ready']['success-reset']['token'] );
		self::assertSame( 4, $attempts->read( $address, $now ) );
		self::assertSame( 2, $attempts->read( $other_address, $now ) );
		$this->assert_cache_invalidated( $run, [ $address, $other_address ], $persistent_cache );
	}

	/**
	 * Test that a reset ordered before concurrent failures clears only old state.
	 *
	 * @param bool $persistent_cache Whether to exercise shared cache invalidation.
	 *
	 * @dataProvider dp_storage_modes
	 */
	public function test_concurrent_failures_after_success_reset_are_retained( bool $persistent_cache ): void {
		$address = '198.51.100.93';
		$now     = time();

		$this->seed_record( $address, 5, $now + HOUR_IN_SECONDS );

		$jobs = [
			[
				'id'         => 'success-reset',
				'operation'  => 'reset',
				'address'    => $address,
				'now'        => $now,
				'ttl'        => MINUTE_IN_SECONDS,
				'interleave' => [
					'signal_after_pattern' => '/^DELETE /i',
					'signal'               => 'reset-written',
				],
			],
		];

		for ( $index = 1; $index <= 2; ++$index ) {
			$jobs[] = [
				'id'         => 'failure-' . $index,
				'operation'  => 'read-increment',
				'address'    => $address,
				'now'        => $now,
				'ttl'        => MINUTE_IN_SECONDS,
				'interleave' => [
					'wait_before_pattern' => '/^INSERT /i',
					'wait_for'            => [ 'reset-written' ],
				],
			];
		}

		$run      = $this->run_workers( $jobs, $persistent_cache );
		$attempts = new LoginAttempts();

		self::assertTrue( $run['ready']['success-reset']['token'] );
		self::assertSame( 5, $run['ready']['failure-1']['observed'] );
		self::assertSame( 5, $run['ready']['failure-2']['observed'] );
		self::assertSame( 2, $attempts->read( $address, $now ) );
		$this->assert_cache_invalidated( $run, [ $address ], $persistent_cache );
	}

	/**
	 * Test database read errors require CAPTCHA instead of looking like zero failures.
	 */
	public function test_storage_read_failure_fails_closed(): void {
		global $wpdb;

		$address = '198.51.100.94';

		update_option(
			'hcaptcha_settings',
			[
				'login_limit'    => 2,
				'login_interval' => 15,
			]
		);
		hcaptcha()->init_hooks();

		$subject = new Login();
		$this->set_protected_property( $subject, 'ip', $address );
		$method   = $this->set_method_accessibility( $subject, 'is_login_limit_exceeded' );
		$previous = $wpdb->suppress_errors();
		$filter   = static function ( string $query ): string {
			if ( false !== stripos( $query, 'SELECT option_value' ) && false !== strpos( $query, LoginAttempts::OPTION_PREFIX ) ) {
				return 'SELECT missing_login_attempt_value FROM missing_login_attempt_table';
			}

			return $query;
		};

		add_filter( 'query', $filter );

		try {
			self::assertTrue( $method->invoke( $subject ) );
		} finally {
			remove_filter( 'query', $filter );
			$wpdb->suppress_errors( $previous );
			// Clear last_error for the surrounding WordPress test lifecycle.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( 'SELECT 1' );
			$method->setAccessible( false );
		}
	}

	/**
	 * Test that a transient InnoDB deadlock does not lose a failed-login count.
	 *
	 * @return void
	 */
	public function test_increment_retries_deadlock(): void {
		global $wpdb;

		$address       = '198.51.100.95';
		$now           = time();
		$record        = hash_hmac( 'sha256', $address, wp_salt( 'nonce' ) ) . '|1|' . ( $now + MINUTE_IN_SECONDS );
		$original_wpdb = $wpdb;

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Temporary database double for retry behavior.
		$wpdb = new class( $record ) {
			/**
			 * Options table name.
			 *
			 * @var string
			 */
			public string $options = 'wp_options';

			/**
			 * Latest database error.
			 *
			 * @var string
			 */
			public string $last_error = '';

			/**
			 * Query count.
			 *
			 * @var int
			 */
			public int $query_count = 0;

			/**
			 * Stored record.
			 *
			 * @var string
			 */
			private string $record;

			/**
			 * Constructor.
			 *
			 * @param string $record Stored record.
			 */
			public function __construct( string $record ) {
				$this->record = $record;
			}

			/**
			 * Keep the prepared query for the database double.
			 *
			 * @param string $query Query.
			 * @param mixed  ...$args Parameters.
			 *
			 * @return string
			 */
			public function prepare( string $query, ...$args ): string {
				return $query;
			}

			/**
			 * Fail the first statement with a retryable deadlock.
			 *
			 * @param string $query Query.
			 *
			 * @return int|false
			 */
			public function query( string $query ) {
				++$this->query_count;
				$this->last_error = 1 === $this->query_count ? 'Deadlock found when trying to get lock' : '';

				return 1 === $this->query_count ? false : 1;
			}

			/**
			 * Read the record after a successful retry.
			 *
			 * @param string $query Query.
			 *
			 * @return string
			 */
			public function get_var( string $query ): string {
				return $this->record;
			}
		};

		try {
			self::assertSame( 1, ( new LoginAttempts() )->increment( $address, $now, MINUTE_IN_SECONDS ) );
			self::assertSame( 2, $wpdb->query_count );
		} finally {
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restore WordPress's database connection.
			$wpdb = $original_wpdb;
		}
	}

	/**
	 * Storage mode data provider.
	 *
	 * The production store always uses the database as authority. The second
	 * mode adds a cross-process persistent cache double and verifies that every
	 * option mutation invalidates it without changing database accounting.
	 *
	 * @return array<string, array{bool}>
	 */
	public function dp_storage_modes(): array {
		return [
			'database-fallback' => [ false ],
			'persistent-cache'  => [ true ],
		];
	}

	// phpcs:disable Generic.Metrics.CyclomaticComplexity.TooHigh -- Bounded process orchestration includes explicit failure cleanup.
	/**
	 * Run independent PHP workers behind one deterministic release barrier.
	 *
	 * @param array[] $jobs             Worker jobs.
	 * @param bool    $persistent_cache Whether to enable shared cache markers.
	 *
	 * @return array{ready: array, results: array, cache_deletions: string[]}
	 * @throws RuntimeException When the worker infrastructure cannot be created.
	 */
	private function run_workers( array $jobs, bool $persistent_cache ): array {
		global $wpdb;

		// WordPress tests wrap each case in a transaction. Publish fixtures and
		// release row locks before independent database connections are started.
		self::commit_transaction();

		$temp_dir  = rtrim( sys_get_temp_dir(), '/\\' ) . '/hcaptcha-login-attempts-' . bin2hex( random_bytes( 8 ) );
		$cache_dir = $temp_dir . '/cache';
		$processes = [];

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- Isolated concurrency-test data.
		if ( ! mkdir( $temp_dir, 0777, true ) && ! is_dir( $temp_dir ) ) {
			throw new RuntimeException( 'Cannot create concurrency-test directory.' );
		}

		if ( $persistent_cache ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- Isolated concurrency-test data.
			mkdir( $cache_dir );

			foreach ( array_unique( array_column( $jobs, 'address' ) ) as $address ) {
				$marker = $cache_dir . '/' . hash( 'sha256', 'options|' . $this->option_name( $address ) );
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Isolated cache marker.
				file_put_contents( $marker, 'stale' );
			}
		}

		$release_file = $temp_dir . '/release';

		try {
			foreach ( $jobs as $index => $job ) {
				$id          = (string) ( $job['id'] ?? 'worker-' . $index );
				$interleave  = (array) ( $job['interleave'] ?? [] );
				$worker_data = array_merge(
					$job,
					[
						'db_host'       => DB_HOST,
						'db_name'       => DB_NAME,
						'db_user'       => DB_USER,
						'db_password'   => DB_PASSWORD,
						'options_table' => $wpdb->options,
						'plugin_root'   => dirname( __DIR__, 4 ),
						'salt'          => wp_salt( 'nonce' ),
						'cache_dir'     => $persistent_cache ? $cache_dir : '',
						'ready_file'    => $temp_dir . '/ready-' . $id,
						'result_file'   => $temp_dir . '/result-' . $id,
						'release_file'  => $release_file,
					],
				);

				if ( isset( $interleave['signal'] ) ) {
					$interleave['signal_file'] = $temp_dir . '/query-' . $interleave['signal'];
				}

				if ( isset( $interleave['wait_for'] ) ) {
					$interleave['wait_for'] = array_map(
						static fn( string $marker ): string => $temp_dir . '/query-' . $marker,
						$interleave['wait_for']
					);
				}

				$worker_data['interleave'] = $interleave;
				$config                    = (string) wp_json_encode( $worker_data );
				$process                   = new Process(
					[ PHP_BINARY, __DIR__ . '/../Helpers/LoginAttemptsWorker.php' ],
					dirname( __DIR__, 4 ),
					[ 'HCAPTCHA_LOGIN_ATTEMPTS_WORKER' => $config ]
				);

				$process->setTimeout( self::TIMEOUT );
				$process->start();
				$processes[ $id ] = $process;
			}

			$this->wait_for_worker_markers( $processes, $temp_dir, 'ready-' );

			$ready = [];

			foreach ( array_keys( $processes ) as $id ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Isolated worker marker.
				$ready[ $id ] = json_decode( (string) file_get_contents( $temp_dir . '/ready-' . $id ), true );
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Deterministic release barrier.
			file_put_contents( $release_file, 'release' );

			$results = [];

			foreach ( $processes as $id => $process ) {
				$exit_code = $process->wait();

				self::assertSame(
					0,
					$exit_code,
					'Worker ' . $id . " failed:\n" . $process->getErrorOutput() . $process->getOutput()
				);
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Isolated worker result.
				$results[ $id ] = json_decode( (string) file_get_contents( $temp_dir . '/result-' . $id ), true );
			}

			$deletions_file  = $cache_dir . '/deletions.log';
			$cache_deletions = is_file( $deletions_file )
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file -- Isolated cache log.
				? array_filter( array_map( 'trim', file( $deletions_file ) ) )
				: [];

			return [
				'ready'           => $ready,
				'results'         => $results,
				'cache_deletions' => $cache_deletions,
			];
		} finally {
			foreach ( $processes as $process ) {
				if ( $process->isRunning() ) {
					$process->stop( 0 );
				}
			}

			$this->remove_temp_dir( $temp_dir );
		}
	}
	// phpcs:enable Generic.Metrics.CyclomaticComplexity.TooHigh

	/**
	 * Wait for every worker to reach the same release barrier.
	 *
	 * @param Process[] $processes Worker processes.
	 * @param string    $temp_dir  Temporary directory.
	 * @param string    $prefix    Marker prefix.
	 *
	 * @return void
	 */
	private function wait_for_worker_markers( array $processes, string $temp_dir, string $prefix ): void {
		$deadline = microtime( true ) + self::TIMEOUT;

		while ( true ) {
			$waiting = [];

			foreach ( $processes as $id => $process ) {
				if ( is_file( $temp_dir . '/' . $prefix . $id ) ) {
					continue;
				}

				if ( ! $process->isRunning() ) {
					self::fail( 'Worker ' . $id . " exited before the barrier:\n" . $process->getErrorOutput() . $process->getOutput() );
				}

				$waiting[] = $id;
			}

			if ( ! $waiting ) {
				return;
			}

			if ( microtime( true ) >= $deadline ) {
				self::fail( 'Workers timed out before the barrier: ' . implode( ', ', $waiting ) );
			}

			usleep( 1000 );
		}
	}

	/**
	 * Seed one raw bounded record.
	 *
	 * @param string $address Address.
	 * @param int    $count   Failure count.
	 * @param int    $expires Expiration timestamp.
	 *
	 * @return void
	 */
	private function seed_record( string $address, int $count, int $expires ): void {
		$hash = hash_hmac( 'sha256', $address, wp_salt( 'nonce' ) );

		update_option( $this->option_name( $address ), $hash . '|' . $count . '|' . $expires, false );
	}

	/**
	 * Get the bounded option name for an address.
	 *
	 * @param string $address Address.
	 *
	 * @return string
	 */
	private function option_name( string $address ): string {
		$hash = hash_hmac( 'sha256', $address, wp_salt( 'nonce' ) );
		$slot = hexdec( substr( $hash, 0, 4 ) ) % LoginAttempts::SLOT_COUNT;

		return LoginAttempts::OPTION_PREFIX . sprintf( '%04d', $slot );
	}

	/**
	 * Find a second test address whose storage slot differs under the current salt.
	 *
	 * @param string $address First address.
	 *
	 * @return string
	 * @throws RuntimeException When no distinct slot can be found.
	 */
	private function address_in_distinct_slot( string $address ): string {
		$option_name = $this->option_name( $address );

		for ( $suffix = 1; $suffix <= 254; ++$suffix ) {
			$candidate = '203.0.113.' . $suffix;

			if ( $option_name !== $this->option_name( $candidate ) ) {
				return $candidate;
			}
		}

		throw new RuntimeException( 'Cannot find a test address in a distinct login-attempt slot.' );
	}

	/**
	 * Assert the shared cache path was invalidated for every address.
	 *
	 * @param array    $run              Worker run result.
	 * @param string[] $addresses        Addresses mutated by workers.
	 * @param bool     $persistent_cache Whether shared cache mode is enabled.
	 *
	 * @return void
	 */
	private function assert_cache_invalidated( array $run, array $addresses, bool $persistent_cache ): void {
		if ( ! $persistent_cache ) {
			self::assertSame( [], $run['cache_deletions'] );

			return;
		}

		foreach ( $addresses as $address ) {
			self::assertContains( $this->option_name( $address ), $run['cache_deletions'] );
		}
	}

	/**
	 * Remove an isolated worker directory.
	 *
	 * @param string $directory Directory path.
	 *
	 * @return void
	 */
	private function remove_temp_dir( string $directory ): void {
		if ( ! is_dir( $directory ) ) {
			return;
		}

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $directory, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $iterator as $item ) {
			if ( $item->isDir() ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Isolated concurrency-test data.
				rmdir( $item->getPathname() );
			} else {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Isolated concurrency-test data.
				unlink( $item->getPathname() );
			}
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Isolated concurrency-test data.
		rmdir( $directory );
	}
}
