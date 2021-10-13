<?php
/**
 * MainTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Unit;

use HCaptcha\Main;
use Mockery;
use tad\FunctionMocker\FunctionMocker;

/**
 * Test Main class.
 *
 * @group main
 */
class MainTest extends HCaptchaTestCase {

	/**
	 * Test init().
	 *
	 * @noinspection PhpUndefinedMethodInspection
	 */
	public function test_is_xml_rpc() {
		$mock = Mockery::mock( Main::class )->shouldAllowMockingProtectedMethods()->makePartial();

		self::assertFalse( $mock->is_xml_rpc() );

		FunctionMocker::replace(
			'defined',
			static function ( $constant_name ) {
				return 'XMLRPC_REQUEST' === $constant_name;
			}
		);

		FunctionMocker::replace(
			'constant',
			static function ( $name ) {
				return 'XMLRPC_REQUEST' === $name;
			}
		);

		self::assertTrue( $mock->is_xml_rpc() );
	}
}
