<?php
/**
 * Run Codeception test files in parallel processes.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedNamespaceInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI runner output.

use Symfony\Component\Process\Process;

require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';

const HCAPTCHA_PARALLEL_MAX_AUTO_PROCESSES = 8;
const HCAPTCHA_PARALLEL_GROUP              = 'hcaptcha_parallel_excluded';

/**
 * Run the parallel test command.
 *
 * @param string[] $arguments Command arguments.
 *
 * @return int
 */
function hcaptcha_run_parallel_tests( array $arguments ): int {
	$root = dirname( __DIR__, 2 );

	if ( ! $arguments ) {
		echo "Usage: php tests/php/run-parallel.php <suite> [--processes=N] [Codeception options]\n";

		return 1;
	}

	$suite = array_shift( $arguments );

	if ( ! preg_match( '/^[a-zA-Z0-9_-]+$/', $suite ) ) {
		echo "Invalid suite name: $suite\n";

		return 1;
	}

	if ( 'integration' !== $suite ) {
		echo "Only the integration suite supports parallel execution.\n";

		return 1;
	}

	[ $process_count, $codeception_arguments ] = hcaptcha_parse_parallel_arguments( $arguments );

	if ( 0 === $process_count ) {
		return 1;
	}

	foreach ( $codeception_arguments as $argument ) {
		if ( 0 === strpos( $argument, '--coverage' ) ) {
			echo "Parallel coverage reports are not merged. Use the sequential test command for coverage.\n";

			return 1;
		}
	}

	$files = hcaptcha_find_test_files( $root . '/tests/php/' . $suite );

	if ( ! $files ) {
		echo "No test files found for the $suite suite.\n";

		return 1;
	}

	$units         = hcaptcha_create_parallel_units(
		$files,
		hcaptcha_normalize_parallel_path( $root . '/tests/php/' . $suite )
	);
	$process_count = min( $process_count, count( $units ) );
	$codecept      = $root . '/vendor/bin/codecept';
	$temp_dir      = rtrim( sys_get_temp_dir(), '/\\' ) . '/hcaptcha-codeception-' . getmypid() . '-' . bin2hex( random_bytes( 4 ) );

	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- Isolated test-runner data.
	if ( ! mkdir( $temp_dir, 0777, true ) && ! is_dir( $temp_dir ) ) {
		echo "Cannot create temporary directory: $temp_dir\n";

		return 1;
	}

	try {
		if ( ! hcaptcha_prepare_parallel_infrastructure( $root, $codecept, $process_count ) ) {
			return 1;
		}

		return hcaptcha_run_test_processes(
			$root,
			$codecept,
			$suite,
			$units,
			$process_count,
			$codeception_arguments,
			$temp_dir
		);
	} finally {
		hcaptcha_remove_parallel_temp_dir( $temp_dir );
	}
}

/**
 * Parse runner-specific arguments and preserve Codeception arguments.
 *
 * @param string[] $arguments Command arguments.
 *
 * @return array{0: int, 1: string[]}
 */
function hcaptcha_parse_parallel_arguments( array $arguments ): array {
	$process_count         = null;
	$codeception_arguments = [];

	for ( $index = 0, $count = count( $arguments ); $index < $count; ++$index ) {
		$argument = $arguments[ $index ];

		if ( 0 === strpos( $argument, '--processes=' ) ) {
			$process_count = substr( $argument, strlen( '--processes=' ) );

			continue;
		}

		if ( '--processes' === $argument ) {
			$process_count = $arguments[ ++$index ] ?? '';

			continue;
		}

		$codeception_arguments[] = $argument;
	}

	$process_count = $process_count ?? getenv( 'HCAPTCHA_PARALLEL_PROCESSES' );
	$process_count = false === $process_count || '' === $process_count
		? hcaptcha_detect_process_count()
		: filter_var(
			$process_count,
			FILTER_VALIDATE_INT,
			[
				'options' => [
					'min_range' => 1,
					'max_range' => 32,
				],
			]
		);

	if ( false === $process_count ) {
		echo "The process count must be an integer from 1 to 32.\n";

		return [ 0, [] ];
	}

	return [ (int) $process_count, $codeception_arguments ];
}

/**
 * Detect a sensible process count for the current machine.
 *
 * @return int
 */
