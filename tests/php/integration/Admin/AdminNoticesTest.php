<?php
/**
 * AdminNoticesTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\Admin;

use HCaptcha\Admin\AdminNotices;
use HCaptcha\Migrations\Migrations;
use HCaptcha\Settings\AntiSpamPage;
use HCaptcha\Settings\PluginSettingsBase;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;

/**
 * Test AdminNotices.
 *
 * @group admin
 * @group admin-notices
 */
class AdminNoticesTest extends HCaptchaWPTestCase {

	/**
	 * Test show_trusted_address_headers_notice().
	 *
	 * @return void
	 */
	public function test_show_trusted_address_headers_notice(): void {
		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );

		wp_set_current_user( $user_id );

		update_option(
			PluginSettingsBase::OPTION_NAME,
			[
				Migrations::REVIEW_TRUSTED_ADDRESS_HEADERS_OPTION => 'on',
			]
		);

		ob_start();
		( new AdminNotices() )->show_trusted_address_headers_notice();
		$output = ob_get_clean();

		self::assertStringContainsString( 'notice notice-warning inline hcaptcha-admin-notice', $output );
		self::assertStringContainsString( 'IP header trust behavior changed.', $output );
		self::assertStringContainsString( 'Review Trusted IP Headers', $output );
		self::assertStringContainsString(
			hcaptcha()->settings()->tab_url( AntiSpamPage::class ) . '#trusted_address_headers',
			$output
		);
		self::assertStringNotContainsString( 'is-dismissible', $output );
	}

	/**
	 * Test show_trusted_address_headers_notice() without a review flag.
	 *
	 * @return void
	 */
	public function test_show_trusted_address_headers_notice_without_flag(): void {
		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );

		wp_set_current_user( $user_id );

		ob_start();
		( new AdminNotices() )->show_trusted_address_headers_notice();
		$output = ob_get_clean();

		self::assertSame( '', $output );
	}

	/**
	 * Test saving Anti-Spam settings clears the trusted address headers review flag.
	 *
	 * @return void
	 */
	public function test_anti_spam_settings_save_clears_trusted_address_headers_notice(): void {
		hcaptcha()->init_hooks();

		update_option(
			PluginSettingsBase::OPTION_NAME,
			[
				Migrations::REVIEW_TRUSTED_ADDRESS_HEADERS_OPTION => 'on',
			]
		);

		$_POST[ PluginSettingsBase::OPTION_NAME ] = [
			'blacklisted_ips' => '',
		];

		update_option(
			PluginSettingsBase::OPTION_NAME,
			[
				'blacklisted_ips' => '',
			]
		);

		$option = get_option( PluginSettingsBase::OPTION_NAME );

		self::assertArrayNotHasKey( Migrations::REVIEW_TRUSTED_ADDRESS_HEADERS_OPTION, $option );
	}
}
