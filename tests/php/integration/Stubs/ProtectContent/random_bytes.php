<?php
/**
 * Entropy failure fixture for the isolated ProtectContent test.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\ProtectContent;

use Exception;

/**
 * Simulate a failed entropy source in the isolated test process.
 *
 * @param int $length Requested byte count.
 *
 * @throws Exception Always throws.
 * @noinspection ThrowRawExceptionInspection
 * @noinspection PhpUnusedParameterInspection
 */
function random_bytes( int $length ) {
	throw new Exception( 'entropy unavailable' );
}
