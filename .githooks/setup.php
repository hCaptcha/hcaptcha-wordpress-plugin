<?php
/**
 * Install repository hooks for local checkouts and worktrees.
 *
 * @package HCaptcha
 */

$root = dirname( __DIR__ );

// A worktree has a .git file instead of a directory.
if ( ! file_exists( $root . '/.git' ) ) {
	echo "Skipping Git hooks outside a Git checkout.\n";
	exit( 0 );
}

chdir( $root );

exec( 'git config --local core.hooksPath .githooks', $output, $exit_code );

if ( 0 !== $exit_code ) {
	exit( $exit_code );
}

exec( 'git config --local hcaptcha.phpPath ' . escapeshellarg( str_replace( '\\', '/', PHP_BINARY ) ), $output, $exit_code );

if ( 0 !== $exit_code ) {
	exit( $exit_code );
}

if ( '\\' !== DIRECTORY_SEPARATOR && ! chmod( $root . '/.githooks/pre-commit', 0755 ) ) {
	fwrite( STDERR, "Could not make the pre-commit hook executable.\n" );
	exit( 1 );
}

echo "Git hooks installed.\n";
