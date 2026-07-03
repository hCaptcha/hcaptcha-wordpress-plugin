<?php
/**
 * CloudflareDetectorTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\Helpers;

use HCaptcha\Helpers\CloudflareDetector;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use ReflectionException;
use tad\FunctionMocker\FunctionMocker;
use WP_Error;

/**
 * Test CloudflareDetector class.
 *
 * @group helpers
 * @group helpers-cloudflare-detector
 */
class CloudflareDetectorTest extends HCaptchaWPTestCase {

	/**
	 * Tear down the test.
	 */
	public function tearDown(): void {
		unset(
			$_SERVER['REMOTE_ADDR'],
			$_SERVER['HTTP_CF_RAY'],
			$_SERVER['HTTP_CF_CONNECTING_IP'],
			$_SERVER['HTTP_CF_VISITOR'],
			$_SERVER['HTTP_CF_IPCOUNTRY']
		);

		$this->delete_detector_transients();

		parent::tearDown();
	}

	/**
	 * Test get_statuses() and get_recommendation().
	 *
	 * @return void
	 */
	public function test_get_statuses_and_recommendations(): void {
		$statuses = CloudflareDetector::get_statuses();

		self::assertSame(
			[
				CloudflareDetector::STATUS_VERIFIED_REQUEST,
				CloudflareDetector::STATUS_HOSTNAME_LIKELY_PROXIED,
				CloudflareDetector::STATUS_HEADERS_DETECTED_UNVERIFIED,
				CloudflareDetector::STATUS_NOT_DETECTED,
			],
			$statuses
		);

		foreach ( $statuses as $status ) {
			self::assertNotSame( '', CloudflareDetector::get_recommendation( $status ) );
		}

		self::assertSame( '', CloudflareDetector::get_recommendation( 'unknown' ) );
	}

	/**
	 * Test get_context() for a verified Cloudflare request.
	 *
	 * @return void
	 */
	public function test_get_context_verified_request(): void {
		set_transient( 'hcaptcha_cloudflare_ip_ranges_v1', [ '173.245.48.0/20' ] );

		$_SERVER['REMOTE_ADDR']           = '173.245.48.1';
		$_SERVER['HTTP_CF_CONNECTING_IP'] = '198.51.100.7';

		$context = CloudflareDetector::get_context();

		self::assertSame( CloudflareDetector::STATUS_VERIFIED_REQUEST, $context['status'] );
		self::assertSame( 'high', $context['confidence'] );
		self::assertSame( '173.245.48.1', $context['remote_addr'] );
		self::assertSame( '198.51.100.7', $context['visitor_ip'] );
		self::assertTrue( $context['cf_headers'] );
		self::assertSame( CloudflareDetector::STATUS_VERIFIED_REQUEST, CloudflareDetector::get_status() );
	}

	/**
	 * Test get_context() for a successful /cdn-cgi/trace probe.
	 *
	 * @return void
	 */
	public function test_get_context_trace_probe(): void {
		$filter = static function () {
			return [
				'response' => [ 'code' => 200 ],
				'body'     => "h=test.test\nip=203.0.113.10\ncolo=RIX\nts=123\n",
			];
		};

		set_transient( 'hcaptcha_cloudflare_ip_ranges_v1', [ '173.245.48.0/20' ] );

		$_SERVER['REMOTE_ADDR'] = '198.51.100.10';

		add_filter( 'pre_http_request', $filter );

		try {
			$context = CloudflareDetector::get_context();
		} finally {
			remove_filter( 'pre_http_request', $filter );
		}

		self::assertSame( CloudflareDetector::STATUS_HOSTNAME_LIKELY_PROXIED, $context['status'] );
		self::assertSame( 'medium', $context['confidence'] );
		self::assertTrue( $context['trace_probe']['success'] );
		self::assertFalse( $context['cf_headers'] );
	}

	/**
	 * Test get_context() for a successful DNS probe.
	 *
	 * @return void
	 */
	public function test_get_context_dns_probe(): void {
		set_transient( 'hcaptcha_cloudflare_ip_ranges_v1', [ '203.0.113.0/24' ] );
		set_transient(
			$this->trace_cache_key(),
			[
				'success' => false,
				'url'     => home_url( '/cdn-cgi/trace' ),
				'data'    => [],
				'error'   => '',
			]
		);
		set_transient(
			$this->dns_cache_key(),
			[
				'success' => true,
				'host'    => 'test.test',
				'ips'     => [ '203.0.113.10' ],
			]
		);

		$_SERVER['REMOTE_ADDR'] = '198.51.100.10';

		$context = CloudflareDetector::get_context();

		self::assertSame( CloudflareDetector::STATUS_HOSTNAME_LIKELY_PROXIED, $context['status'] );
		self::assertSame( 'medium', $context['confidence'] );
		self::assertTrue( $context['dns_probe']['success'] );
	}

