<?php
/**
 * PluginSettingsBaseTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedMethodInspection */
/** @noinspection PhpArrayShapeAttributeCanBeAddedInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Unit\Settings;

use HCaptcha\Settings\PluginSettingsBase;
use HCaptcha\Tests\Unit\HCaptchaTestCase;
use Mockery;
use ReflectionClass;
use ReflectionException;
use tad\FunctionMocker\FunctionMocker;
use WP_Mock;

/**
 * Class PluginSettingsBaseTest
 *
 * @group settings
 * @group plugin-base
 */
class PluginSettingsBaseTest extends HCaptchaTestCase {

	/**
	 * Test constructor.
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_constructor() {
		$classname = PluginSettingsBase::class;

		$subject = Mockery::mock( $classname )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'is_tab' )->once()->andReturn( true );
		$subject->shouldReceive( 'init' )->once()->with();

		WP_Mock::expectFilterAdded( 'admin_footer_text', [ $subject, 'admin_footer_text' ] );
		WP_Mock::expectFilterAdded( 'update_footer', [ $subject, 'update_footer' ], PHP_INT_MAX );

		$constructor = ( new ReflectionClass( $classname ) )->getConstructor();

		self::assertNotNull( $constructor );

		$constructor->invoke( $subject );
	}

	/**
	 * Test plugin_basename().
	 */
	public function test_plugin_basename() {
		$plugin_file      = '/var/www/wp-content/plugins/hcaptcha-wordpress-plugin/hcaptcha.php';
		$plugin_base_name = 'hcaptcha-wordpress-plugin/hcaptcha.php';

		$subject = Mockery::mock( PluginSettingsBase::class )->makePartial()->shouldAllowMockingProtectedMethods();

		$constant = FunctionMocker::replace( 'constant', $plugin_file );

		WP_Mock::userFunction( 'plugin_basename' )->with( $plugin_file )->once()->andReturn( $plugin_base_name );

		$method = 'plugin_basename';
		self::assertSame( $plugin_base_name, $subject->$method() );
		$constant->wasCalledWithOnce( [ 'HCAPTCHA_FILE' ] );
	}

	/**
	 * Test plugin_url().
	 */
	public function test_plugin_url() {
		$plugin_url = 'http://test.test/wp-content/plugins/hcaptcha-wordpress-plugin';

		$subject = Mockery::mock( PluginSettingsBase::class )->makePartial()->shouldAllowMockingProtectedMethods();

		$constant = FunctionMocker::replace( 'constant', $plugin_url );

		$method = 'plugin_url';
		self::assertSame( $plugin_url, $subject->$method() );
		$constant->wasCalledWithOnce( [ 'HCAPTCHA_URL' ] );
	}

	/**
	 * Test plugin_version().
	 */
	public function test_plugin_version() {
		$plugin_version = '1.0.0';

		$subject = Mockery::mock( PluginSettingsBase::class )->makePartial()->shouldAllowMockingProtectedMethods();

		$constant = FunctionMocker::replace( 'constant', $plugin_version );

		$method = 'plugin_version';
		self::assertSame( $plugin_version, $subject->$method() );
		$constant->wasCalledWithOnce( [ 'HCAPTCHA_VERSION' ] );
	}

	/**
	 * Test settings_link_label().
	 */
	public function test_settings_link_label() {
		$subject = Mockery::mock( PluginSettingsBase::class )->makePartial()->shouldAllowMockingProtectedMethods();

		$method = 'settings_link_label';
		self::assertSame( 'hCaptcha Settings', $subject->$method() );
	}

	/**
	 * Test settings_link_text().
	 */
	public function test_settings_link_text() {
		$subject = Mockery::mock( PluginSettingsBase::class )->makePartial()->shouldAllowMockingProtectedMethods();

		$method = 'settings_link_text';
		self::assertSame( 'Settings', $subject->$method() );
	}

	/**
	 * Test text_domain().
	 */
	public function test_text_domain() {
		$subject = Mockery::mock( PluginSettingsBase::class )->makePartial()->shouldAllowMockingProtectedMethods();

		$method = 'text_domain';
		self::assertSame( 'hcaptcha-for-forms-and-more', $subject->$method() );
	}
}
