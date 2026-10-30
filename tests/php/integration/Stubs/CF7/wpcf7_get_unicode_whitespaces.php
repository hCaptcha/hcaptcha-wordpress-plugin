<?php
/**
 * Optional Contact Form 7 whitespace helper fixture.
 *
 * @package HCaptcha\Tests
 */

/**
 * Return a regex whitespace escape for the isolated compatibility test.
 *
 * @return string
 */
function wpcf7_get_unicode_whitespaces(): string {
	return '\\s';
}
