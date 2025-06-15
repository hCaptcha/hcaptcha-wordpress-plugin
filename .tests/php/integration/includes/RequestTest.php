<?php
/**
 * RequestTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\includes;

use HCaptcha\Tests\Integration\HCaptchaWPTestCase;

/**
 * Test request file.
 *
 * @group request
 */
class RequestTest extends HCaptchaWPTestCase {

	/**
	 * Tear down the test.
	 */
	public function tearDown(): void {
		unset(
			$_SERVER['HTTP_TRUE_CLIENT_IP'],
			$_SERVER['HTTP_CF_CONNECTING_IP'],
			$_SERVER['HTTP_X_REAL_IP'],
			$_SERVER['HTTP_CLIENT_IP'],
			$_SERVER['HTTP_X_FORWARDED_FOR'],
			$_SERVER['HTTP_X_FORWARDED'],
			$_SERVER['HTTP_X_CLUSTER_CLIENT_IP'],
			$_SERVER['HTTP_FORWARDED_FOR'],
			$_SERVER['HTTP_FORWARDED'],
			$_SERVER['REMOTE_ADDR'],
			$GLOBALS['current_user']
		);

		parent::tearDown();
	}

	/**
	 * Test hcap_get_user_ip().
	 *
	 * @param array        $headers  $_SERVER headers.
	 * @param string|false $expected User IP.
	 *
	 * @dataProvider dp_test_hcap_get_user_ip
	 */
	public function test_hcap_get_user_ip( array $headers, $expected ): void {
		unset(
			$_SERVER['HTTP_TRUE_CLIENT_IP'],
			$_SERVER['HTTP_CF_CONNECTING_IP'],
			$_SERVER['HTTP_X_REAL_IP'],
			$_SERVER['HTTP_CLIENT_IP'],
			$_SERVER['HTTP_X_FORWARDED_FOR'],
			$_SERVER['HTTP_X_FORWARDED'],
			$_SERVER['HTTP_X_CLUSTER_CLIENT_IP'],
			$_SERVER['HTTP_FORWARDED_FOR'],
			$_SERVER['HTTP_FORWARDED'],
			$_SERVER['REMOTE_ADDR']
		);

		foreach ( $headers as $header => $ip ) {
			$_SERVER[ $header ] = $ip;
		}

		self::assertSame( $expected, hcap_get_user_ip() );
	}

	/**
	 * Data provider for test_hcap_get_user_ip().
	 */
	public function dp_test_hcap_get_user_ip(): array {
		return [
			'HTTP_TRUE_CLIENT_IP'      => [
				[ 'HTTP_TRUE_CLIENT_IP' => '7.7.7.1' ],
				'7.7.7.1',
			],
			'HTTP_CF_CONNECTING_IP'    => [
				[ 'HTTP_CF_CONNECTING_IP' => '7.7.7.1' ],
				'7.7.7.1',
			],
			'HTTP_X_REAL_IP'           => [
				[ 'HTTP_X_REAL_IP' => '7.7.7.1' ],
				'7.7.7.1',
			],
			'HTTP_CLIENT_IP'           => [
				[ 'HTTP_CLIENT_IP' => '7.7.7.2' ],
				'7.7.7.2',
			],
			'HTTP_X_FORWARDED_FOR'     => [
				[ 'HTTP_X_FORWARDED_FOR' => '7.7.7.3' ],
				'7.7.7.3',
			],
			'HTTP_X_FORWARDED'         => [
				[ 'HTTP_X_FORWARDED' => '7.7.7.4' ],
				'7.7.7.4',
			],
			'HTTP_X_CLUSTER_CLIENT_IP' => [
				[ 'HTTP_X_CLUSTER_CLIENT_IP' => '7.7.7.5' ],
				'7.7.7.5',
			],
			'HTTP_FORWARDED_FOR'       => [
				[ 'HTTP_FORWARDED_FOR' => '7.7.7.6' ],
				'7.7.7.6',
			],
			'HTTP_FORWARDED'           => [
				[ 'HTTP_FORWARDED' => '7.7.7.7' ],
				'7.7.7.7',
			],
			'REMOTE_ADDR'              => [
				[ 'REMOTE_ADDR' => '7.7.7.8' ],
				'7.7.7.8',
			],
			'Order'                    => [
				[
					'HTTP_FORWARDED' => '7.7.7.9',
					'REMOTE_ADDR'    => '7.7.7.10',
				],
				'7.7.7.9',
			],
			'empty'                    => [
				[],
				false,
			],
			'zero IPv4'                => [
				[ 'REMOTE_ADDR' => '0.0.0.0' ],
				false,
			],
			'local IPv4'               => [
				[ 'REMOTE_ADDR' => '127.0.0.1' ],
				false,
			],
			'empty IPv6'               => [
				[ 'REMOTE_ADDR' => '::' ],
				false,
			],
			'address chain'            => [
				[ 'REMOTE_ADDR' => '7.7.7.11, 7.7.7.12' ],
				'7.7.7.11',
			],
		];
	}

	/**
	 * Test hcap_get_error_message().
	 *
	 * @return void
	 */
	public function test_hcap_get_error_message(): void {
		self::assertSame( '', hcap_get_error_message( 'wrong-error-code' ) );
		self::assertSame(
			'hCaptcha error: The request is invalid or malformed.',
			hcap_get_error_message( 'bad-request' )
		);
		self::assertSame(
			'hCaptcha errors: Your secret key is missing.; The hCaptcha is invalid.',
			hcap_get_error_message( [ 'missing-input-secret', 'fail' ] )
		);
	}

	/**
	 * Test hcap_check_site_config.
	 *
	 * @return void
	 */
	public function test_hcap_check_site_config(): void {
		add_filter(
			'pre_http_request',
			static function ( $value, $parsed_args, $url ) use ( &$result ) {
				if ( false !== strpos( $url, 'hcaptcha.com' ) ) {
					return null === $result ? [] : [ 'body' => wp_json_encode( $result ) ];
				}

				return $value;
			},
			10,
			3
		);

		// Cannot communicate.
		$result   = null;
		$expected = [
			'error' => 'Cannot communicate with hCaptcha server.',
		];

		self::assertSame( $expected, hcap_check_site_config() );

		// Cannot decode.
		$result   = [];
		$expected = [
			'error' => 'Cannot decode hCaptcha server response.',
		];

		self::assertSame( $expected, hcap_check_site_config() );

		// Error.
		$error    = 'some error';
		$result   = [
			'pass'  => false,
			'error' => $error,
		];
		$expected = [
			'error' => $error,
		];

		self::assertSame( $expected, hcap_check_site_config() );

		// Success.
		$result   = [
			'pass' => true,
		];
		$expected = $result;

		self::assertSame( $expected, hcap_check_site_config() );
	}
}
