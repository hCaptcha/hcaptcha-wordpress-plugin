<?php
/**
 * ChangelogTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Unit;

use RuntimeException;
use function hcaptcha_generate_changelog;

require_once dirname( __DIR__, 3 ) . '/.github/scripts/update-changelog.php';

/**
 * Test release-note generation without changing the repository.
 */
class ChangelogTest extends HCaptchaTestCase {

	/**
	 * Replace only the current release and preserve the archived records.
	 */
	public function test_generate_changelog(): void {
		$readme    = "= 9.9.9 =\nDescription.\n\n== Changelog ==\n\n= 5.5.0 =\n* New records.\n\n= 5.4.1 =\n* Readme records.\n";
		$changelog = "= 5.5.0 =\n* Stale records.\n\n= 5.4.1 =\n* Archived records.\n\n= 1.0.0 =\n* First release.\n";
		$expected  = "= 5.5.0 =\n* New records.\n\n= 5.4.1 =\n* Archived records.\n\n= 1.0.0 =\n* First release.\n";

		self::assertSame( $expected, hcaptcha_generate_changelog( $readme, $changelog ) );
		self::assertSame( $expected, hcaptcha_generate_changelog( $readme, $expected ) );
	}

	/**
	 * A new release must retain all existing history and exclude the readme footer.
	 */
	public function test_new_release_with_windows_line_endings(): void {
		$readme    = "== Changelog ==\r\n\r\n= 5.5.0 =\r\n* New records.\r\n\r\n[See changelog for all versions](https://example.com/changelog.txt).\r\n";
		$changelog = "= 5.4.1 =\r\n* Archived records.\r\n";
		$expected  = "= 5.5.0 =\n* New records.\n\n= 5.4.1 =\n* Archived records.\n";

		self::assertSame( $expected, hcaptcha_generate_changelog( $readme, $changelog ) );
	}

	/**
	 * Prerelease notes must stop at the next readme section.
	 */
	public function test_prerelease(): void {
		$readme   = "== Changelog ==\n\n= 5.5.0-rc.1 =\n* Prerelease.\n\n== Upgrade Notice ==\nUnrelated content.\n";
		$expected = "= 5.5.0-rc.1 =\n* Prerelease.\n";

		self::assertSame( $expected, hcaptcha_generate_changelog( $readme, '' ) );
	}

	/**
	 * Missing release notes must fail rather than silently producing stale output.
	 */
	public function test_missing_release_notes(): void {
		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'No release notes found in readme.txt.' );

		hcaptcha_generate_changelog( "== Changelog ==\nNo version.\n", '' );
	}

	/**
	 * Version headings outside the changelog must never become release notes.
	 */
	public function test_missing_changelog_section(): void {
		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'No Changelog section found in readme.txt.' );

		hcaptcha_generate_changelog( "= 5.5.0 =\nDescription.\n", '' );
	}
}