	/**
	 * Test get_context() for Cloudflare headers without verification.
	 *
	 * @return void
	 */
	public function test_get_context_headers_detected_unverified(): void {
		$this->prepare_negative_context_probes();

		$_SERVER['REMOTE_ADDR'] = '198.51.100.10';
		$_SERVER['HTTP_CF_RAY'] = 'ray-id';

		$context = CloudflareDetector::get_context();

		self::assertSame( CloudflareDetector::STATUS_HEADERS_DETECTED_UNVERIFIED, $context['status'] );
		self::assertSame( 'low', $context['confidence'] );
		self::assertTrue( $context['cf_headers'] );
	}

	/**
	 * Test get_context() when Cloudflare is not detected.
	 *
	 * @return void
	 */
	public function test_get_context_not_detected(): void {
		$this->prepare_negative_context_probes();

		$_SERVER['REMOTE_ADDR'] = '198.51.100.10';

		$context = CloudflareDetector::get_context();

		self::assertSame( CloudflareDetector::STATUS_NOT_DETECTED, $context['status'] );
		self::assertSame( 'none', $context['confidence'] );
		self::assertFalse( $context['cf_headers'] );
	}

	/**
	 * Test get_cloudflare_ranges().
	 *
	 * @return void
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_get_cloudflare_ranges(): void {
		$subject = new CloudflareDetector();
		$method  = $this->set_method_accessibility( $subject, 'get_cloudflare_ranges' );

		set_transient( 'hcaptcha_cloudflare_ip_ranges_v1', [ '203.0.113.0/24' ] );

		self::assertSame( [ '203.0.113.0/24' ], $method->invoke( null ) );

		delete_transient( 'hcaptcha_cloudflare_ip_ranges_v1' );

		$success_filter = static function ( $preempt, $parsed_args, $url ) {
			if ( 'https://www.cloudflare.com/ips-v4' === $url ) {
				return [
					'response' => [ 'code' => 200 ],
					'body'     => "203.0.113.0/24\ninvalid\n203.0.113.0/24\n",
				];
			}

			if ( 'https://www.cloudflare.com/ips-v6' === $url ) {
				return [
					'response' => [ 'code' => 200 ],
					'body'     => "2001:db8::/32\n",
				];
			}

			return null;
		};

		add_filter( 'pre_http_request', $success_filter, 10, 3 );

		try {
			self::assertSame( [ '203.0.113.0/24', '2001:db8::/32' ], $method->invoke( null ) );
		} finally {
			remove_filter( 'pre_http_request', $success_filter );
		}

		delete_transient( 'hcaptcha_cloudflare_ip_ranges_v1' );

		$fallback_filter = static function ( $preempt, $parsed_args, $url ) {
			if ( 'https://www.cloudflare.com/ips-v4' === $url ) {
				return new WP_Error( 'download-error', 'Download failed.' );
			}

			return [
				'response' => [ 'code' => 500 ],
				'body'     => '',
			];
		};

		add_filter( 'pre_http_request', $fallback_filter, 10, 3 );

		try {
			$ranges = $method->invoke( null );
		} finally {
			remove_filter( 'pre_http_request', $fallback_filter );
		}

		self::assertContains( '173.245.48.0/20', $ranges );
	}

	/**
	 * Test get_cdn_cgi_trace_probe().
	 *
	 * @return void
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_get_cdn_cgi_trace_probe(): void {
		$subject = new CloudflareDetector();
		$method  = $this->set_method_accessibility( $subject, 'get_cdn_cgi_trace_probe' );
		$cached  = [
			'success' => true,
			'url'     => home_url( '/cdn-cgi/trace' ),
			'data'    => [ 'h' => 'cached' ],
			'error'   => '',
		];

		set_transient( $this->trace_cache_key(), $cached );

		self::assertSame( $cached, $method->invoke( null ) );

		delete_transient( $this->trace_cache_key() );

		$this->assert_trace_probe_response(
			new WP_Error( 'trace-error', 'Trace failed.' ),
			[
				'success' => false,
				'error'   => 'Trace failed.',
			]
		);

		$this->assert_trace_probe_response(
			[
				'response' => [ 'code' => 404 ],
				'body'     => 'not-a-trace',
			],
			[
				'success'   => false,
				'http_code' => 404,
			]
		);

		$this->assert_trace_probe_response(
			[
				'response' => [ 'code' => 200 ],
				'body'     => "h=test.test\nip=203.0.113.10\ncolo=RIX\nts=123\n",
			],
			[
				'success'   => true,
				'http_code' => 200,
			]
		);
	}

	/**
	 * Test get_dns_probe().
	 *
	 * @return void
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_get_dns_probe(): void {
		$subject = new CloudflareDetector();
		$method  = $this->set_method_accessibility( $subject, 'get_dns_probe' );
		$cached  = [
			'success' => true,
			'host'    => 'test.test',
			'ips'     => [ '203.0.113.10' ],
		];

		set_transient( $this->dns_cache_key(), $cached );

		self::assertSame( $cached, $method->invoke( null ) );

		delete_transient( $this->dns_cache_key() );
		set_transient( 'hcaptcha_cloudflare_ip_ranges_v1', [ '203.0.113.0/24' ] );

		$this->mock_resolvers(
			[
				[ 'ip' => '203.0.113.10' ],
			],
			[]
		);

		$result = $method->invoke( null );

		self::assertTrue( $result['success'] );
		self::assertSame( [ '203.0.113.10' ], $result['ips'] );

		$home_url_filter = static function () {
			return '';
		};

		delete_transient( $this->dns_cache_key() );
		add_filter( 'home_url', $home_url_filter );

		try {
			self::assertSame(
				[
					'success' => false,
					'host'    => '',
					'ips'     => [],
					'error'   => 'Unable to parse site host.',
				],
				$method->invoke( null )
			);
		} finally {
			remove_filter( 'home_url', $home_url_filter );
		}
	}

	/**
	 * Test private helpers.
	 *
	 * @return void
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_private_helpers(): void {
		$subject = new CloudflareDetector();

		$parse_trace_body = $this->set_method_accessibility( $subject, 'parse_cdn_cgi_trace_body' );
		$looks_like_trace = $this->set_method_accessibility( $subject, 'looks_like_cloudflare_trace' );
		$is_valid_cidr    = $this->set_method_accessibility( $subject, 'is_valid_cidr' );
		$ip_in_ranges     = $this->set_method_accessibility( $subject, 'ip_in_cloudflare_ranges' );
		$fallback_ranges  = $this->set_method_accessibility( $subject, 'get_fallback_cloudflare_ranges' );

		$data = $parse_trace_body->invoke(
			null,
			"\nno-equals\nh=test.test\nip=203.0.113.10\ncolo=RIX\nts=123\nbad key=value\n!!!=skip\n"
		);

		self::assertSame( 'test.test', $data['h'] );
		self::assertSame( 'value', $data['badkey'] );
		self::assertArrayNotHasKey( '', $data );

		self::assertFalse( $looks_like_trace->invoke( null, [] ) );
		self::assertFalse(
			$looks_like_trace->invoke(
				null,
				[
					'h'    => 'test.test',
					'ip'   => 'not-an-ip',
					'colo' => 'RIX',
					'ts'   => '123',
				]
			)
		);
		self::assertFalse(
			$looks_like_trace->invoke(
				null,
				[
					'h'    => 'test.test',
					'ip'   => '203.0.113.10',
					'colo' => 'RIGA',
					'ts'   => '123',
				]
			)
		);
		self::assertTrue( $looks_like_trace->invoke( null, $data ) );

		self::assertFalse( $is_valid_cidr->invoke( null, '203.0.113.1' ) );
		self::assertFalse( $is_valid_cidr->invoke( null, 'not-an-ip/24' ) );
		self::assertFalse( $is_valid_cidr->invoke( null, '203.0.113.0/nope' ) );
		self::assertFalse( $is_valid_cidr->invoke( null, '203.0.113.0/33' ) );
		self::assertTrue( $is_valid_cidr->invoke( null, '203.0.113.0/24' ) );
		self::assertTrue( $is_valid_cidr->invoke( null, '2001:db8::/32' ) );

		set_transient( 'hcaptcha_cloudflare_ip_ranges_v1', [ '203.0.113.0/24' ] );

		self::assertFalse( $ip_in_ranges->invoke( null, 'not-an-ip' ) );
		self::assertFalse( $ip_in_ranges->invoke( null, '198.51.100.10' ) );
		self::assertTrue( $ip_in_ranges->invoke( null, '203.0.113.10' ) );

		self::assertContains( '173.245.48.0/20', $fallback_ranges->invoke( null ) );
	}

	/**
	 * Test resolve_host_ips().
	 *
	 * @return void
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_resolve_host_ips(): void {
		$subject = new CloudflareDetector();
		$method  = $this->set_method_accessibility( $subject, 'resolve_host_ips' );

		$this->mock_resolvers(
			[
				[ 'ip' => '203.0.113.10' ],
				[ 'ip' => 'not-an-ip' ],
				[ 'ipv6' => '2001:db8::1' ],
			],
			[ '203.0.113.10', '203.0.113.11', 'not-an-ip' ]
		);

		self::assertSame(
			[ '203.0.113.10', '2001:db8::1', '203.0.113.11' ],
			$method->invoke( null, 'example.com' )
		);

		FunctionMocker::replace( 'function_exists', false );

		self::assertSame( [], $method->invoke( null, 'example.com' ) );
	}

	/**
	 * Mock DNS resolvers.
	 *
	 * @param array $dns_records DNS records.
	 * @param array $host_records Host records.
	 *
	 * @return void
	 */
	private function mock_resolvers( array $dns_records, array $host_records ): void {
		$dns_get_record = static function () use ( $dns_records ) {
			return $dns_records;
		};
		$gethostbynamel = static function () use ( $host_records ) {
			return $host_records;
		};

		FunctionMocker::replace(
			'function_exists',
			static function ( $function_name ) {
				return in_array( $function_name, [ 'dns_get_record', 'gethostbynamel' ], true );
			}
		);
		FunctionMocker::replace( 'HCaptcha\Helpers\dns_get_record', $dns_get_record );
		FunctionMocker::replace( 'HCaptcha\Helpers\gethostbynamel', $gethostbynamel );
	}

