<?php
/**
 * CloudflareDetector class' file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Helpers;

/**
 * Cloudflare detector.
 */
class CloudflareDetector {

	public const STATUS_VERIFIED_REQUEST            = 'verified_request';
	public const STATUS_HOSTNAME_LIKELY_PROXIED     = 'hostname_likely_proxied';
	public const STATUS_HEADERS_DETECTED_UNVERIFIED = 'headers_detected_unverified';
	public const STATUS_NOT_DETECTED                = 'not_detected';

	private const CACHE_TTL = DAY_IN_SECONDS;

	/**
	 * Return Cloudflare detection status.
	 *
	 * @noinspection PhpUnused
	 */
	public static function get_status(): string {
		$context = self::get_context();

		return $context['status'];
	}

	/**
	 * Return Cloudflare detection context for diagnostics UI.
	 */
	public static function get_context(): array {
		$remote_addr    = (string) Request::filter_input( INPUT_SERVER, 'REMOTE_ADDR' );
		$has_cf_headers = self::has_cloudflare_headers();
		$is_cf_remote   = self::ip_in_cloudflare_ranges( $remote_addr );

		if ( $is_cf_remote ) {
			$visitor_ip = (string) Request::filter_input( INPUT_SERVER, 'HTTP_CF_CONNECTING_IP' ) ?: $remote_addr;

			return [
				'status'      => self::STATUS_VERIFIED_REQUEST,
				'confidence'  => 'high',
				'remote_addr' => $remote_addr,
				'visitor_ip'  => $visitor_ip,
				'reason'      => __( 'REMOTE_ADDR is within Cloudflare IP ranges.', 'hcaptcha-for-forms-and-more' ),
				'cf_headers'  => $has_cf_headers,
			];
		}

		$trace_probe = self::get_cdn_cgi_trace_probe();

		if ( ! empty( $trace_probe['success'] ) ) {
			return [
				'status'      => self::STATUS_HOSTNAME_LIKELY_PROXIED,
				'confidence'  => 'medium',
				'remote_addr' => $remote_addr,
				'visitor_ip'  => $remote_addr,
				'reason'      => __( '/cdn-cgi/trace returned a Cloudflare trace response.', 'hcaptcha-for-forms-and-more' ),
				'cf_headers'  => $has_cf_headers,
				'trace_probe' => $trace_probe,
			];
		}

		$dns_probe = self::get_dns_probe();

		if ( ! empty( $dns_probe['success'] ) ) {
			return [
				'status'      => self::STATUS_HOSTNAME_LIKELY_PROXIED,
				'confidence'  => 'medium',
				'remote_addr' => $remote_addr,
				'visitor_ip'  => $remote_addr,
				'reason'      => __( 'The site hostname resolves to Cloudflare IP ranges.', 'hcaptcha-for-forms-and-more' ),
				'cf_headers'  => $has_cf_headers,
				'dns_probe'   => $dns_probe,
			];
		}

		if ( $has_cf_headers ) {
			return [
				'status'      => self::STATUS_HEADERS_DETECTED_UNVERIFIED,
				'confidence'  => 'low',
				'remote_addr' => $remote_addr,
				'visitor_ip'  => $remote_addr,
				'reason'      => __( 'Cloudflare headers are present, but REMOTE_ADDR is not a Cloudflare IP. The server may restore the real visitor IP before PHP, or the headers may be spoofed.', 'hcaptcha-for-forms-and-more' ),
				'cf_headers'  => true,
			];
		}

		return [
			'status'      => self::STATUS_NOT_DETECTED,
			'confidence'  => 'none',
			'remote_addr' => $remote_addr,
			'visitor_ip'  => $remote_addr,
			'reason'      => __( 'Cloudflare was not detected for this request or hostname.', 'hcaptcha-for-forms-and-more' ),
			'cf_headers'  => false,
		];
	}

	/**
	 * Return recommendation for a Cloudflare detection status.
	 *
	 * @param string $status Cloudflare detection status.
	 *
	 * @return string
	 */
	public static function get_recommendation( string $status ): string {
		switch ( $status ) {
			case self::STATUS_VERIFIED_REQUEST:
				return __( 'Cloudflare appears to be proxying this request. Select CF-Connecting-IP and save settings so hCaptcha can use the visitor IP address.', 'hcaptcha-for-forms-and-more' );
			case self::STATUS_HOSTNAME_LIKELY_PROXIED:
				return __( 'Cloudflare appears to be enabled for this site. If Cloudflare proxies traffic to WordPress, select CF-Connecting-IP and save settings.', 'hcaptcha-for-forms-and-more' );
			case self::STATUS_HEADERS_DETECTED_UNVERIFIED:
				return __( 'Cloudflare headers are present, but the request was not verified as coming from Cloudflare. Do not enable CF-Connecting-IP unless direct access to the origin is blocked.', 'hcaptcha-for-forms-and-more' );
			case self::STATUS_NOT_DETECTED:
				return __( 'Cloudflare was not detected. Leave Trusted IP Headers empty unless another trusted proxy or CDN overwrites these headers.', 'hcaptcha-for-forms-and-more' );
			default:
				return '';
		}
	}

