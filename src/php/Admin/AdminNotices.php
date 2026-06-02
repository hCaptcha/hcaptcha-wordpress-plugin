<?php
/**
 * AdminNotices class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Admin;

use HCaptcha\Migrations\Migrations;
use HCaptcha\Settings\AntiSpamPage;

/**
 * Class AdminNotices.
 */
class AdminNotices {

	/**
	 * AdminNotices constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Init class hooks.
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		add_action( 'hcap_admin_notices', [ $this, 'show_trusted_address_headers_notice' ] );
	}

	/**
	 * Show trusted address headers review notice.
	 *
	 * @return void
	 * @noinspection HtmlUnknownTarget
	 */
	public function show_trusted_address_headers_notice(): void {
		if ( ! current_user_can( 'manage_options' ) || ! $this->should_show_trusted_address_headers_notice() ) {
			return;
		}

		$settings = hcaptcha()->settings();
		$url      = $settings
			? $settings->tab_url( AntiSpamPage::class ) . '#trusted_address_headers'
			: admin_url( 'admin.php?page=hcaptcha-antispampage#trusted_address_headers' );

		$message = __(
			'IP header trust behavior changed. hCaptcha now uses REMOTE_ADDR unless Trusted IP Headers are selected. If your site is behind Cloudflare, another CDN, or a proxy, review Trusted IP Headers and save the Anti-Spam settings.',
			'hcaptcha-for-forms-and-more'
		);

		printf(
			'<div class="notice notice-warning inline hcaptcha-admin-notice"><p>%1$s</p><p><a class="button button-primary" href="%2$s">%3$s</a></p></div>',
			esc_html( $message ),
			esc_url( $url ),
			esc_html__( 'Review Trusted IP Headers', 'hcaptcha-for-forms-and-more' )
		);
	}

	/**
	 * Whether trusted address headers review notice should be shown.
	 *
	 * @return bool
	 */
	private function should_show_trusted_address_headers_notice(): bool {
		$settings = hcaptcha()->settings();
		$raw      = $settings ? (array) $settings->get_raw_settings() : [];

		return 'on' === ( $raw[ Migrations::REVIEW_TRUSTED_ADDRESS_HEADERS_OPTION ] ?? '' );
	}
}
