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
	 * Cookie expiration.
	 *
	 * 5 minutes in seconds.
	 */
	private const COOKIE_EXPIRATION = 5 * MINUTE_IN_SECONDS;

	/**
	 * Error message.
	 *
	 * @var string
	 */
	protected $error_message = '';

	/**
	 * Request URI.
	 *
	 * @var string
	 */
	protected $request_uri = '';

	/**
	 * Init class.
	 *
	 * @return void
	 */
	public function init(): void {
		if ( ! Request::is_frontend() ) {
			return;
		}

		$settings = hcaptcha()->settings();

		if ( ! $settings->is_on( 'protect_content' ) ) {
			return;
		}

		$this->request_uri = $this->normalize_url( Request::filter_input( INPUT_SERVER, 'REQUEST_URI' ) );

		$protected_urls = explode( "\n", $settings->get( 'protected_urls' ) );
		$protected_urls = array_filter( array_map( 'trim', $protected_urls ) );
		$protected_urls = $protected_urls ?: [ '/' ]; // Protect all URLs by default.

		$found = false;

		foreach ( $protected_urls as $url ) {
			if ( preg_match( '!' . preg_quote( $url, '!' ) . '!i', $this->request_uri ) ) {
				$found = true;

				break;
			}
		}

		if ( ! $found ) {
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
		// It is always too fast with Pro.
		hcaptcha()->settings()->set( 'set_min_submit_time', [ '' ] );

		$error_message = API::verify_post( self::NONCE, self::ACTION );

		if ( null === $error_message ) {
			$time   = time();
			$cookie = $time . '|' . wp_hash( $time );

			$this->setcookie( self::COOKIE_NAME, $cookie, $time + self::COOKIE_EXPIRATION, '/' );
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
		$cookie     = Request::filter_input( INPUT_COOKIE, self::COOKIE_NAME );
		$cookie_arr = explode( '|', $cookie );

		$time        = (int) $cookie_arr[0];
		$hashed_time = (string) ( $cookie_arr[1] ?? '' );

		if ( wp_hash( $time ) !== $hashed_time ) {
			return false;
		}

		return time() - $time < self::COOKIE_EXPIRATION;
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

				$args = [
					'action' => static::ACTION,
					'name'   => static::NONCE,
					'force'  => true,
					'theme'  => 'auto',
					'size'   => 'normal',
					'id'     => [
						'source'  => [ hcaptcha()->settings()->get_plugin_name() ],
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
	 * @param string $name               The name of the cookie.
	 * @param string $value              The value of the cookie.
	 * @param int    $expires_or_options The time the cookie expires.
	 * @param string $path               The path on the server in which the cookie will be available on.
	 * @param string $domain             The domain that the cookie is available.
	 * @param bool   $secure             Indicates that the cookie should only be transmitted over HTTPS.
	 * @param bool   $httponly           When true, the cookie will be made accessible only through the HTTP protocol.
	 *
	 * @return bool
	 * @noinspection PhpUnusedParameterInspection
	 */
	protected function setcookie(
		string $name,
		string $value = '',
		int $expires_or_options = 0,
		string $path = '',
		string $domain = '',
		bool $secure = false,
		bool $httponly = false
	): bool {
		// @codeCoverageIgnoreStart
		return setcookie( $name, $value, $expires_or_options, $path );
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
	 * @param string $url URL.
	 *
	 * @return string
	 */
	private function normalize_url( string $url ): string {
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

		// Rebuild the URL.
		$url = $parts['scheme'] ? $parts['scheme'] . '://' : '';

		$url .= $parts['host'];
		$url .= $parts['path'] ?: '';
		$url .= $parts['query'] ? '?' . $parts['query'] : '';
		$url .= $parts['fragment'] ? '#' . $parts['fragment'] : '';

		return $url;
	}
}
