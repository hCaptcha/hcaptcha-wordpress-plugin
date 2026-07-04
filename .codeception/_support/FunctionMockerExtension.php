<?php
/**
 * FunctionMockerExtension class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Codeception;

use Codeception\Extension;
use function HCaptcha\Tests\Integration\hcaptcha_get_function_mocker_external_plugin_whitelist;
use function HCaptcha\Tests\Integration\hcaptcha_init_function_mocker;

require_once dirname( __DIR__, 2 ) . '/tests/php/integration/function-mocker-bootstrap.php';

/**
 * Initialize FunctionMocker before WPLoader boots WordPress.
 */
class FunctionMockerExtension extends Extension {

	/**
	 * Initialize extension.
	 *
	 * @return void
	 */
	public function _initialize(): void {
		parent::_initialize();

		$hcaptcha_path = dirname( __DIR__, 2 );
		$params        = include $hcaptcha_path . '/.codeception/_config/params.php';

		hcaptcha_init_function_mocker(
			$hcaptcha_path,
			hcaptcha_get_function_mocker_external_plugin_whitelist( $params['WP_ROOT_PATH'] ?? '' )
		);
	}
}
