<?php
/**
 * NotificationsTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\Admin;

use HCaptcha\Admin\Notifications;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use ReflectionException;

/**
 * Test NotificationsTest class.
 *
 * @group notifications
 */
class NotificationsTest extends HCaptchaWPTestCase {

	/**
	 * Test init().
	 *
	 * @param bool $empty_keys Whether keys are empty.
	 *
	 * @dataProvider dp_test_init
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_init( bool $empty_keys ) {
		$site_key   = '';
		$secret_key = '';

		$expected = [
			'register'        =>
				[
					'title'   => 'Get your hCaptcha site keys',
					'message' => 'To use <a href="https://www.hcaptcha.com/?r=wp&utm_source=wordpress&utm_medium=wpplugin&utm_campaign=sk" target="_blank">hCaptcha</a>, please register <a href="https://www.hcaptcha.com/signup-interstitial/?r=wp&utm_source=wordpress&utm_medium=wpplugin&utm_campaign=sk" target="_blank">here</a> to get your site and secret keys.',
					'button'  =>
						[
							'url'  => 'https://www.hcaptcha.com/signup-interstitial/?r=wp&utm_source=wordpress&utm_medium=wpplugin&utm_campaign=sk',
							'text' => 'Get site keys',
						],
				],
			'pro-free-trial'  =>
				[
					'title'   => 'Try Pro for free',
					'message' => 'Want low friction and custom themes? <a href="https://www.hcaptcha.com/pro?r=wp&utm_source=wordpress&utm_medium=wpplugin&utm_campaign=not" target="_blank">hCaptcha Pro</a> is for you. <a href="https://dashboard.hcaptcha.com/?r=wp&utm_source=wordpress&utm_medium=wpplugin&utm_campaign=not" target="_blank">Start a free trial in your dashboard</a>, no credit card required.',
					'button'  =>
						[
							'url'  => 'https://www.hcaptcha.com/pro?r=wp&utm_source=wordpress&utm_medium=wpplugin&utm_campaign=not',
							'text' => 'Try Pro',
						],
				],
			'post-leadership' => [
				'title'   => 'hCaptcha\'s Leadership',
				'message' => 'hCaptcha Named a Technology Leader in Bot Management: 2023 SPARK Matrix™',
				'button'  => [
					'url'  => 'https://www.hcaptcha.com/post/hcaptcha-named-a-technology-leader-in-bot-management/?r=wp&utm_source=wordpress&utm_medium=wpplugin&utm_campaign=not',
					'text' => 'Read post',
				],
			],
		];

		if ( ! $empty_keys ) {
			$site_key   = 'some_key';
			$secret_key = 'secret_key';

			unset( $expected['register'] );
		}

		add_filter(
			'hcap_site_key',
			static function ( $key ) use ( $site_key ) {
				return $site_key;
			}
		);
		add_filter(
			'hcap_secret_key',
			static function ( $key ) use ( $secret_key ) {
				return $secret_key;
			}
		);

		$subject = new Notifications();

		$subject->init();

		self::assertSame(
			10,
			has_action(
				'admin_enqueue_scripts',
				[ $subject, 'admin_enqueue_scripts' ]
			)
		);
		self::assertSame(
			10,
			has_action(
				'wp_ajax_' . Notifications::DISMISS_NOTIFICATION_ACTION,
				[ $subject, 'dismiss_notification' ]
			)
		);
		self::assertSame(
			10,
			has_action(
				'wp_ajax_' . Notifications::RESET_NOTIFICATIONS_ACTION,
				[ $subject, 'reset_notifications' ]
			)
		);

		$this->assertSame( $expected, $this->get_protected_property( $subject, 'notifications' ) );
	}

	/**
	 * Data provider for test_init().
	 *
	 * @return array
	 */
	public function dp_test_init(): array {
		return [
			'empty keys'     => [ true ],
			'not empty keys' => [ false ],
		];
	}

