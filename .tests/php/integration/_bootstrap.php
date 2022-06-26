<?php
/**
 * Bootstrap file for unit tests that run before all tests.
 *
 * @since   {VERSION}
 * @link    {URL}
 * @license GPLv2 or later
 * @package PluginName
 * @author  {AUTHOR}
 */

use tad\FunctionMocker\FunctionMocker;

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
			'uniqid',
		],
	]
);
