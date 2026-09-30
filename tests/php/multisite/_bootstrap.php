<?php
/**
 * Bootstrap file for multisite integration tests.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration;

$hcaptcha_path = dirname( __DIR__, 3 );
$loader        = require $hcaptcha_path . '/vendor/autoload.php';

$loader->addPsr4( '', dirname( __DIR__ ) . '/integration/Stubs/', true );
