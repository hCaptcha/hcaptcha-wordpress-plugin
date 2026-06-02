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

		delete_option( 'hcaptcha_versions' );

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
		$this->set_server_headers( $headers );

		self::assertSame( $expected, hcap_get_user_ip() );
	}

	/**
	 * Data provider for test_hcap_get_user_ip().
	 */
	public function dp_test_hcap_get_user_ip(): array {
		return [
			'untrusted header'       => [
				[ 'HTTP_TRUE_CLIENT_IP' => '7.7.7.1' ],
				false,
			],
			'untrusted header order' => [
				[
					'HTTP_FORWARDED' => '7.7.7.9',
					'REMOTE_ADDR'    => '7.7.7.10',
				],
				'7.7.7.10',
			],
			'REMOTE_ADDR'            => [
				[ 'REMOTE_ADDR' => '7.7.7.8' ],
				'7.7.7.8',
			],
			'empty'                  => [
				[],
				false,
			],
			'zero IPv4'              => [
				[ 'REMOTE_ADDR' => '0.0.0.0' ],
				false,
			],
			'local IPv4'             => [
				[ 'REMOTE_ADDR' => '127.0.0.1' ],
				false,
			],
			'empty IPv6'             => [
				[ 'REMOTE_ADDR' => '::' ],
				false,
			],
			'address chain'          => [
				[ 'REMOTE_ADDR' => '7.7.7.11, 7.7.7.12' ],
				'7.7.7.11',
			],
		];
	}

	/**
	 * Test hcap_get_user_ip() with trusted address headers.
	 *
	 * @param array        $headers         $_SERVER headers.
	 * @param array        $trusted_headers Trusted headers.
	 * @param string|false $expected        User IP.
	 *
	 * @dataProvider dp_test_hcap_get_user_ip_with_trusted_address_headers
	 */
	public function test_hcap_get_user_ip_with_trusted_address_headers(
		array $headers,
		array $trusted_headers,
		$expected
	): void {
		update_option( 'hcaptcha_settings', [ 'trusted_address_headers' => $trusted_headers ] );
		hcaptcha()->settings()->init();

		$this->set_server_headers( $headers );

		self::assertSame( $expected, hcap_get_user_ip() );
	}

	/**
	 * Data provider for test_hcap_get_user_ip_with_trusted_address_headers().
	 */
	public function dp_test_hcap_get_user_ip_with_trusted_address_headers(): array {
		return [
			'HTTP_TRUE_CLIENT_IP'      => [
				[ 'HTTP_TRUE_CLIENT_IP' => '7.7.7.1' ],
				[ 'HTTP_TRUE_CLIENT_IP' ],
				'7.7.7.1',
			],
			'HTTP_CF_CONNECTING_IP'    => [
				[ 'HTTP_CF_CONNECTING_IP' => '7.7.7.1' ],
				[ 'HTTP_CF_CONNECTING_IP' ],
				'7.7.7.1',
			],
			'HTTP_X_REAL_IP'           => [
				[ 'HTTP_X_REAL_IP' => '7.7.7.1' ],
				[ 'HTTP_X_REAL_IP' ],
				'7.7.7.1',
			],
			'HTTP_CLIENT_IP'           => [
				[ 'HTTP_CLIENT_IP' => '7.7.7.2' ],
				[ 'HTTP_CLIENT_IP' ],
				'7.7.7.2',
			],
			'HTTP_X_FORWARDED_FOR'     => [
				[ 'HTTP_X_FORWARDED_FOR' => '7.7.7.3' ],
				[ 'HTTP_X_FORWARDED_FOR' ],
				'7.7.7.3',
			],
			'HTTP_X_FORWARDED'         => [
				[ 'HTTP_X_FORWARDED' => '7.7.7.4' ],
				[ 'HTTP_X_FORWARDED' ],
				'7.7.7.4',
			],
			'HTTP_X_CLUSTER_CLIENT_IP' => [
				[ 'HTTP_X_CLUSTER_CLIENT_IP' => '7.7.7.5' ],
				[ 'HTTP_X_CLUSTER_CLIENT_IP' ],
				'7.7.7.5',
			],
			'HTTP_FORWARDED_FOR'       => [
				[ 'HTTP_FORWARDED_FOR' => '7.7.7.6' ],
				[ 'HTTP_FORWARDED_FOR' ],
				'7.7.7.6',
			],
			'HTTP_FORWARDED'           => [
				[ 'HTTP_FORWARDED' => '7.7.7.7' ],
				[ 'HTTP_FORWARDED' ],
				'7.7.7.7',
			],
			'Order'                    => [
				[
					'HTTP_FORWARDED' => '7.7.7.9',
					'REMOTE_ADDR'    => '7.7.7.10',
				],
				[ 'HTTP_FORWARDED' ],
				'7.7.7.9',
			],
			'address chain'            => [
				[ 'HTTP_X_FORWARDED_FOR' => '7.7.7.11, 7.7.7.12' ],
				[ 'HTTP_X_FORWARDED_FOR' ],
				'7.7.7.11',
			],
		];
	}

	/**
	 * Test hcap_get_user_ip() with a trusted address headers filter.
	 *
	 * @return void
	 */
	public function test_hcap_get_user_ip_with_trusted_address_headers_filter(): void {
		$filter = static function ( $headers ) {
			$headers[] = 'HTTP_X_REAL_IP';

			return $headers;
		};

		add_filter( 'hcap_trusted_address_headers', $filter );

		$this->set_server_headers(
			[
				'HTTP_X_REAL_IP' => '7.7.7.13',
				'REMOTE_ADDR'    => '7.7.7.14',
			]
		);

		self::assertSame( '7.7.7.13', hcap_get_user_ip() );

		remove_filter( 'hcap_trusted_address_headers', $filter );
	}

	/**
	 * Test hcap_get_user_ip() preserves legacy headers before migration.
	 *
	 * @return void
	 */
	public function test_hcap_get_user_ip_preserves_legacy_headers_before_migration(): void {
		update_option( 'hcaptcha_settings', [ 'site_key' => 'some key' ] );
		update_option( 'hcaptcha_versions', [ '4.26.0' => time() ] );
		hcaptcha()->settings()->init();

		$this->set_server_headers(
			[
				'HTTP_X_FORWARDED_FOR' => '7.7.7.15',
				'REMOTE_ADDR'          => '7.7.7.16',
			]
		);

		self::assertSame( '7.7.7.15', hcap_get_user_ip() );
	}

	/**
	 * Test hcap_get_user_ip() uses REMOTE_ADDR after migration without setting.
	 *
	 * @return void
	 */
	public function test_hcap_get_user_ip_uses_remote_addr_after_migration_without_setting(): void {
		update_option( 'hcaptcha_settings', [ 'site_key' => 'some key' ] );
		update_option( 'hcaptcha_versions', [ '5.0.0' => time() ] );
		hcaptcha()->settings()->init();

		$this->set_server_headers(
			[
				'HTTP_X_FORWARDED_FOR' => '7.7.7.17',
				'REMOTE_ADDR'          => '7.7.7.18',
			]
		);

		self::assertSame( '7.7.7.18', hcap_get_user_ip() );
	}

	/**
	 * Set server headers.
	 *
	 * @param array $headers Headers.
	 *
	 * @return void
	 */
	private function set_server_headers( array $headers ): void {
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
