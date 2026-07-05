<?php
/**
 * WP-CLI exit exception test double.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\CLI;

use RuntimeException;

/**
 * Exception thrown by the WP_CLI test double on errors.
 */
class WPCLIExitException extends RuntimeException {}
