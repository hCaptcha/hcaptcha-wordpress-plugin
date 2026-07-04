<?php
/**
 * InstallTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\Helpers;

use HCaptcha\Helpers\Install;
use HCaptcha\Settings\PluginSettingsBase;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use ReflectionException;

/**
 * Test Installation class.
 *
 * @group helpers
 * @group helpers-install
 */
class InstallTest extends HCaptchaWPTestCase {

	/**
	 * Test constructor registers an activation hook.
	 *
	 * @return void
	 */
	public function test_constructor_registers_activation_hook(): void {
		$subject = new Install();
		$hook    = 'activate_' . plugin_basename( HCAPTCHA_FILE );

		self::assertSame( 10, has_action( $hook, [ $subject, 'activation_hook' ] ) );

		remove_action( $hook, [ $subject, 'activation_hook' ] );
	}

	/**
	 * Test activation_hook().
	 *
	 * @return void
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_activation_hook(): void {
		$subject  = new Install();
		$filter   = static function () {
			return [
				'body' => wp_json_encode(
					[
						'pass'     => true,
						'features' => [ 'custom_theme' => false ],
					]
				),
			];
		};
		$settings = $this->get_protected_property( hcaptcha(), 'settings' );

		$this->set_protected_property( hcaptcha(), 'settings', null );
		add_filter( 'pre_http_request', $filter );

		try {
			$subject->activation_hook();
		} finally {
			remove_filter( 'pre_http_request', $filter );

			if ( null === hcaptcha()->settings() ) {
				$this->set_protected_property( hcaptcha(), 'settings', $settings );
			}
		}

		self::assertNotNull( hcaptcha()->settings() );
		self::assertSame( 'free', get_option( PluginSettingsBase::OPTION_NAME )['license'] );
	}
}
