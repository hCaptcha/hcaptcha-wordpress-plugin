<?php
/**
 * Loads configuration parameters.
 *
 * Don't modify this file directly.
 * Create and use 'params.local.php' instead.
 *
 * @package hcaptcha-wp
 */

global $argv;

if ( in_array( 'acceptance', $argv, true ) ) {
	return [];
}

if ( in_array( 'unit', $argv, true ) ) {
	return [];
}

$config = '.codeception/_config/params.github-actions.php';
if ( in_array( 'github-actions', $argv, true ) && file_exists( $config ) ) {
	$params = include $config;
} else {
	$config = '.codeception/_config/params.local.php';

	if ( ! file_exists( $config ) ) {
		die( "No valid config provided.\nPlease use 'params.example.php' as a template to create your own 'params.local.php'.\n" );
	}

	$params = include $config;
}

$shard = getenv( 'HCAPTCHA_TEST_SHARD' );

if ( false !== $shard && '' !== $shard ) {
	if ( ! preg_match( '/^[1-9][0-9]*$/', $shard ) ) {
		throw new RuntimeException( 'HCAPTCHA_TEST_SHARD must be a positive integer.' );
	}

	$database_name = $params['DB_NAME'] . '_test_' . $shard;

	if ( 64 < strlen( $database_name ) ) {
		throw new RuntimeException( 'The parallel test database name exceeds the MySQL 64-character limit.' );
	}

	$params['DB_NAME'] = $database_name;
}

return $params;
