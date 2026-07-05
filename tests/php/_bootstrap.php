<?php
/**
 * Global Codeception bootstrap file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests;

$hcaptcha_path = dirname( __DIR__, 2 );

require_once $hcaptcha_path . '/vendor/autoload.php';
require_once $hcaptcha_path . '/.codeception/_support/FunctionMockerExtension.php';