	/**
	 * Assert a trace probe response.
	 *
	 * @param array|WP_Error $response HTTP response.
	 * @param array          $expected Expected partial result.
	 *
	 * @return void
	 * @throws ReflectionException Reflection exception.
	 */
	private function assert_trace_probe_response( $response, array $expected ): void {
		$subject = new CloudflareDetector();
		$method  = $this->set_method_accessibility( $subject, 'get_cdn_cgi_trace_probe' );
		$filter  = static function () use ( $response ) {
			return $response;
		};

		delete_transient( $this->trace_cache_key() );
		add_filter( 'pre_http_request', $filter );

		try {
			$result = $method->invoke( null );
		} finally {
			remove_filter( 'pre_http_request', $filter );
		}

		foreach ( $expected as $key => $value ) {
			self::assertSame( $value, $result[ $key ] );
		}
	}

	/**
	 * Prepare negative probes for context tests.
	 *
	 * @return void
	 */
	private function prepare_negative_context_probes(): void {
		set_transient( 'hcaptcha_cloudflare_ip_ranges_v1', [ '203.0.113.0/24' ] );
		set_transient(
			$this->trace_cache_key(),
			[
				'success' => false,
				'url'     => home_url( '/cdn-cgi/trace' ),
				'data'    => [],
				'error'   => '',
			]
		);
		set_transient(
			$this->dns_cache_key(),
			[
				'success' => false,
				'host'    => 'test.test',
				'ips'     => [],
			]
		);
	}

	/**
	 * Delete detector transients.
	 *
	 * @return void
	 */
	private function delete_detector_transients(): void {
		delete_transient( 'hcaptcha_cloudflare_ip_ranges_v1' );
		delete_transient( $this->trace_cache_key() );
		delete_transient( $this->dns_cache_key() );
	}

	/**
	 * Get a trace cache key.
	 *
	 * @return string
	 */
	private function trace_cache_key(): string {
		return 'hcaptcha_cf_trace_' . md5( home_url( '/cdn-cgi/trace' ) );
	}

	/**
	 * Get DNS cache key.
	 *
	 * @return string
	 */
	private function dns_cache_key(): string {
		return 'hcaptcha_cf_dns_' . md5( 'test.test' );
	}
}
