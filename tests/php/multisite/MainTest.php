<?php
/**
 * MainTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpIllegalPsrClassPathInspection */

namespace HCaptcha\Tests\Integration;

use HCaptcha\Main;
use HCaptcha\WP\Signup;
use lucatume\WPBrowser\TestCase\WPTestCase;
use ReflectionException;
use ReflectionProperty;

/**
 * Test Main module definitions on WordPress multisite.
 *
 * Use WPTestCase directly because this suite does not mock functions.
 *
 * @group main
 */
class MainTest extends WPTestCase {

	/**
	 * Test module definitions for multisite registration.
	 *
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_load_modules_on_multisite(): void {
		self::assertTrue( is_multisite() );

		$subject = new Main();
		$active  = new ReflectionProperty( $subject, 'active' );
		$active->setAccessible( true );
		$active->setValue( $subject, false );
		$subject->load_modules();

		self::assertSame( [ [ 'wp_status', 'signup' ], '', Signup::class ], $subject->modules['Signup Form'] );
		self::assertSame(
			[ [ 'theme_my_login_status', 'signup' ], 'theme-my-login/theme-my-login.php', \HCaptcha\ThemeMyLogin\Signup::class ],
			$subject->modules['Theme My Login Signup']
		);
		self::assertArrayNotHasKey( 'Theme My Login Register', $subject->modules );
	}
}
