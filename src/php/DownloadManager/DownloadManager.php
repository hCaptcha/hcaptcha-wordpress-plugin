<?php
/**
 * DownloadManager class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\DownloadManager;

use HCaptcha\Helpers\HCaptcha;

/**
 * Class DownloadManager.
 */
class DownloadManager {

	/**
	 * Nonce action.
	 */
	private const ACTION = 'hcaptcha_download_manager';

	/**
	 * Nonce name.
	 */
	private const NONCE = 'hcaptcha_download_manager_nonce';

	/**
	 * DownloadManager constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Add hooks.
	 *
	 * @return void
	 */
	public function init_hooks(): void {
		add_action( 'wpdm_after_fetch_template', [ $this, 'add_hcaptcha' ], 10, 2 );
		add_action( 'wpdm_onstart_download', [ $this, 'verify' ] );
		add_action( 'wp_head', [ $this, 'print_inline_styles' ], 20 );
	}

	/**
	 * Filters the template created by the Download Manager plugin and adds hcaptcha.
	 *
	 * @param string $template Template.
	 * @param array  $vars     Variables.
	 *
	 * @return string
	 * @noinspection PhpUnusedParameterInspection
	 * @noinspection HtmlUnknownAttribute
	 */
	public function add_hcaptcha( string $template, array $vars ): string {
		$form_id = 0;

		if ( preg_match( '/wpdmdl=(\d+)/', $template, $m ) ) {
			$form_id = (int) $m[1];
		}

		$args = [
			'action' => self::ACTION,
			'name'   => self::NONCE,
			'id'     => [
				'source'  => HCaptcha::get_class_source( __CLASS__ ),
				'form_id' => $form_id,
			],
		];

		$hcaptcha = HCaptcha::form( $args );

		$template = (string) preg_replace( '/(<ul class="list-group ml)/', $hcaptcha . '$1', $template );
		$template = (string) preg_replace( '/<a (.+)?<\/a>/s', '<button type="submit" $1</button>', $template );
		$template = str_replace( 'download-on-click', '', $template );
		$url      = '';

		if ( preg_match( '/data-downloadurl="(.+)?"/', $template, $m ) ) {
			$url = $m[1];
		}

		return '<form method="post" action="' . $url . '">' . $template . '</form>';
	}

	/**
	 * Verify request.
	 *
	 * @param array|null $package Result of the hCaptcha verification.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 * @noinspection ForgottenDebugOutputInspection
	 * @noinspection PhpMissingParamTypeInspection
	 */
	public function verify( $package ): void {

		$result = hcaptcha_verify_post( self::NONCE, self::ACTION );

		if ( null === $result ) {
			return;
		}

		wp_die(
			esc_html( $result ),
			esc_html__( 'hCaptcha error', 'hcaptcha-for-forms-and-more' ),
			[
				'back_link' => true,
				'response'  => 303,
			]
		);
	}

	/**
	 * Print inline styles.
	 *
	 * @return void
	 * @noinspection CssUnusedSymbol
	 * @noinspection CssUnresolvedCustomProperty
	 */
	public function print_inline_styles(): void {
		$css = <<<CSS
	.wpdm-button-area + .h-captcha {
		margin-bottom: 1rem;
	}

	.w3eden .btn-primary {
		background-color: var(--color-primary) !important;
		color: #fff !important;
	}
CSS;

		HCaptcha::css_display( $css );
	}
}
