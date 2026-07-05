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

namespace HCaptcha\Tests\Integration;

$hcaptcha_path = dirname( __DIR__, 3 );
$loader        = require $hcaptcha_path . '/vendor/autoload.php';

$loader->addPsr4( '', __DIR__ . '/Stubs/', true );