function hcaptcha_detect_process_count(): int {
	$cpu_count = filter_var(
		getenv( 'NUMBER_OF_PROCESSORS' ),
		FILTER_VALIDATE_INT,
		[ 'options' => [ 'min_range' => 1 ] ]
	);

	if ( false === $cpu_count && is_readable( '/proc/cpuinfo' ) ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local system information.
		$cpu_info = file_get_contents( '/proc/cpuinfo' );

		if ( is_string( $cpu_info ) ) {
			$cpu_count = preg_match_all( '/^processor\s*:/m', $cpu_info );
		}
	}

	$cpu_count = false === $cpu_count || 1 > $cpu_count ? 2 : $cpu_count;

	return min( HCAPTCHA_PARALLEL_MAX_AUTO_PROCESSES, $cpu_count );
}

/**
 * Find test files in a Codeception suite.
 *
 * @param string $suite_dir Suite directory.
 *
 * @return string[]
 */
function hcaptcha_find_test_files( string $suite_dir ): array {
	if ( ! is_dir( $suite_dir ) ) {
		return [];
	}

	$files    = [];
	$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $suite_dir ) );

	foreach ( $iterator as $file ) {
		if ( ! $file->isFile() || ! preg_match( '/(?:Test|Cest|Cept)\.php$|\.feature$/', $file->getFilename() ) ) {
			continue;
		}

		$files[] = hcaptcha_normalize_parallel_path( $file->getPathname() );
	}

	sort( $files, SORT_STRING );

	return $files;
}

/**
 * Create isolated test units from top-level suite directories.
 *
 * @param string[] $files     Test files.
 * @param string   $suite_dir Suite directory.
 *
 * @return array<int,array{name: string, files: string[], weight: int}>
 */
function hcaptcha_create_parallel_units( array $files, string $suite_dir ): array {
	$units     = [];
	$suite_dir = rtrim( $suite_dir, '/' ) . '/';

	foreach ( $files as $file ) {
		$relative_path = 0 === strpos( $file, $suite_dir ) ? substr( $file, strlen( $suite_dir ) ) : basename( $file );
		$unit_name     = explode( '/', $relative_path, 2 )[0];

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local test source.
		$contents = file_get_contents( $file );
		$weight   = is_string( $contents )
			? max( 1, preg_match_all( '/\bfunction\s+test[_a-zA-Z0-9]*\s*\(/', $contents ) )
			: 1;

		$units[ $unit_name ]['name']    = $unit_name;
		$units[ $unit_name ]['files'][] = $file;
		$units[ $unit_name ]['weight']  = ( $units[ $unit_name ]['weight'] ?? 0 ) + $weight;
	}

	$weighted_units = array_values( $units );

	usort(
		$weighted_units,
		static function ( array $left, array $right ): int {
			return $right['weight'] <=> $left['weight'] ?: strcmp( $left['files'][0], $right['files'][0] );
		}
	);

	foreach ( $weighted_units as &$weighted_unit ) {
		sort( $weighted_unit['files'], SORT_STRING );
	}
	unset( $weighted_unit );

	return $weighted_units;
}

/**
 * Prepare databases and Codeception support classes for a parallel run.
 *
 * @param string $root          Project root.
 * @param string $codecept      Codeception executable.
 * @param int    $process_count Process count.
 *
 * @return bool
 */
function hcaptcha_prepare_parallel_infrastructure( string $root, string $codecept, int $process_count ): bool {
	return hcaptcha_reset_parallel_databases( $process_count ) && hcaptcha_build_codeception( $root, $codecept );
}

/**
 * Drop databases owned by parallel test slots before a run.
 *
 * @param int $process_count Process count.
 *
 * @return bool
 */
function hcaptcha_reset_parallel_databases( int $process_count ): bool {
	$params    = include dirname( __DIR__, 2 ) . '/.codeception/_config/params.php';
	$db_host   = $params['DB_HOST'];
	$db_port   = 0;
	$db_socket = null;

	if ( preg_match( '/^(.+):(\d+)$/', $db_host, $matches ) ) {
		$db_host = $matches[1];
		$db_port = (int) $matches[2];
	} elseif ( preg_match( '/^(.+):(.+)$/', $db_host, $matches ) ) {
		$db_host   = $matches[1];
		$db_socket = $matches[2];
	}

	// phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysqli_report -- Parallel test database lifecycle.
	mysqli_report( MYSQLI_REPORT_OFF );
	// phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysqli_init -- Parallel test database lifecycle.
	$connection = mysqli_init();

	if (
		false === $connection ||
		! $connection->real_connect(
			$db_host,
			$params['DB_USER'],
			$params['DB_PASSWORD'],
			null,
			$db_port,
			$db_socket
		)
	) {
		echo "Cannot connect to MySQL to prepare parallel test databases.\n";

		return false;
	}

	for ( $shard = 1; $shard <= $process_count; ++$shard ) {
		$database_name = $params['DB_NAME'] . '_test_' . $shard;
		$escaped_name  = str_replace( '`', '``', $database_name );

		if ( ! $connection->query( "DROP DATABASE IF EXISTS `$escaped_name`" ) ) {
			echo "Cannot reset parallel test database: $database_name\n";
			$connection->close();

			return false;
		}
	}

	$connection->close();

	return true;
}

