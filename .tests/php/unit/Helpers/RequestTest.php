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

namespace HCaptcha\Tests\Unit\Helpers;

use HCaptcha\Helpers\Request;
use HCaptcha\Tests\Unit\HCaptchaTestCase;
use Mockery;
use Mockery\Mock;
use tad\FunctionMocker\FunctionMocker;
use WP_Mock;

/**
 * Test Request class.
 *
 * @group helpers
 * @group helpers-request
 */
class RequestTest extends HCaptchaTestCase {

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
		$request_uri            = 'https://test.test/wp-json/some-request/';
		$_SERVER['REQUEST_URI'] = $request_uri;

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

		WP_Mock::userFunction( 'rest_get_url_prefix' )->andreturn( 'wp-json' );

		self::assertTrue( Request::is_rest() );

		// Case #3.
		unset( $_GET['rest_route'] );

		Mockery::mock( 'WP_Rewrite' );

		// Case #4.
		WP_Mock::userFunction( 'add_query_arg' )->with( [] )->andReturnUsing(
			static function ( $args ) use ( &$request_uri ) {
				return $request_uri;
			}
		);
		WP_Mock::userFunction( 'wp_parse_url' )->andReturnUsing(
			static function ( $url, $component ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
				return parse_url( $url, $component );
			}
		);
		WP_Mock::userFunction( 'rest_url' )->with()->andReturn( 'https://test.test/wp-json/' );
		WP_Mock::passthruFunction( 'trailingslashit' );

		self::assertTrue( Request::is_rest() );

		// Not a REST request.
		$request_uri            = 'https://test.test/some-page/';
		$_SERVER['REQUEST_URI'] = $request_uri;

		self::assertFalse( Request::is_rest() );
	}
}
