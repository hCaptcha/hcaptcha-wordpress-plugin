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
			static function () use ( $site_key ) {
				return $site_key;
			}
		);
		add_filter(
			'hcap_secret_key',
			static function () use ( $secret_key ) {
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
	 * @noinspection HtmlUnknownAttribute
	 */
	public function test_show() {
		global $current_user;

		unset( $current_user );

		$user_id = 1;

		wp_set_current_user( $user_id );

		$site_key   = '';
		$secret_key = '';

		add_filter(
			'hcap_site_key',
			static function () use ( $site_key ) {
				return $site_key;
			}
		);
		add_filter(
			'hcap_secret_key',
			static function () use ( $secret_key ) {
				return $secret_key;
			}
		);

		$expected = '
<div id="hcaptcha-notifications">
	<div id="hcaptcha-notifications-header">
		Notifications
	</div>
	<div
			class="hcaptcha-notification notice notice-info is-dismissible inline"
			data-id="register">
		<div class="hcaptcha-notification-title">
			Get your hCaptcha site keys
		</div>
		<p>To use <a
				href="https://www.hcaptcha.com/?r=wp&amp;utm_source=wordpress&amp;utm_medium=wpplugin&amp;utm_campaign=sk"
				target="_blank">hCaptcha</a>, please register <a
				href="https://www.hcaptcha.com/signup-interstitial/?r=wp&amp;utm_source=wordpress&amp;utm_medium=wpplugin&amp;utm_campaign=sk"
				target="_blank">here</a> to get your site and secret keys.</p>
		<div class="hcaptcha-notification-buttons hidden">
			<a href="https://www.hcaptcha.com/signup-interstitial/?r=wp&#038;utm_source=wordpress&#038;utm_medium=wpplugin&#038;utm_campaign=sk"
			   class="button button-primary" target="_blank">
				Get site keys </a>
		</div>
	</div>
	<div
			class="hcaptcha-notification notice notice-info is-dismissible inline"
			data-id="pro-free-trial">
		<div class="hcaptcha-notification-title">
			Try Pro for free
		</div>
		<p>Want low friction and custom themes? <a
				href="https://www.hcaptcha.com/pro?r=wp&amp;utm_source=wordpress&amp;utm_medium=wpplugin&amp;utm_campaign=not"
				target="_blank">hCaptcha Pro</a> is for you. <a
				href="https://dashboard.hcaptcha.com/?r=wp&amp;utm_source=wordpress&amp;utm_medium=wpplugin&amp;utm_campaign=not"
				target="_blank">Start a free trial in your dashboard</a>, no credit card required.</p>
		<div class="hcaptcha-notification-buttons hidden">
			<a href="https://www.hcaptcha.com/pro?r=wp&#038;utm_source=wordpress&#038;utm_medium=wpplugin&#038;utm_campaign=not"
			   class="button button-primary" target="_blank">
				Try Pro </a>
		</div>
	</div>
	<div
			class="hcaptcha-notification notice notice-info is-dismissible inline"
			data-id="post-leadership">
		<div class="hcaptcha-notification-title">
			hCaptcha&#039;s Leadership
		</div>
		<p>hCaptcha Named a Technology Leader in Bot Management: 2023 SPARK Matrix™</p>
		<div class="hcaptcha-notification-buttons hidden">
			<a href="https://www.hcaptcha.com/post/hcaptcha-named-a-technology-leader-in-bot-management/?r=wp&#038;utm_source=wordpress&#038;utm_medium=wpplugin&#038;utm_campaign=not"
			   class="button button-primary" target="_blank">
				Read post </a>
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

		$expected = $this->trim_tags( $expected );

		$subject = new Notifications();
		$subject->init();

		ob_start();
		$subject->show();
		$actual = $this->trim_tags( ob_get_clean() );

		$header  = '<div id="hcaptcha-notifications"> <div id="hcaptcha-notifications-header"> Notifications </div>';
		$body    = '<div .+</div>';
		$footer  = '<div id="hcaptcha-notifications-footer"> <div id="hcaptcha-navigation"> <a class="prev disabled"></a> <a class="next "></a> </div> </div> </div>';
		$pattern = "#($header) ($body) ($footer)#";

		preg_match( $pattern, $expected, $expected_matches );
		preg_match( $pattern, $actual, $actual_matches );

		self::assertSame( $expected_matches[1], $actual_matches[1] );
		self::assertSame( $expected_matches[3], $actual_matches[3] );

		$expected_body = $expected_matches[2];
		$actual_body   = $actual_matches[2];

		$notification_pattern = '#<div class="hcaptcha-notification notice.+?> <div .+?>.+?</div> <p>.+?</p> <div .+?>.+?</div> </div>#s';

		preg_match_all(
			$notification_pattern,
			$expected_body,
			$expected_notifications
		);
		preg_match_all(
			$notification_pattern,
			$actual_body,
			$actual_notifications
		);

		$expected_notifications = $expected_notifications[0];
		$actual_notifications   = $actual_notifications[0];

		$sorted_actual_notifications = [];

		foreach ( $actual_notifications as $actual_notification ) {
			preg_match( '/data-id="(.+?)"/', $actual_notification, $m );
			$data_id = $m[1];

			foreach ( $expected_notifications as $key => $expected_notification ) {
				if ( false !== strpos( $expected_notification, $data_id ) ) {
					$sorted_actual_notifications[ $key ] = $actual_notification;
				}
			}
		}

		ksort( $sorted_actual_notifications );

		self::assertSame( $expected_notifications, $sorted_actual_notifications );

		// Dismiss Pro notification.
		update_user_meta( $user_id, Notifications::HCAPTCHA_DISMISSED_META_KEY, [ 'pro-free-trial', 'some-other-key' ] );

		$dismissed_notification = '
<div
		class="hcaptcha-notification notice notice-info is-dismissible inline"
		data-id="pro-free-trial">
	<div class="hcaptcha-notification-title">
		Try Pro for free
	</div>
	<p>Want low friction and custom themes? <a
			href="https://www.hcaptcha.com/pro?r=wp&amp;utm_source=wordpress&amp;utm_medium=wpplugin&amp;utm_campaign=not"
			target="_blank">hCaptcha Pro</a> is for you. <a
			href="https://dashboard.hcaptcha.com/?r=wp&amp;utm_source=wordpress&amp;utm_medium=wpplugin&amp;utm_campaign=not"
			target="_blank">Start a free trial in your dashboard</a>, no credit card required.</p>
	<div class="hcaptcha-notification-buttons hidden">
		<a href="https://www.hcaptcha.com/pro?r=wp&#038;utm_source=wordpress&#038;utm_medium=wpplugin&#038;utm_campaign=not"
		   class="button button-primary" target="_blank">
			Try Pro </a>
	</div>
</div>
';

		$expected = str_replace( $this->trim_tags( $dismissed_notification ), '', $expected );
		$expected = $this->trim_tags( $expected );

		ob_start();
		$subject->show();

		$actual = $this->trim_tags( ob_get_clean() );

		preg_match( $pattern, $expected, $expected_matches );
		preg_match( $pattern, $actual, $actual_matches );

		self::assertSame( $expected_matches[1], $actual_matches[1] );
		self::assertSame( $expected_matches[3], $actual_matches[3] );

		$expected_body = $expected_matches[2];
		$actual_body   = $actual_matches[2];

		preg_match_all(
			$notification_pattern,
			$expected_body,
			$expected_notifications
		);
		preg_match_all(
			$notification_pattern,
			$actual_body,
			$actual_notifications
		);

		$expected_notifications = $expected_notifications[0];
		$actual_notifications   = $actual_notifications[0];

		$sorted_actual_notifications = [];

		foreach ( $actual_notifications as $actual_notification ) {
			preg_match( '/data-id="(.+?)"/', $actual_notification, $m );
			$data_id = $m[1];

			foreach ( $expected_notifications as $key => $expected_notification ) {
				if ( false !== strpos( $expected_notification, $data_id ) ) {
					$sorted_actual_notifications[ $key ] = $actual_notification;
				}
			}
		}

		ksort( $sorted_actual_notifications );

		self::assertSame( $expected_notifications, $sorted_actual_notifications );
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

	/**
	 * Test admin_enqueue_scripts().
	 *
	 * @return void
	 */
	public function test_admin_enqueue_scripts() {
		$params         = [
			'ajaxUrl'                   => 'http://test.test/wp-admin/admin-ajax.php',
			'dismissNotificationAction' => Notifications::DISMISS_NOTIFICATION_ACTION,
			'dismissNotificationNonce'  => wp_create_nonce( Notifications::DISMISS_NOTIFICATION_ACTION ),
			'resetNotificationAction'   => Notifications::RESET_NOTIFICATIONS_ACTION,
			'resetNotificationNonce'    => wp_create_nonce( Notifications::RESET_NOTIFICATIONS_ACTION ),
		];
		$expected_extra = [
			'group' => 1,
			// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
			'data'  => 'var HCaptchaNotificationsObject = ' . json_encode( $params ) . ';',
		];

		$subject = new Notifications();

		$subject->admin_enqueue_scripts();

		self::assertTrue( wp_script_is( Notifications::HANDLE ) );

		$script = wp_scripts()->registered[ Notifications::HANDLE ];
		self::assertSame( HCAPTCHA_URL . '/assets/js/notifications.min.js', $script->src );
		self::assertSame( [ 'jquery' ], $script->deps );
		self::assertSame( HCAPTCHA_VERSION, $script->ver );
		self::assertSame( $expected_extra, $script->extra );

		self::assertTrue( wp_style_is( Notifications::HANDLE ) );
		$style = wp_styles()->registered[ Notifications::HANDLE ];
		self::assertSame( HCAPTCHA_URL . '/assets/css/notifications.min.css', $style->src );
		self::assertSame( [], $style->deps );
		self::assertSame( HCAPTCHA_VERSION, $style->ver );
	}

	/**
	 * Trim spaces before and after tags.
	 *
	 * @param string $html Html.
	 *
	 * @return string
	 */
	private function trim_tags( string $html ): string {
		return preg_replace(
			[ '/\s+/' ],
			[ ' ' ],
			trim( $html )
		);
	}
}
