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
		unset( $_SERVER['REQUEST_URI'], $_GET['wc-ajax'], $_GET['rest_route'], $_SERVER['REQUEST_METHOD'] );

		parent::tearDown();
	}

	/**
	 * Test is_frontend().
	 *
	 * @return void
	 */
	public function test_is_frontend(): void {
		FunctionMocker::replace( '\HCaptcha\Helpers\Request::is_xml_rpc', true );

		self::assertFalse( Request::is_frontend() );

		FunctionMocker::replace( '\HCaptcha\Helpers\Request::is_xml_rpc', false );
		FunctionMocker::replace( '\HCaptcha\Helpers\Request::is_cli', true );

		self::assertFalse( Request::is_frontend() );

		FunctionMocker::replace( '\HCaptcha\Helpers\Request::is_cli', false );
		FunctionMocker::replace( '\HCaptcha\Helpers\Request::is_wc_ajax', true );

		self::assertFalse( Request::is_frontend() );

		FunctionMocker::replace( '\HCaptcha\Helpers\Request::is_wc_ajax', false );
		FunctionMocker::replace( 'is_admin', true );

		self::assertFalse( Request::is_frontend() );

		FunctionMocker::replace( 'is_admin', false );
		FunctionMocker::replace( 'wp_doing_ajax', true );

		self::assertFalse( Request::is_frontend() );

		FunctionMocker::replace( 'wp_doing_ajax', false );
		FunctionMocker::replace( 'wp_doing_cron', true );

		self::assertFalse( Request::is_frontend() );

		FunctionMocker::replace( 'wp_doing_cron', false );
		FunctionMocker::replace( '\HCaptcha\Helpers\Request::is_rest', true );

		self::assertFalse( Request::is_frontend() );

		FunctionMocker::replace( '\HCaptcha\Helpers\Request::is_rest', false );

		self::assertTrue( Request::is_frontend() );
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
	 * Test is_cli().
	 */
	public function test_is_cli(): void {
		self::assertFalse( Request::is_cli() );

		FunctionMocker::replace(
			'defined',
			static function ( $constant_name ) {
				return 'WP_CLI' === $constant_name;
			}
		);

		FunctionMocker::replace(
			'constant',
			static function ( $name ) {
				return 'WP_CLI' === $name;
			}
		);

		self::assertTrue( Request::is_cli() );
	}

	/**
	 * Test is_wc_ajax().
	 *
	 * @return void
	 */
	public function test_is_wp_ajax(): void {
		self::assertFalse( Request::is_wc_ajax() );

		$_GET['wc-ajax'] = 'some-action';

		self::assertTrue( Request::is_wc_ajax() );
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

	/**
	 * Test is_post().
	 *
	 * @return void
	 */
	public function test_is_post(): void {
		self::assertFalse( Request::is_post() );

		$_SERVER['REQUEST_METHOD'] = 'POST';

		WP_Mock::passthruFunction( 'wp_unslash' );

		self::assertTrue( Request::is_post() );
	}
}
