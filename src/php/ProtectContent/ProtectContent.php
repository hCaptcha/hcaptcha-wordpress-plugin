<?php
/**
 * ProtectContent class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\ProtectContent;

use HCaptcha\Helpers\API;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Helpers\Request;

/**
 * Class ProtectContent
 */
class ProtectContent {

	/**
	 * Nonce action.
	 */
	protected const ACTION = 'hcaptcha_protect_content';

	/**
	 * Nonce name.
	 */
	protected const NONCE = 'hcaptcha_protect_content_nonce';

	/**
	 * Cookie name.
	 */
	private const COOKIE_NAME = 'hcaptcha_content_protection';

	/**
	 * Browser session cookie name.
	 */
	private const SESSION_COOKIE_NAME = 'hcaptcha_content_protection_session';

	/**
	 * Cookie expiration.
	 *
	 * 5 minutes in seconds.
	 */
	private const COOKIE_EXPIRATION = 5 * MINUTE_IN_SECONDS;

	/**
	 * Number of random bytes in a clearance or session token.
	 */
	private const TOKEN_BYTES = 32;

	/**
	 * Error message.
	 *
	 * @var string
	 */
	protected string $error_message = '';

	/**
	 * Request URI.
	 *
	 * @var string
	 */
	protected string $request_uri = '';

	/**
	 * Matched protected URL rule.
	 *
	 * Each configured rule is an authorization scope. The longest matching rule
	 * allows refreshes and navigation within that set without unlocking another
	 * configured set.
	 *
	 * @var string
	 */
	protected string $resource_scope = '';

