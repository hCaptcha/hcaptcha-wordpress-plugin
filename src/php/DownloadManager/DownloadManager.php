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
	 * Nonce action.
	 */
	const ACTION = 'hcaptcha_download_manager';

	/**
	 * Nonce name.
	 */
	const NONCE = 'hcaptcha_download_manager_nonce';

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
		add_action( 'wpdm_after_fetch_template', [ $this, 'add_hcaptcha' ], 10, 2 );
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
		$hcaptcha = hcap_form( self::ACTION, self::NONCE );

		$template = (string) preg_replace( '/(<ul class="list-group ml)/', $hcaptcha . '$1', $template );
		$template = (string) preg_replace( '/<a (.+)?<\/a>/', '<button type="submit" $1</button>', $template );
		$template = str_replace( 'download-on-click', '', $template );
		$url      = '';

		if ( preg_match( '/href=\'(.+)?\'/', $template, $m ) ) {
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
	 */
	public function verify( $package ) {

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
}
