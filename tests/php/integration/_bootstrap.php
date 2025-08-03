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

$loader->addPsr4( '', __DIR__ . '/Stubs/', true );

FunctionMocker::init(
	[
		'blacklist'             => [
			realpath( HCAPTCHA_PATH ),
		],
		'whitelist'             => [
			realpath( HCAPTCHA_PATH . '/src/php' ),
			realpath( HCAPTCHA_PATH . '/tests/php/integration/Stubs' ),
		],
		'redefinable-internals' => [
			'class_exists',
			'constant',
			'defined',
			'filter_input',
			'function_exists',
			'setcookie',
			'time',
			'uniqid',
		],
	]
);