	/**
	 * Test show().
	 *
	 * @return void
	 * @noinspection UnusedFunctionResultInspection
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_show() {
		global $current_user;

		unset( $current_user );
		wp_set_current_user( 1 );

		$site_key   = '';
		$secret_key = '';

		add_filter(
			'hcap_site_key',
			static function ( $key ) use ( $site_key ) {
				return $site_key;
			}
		);
		add_filter(
			'hcap_secret_key',
			static function ( $key ) use ( $secret_key ) {
				return $secret_key;
			}
		);

		$expected = '		<div id="hcaptcha-notifications">
			<div id="hcaptcha-notifications-header">
				Notifications			</div>
							<div
						class="hcaptcha-notification notice notice-info is-dismissible inline"
						data-id="register">
					<div class="hcaptcha-notification-title">
						Get your hCaptcha site keys					</div>
					<p>To use <a href="https://www.hcaptcha.com/?r=wp&amp;utm_source=wordpress&amp;utm_medium=wpplugin&amp;utm_campaign=sk" target="_blank">hCaptcha</a>, please register <a href="https://www.hcaptcha.com/signup-interstitial/?r=wp&amp;utm_source=wordpress&amp;utm_medium=wpplugin&amp;utm_campaign=sk" target="_blank">here</a> to get your site and secret keys.</p>
										<div class="hcaptcha-notification-buttons hidden">
						<a href="https://www.hcaptcha.com/signup-interstitial/?r=wp&#038;utm_source=wordpress&#038;utm_medium=wpplugin&#038;utm_campaign=sk" class="button button-primary" target="_blank">
							Get site keys						</a>
					</div>
									</div>
								<div
						class="hcaptcha-notification notice notice-info is-dismissible inline"
						data-id="pro-free-trial">
					<div class="hcaptcha-notification-title">
						Try Pro for free					</div>
					<p>Want low friction and custom themes? <a href="https://www.hcaptcha.com/pro?r=wp&amp;utm_source=wordpress&amp;utm_medium=wpplugin&amp;utm_campaign=not" target="_blank">hCaptcha Pro</a> is for you. <a href="https://dashboard.hcaptcha.com/?r=wp&amp;utm_source=wordpress&amp;utm_medium=wpplugin&amp;utm_campaign=not" target="_blank">Start a free trial in your dashboard</a>, no credit card required.</p>
										<div class="hcaptcha-notification-buttons hidden">
						<a href="https://www.hcaptcha.com/pro?r=wp&#038;utm_source=wordpress&#038;utm_medium=wpplugin&#038;utm_campaign=not" class="button button-primary" target="_blank">
							Try Pro						</a>
					</div>
									</div>
								<div
						class="hcaptcha-notification notice notice-info is-dismissible inline"
						data-id="post-leadership">
					<div class="hcaptcha-notification-title">
						hCaptcha&#039;s Leadership					</div>
					<p>hCaptcha Named a Technology Leader in Bot Management: 2023 SPARK Matrix™</p>
										<div class="hcaptcha-notification-buttons hidden">
						<a href="https://www.hcaptcha.com/post/hcaptcha-named-a-technology-leader-in-bot-management/?r=wp&#038;utm_source=wordpress&#038;utm_medium=wpplugin&#038;utm_campaign=not" class="button button-primary" target="_blank">
							Read post						</a>
					</div>
									</div>
							<div id="hcaptcha-notifications-footer">
				<div id="hcaptcha-navigation">
					<a class="prev disabled"></a>
					<a class="next "></a>
				</div>
			</div>
		</div>
		';

		$subject = new Notifications();
		$subject->init();

		// Do not shuffle notifications for test purposes.
		$this->set_protected_property( $subject, 'shuffle', false );

		ob_start();
		$subject->show();
		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test show() without notifications.
	 *
	 * @return void
	 * @noinspection UnusedFunctionResultInspection
	 */
	public function test_show_without_notifications() {
		global $current_user;

		unset( $current_user );

		$expected = '';

		$subject = new Notifications();

		ob_start();
		$subject->show();
		self::assertSame( $expected, ob_get_clean() );
	}
}
