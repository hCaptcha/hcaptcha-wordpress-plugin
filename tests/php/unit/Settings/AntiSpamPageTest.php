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
use ReflectionClass;
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
	 * Create a page without settings initialization.
	 *
	 * @return AntiSpamPage
	 */
	private function create_subject(): AntiSpamPage {
		return ( new ReflectionClass( AntiSpamPage::class ) )->newInstanceWithoutConstructor();
	}

	/**
	 * Test page labels and the cached sorted country list.
	 *
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_page_labels_and_country_names(): void {
		$subject = $this->create_subject();
		self::assertSame( 'Anti-Spam', $this->set_method_accessibility( $subject, 'page_title' )->invoke( $subject ) );
		self::assertSame( 'anti-spam', $this->set_method_accessibility( $subject, 'section_title' )->invoke( $subject ) );

		$method    = $this->set_method_accessibility( $subject, 'get_country_names' );
		$countries = $method->invoke( $subject );
		self::assertSame( 'Canada', $countries['CA'] );
		self::assertSame( 'Latvia', $countries['LV'] );
		self::assertSame( $countries, $method->invoke( $subject ) );
	}

	/**
	 * Test accepted IPs, ranges, and invalid inputs.
	 *
	 * @param string $input    IP input.
	 * @param bool   $expected Validity.
	 *
	 * @dataProvider dp_test_is_valid_ip_or_range
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_is_valid_ip_or_range( string $input, bool $expected ): void {
		$subject = $this->create_subject();
		$method  = $this->set_method_accessibility( $subject, 'is_valid_ip_or_range' );
		self::assertSame( $expected, $method->invoke( $subject, $input ) );
	}

	/**
	 * IP validation cases.
	 *
	 * @return array
	 */
	public function dp_test_is_valid_ip_or_range(): array {
		return [
			'IPv4'               => [ ' 192.0.2.1 ', true ],
			'IPv6'               => [ '2001:db8::1', true ],
			'IPv4 CIDR'          => [ '192.0.2.0/24', true ],
			'IPv6 CIDR'          => [ '2001:db8::/32', true ],
			'bad CIDR prefix'    => [ '192.0.2.0/33', false ],
			'bad CIDR address'   => [ 'bad/24', false ],
			'IPv4 range'         => [ '192.0.2.1 - 192.0.2.5', true ],
			'IPv6 range'         => [ '2001:db8::1-2001:db8::5', true ],
			'mixed range'        => [ '192.0.2.1-2001:db8::1', false ],
			'bad range endpoint' => [ '192.0.2.1-bad', false ],
			'other input'        => [ 'not an IP', false ],
		];
	}


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

		$subject = Mockery::mock( AntiSpamPage::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
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
