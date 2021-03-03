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
			realpath( HCAPTCHA_PATH . '/hcaptcha.php' ),
			realpath( HCAPTCHA_PATH . '/backend' ),
			realpath( HCAPTCHA_PATH . '/bbp' ),
			realpath( HCAPTCHA_PATH . '/bp' ),
			realpath( HCAPTCHA_PATH . '/cf7' ),
			realpath( HCAPTCHA_PATH . '/common' ),
			realpath( HCAPTCHA_PATH . '/default' ),
			realpath( HCAPTCHA_PATH . '/jetpack' ),
			realpath( HCAPTCHA_PATH . '/mailchimp' ),
			realpath( HCAPTCHA_PATH . '/nf' ),
			realpath( HCAPTCHA_PATH . '/subscriber' ),
			realpath( HCAPTCHA_PATH . '/wc' ),
			realpath( HCAPTCHA_PATH . '/wc_wl' ),
			realpath( HCAPTCHA_PATH . '/wpforms' ),
			realpath( HCAPTCHA_PATH . '/wpforo' ),
			realpath( HCAPTCHA_PATH . '/.tests/php/integration/Stubs' ),
		],
		'redefinable-internals' => [
			'defined',
			'uniqid',
		],
	]
);

require_once HCAPTCHA_PATH . '/.tests/php/integration/Stubs/bp-groups-template.php';
