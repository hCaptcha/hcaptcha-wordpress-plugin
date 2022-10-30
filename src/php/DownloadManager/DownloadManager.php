<?php
/**
 * DownloadManager class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\DownloadManager;

/**
 * Class DownloadManager.
 */
class DownloadManager {

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
	public function init_hooks() {
		add_action( 'wpdm_after_fetch_template', [ $this, 'add_hcaptcha' ], 10, 4 );
		add_action( 'wpdm_onstart_download', [ $this, 'verify' ], 10 );
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
	public function add_hcaptcha( $template, $vars ) {
		$hcaptcha = hcap_form( HCAPTCHA_ACTION, HCAPTCHA_NONCE, true );

		$template = preg_replace( '/(<ul class="list-group ml)/', $hcaptcha . '$1', $template );
		$template = preg_replace( '/<a (.+)?<\/a>/', '<button type="submit" $1</button>', $template );
		$template = str_replace( 'download-on-click', '', $template );
		$url      = '';

		if ( preg_match( '/href=\'(.+)?\'/', $template, $m ) ) {
			$url = $m[1];
		}

		return '<form method="post" action="' . $url . '">' . $template . '</form>';
	}

	/**
	 * Verify request filter.
	 *
	 * @param array|null $package Result of the hCaptcha verification.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 * @noinspection ForgottenDebugOutputInspection
	 */
	public function verify( $package ) {

		$result = hcaptcha_verify_post();

		if ( null === $result ) {
			return;
		}

		$backlink = site_url( wp_get_referer() );
		wp_die(
			esc_html( $result ),
			'hCaptcha error',
			[
				'response'  => 303,
				'backlink'  => esc_url( $backlink ),
				'link_url'  => esc_url( $backlink ),
				'link_text' => esc_html__( 'Go back', 'hcaptcha-for-forms-and-more' ),
			]
		);
	}
}
