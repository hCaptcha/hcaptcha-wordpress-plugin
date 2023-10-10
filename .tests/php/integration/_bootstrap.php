<?php
/**
 * Bootstrap file for integration tests that run before all tests.
 *
 * @since   {VERSION}
 * @link    {URL}
 * @license GPLv2 or later
 * @package PluginName
 * @author  {AUTHOR}
 */

use tad\FunctionMocker\FunctionMocker;

$loader = require HCAPTCHA_PATH . '/vendor/autoload.php';
$loader->add( '', HCAPTCHA_PATH . '/.tests/php/integration/Stubs' );

FunctionMocker::init(
	[
		'blacklist'             => [
			realpath( HCAPTCHA_PATH ),
		],
		'whitelist'             => [
			realpath( HCAPTCHA_PATH . '/src/php' ),
			realpath( HCAPTCHA_PATH . '/.tests/php/integration/Stubs' ),
		],
		'redefinable-internals' => [
			'defined',
			'filter_input',
			'time',
			'uniqid',
		],
	]
);