/**
 * Build Codeception support classes once before workers start.
 *
 * @param string $root     Project root.
 * @param string $codecept Codeception executable.
 *
 * @return bool
 */
function hcaptcha_build_codeception( string $root, string $codecept ): bool {
	$process = new Process( [ PHP_BINARY, $codecept, 'build', '-c', 'codeception.yml' ], $root );

	$process->setTimeout( null );
	$process->run();

	if ( $process->isSuccessful() ) {
		return true;
	}

	echo $process->getOutput();
	echo $process->getErrorOutput();

	return false;
}

// phpcs:disable Generic.Metrics.CyclomaticComplexity.TooHigh
// phpcs:disable Generic.Metrics.NestingLevel.TooHigh
/**
 * Run isolated test units through a fixed-size process pool.
 *
 * @param string                                                       $root                  Project root.
 * @param string                                                       $codecept              Codeception executable.
 * @param string                                                       $suite                 Suite name.
 * @param array<int,array{name: string, files: string[], weight: int}> $units                  Test units.
 * @param int                                                          $process_count          Process count.
 * @param string[]                                                     $codeception_arguments Additional arguments.
 * @param string                                                       $temp_dir              Temporary directory.
 *
 * @return int
 */
function hcaptcha_run_test_processes(
	string $root,
	string $codecept,
	string $suite,
	array $units,
	int $process_count,
	array $codeception_arguments,
	string $temp_dir
): int {
	$all_files  = array_merge( ...array_column( $units, 'files' ) );
	$task_count = count( $units );

	foreach ( $units as $task_index => &$unit ) {
		$task_number         = $task_index + 1;
		$unit['task_number'] = $task_number;
		$unit['group_file']  = $temp_dir . '/excluded-' . $task_number . '.txt';
		$excluded            = array_values( array_diff( $all_files, $unit['files'] ) );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Isolated runner data.
		if ( false === file_put_contents( $unit['group_file'], implode( PHP_EOL, $excluded ) . PHP_EOL ) ) {
			echo 'Cannot create test group file: ' . $unit['group_file'] . "\n";

			return 1;
		}
	}
	unset( $unit );

	$slots           = array_fill( 1, $process_count, null );
	$next_task_index = 0;
	$completed       = 0;
	$failed          = [];
	$counts          = [
		'tests'      => 0,
		'assertions' => 0,
		'skipped'    => 0,
	];
	$counts_complete = true;
	$started_at      = microtime( true );

	echo "Running $task_count isolated $suite test groups in $process_count parallel processes.\n";

	while ( $completed < $task_count ) {
		foreach ( $slots as $slot_number => &$task ) {
			if ( null === $task && $next_task_index < $task_count ) {
				$task = hcaptcha_start_parallel_task(
					$root,
					$codecept,
					$suite,
					$units[ $next_task_index ],
					$slot_number,
					$codeception_arguments,
					$temp_dir
				);
				++$next_task_index;
			}

			if ( null === $task || $task['process']->isRunning() ) {
				continue;
			}

			++$completed;

			$process  = $task['process'];
			$duration = microtime( true ) - $task['started'];
			$status   = $process->isSuccessful() ? 'Passed' : 'Failed';

			printf(
				"[%d/%d] %s %s (%d files) in %.1f s.\n",
				$completed,
				$task_count,
				$status,
				$task['name'],
				count( $task['files'] ),
				$duration
			);

			if ( $process->isSuccessful() ) {
				$result_counts = hcaptcha_get_codeception_result_counts( $process->getOutput() );

				if ( null === $result_counts ) {
					$counts_complete = false;
				} else {
					foreach ( $counts as $key => $value ) {
						$counts[ $key ] += $result_counts[ $key ];
					}
				}
			} else {
				$failed[] = $task;
			}

			$task = null;
		}
		unset( $task );

		if ( $completed < $task_count ) {
			usleep( 100000 );
		}
	}

	foreach ( $failed as $task ) {
		echo "\n--- {$task['name']} output ---\n";
		echo $task['process']->getOutput();
		echo $task['process']->getErrorOutput();
	}

	$duration = microtime( true ) - $started_at;

	if ( $failed ) {
		printf( "\nParallel %s suite failed in %.1f s.\n", $suite, $duration );

		return 1;
	}

	if ( $counts_complete ) {
		printf(
			"Aggregate: %d tests, %d assertions, %d skipped.\n",
			$counts['tests'],
			$counts['assertions'],
			$counts['skipped']
		);
	}

	printf( "Parallel %s suite passed in %.1f s.\n", $suite, $duration );

	return 0;
}
// phpcs:enable Generic.Metrics.NestingLevel.TooHigh
// phpcs:enable Generic.Metrics.CyclomaticComplexity.TooHigh

