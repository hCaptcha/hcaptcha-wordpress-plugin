<?php
/**
 * RequestTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpLanguageLevelInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Integration\Helpers;

use HCaptcha\Helpers\Request;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use tad\FunctionMocker\FunctionMocker;

/**
 * Test Request class.
 *
 * @group helpers
 * @group helpers-request
 */
class RequestTest extends HCaptchaWPTestCase {

	/**
	 * Tear down test.
	 */
	public function tearDown(): void {
		unset( $_SERVER['REQUEST_URI'], $_GET['rest_route'] );

		parent::tearDown();
	}

	/**
	 * Test is_xml_rpc().
	 */
	public function test_is_xml_rpc(): void {
		self::assertFalse( Request::is_xml_rpc() );

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

		self::assertTrue( Request::is_xml_rpc() );
	}


	/**
	 * Test is_rest().
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function test_is_rest(): void {
		// No REQUEST_URI.
		unset( $_SERVER['REQUEST_URI'] );

		self::assertFalse( Request::is_rest() );

		// Case #1.
		$_SERVER['REQUEST_URI'] = 'https://test.test/wp-json/some-request/';

		FunctionMocker::replace(
			'defined',
			static function ( $constant_name ) {
				return 'REST_REQUEST' === $constant_name;
			}
		);

		FunctionMocker::replace(
			'constant',
			static function ( $constant_name ) {
				return 'REST_REQUEST' === $constant_name;
			}
		);

		self::assertTrue( Request::is_rest() );

		// Case #2.
		FunctionMocker::replace(
			'constant',
			static function ( $constant_name ) {
				return false;
			}
		);

		$route              = '/wp-json/some-route';
		$_GET['rest_route'] = $route;

		FunctionMocker::replace(
			'filter_input',
			static function ( $type, $var_name, $filter ) use ( $route ) {
				if (
					INPUT_GET === $type &&
					'rest_route' === $var_name &&
					FILTER_SANITIZE_FULL_SPECIAL_CHARS === $filter
				) {
					return $route;
				}

				return false;
			}
		);

		self::assertTrue( Request::is_rest() );

		// Case #3.
		unset( $_GET['rest_route'] );

		// Case #4.
		add_filter(
			'rest_url',
			static function ( $url ) {
				return 'https://test.test/wp-json/';
			}
		);

		self::assertTrue( Request::is_rest() );

		// Not a REST request.
		$_SERVER['REQUEST_URI'] = 'https://test.test/some-page/';

		self::assertFalse( Request::is_rest() );
	}
}