	/**
	 * Get allowed Cloudflare detection statuses.
	 *
	 * @return string[]
	 */
	public static function get_statuses(): array {
		return [
			self::STATUS_VERIFIED_REQUEST,
			self::STATUS_HOSTNAME_LIKELY_PROXIED,
			self::STATUS_HEADERS_DETECTED_UNVERIFIED,
			self::STATUS_NOT_DETECTED,
		];
	}

	/**
	 * Check whether an IP belongs to Cloudflare ranges.
	 *
	 * @param string $ip IP address to check.
	 *
	 * @return bool True if IP is in Cloudflare ranges, false otherwise.
	 */
	private static function ip_in_cloudflare_ranges( string $ip ): bool {
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return false;
		}

		foreach ( self::get_cloudflare_ranges() as $cidr ) {
			if ( Request::is_ip_in_range( $ip, $cidr ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get Cloudflare IP ranges.
	 *
	 * Remote requests are cached for 24 hours.
	 */
	private static function get_cloudflare_ranges(): array {
		$cache_key = 'hcaptcha_cloudflare_ip_ranges_v1';
		$cached    = get_transient( $cache_key );

		if ( is_array( $cached ) && $cached ) {
			return $cached;
		}

		$ranges = [];

		foreach ( [ 'https://www.cloudflare.com/ips-v4', 'https://www.cloudflare.com/ips-v6' ] as $url ) {
			$response = wp_remote_get(
				$url,
				[
					'timeout'     => 5,
					'redirection' => 0,
				]
			);

			if ( is_wp_error( $response ) ) {
				continue;
			}

			if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
				continue;
			}

			$body = wp_remote_retrieve_body( $response );

			foreach ( preg_split( '/\R/', $body ) as $line ) {
				$line = trim( $line );

				if ( self::is_valid_cidr( $line ) ) {
					$ranges[] = $line;
				}
			}
		}

		if ( ! $ranges ) {
			$ranges = self::get_fallback_cloudflare_ranges();
		}

		$ranges = array_values( array_unique( $ranges ) );

		set_transient( $cache_key, $ranges, self::CACHE_TTL );

		return $ranges;
	}

	/**
	 * Probe /cdn-cgi/trace for the current site hostname.
	 *
	 * Remote request result is cached for 24 hours.
	 */
	private static function get_cdn_cgi_trace_probe(): array {
		$url       = home_url( '/cdn-cgi/trace' );
		$cache_key = 'hcaptcha_cf_trace_' . md5( $url );
		$cached    = get_transient( $cache_key );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$result = [
			'success' => false,
			'url'     => $url,
			'data'    => [],
			'error'   => '',
		];

		$response = wp_remote_get(
			$url,
			[
				'timeout'     => 5,
				'redirection' => 3,
			]
		);

		if ( is_wp_error( $response ) ) {
			$result['error'] = $response->get_error_message();

			set_transient( $cache_key, $result, self::CACHE_TTL );

			return $result;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = self::parse_cdn_cgi_trace_body( $body );

		$result['http_code'] = $code;
		$result['data']      = $data;

		if ( 200 === $code && self::looks_like_cloudflare_trace( $data ) ) {
			$result['success'] = true;
		}

		set_transient( $cache_key, $result, self::CACHE_TTL );

		return $result;
	}

	/**
	 * Probe public DNS records for the current site hostname.
	 *
	 * Result is cached for 24 hours.
	 */
	private static function get_dns_probe(): array {
		$host = wp_parse_url( home_url(), PHP_URL_HOST );

		if ( ! $host ) {
			return [
				'success' => false,
				'host'    => '',
				'ips'     => [],
				'error'   => 'Unable to parse site host.',
			];
		}

		$cache_key = 'hcaptcha_cf_dns_' . md5( $host );
		$cached    = get_transient( $cache_key );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$ips = self::resolve_host_ips( $host );

		$result = [
			'success' => false,
			'host'    => $host,
			'ips'     => $ips,
		];

		foreach ( $ips as $ip ) {
			if ( self::ip_in_cloudflare_ranges( $ip ) ) {
				$result['success'] = true;
				break;
			}
		}

		set_transient( $cache_key, $result, self::CACHE_TTL );

		return $result;
	}

	/**
	 * Check whether Cloudflare-like headers are present.
	 */
	private static function has_cloudflare_headers(): bool {
		$headers = [
			'HTTP_CF_RAY',
			'HTTP_CF_CONNECTING_IP',
			'HTTP_CF_VISITOR',
			'HTTP_CF_IPCOUNTRY',
		];

		foreach ( $headers as $header ) {
			if ( '' !== (string) Request::filter_input( INPUT_SERVER, $header ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Parse Cloudflare /cdn-cgi/trace response body.
	 *
	 * @param string $body Response body.
	 *
	 * @return array Parsed data.
	 */
	private static function parse_cdn_cgi_trace_body( string $body ): array {
		$data = [];

		foreach ( preg_split( '/\R/', $body ) as $line ) {
			$line = trim( $line );

			if ( '' === $line || ! str_contains( $line, '=' ) ) {
				continue;
			}

			[ $key, $value ] = explode( '=', $line, 2 );

			$key = sanitize_key( $key );

			if ( '' === $key ) {
				continue;
			}

			$data[ $key ] = sanitize_text_field( $value );
		}

		return $data;
	}

	/**
	 * Check whether the parsed /cdn-cgi / trace body looks like Cloudflare trace.
	 *
	 * @param array $data Parsed data.
	 *
	 * @return bool True if looks like Cloudflare trace, false otherwise.
	 */
	private static function looks_like_cloudflare_trace( array $data ): bool {
		if ( empty( $data['h'] ) || empty( $data['ip'] ) || empty( $data['colo'] ) || empty( $data['ts'] ) ) {
			return false;
		}

		if ( ! filter_var( $data['ip'], FILTER_VALIDATE_IP ) ) {
			return false;
		}

		if ( ! preg_match( '/^[A-Z]{3}$/', $data['colo'] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Resolve A and AAAA records for a hostname.
	 *
	 * @param string $host Hostname to resolve.
	 *
	 * @return array List of resolved IP addresses.
	 */
	private static function resolve_host_ips( string $host ): array {
		$ips = [];

		if ( function_exists( 'dns_get_record' ) ) {
			$records = dns_get_record( $host, DNS_A + DNS_AAAA );

			if ( is_array( $records ) ) {
				foreach ( $records as $record ) {
					if ( ! empty( $record['ip'] ) && filter_var( $record['ip'], FILTER_VALIDATE_IP ) ) {
						$ips[] = $record['ip'];
					}

					if ( ! empty( $record['ipv6'] ) && filter_var( $record['ipv6'], FILTER_VALIDATE_IP ) ) {
						$ips[] = $record['ipv6'];
					}
				}
			}
		}

		if ( function_exists( 'gethostbynamel' ) ) {
			$ipv4_records = gethostbynamel( $host );

			if ( is_array( $ipv4_records ) ) {
				foreach ( $ipv4_records as $ip ) {
					if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
						$ips[] = $ip;
					}
				}
			}
		}

		return array_values( array_unique( $ips ) );
	}

	/**
	 * Validate CIDR notation
	 *
	 * @param string $cidr CIDR notation to validate.
	 *
	 * @return bool True if valid, false otherwise.
	 */
	private static function is_valid_cidr( string $cidr ): bool {
		if ( ! str_contains( $cidr, '/' ) ) {
			return false;
		}

		[ $ip, $bits ] = explode( '/', $cidr, 2 );

		if ( false === filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return false;
		}

		if ( ! is_numeric( $bits ) ) {
			return false;
		}

		$bits = (int) $bits;
		$max  = str_contains( $ip, ':' ) ? 128 : 32;

		return $bits >= 0 && $bits <= $max;
	}

	/**
	 * Fallback Cloudflare ranges.
	 */
	private static function get_fallback_cloudflare_ranges(): array {
		return [
			'173.245.48.0/20',
			'103.21.244.0/22',
			'103.22.200.0/22',
			'103.31.4.0/22',
			'141.101.64.0/18',
			'108.162.192.0/18',
			'190.93.240.0/20',
			'188.114.96.0/20',
			'197.234.240.0/22',
			'198.41.128.0/17',
			'162.158.0.0/15',
			'104.16.0.0/13',
			'104.24.0.0/14',
			'172.64.0.0/13',
			'131.0.72.0/22',
			'2400:cb00::/32',
			'2606:4700::/32',
			'2803:f800::/32',
			'2405:b500::/32',
			'2405:8100::/32',
			'2a06:98c0::/29',
			'2c0f:f248::/32',
		];
	}
}
