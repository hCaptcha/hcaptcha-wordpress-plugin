<?php
/**
 * DownloadManager class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\DownloadManager;

use HCaptcha\Helpers\API;
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
	 * @noinspection HtmlUnknownAttribute
	 * @noinspection UnnecessaryCastingInspection
	 */
	public function add_hcaptcha( string $template, array $vars ): string {
		$form_id = $this->get_package_id( $vars );

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

		return '<form method="post" action="' . esc_url( $url ) . '">' . $template . '</form>';
	}

	/**
	 * Verify request.
	 *
	 * @param array|null $package Download Manager package being downloaded.
	 *
	 * @return void
	 * @noinspection ForgottenDebugOutputInspection
	 * @noinspection PhpMissingParamTypeInspection
	 */
	public function verify( $package ): void {
		$package_id = $this->get_package_id( $package );
		$result     = $package_id > 0 ?
			API::verify( $this->get_entry( $package_id ) ) :
			hcap_get_error_messages()['bad-signature'];

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
	 * Get entry.
	 *
	 * @param int $package_id Download Manager package ID.
	 *
	 * @return array
	 */
	private function get_entry( int $package_id ): array {
		return [
			'nonce_name'   => self::NONCE,
			'nonce_action' => self::ACTION,
			'expected_id'  => $this->get_expected_id( $package_id ),
			'data'         => [ 'package_id' => $package_id ],
		];
	}

	/**
	 * Get expected hCaptcha widget id.
	 *
	 * @param int $form_id Form id.
	 *
	 * @return array
	 */
	private function get_expected_id( int $form_id ): array {
		return [
			'source'  => HCaptcha::get_class_source( __CLASS__ ),
			'form_id' => $form_id,
		];
	}

	/**
	 * Get a positive package ID without reinterpreting malformed values.
	 *
	 * @param mixed $package Download Manager package data.
	 *
	 * @return int
	 */
	private function get_package_id( $package ): int {
		if ( ! is_array( $package ) || ! isset( $package['ID'] ) ) {
			return 0;
		}

		$id = $package['ID'];

		if ( is_int( $id ) ) {
			return max( $id, 0 );
		}

		if ( ! is_string( $id ) || ! ctype_digit( $id ) ) {
			return 0;
		}

		$id = ltrim( $id, '0' );

		return '' !== $id && (string) (int) $id === $id ? (int) $id : 0;
	}

	/**
	 * Print inline styles.
	 *
	 * @return void
	 * @noinspection CssUnusedSymbol
	 * @noinspection CssUnresolvedCustomProperty
	 */
	public function print_inline_styles(): void {
		/* language=CSS */
		$css = '
	.wpdm-button-area + .h-captcha {
		margin-bottom: 1rem;
	}

	.w3eden .btn-primary {
		background-color: var(--color-primary) !important;
		color: #fff !important;
	}
';

		HCaptcha::css_display( $css );
	}
}
