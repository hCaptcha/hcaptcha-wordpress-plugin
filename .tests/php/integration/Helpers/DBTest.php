<?php
/**
 * DBTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\Helpers;

use HCaptcha\Helpers\DB;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;

/**
 * Test DB class.
 *
 * @group helpers
 * @group helpers-db
 */
class DBTest extends HCaptchaWPTestCase {

	/**
	 * Test prepare_in().
	 *
	 * @param mixed|array $items    Item(s) to be joined into string.
	 * @param string|null $format   %s or %d.
	 * @param string      $expected Expected value.
	 *
	 * @dataProvider dp_test_prepare_in
	 * @return void
	 */
	public function test_prepare_in( $items, ?string $format, string $expected ): void {
		if ( null === $format ) {
			self::assertSame( $expected, DB::prepare_in( $items ) );
		} else {
			self::assertSame( $expected, DB::prepare_in( $items, $format ) );
		}
	}

	/**
	 * Data provider for test_prepare_in().
	 *
	 * @return array
	 */
	public function dp_test_prepare_in(): array {
		return [
			'Empty items'                              => [ '', null, "''" ],
			'Single string item'                       => [ 'id', null, "'id'" ],
			'Array string items'                       => [ [ 'id' ], null, "'id'" ],
			'Array string items with format'           => [ [ 'foo', 'bar' ], '%s', "'foo','bar'" ],
			'Array int items with format'              => [ [ 12, 25 ], '%d', '12,25' ],
			'Array int-like items with format'         => [ [ '12', '25' ], '%d', '12,25' ],
			'Array not int-like items with int format' => [ [ 'foo', 'bar' ], '%d', '0,0' ],
		];
	}
}
