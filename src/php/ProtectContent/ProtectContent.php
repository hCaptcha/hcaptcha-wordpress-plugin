<?php
/**
 * ProtectContent class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\ProtectContent;

use HCaptcha\Helpers\HCaptcha;

/**
 * Class ProtectContent
 */
class ProtectContent {

	/**
	 * Init class.
	 *
	 * @return void
	 */
	public function init(): void {
		$this->init_hooks();
	}

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		add_action( 'template_redirect', [ $this, 'show_protection_page' ], -PHP_INT_MAX );
//		add_action( 'init', [ $this, 'verify' ], - PHP_INT_MAX );
	}

	/**
	 * Display the protection page.
	 *
	 * @return void
	 * @noinspection CssUnusedSymbol
	 */
	public function show_protection_page(): void {
		/* language=CSS */
		$css = '
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
		margin-top: 4rem;
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
';

		/* language=HTML */
		?>
		<html lang="en-US" dir="ltr">
		<head>
			<title>Content Protection</title>
			<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
			<meta http-equiv="X-UA-Compatible" content="IE=Edge">
			<meta name="robots" content="noindex,nofollow">
			<meta name="viewport" content="width=device-width,initial-scale=1">
			<meta http-equiv="refresh" content="390">
			<style>
				<?php HCaptcha::css_display( $css ); ?>
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
		</body>
		</html>
		<?php

		exit();
	}
}