	/**
	 * Init class.
	 *
	 * @return void
	 */
	public function init(): void {
		$this->resource_scope = '';

		if ( ! Request::is_frontend() ) {
			return;
		}

		$settings = hcaptcha()->settings();

		if ( ! $settings || ! $settings->is_on( 'protect_content' ) ) {
			return;
		}

		// Do not use Request::filter_input() here: sanitize_text_field() strips
		// percent-encoded octets and can make routed URLs miss the protection list.
		$request_uri = '';

		if ( isset( $_SERVER['REQUEST_URI'] ) && is_string( $_SERVER['REQUEST_URI'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$request_uri = wp_unslash( $_SERVER['REQUEST_URI'] );
		}

		$this->request_uri = $this->normalize_url( $request_uri );
		$request_uri       = $this->normalize_url( $request_uri, true );

		$protected_urls = explode( "\n", $settings->get( 'protected_urls' ) );
		$protected_urls = array_filter( array_map( 'trim', $protected_urls ) );
		$protected_urls = $protected_urls ?: [ '/' ]; // Protect all URLs by default.

		foreach ( $protected_urls as $url ) {
			if (
				preg_match( '!' . preg_quote( $url, '!' ) . '!i', $request_uri ) &&
				strlen( $url ) > strlen( $this->resource_scope )
			) {
				$this->resource_scope = $url;
			}
		}

		if ( '' === $this->resource_scope ) {
			return;
		}

		$this->init_hooks();
	}

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		add_action( 'template_redirect', [ $this, 'protect_content' ], -PHP_INT_MAX );
	}

	/**
	 * Protect site content.
	 *
	 * @return void
	 */
	public function protect_content(): void {
		if ( $this->is_valid_cookie() ) {
			return;
		}

		$this->ensure_session_cookie();

		if ( 'post' === strtolower( Request::filter_input( INPUT_SERVER, 'REQUEST_METHOD' ) ) ) {
			$this->error_message = $this->verify();
		}

		$this->show_protection_page();
	}

	/**
	 * Verify hCaptcha.
	 *
	 * @return string
	 */
	protected function verify(): string {
		$settings = hcaptcha()->settings();

		// It is always too fast with Pro.
		$settings && $settings->set( 'set_min_submit_time', [ '' ] );

		$error_message = API::verify_post( self::NONCE, self::ACTION );

		if ( null === $error_message ) {
			$session_id = $this->get_session_id();

			if ( '' === $session_id || '' === $this->resource_scope ) {
				return __( 'Your browser session could not be established. Please try again.', 'hcaptcha-for-forms-and-more' );
			}

			$time   = time();
			$cookie = $this->create_clearance( $session_id, $time );

			$this->setcookie(
				self::COOKIE_NAME,
				$cookie,
				[
					'expires'  => $time + self::COOKIE_EXPIRATION,
					'path'     => '/',
					'secure'   => is_ssl(),
					'httponly' => true,
					'samesite' => 'Lax',
				]
			);

			wp_safe_redirect( $this->request_uri );
		}

		return (string) $error_message;
	}

	/**
	 * Check whether the cookie is valid.
	 *
	 * @return bool
	 */
	protected function is_valid_cookie(): bool {
		$cookie     = $this->get_cookie( self::COOKIE_NAME );
		$session_id = $this->get_session_id();
		$parts      = explode( '.', $cookie );

		if (
			6 !== count( $parts ) ||
			'v2' !== $parts[0] ||
			'' === $session_id ||
			'' === $this->resource_scope
		) {
			return false;
		}

		[ , $expires, $token, $session_binding, $scope_binding, $signature ] = $parts;

		if (
			! preg_match( '/^[0-9]{1,12}$/D', $expires ) ||
			! preg_match( '/^[a-f0-9]{64}$/D', $token ) ||
			! preg_match( '/^[a-f0-9]{64}$/D', $session_binding ) ||
			! preg_match( '/^[a-f0-9]{64}$/D', $scope_binding ) ||
			! preg_match( '/^[a-f0-9]{64}$/D', $signature )
		) {
			return false;
		}

		$payload = implode( '.', array_slice( $parts, 0, 5 ) );

		if ( ! hash_equals( $this->sign_clearance( $payload ), $signature ) ) {
			return false;
		}

		$expires = (int) $expires;
		$now     = time();

		if ( $expires <= $now || $expires > $now + self::COOKIE_EXPIRATION ) {
			return false;
		}

		return hash_equals( $this->hash_binding( 'session', $session_id ), $session_binding ) &&
			hash_equals( $this->hash_binding( 'scope', $this->resource_scope ), $scope_binding );
	}

	/**
	 * Establish the independent browser credential before a challenge succeeds.
	 *
	 * Copying a later clearance response does not copy this credential. Theft of
	 * both cookies is equivalent to theft of the browser session and is outside
	 * the clearance-only replay protection model.
	 *
	 * @return void
	 */
	protected function ensure_session_cookie(): void {
		if ( '' !== $this->get_session_id() ) {
			return;
		}

		$this->setcookie(
			self::SESSION_COOKIE_NAME,
			'v1.' . $this->generate_token(),
			[
				'expires'  => 0,
				'path'     => '/',
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			]
		);
	}

	/**
	 * Get the browser session ID from the request.
	 *
	 * @return string
	 */
	private function get_session_id(): string {
		$session_id = $this->get_cookie( self::SESSION_COOKIE_NAME );

		return preg_match( '/^v1\.[a-f0-9]{64}$/D', $session_id ) ? $session_id : '';
	}

	/**
	 * Get a scalar cookie value.
	 *
	 * @param string $name Cookie name.
	 *
	 * @return string
	 */
	private function get_cookie( string $name ): string {
		$value = Request::filter_input( INPUT_COOKIE, $name );

		return is_string( $value ) ? $value : '';
	}

	/**
	 * Generate an unpredictable token.
	 *
	 * @return string
	 */
	protected function generate_token(): string {
		return bin2hex( random_bytes( self::TOKEN_BYTES ) );
	}

	/**
	 * Create a signed, session- and resource-bound clearance.
	 *
	 * The payload is stateless on the server and cannot outlive its embedded
	 * five-minute expiry, so successful challenges create no records to prune.
	 *
	 * @param string $session_id Browser session ID.
	 * @param int    $time       Issuance time.
	 *
	 * @return string
	 */
	private function create_clearance( string $session_id, int $time ): string {
		$payload = implode(
			'.',
			[
				'v2',
				(string) ( $time + self::COOKIE_EXPIRATION ),
				$this->generate_token(),
				$this->hash_binding( 'session', $session_id ),
				$this->hash_binding( 'scope', $this->resource_scope ),
			]
		);

		return $payload . '.' . $this->sign_clearance( $payload );
	}

	/**
	 * Hash a session or resource binding.
	 *
	 * @param string $type  Binding type.
	 * @param string $value Binding value.
	 *
	 * @return string
	 */
	private function hash_binding( string $type, string $value ): string {
		return hash_hmac( 'sha256', $type . '|' . $value, wp_salt( 'auth' ) );
	}

	/**
	 * Sign a clearance payload.
	 *
	 * @param string $payload Clearance payload.
	 *
	 * @return string
	 */
	private function sign_clearance( string $payload ): string {
		return hash_hmac( 'sha256', 'clearance|' . $payload, wp_salt( 'auth' ) );
	}

	/**
	 * Display the protection page.
	 *
	 * @return void
	 * @noinspection CssUnusedSymbol
	 */
	protected function show_protection_page(): void {
		$css = /* @lang CSS */ '
	* {
		box-sizing: border-box;
		margin: 0;
		padding: 0;
	}

	html {
		line-height: 1.15;
		-webkit-text-size-adjust: 100%;
		color: #5c6f8a;
		font-family: system-ui, -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Helvetica Neue, Arial, Noto Sans, sans-serif, Apple Color Emoji, Segoe UI Emoji, Segoe UI Symbol, Noto Color Emoji;
	}

	body {
		display: flex;
		flex-direction: column;
		height: 100vh;
		min-height: 100vh;
		margin-top: 0;
		margin-bottom: 0;
	}

	.main-content {
		margin: 8rem auto;
		max-width: 60rem;
		padding-left: 1.5rem;
	}

	@media (width <= 720px) {
		.main-content {
			margin-top: 4rem;
		}
	}

	.h2 {
		font-size: 1.5rem;
		font-weight: 500;
		line-height: 2.25rem;
	}

	@media (width <= 720px) {
		.h2 {
			font-size: 1.25rem;
			line-height: 1.5rem;
		}
	}

	body.theme-dark {
		background-color: #1b1b1d;
		color: #e3e3e3;
	}

	body.theme-dark a {
		color: #00bcb7;
	}

	body.theme-dark a:hover {
		color: #00bcb7;
		text-decoration: underline;
	}

	body.theme-dark .footer-inner {
		border-top: 1px solid #e3e3e3;
	}

	body.theme-light {
		background-color: #fff;
		color: #5c6f8a;
	}

	body.theme-light a {
		color: #0075ab;
	}

	body.theme-light a:hover {
		color: #0075ab;
		text-decoration: underline;
	}
	
	body.theme-light .footer-inner {
		border-top: 1px solid #5c6f8a;
	}

	a {
		background-color: transparent;
		color: #0075ab;
		text-decoration: none;
		transition: color .15s ease;
	}

	a:hover {
		color: #0075ab;
		text-decoration: underline;
	}

	.main-content {
		margin: 8rem auto;
		max-width: 60rem;
		padding-left: 1.5rem;
		padding-right: 1.5rem;
		width: 100%;
	}

	.spacer {
		margin: 2rem 0;
	}

	.spacer-top {
		margin-top: 2rem;
	}

	.spacer-bottom {
		margin-bottom: 2rem;
	}

	@media (width <= 720px) {
		.main-content {
			margin-top: 4rem;
		}
	}

	.main-wrapper {
		align-items: center;
		display: flex;
		flex: 1;
		flex-direction: column;
	}

	.h1 {
		font-size: 2.5rem;
		font-weight: 500;
		line-height: 3.75rem;
	}

	.h2 {
		font-weight: 500;
	}

	.core-msg, .h2 {
		font-size: 1.5rem;
		line-height: 2.25rem;
	}

	.core-msg {
		font-weight: 400;
	}

	@media (width <= 720px) {
		.h1 {
			font-size: 1.5rem;
			line-height: 1.75rem;
		}

		.h2 {
			font-size: 1.25rem;
		}

		.core-msg, .h2 {
			line-height: 1.5rem;
		}

		.core-msg {
			font-size: 1rem;
		}
	}

	.text-center {
		text-align: center;
	}

	.footer {
		font-size: .75rem;
		line-height: 1.125rem;
		margin: 0 auto;
		max-width: 60rem;
		padding-left: 1.5rem;
		padding-right: 1.5rem;
		width: 100%;
	}

	.footer-inner {
		border-top: 1px solid #5c6f8a;
		padding-bottom: 1rem;
		padding-top: 1rem;
	}

	.clearfix:after {
		clear: both;
		content: "";
		display: table;
	}

	.footer-text {
		margin-bottom: .5rem;
	}

	.core-msg, .zone-name-title {
		overflow-wrap: break-word;
	}

	@media (width <= 720px) {
		.zone-name-title {
			margin-bottom: 1rem;
		}
	}

	@media (prefers-color-scheme: dark) {
		body {
			background-color: #1b1b1d;
			color: #e3e3e3;
		}

		body a {
			color: #00bcb7;
		}

		body a:hover {
			color: #00bcb7;
			text-decoration: underline;
		}

		.footer-inner {
			border-top: 1px solid #e3e3e3;
		}
	}
	
	.main-content .h-captcha {
		margin-bottom: 0;
	}
	
	#hcaptcha-submit {
		display: none;
	}
';

		?>
		<html lang="en-US" dir="ltr">
		<head>
			<title>Content Protection</title>
			<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
			<meta http-equiv="X-UA-Compatible" content="IE=Edge">
			<meta name="robots" content="noindex,nofollow">
			<meta name="viewport" content="width=device-width,initial-scale=1">
			<meta http-equiv="refresh" content="<?php echo esc_attr( self::COOKIE_EXPIRATION ); ?>">
			<style>
				<?php

				HCaptcha::css_display( $css );
				hcaptcha()->print_inline_styles();

				?>
			</style>
			<?php wp_site_icon(); ?>
		</head>
		<body>
		<div class="main-wrapper" role="main">
			<div class="main-content">
				<h1 class="zone-name-title h1">
					<?php echo wp_kses_post( wp_parse_url( home_url(), PHP_URL_HOST ) ); ?>
				</h1>

				<p class="h2 spacer-bottom">
					<?php esc_html_e( 'Verifying you are human. This may take a few seconds.', 'hcaptcha-for-forms-and-more' ); ?>
				</p>

				<form method="post" action="<?php echo esc_url( $this->request_uri ); ?>">
				<?php

				$settings = hcaptcha()->settings();
				$args     = [
					'action' => static::ACTION,
					'name'   => static::NONCE,
					'force'  => true,
					'theme'  => 'auto',
					'size'   => 'normal',
					'id'     => [
						'source'  => [ $settings ? $settings->get_plugin_name() : '' ],
						'form_id' => 'protect',
					],
				];

				HCaptcha::form_display( $args );

				?>
				<p id="hcaptcha-error"><?php echo esc_html( $this->error_message ); ?></p>
				<input type="submit" id="hcaptcha-submit" value="Submit">
				</form>

				<div class="core-msg spacer spacer-top">
					<?php

					echo wp_kses_post(
						sprintf(
						/* translators: 1: Site link. */
							__( '%1$s needs to review the security of your connection before proceeding.', 'hcaptcha-for-forms-and-more' ),
							wp_parse_url( home_url(), PHP_URL_HOST )
						)
					);

					?>
				</div>
			</div>
		</div>
		<div class="footer text-center" role="contentinfo">
			<div class="footer-inner">
				<div class="clearfix footer-text">
					<div>
						<?php esc_html_e( 'The hCaptcha plugin', 'hcaptcha-for-forms-and-more' ); ?>
					</div>
				</div>
				<div>
					<?php

					echo wp_kses_post(
						sprintf(
						/* translators: 1: hCaptcha link. */
							__( 'Privacy and security by %1$s', 'hcaptcha-for-forms-and-more' ),
							'<a href="https://www.hcaptcha.com/?r=wp&utm_source=wordpress&utm_medium=wpplugin&utm_campaign=sk" target="_blank" rel="noopener noreferrer">hCaptcha</a>'
						)
					);

					?>
				</div>
			</div>
		</div>
		<script>
			document.addEventListener( 'hCaptchaLoaded', function() {
				if ( document.getElementById( 'hcaptcha-error' ).innerText.length === 0 ) {
					document.getElementById( 'hcaptcha-submit' ).click();
				}
			} );
		</script>
		<?php

		hcaptcha()->print_footer_scripts();
		_wp_footer_scripts();

		?>
		</body>
		</html>
		<?php

		$this->exit();
	}