/**
 * Start one isolated Codeception task.
 *
 * @param string                                                                     $root                  Project root.
 * @param string                                                                     $codecept              Codeception executable.
 * @param string                                                                     $suite                 Suite name.
 * @param array{name: string, files: string[], task_number: int, group_file: string} $unit                   Test unit.
 * @param int                                                                        $slot_number           Process slot number.
 * @param string[]                                                                   $codeception_arguments Additional arguments.
 * @param string                                                                     $temp_dir              Temporary directory.
 *
 * @return array{name: string, files: string[], process: Process, started: float}
 */
function hcaptcha_start_parallel_task(
	string $root,
	string $codecept,
	string $suite,
	array $unit,
	int $slot_number,
	array $codeception_arguments,
	string $temp_dir
): array {
	$task_number = $unit['task_number'];
	$output_dir  = hcaptcha_normalize_parallel_path( $temp_dir . '/output-' . $task_number );
	$command     = array_merge(
		[
			PHP_BINARY,
			$codecept,
			'run',
			$suite,
			'-c',
			'codeception.yml',
			'--no-rebuild',
			'--no-colors',
			'-o',
			'paths: output: ' . $output_dir,
			'-o',
			'groups: ' . HCAPTCHA_PARALLEL_GROUP . ': ' . hcaptcha_normalize_parallel_path( $unit['group_file'] ),
			'--skip-group',
			HCAPTCHA_PARALLEL_GROUP,
		],
		$codeception_arguments
	);
	$process     = new Process(
		$command,
		$root,
		[
			'HCAPTCHA_FUNCTION_MOCKER_CACHE' => $temp_dir . '/function-mocker-' . $task_number,
			'HCAPTCHA_FUNCTION_MOCKER_LOCK'  => $temp_dir . '/function-mocker.lock',
			'HCAPTCHA_TEST_SHARD'            => (string) $slot_number,
		]
	);

	$process->setTimeout( null );
	$process->start();

	return [
		'name'    => $unit['name'],
		'files'   => $unit['files'],
		'process' => $process,
		'started' => microtime( true ),
	];
}

/**
 * Read test counts from Codeception output.
 *
 * @param string $output Codeception output.
 *
 * @return array{tests: int, assertions: int, skipped: int}|null
 */
function hcaptcha_get_codeception_result_counts( string $output ): ?array {
	if ( preg_match( '/Tests:\s*(\d+), Assertions:\s*(\d+)[^\r\n]*/', $output, $matches ) ) {
		$skipped = preg_match( '/Skipped:\s*(\d+)/', $matches[0], $skipped_matches )
			? (int) $skipped_matches[1]
			: 0;

		return [
			'tests'      => (int) $matches[1],
			'assertions' => (int) $matches[2],
			'skipped'    => $skipped,
		];
	}

	if ( preg_match( '/OK \((\d+) tests?, (\d+) assertions?\)/', $output, $matches ) ) {
		return [
			'tests'      => (int) $matches[1],
			'assertions' => (int) $matches[2],
			'skipped'    => 0,
		];
	}

	return null;
}

/**
 * Normalize a path for Codeception configuration.
 *
 * @param string $path File path.
 *
 * @return string
 */
function hcaptcha_normalize_parallel_path( string $path ): string {
	return str_replace( '\\', '/', $path );
}

/**
 * Remove temporary files created by the parallel runner.
 *
 * @param string $directory Temporary directory.
 *
 * @return void
 */
function hcaptcha_remove_parallel_temp_dir( string $directory ): void {
	if ( ! is_dir( $directory ) ) {
		return;
	}

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $directory, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);

	foreach ( $iterator as $item ) {
		if ( $item->isDir() ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Isolated test-runner data.
			rmdir( $item->getPathname() );
		} else {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Isolated test-runner data.
			unlink( $item->getPathname() );
		}
	}

	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Isolated test-runner data.
	rmdir( $directory );
}

exit( hcaptcha_run_parallel_tests( array_slice( $argv, 1 ) ) );
