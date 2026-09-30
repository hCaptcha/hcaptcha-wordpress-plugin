<?php
/**
 * ParallelRunnerTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Unit;

use function hcaptcha_create_parallel_units;
use function hcaptcha_find_test_files;
use function hcaptcha_normalize_parallel_path;
use function hcaptcha_parse_parallel_arguments;
use function hcaptcha_partition_parallel_units;

require_once dirname( __DIR__ ) . '/run-parallel.php';

/**
 * Test CI shard assignment without starting WordPress or MySQL.
 */
class ParallelRunnerTest extends HCaptchaTestCase {
	/**
	 * Runner options must not be passed through to Codeception.
	 */
	public function test_parse_shard_arguments(): void {
		self::assertSame(
			[ 2, 2, 3, [ '--env', 'github-actions' ] ],
			hcaptcha_parse_parallel_arguments( [ '--processes=2', '--shard=2/3', '--env', 'github-actions' ] )
		);

		self::assertSame(
			[ 2, 1, 1, [ '--env', 'github-actions' ] ],
			hcaptcha_parse_parallel_arguments( [ '--processes=2', '--env', 'github-actions' ] )
		);
	}

	/**
	 * Every integration test file must occur in exactly one shard.
	 */
	public function test_partition_covers_all_integration_files_once(): void {
		$suite_dir = dirname( __DIR__ ) . '/integration';
		$files     = hcaptcha_find_test_files( $suite_dir );
		$units     = hcaptcha_create_parallel_units( $files, hcaptcha_normalize_parallel_path( $suite_dir ) );
		$shards    = hcaptcha_partition_parallel_units( $units, 2 );
		$assigned  = [];

		self::assertCount( 2, $shards );

		foreach ( $shards as $shard ) {
			self::assertNotEmpty( $shard );

			foreach ( $shard as $unit ) {
				$assigned[] = $unit['files'];
			}
		}

		$assigned = array_merge( ...$assigned );

		sort( $assigned, SORT_STRING );
		self::assertSame( $files, $assigned );
	}
}