	/**
	 * Setcookie wrapper for test purposes.
	 *
	 * @param string $name    The name of the cookie.
	 * @param string $value   The value of the cookie.
	 * @param array  $options The cookie options.
	 *
	 * @return bool
	 */
	protected function setcookie( string $name, string $value = '', array $options = [] ): bool {
		// @codeCoverageIgnoreStart
		$options = wp_parse_args(
			$options,
			[
				'expires'  => 0,
				'path'     => '',
				'domain'   => '',
				'secure'   => false,
				'httponly' => false,
				'samesite' => 'Lax',
			]
		);

		return setcookie( $name, $value, $options );
		// @codeCoverageIgnoreEnd
	}

	/**
	 * Exit wrapper for test purposes.
	 *
	 * @return void
	 */
	protected function exit(): void {
		// @codeCoverageIgnoreStart
		exit();
		// @codeCoverageIgnoreEnd
	}

	/**
	 * Normalize URL.
	 *
	 * @param string $url         URL.
	 * @param bool   $decode_path Whether to decode percent-encoded path octets.
	 *
	 * @return string
	 */
	private function normalize_url( string $url, bool $decode_path = false ): string {
		$scheme = is_ssl() ? 'https' : 'http';
		$host   = wp_parse_url( home_url(), PHP_URL_HOST );

		$parts = wp_parse_url( $url );
		$parts = wp_parse_args(
			$parts,
			[
				'scheme'   => $scheme,
				'host'     => $host,
				'path'     => '',
				'query'    => '',
				'fragment' => '',
			]
		);

		// Force the site origin because a protocol-relative REQUEST_URI can otherwise
		// turn the protection form action into a cross-origin URL.
		$parts = array_merge(
			$parts,
			[
				'scheme' => $scheme,
				'host'   => $host,
			]
		);

		if ( $decode_path ) {
			$parts['path'] = rawurldecode( $parts['path'] );
		}

		// Rebuild the URL.
		$url = $parts['scheme'] . '://';

		$url .= $parts['host'];
		$url .= $parts['path'] ?: '';
		$url .= $parts['query'] ? '?' . $parts['query'] : '';
		$url .= $parts['fragment'] ? '#' . $parts['fragment'] : '';

		return $url;
	}
}
