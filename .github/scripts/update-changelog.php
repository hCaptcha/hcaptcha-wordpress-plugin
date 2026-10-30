<?php
/**
 * Generate changelog.txt from the latest readme.txt release notes.
 *
 * @package HCaptcha
 */

/**
 * Replace the latest release section while preserving the archived history.
 *
 * @param string $readme    Readme content.
 * @param string $changelog Changelog content.
 *
 * @return string
 * @throws RuntimeException When the readme has no release notes.
 */
function hcaptcha_generate_changelog( string $readme, string $changelog ): string {
	$readme    = str_replace( [ "\r\n", "\r" ], "\n", $readme );
	$changelog = str_replace( [ "\r\n", "\r" ], "\n", $changelog );

	if ( ! preg_match( '/^== Changelog ==[ \t]*$\n(.*?)(?=^== [^=\n]+ ==[ \t]*$|\z)/ms', $readme, $section ) ) {
		throw new RuntimeException( 'No Changelog section found in readme.txt.' );
	}

	$heading = '^= ([0-9]+\.[0-9]+\.[0-9]+(?:-[^\s=]+)?) =[ \t]*$';

	if ( ! preg_match( '/' . $heading . '\n(.*?)(?=^= [^=\n]+ =[ \t]*$|^\[See changelog for all versions\]|\z)/ms', $section[1], $release ) ) {
		throw new RuntimeException( 'No release notes found in readme.txt.' );
	}

	$history = preg_replace(
		'/^= ' . preg_quote( $release[1], '/' ) . ' =[ \t]*$\n.*?(?=^= [^=\n]+ =[ \t]*$|\z)/ms',
		'',
		$changelog
	);

	return rtrim( '= ' . $release[1] . " =\n" . trim( $release[2] ) . "\n\n" . ltrim( $history, "\n" ), "\n" ) . "\n";
}

/**
 * Read a file from the Git index, including partially staged changes.
 *
 * @param string $file Tracked file name.
 *
 * @return string
 * @throws RuntimeException When the file cannot be read.
 */
function hcaptcha_read_staged_changelog_file( string $file ): string {
	exec( 'git show ' . escapeshellarg( ':' . $file ), $lines, $exit_code );

	if ( 0 !== $exit_code ) {
		throw new RuntimeException( 'Could not read the staged ' . $file . '.' );
	}

	return implode( "\n", $lines ) . "\n";
}

/**
 * Run the generator or check that the committed changelog is current.
 *
 * @param string[] $arguments Command arguments.
 *
 * @return int
 */
function hcaptcha_update_changelog( array $arguments ): int {
	if ( array_diff( $arguments, [ '--check', '--staged', '--generate-only' ] ) ) {
		fwrite( STDERR, "Usage: php .github/scripts/update-changelog.php [--check|--staged]\n" );

		return 1;
	}

	$root = dirname( __DIR__, 2 );
	chdir( $root );

	try {
		if ( in_array( '--staged', $arguments, true ) ) {
			$readme    = hcaptcha_read_staged_changelog_file( 'readme.txt' );
			$changelog = hcaptcha_read_staged_changelog_file( 'changelog.txt' );
		} else {
			$readme    = file_get_contents( $root . '/readme.txt' );
			$changelog = file_get_contents( $root . '/changelog.txt' );
		}

		if ( false === $readme || false === $changelog ) {
			throw new RuntimeException( 'Could not read readme.txt or changelog.txt.' );
		}

		$generated = hcaptcha_generate_changelog( $readme, $changelog );

		if ( in_array( '--check', $arguments, true ) ) {
			if ( $generated !== str_replace( [ "\r\n", "\r" ], "\n", $changelog ) ) {
				fwrite( STDERR, "changelog.txt is out of date. Run composer changelog and commit the result.\n" );

				return 1;
			}

			echo "Changelog is up to date.\n";

			return 0;
		}

		if ( false === file_put_contents( $root . '/changelog.txt', $generated ) ) {
			throw new RuntimeException( 'Could not write changelog.txt.' );
		}
	} catch ( RuntimeException $exception ) {
		fwrite( STDERR, $exception->getMessage() . "\n" );

		return 1;
	}

	echo "Changelog generated from readme.txt.\n";

	return 0;
}

if ( isset( $argv[0] ) && realpath( $argv[0] ) === __FILE__ ) {
	exit( hcaptcha_update_changelog( array_slice( $argv, 1 ) ) );
}
