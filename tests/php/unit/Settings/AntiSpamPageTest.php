<?php
/**
 * AntiSpamPageTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Unit\Settings;

use HCaptcha\Settings\AntiSpamPage;
use HCaptcha\Settings\PluginSettingsBase;
use HCaptcha\Tests\Unit\HCaptchaTestCase;
use Mockery;
use ReflectionException;
use tad\FunctionMocker\FunctionMocker;
use WP_Mock;

/**
 * Class AntiSpamPageTest.
 *
 * @group settings
 * @group settings-anti-spam
 */
class AntiSpamPageTest extends HCaptchaTestCase {

	/**
	 * Test authenticated XML-RPC setting definition.
	 *
	 * @return void
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_authenticated_xml_rpc_setting(): void {
		FunctionMocker::replace( 'is_multisite', false );

		WP_Mock::userFunction( 'get_option' )
			->once()
			->with( PluginSettingsBase::OPTION_NAME, [] )
			->andReturn( [] );

		$subject = Mockery::mock( AntiSpamPage::class )->makePartial()->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'option_name' )->andReturn( PluginSettingsBase::OPTION_NAME );
		$subject->init_form_fields();

		$form_fields = $this->get_protected_property( $subject, 'form_fields' );
		$field       = $form_fields[ AntiSpamPage::DISABLE_XML_RPC_AUTH ];

		self::assertArrayNotHasKey( 'label', $field );
		self::assertSame( 'checkbox', $field['type'] );
		self::assertSame( AntiSpamPage::SECTION_LOGIN_PROTECTION, $field['section'] );
		self::assertSame( [ 'on' => 'Disable Authenticated XML-RPC' ], $field['options'] );
		self::assertSame(
			'Block XML-RPC methods that require authentication. Public methods, such as pingbacks, remain available.',
			$field['helper']
		);
	}
}
