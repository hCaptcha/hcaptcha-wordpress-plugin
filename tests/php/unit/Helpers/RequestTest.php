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
	 * Teardown test.
	 */
	public function tearDown(): void {
		unset( $_GET, $_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD'] );

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

	/**
	 * Test filter_input().
	 *
	 * @return void
	 */
	public function test_filter_input(): void {
		WP_Mock::passthruFunction( 'wp_unslash' );
		WP_Mock::passthruFunction( 'sanitize_text_field' );

		// Test with GET.
		$type              = INPUT_GET;
		$var_name          = 'some_var';
		$value             = 'some_value';
		$_GET[ $var_name ] = $value;

		self::assertSame( '', Request::filter_input( $type, 'wrong_var_name' ) );
		self::assertSame( $value, Request::filter_input( $type, $var_name ) );

		// Test with POST.
		unset( $_GET );
		$type               = INPUT_POST;
		$_POST[ $var_name ] = $value;

		self::assertSame( '', Request::filter_input( $type, 'wrong_var_name' ) );
		self::assertSame( $value, Request::filter_input( $type, $var_name ) );

		// Test with SERVER.
		unset( $_POST );
		$type                 = INPUT_SERVER;
		$_SERVER[ $var_name ] = $value;

		self::assertSame( '', Request::filter_input( $type, 'wrong_var_name' ) );
		self::assertSame( $value, Request::filter_input( $type, $var_name ) );

		// Test with COOKIE.
		unset( $_COOKIE );
		$type                 = INPUT_COOKIE;
		$_COOKIE[ $var_name ] = $value;

		self::assertSame( '', Request::filter_input( $type, 'wrong_var_name' ) );
		self::assertSame( $value, Request::filter_input( $type, $var_name ) );

		// Test with a wrong input type.
		unset( $_SERVER[ $var_name ] );
		$type = 999;

		self::assertSame( '', Request::filter_input( $type, 'wrong_var_name' ) );
		self::assertSame( '', Request::filter_input( $type, $var_name ) );
	}

	/**
	 * Test is_ip_in_range().
	 *
	 * @dataProvider dp_test_is_ip_in_range
	 *
	 * @param string $ip       IP address.
	 * @param string $range    IP range.
	 * @param bool   $expected Expected result.
	 *
	 * @return void
	 */
	public function test_is_ip_in_range( string $ip, string $range, bool $expected ): void {
		$this->assertSame(
			$expected,
			Request::is_ip_in_range( $ip, $range ),
			"Failed asserting that IP '$ip' matches range '$range'"
		);
	}

	/**
	 * Data provider for test_is_ip_in_range().
	 *
	 * @return array[]
	 */
	public function dp_test_is_ip_in_range(): array {
		return [
			// Single IP.
			[ '192.168.1.1', '192.168.1.1', true ],
			[ '192.168.1.2', '192.168.1.1', false ],
			[ '::1', '::1', true ],
			[ '::2', '::1', false ],

			// CIDR (IPv4).
			[ '10.0.0.1', '10.0.0.0/24', true ],
			[ '10.0.1.1', '10.0.0.0/24', false ],
			[ '192.168.0.128', '192.168.0.0/25', false ],
			[ '192.168.0.128', '192.168.0.128/25', true ],

			// CIDR (IPv6).
			[ '2001:db8::1', '2001:db8::/64', true ],
			[ '2001:db8:1::1', '2001:db8::/64', false ],

			// Range (IPv4).
			[ '192.168.1.10', '192.168.1.10-192.168.1.20', true ],
			[ '192.168.1.21', '192.168.1.10-192.168.1.20', false ],

			// Range (IPv6).
			[ '2001:db8::5', '2001:db8::1-2001:db8::f', true ],
			[ '2001:db8::10', '2001:db8::1-2001:db8::f', false ],

			// Mixed types.
			[ '192.168.1.1', '2001:db8::/64', false ], // IPv4 vs IPv6.
			[ '2001:db8::1', '192.168.1.0/24', false ], // IPv6 vs IPv4.

			// Invalid IP/range.
			[ 'not-an-ip', '192.168.1.0/24', false ],
			[ '192.168.1.1', 'not-a-range', false ],

			// Edge cases.
			[ '192.168.1.1', '192.168.1.1-192.168.1.1', true ],
			[ '192.168.1.1', '192.168.1.1/33', false ],
			[ '192.168.1.1', '192.168.1.1/-24', false ],
		];
	}

	/**
	 * Test current_url().
	 *
	 * @param string $home_url    Home URL.
	 * @param string $request_uri Request URI.
	 * @param string $expected    Expected result.
	 *
	 * @return void
	 * @dataProvider dp_test_current_url_returns_expected_url
	 */
	public function test_current_url( string $home_url, string $request_uri, string $expected ): void {
		WP_Mock::userFunction( 'home_url' )->with()->andReturn( $home_url );
		WP_Mock::userFunction( 'wp_parse_url' )->with( $home_url )->andReturnUsing(
			function ( $url, $component = -1 ) use ( $home_url ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
				return parse_url( $home_url, $component );
			}
		);
		WP_Mock::passthruFunction( 'wp_unslash' );
		WP_Mock::passthruFunction( 'sanitize_text_field' );
		WP_Mock::passthruFunction( 'sanitize_url' );

		$_SERVER['REQUEST_URI'] = $request_uri;

		$this->assertEquals( $expected, Request::current_url() );
	}

	/**
	 * Data provider for test_current_url().
	 *
	 * @return array
	 */
	public function dp_test_current_url_returns_expected_url(): array {
		return [
			[ 'https://example.com', '', 'https://example.com' ],
			[ 'https://example.com', '/', 'https://example.com/' ],
			[ 'https://example.com', '/test/path', 'https://example.com/test/path' ],
			[ 'https://example.com:8080', '', 'https://example.com:8080' ],
			[ 'https://example.com:8080', '/test/path', 'https://example.com:8080/test/path' ],
		];
	}
}
