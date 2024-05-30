<?php
/**
 * NotificationsTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpLanguageLevelInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Integration\Admin;

use HCaptcha\Admin\Notifications;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use Mockery;
use ReflectionException;

/**
 * Test NotificationsTest class.
 *
 * @group notifications
 */
class NotificationsTest extends HCaptchaWPTestCase {

	/**
	 * Tear down test.
	 *
	 * @return void
	 */
	public function tearDown(): void { // phpcs:ignore PHPCompatibility.FunctionDeclarations.NewReturnTypeDeclarations.voidFound
		unset( $_REQUEST['action'], $_REQUEST['nonce'] );

		parent::tearDown();
	}

	/**
	 * Test init().
	 *
	 * @param bool $empty_keys Whether keys are empty.
	 * @param bool $pro Whether it is a Pro account.
	 * @param bool $force Whether a force option is on.
	 *
	 * @dataProvider dp_test_init
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_init( bool $empty_keys, bool $pro, bool $force ) {
		$site_key   = '';
		$secret_key = '';

		$expected = [
			'register'            =>
				[
					'title'   => 'Get your hCaptcha site keys',
					'message' => 'To use <a href="https://www.hcaptcha.com/?r=wp&utm_source=wordpress&utm_medium=wpplugin&utm_campaign=sk" target="_blank">hCaptcha</a>, please register <a href="https://www.hcaptcha.com/signup-interstitial/?r=wp&utm_source=wordpress&utm_medium=wpplugin&utm_campaign=sk" target="_blank">here</a> to get your site and secret keys.',
					'button'  =>
						[
							'url'  => 'https://www.hcaptcha.com/signup-interstitial/?r=wp&utm_source=wordpress&utm_medium=wpplugin&utm_campaign=sk',
							'text' => 'Get site keys',
						],
				],
			'pro-free-trial'      =>
				[
					'title'   => 'Try Pro for free',
					'message' => 'Want low friction and custom themes? <a href="https://www.hcaptcha.com/pro?r=wp&utm_source=wordpress&utm_medium=wpplugin&utm_campaign=not" target="_blank">hCaptcha Pro</a> is for you. <a href="https://dashboard.hcaptcha.com/?r=wp&utm_source=wordpress&utm_medium=wpplugin&utm_campaign=not" target="_blank">Start a free trial in your dashboard</a>, no credit card required.',
					'button'  =>
						[
							'url'  => 'https://www.hcaptcha.com/pro?r=wp&utm_source=wordpress&utm_medium=wpplugin&utm_campaign=not',
							'text' => 'Try Pro',
						],
				],
			'post-leadership'     => [
				'title'   => 'hCaptcha\'s Leadership',
				'message' => 'hCaptcha Named a Technology Leader in Bot Management: 2023 SPARK Matrix™',
				'button'  => [
					'url'  => 'https://www.hcaptcha.com/post/hcaptcha-named-a-technology-leader-in-bot-management/?r=wp&utm_source=wordpress&utm_medium=wpplugin&utm_campaign=not',
					'text' => 'Read post',
				],
			],
			'please-rate'         => [
				'title'   => 'Rate hCaptcha plugin',
				'message' => 'Please rate <strong>hCaptcha for WordPress</strong> <a href="https://wordpress.org/support/plugin/hcaptcha-for-forms-and-more/reviews/?filter=5#new-post" target="_blank" rel="noopener noreferrer">★★★★★</a> on <a href="https://wordpress.org/support/plugin/hcaptcha-for-forms-and-more/reviews/?filter=5#new-post" target="_blank" rel="noopener noreferrer">WordPress.org</a>. Thank you!',
				'button'  => [
					'url'  => 'https://wordpress.org/support/plugin/hcaptcha-for-forms-and-more/reviews/?filter=5#new-post',
					'text' => 'Rate',
				],
			],
			'search-integrations' => [
				'title'   => 'Search on Integrations page',
				'message' => 'Now you can search for plugin an themes on the Integrations page.',
				'button'  => [
					'url'  => 'http://test.test/wp-admin/options-general.php?page=hcaptcha&tab=integrations#hcaptcha-integrations-search',
					'text' => 'Start search',
				],
			],
			'enterprise-support'  => [
				'title'   => 'Support for Enterprise features',
				'message' => 'The hCaptcha plugin commenced support for Enterprise features. Solve your fraud and abuse problem today.',
				'button'  => [
					'url'  => 'https://www.hcaptcha.com/#enterprise-features?r=wp&utm_source=wordpress&utm_medium=wpplugin&utm_campaign=not',
					'text' => 'Get started',
				],
			],
			'statistics'          => [
				'title'   => 'Events statistics and Forms admin page',
				'message' => '<a href="http://test.test/wp-admin/options-general.php?page=hcaptcha&tab=general#statistics_1" target="_blank">Turn on</a> events statistics and <a href="http://test.test/wp-admin/options-general.php?page=hcaptcha&tab=forms" target="_blank">see</a> how your forms are used.',
				'button'  => [
					'url'  => 'http://test.test/wp-admin/options-general.php?page=hcaptcha&tab=general#statistics_1',
					'text' => 'Turn on stats',
				],
			],
			'events_page'         => [
				'title'   => 'Events admin page',
				'message' => '<a href="http://test.test/wp-admin/options-general.php?page=hcaptcha&tab=general#statistics_1" target="_blank">Turn on</a> events statistics and <a href="https://dashboard.hcaptcha.com/?r=wp&utm_source=wordpress&utm_medium=wpplugin&utm_campaign=not" target="_blank">upgrade to Pro</a> to <a href="http://test.test/wp-admin/options-general.php?page=hcaptcha&tab=events" target="_blank">see</a> complete statistics on form events.',
				'button'  => [
					'url'  => 'http://test.test/wp-admin/options-general.php?page=hcaptcha&tab=general#statistics_1',
					'text' => 'Turn on stats',
				],
			],
			'force'               => [
				'title'   => 'Force hCaptcha',
				'message' => 'Force hCaptcha check before submitting the form and simplify the user experience.',
				'button'  => [
					'url'  => 'http://test.test/wp-admin/options-general.php?page=hcaptcha&tab=general#force_1',
					'text' => 'Turn on force',
				],
			],
		];

		if ( ! $empty_keys ) {
			$site_key   = 'some_key';
			$secret_key = 'secret_key';

			unset( $expected['register'] );
		}

		if ( $pro ) {
			unset( $expected['pro-free-trial'] );

			update_option( 'hcaptcha_settings', [ 'license' => 'pro' ] );
		}

		if ( $force ) {
			unset( $expected['force'] );

			update_option( 'hcaptcha_settings', [ 'force' => [ 'on' ] ] );
		}

		hcaptcha()->init_hooks();

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
			'empty_keys' => [ true, false, false ],
			'pro'        => [ false, true, false ],
			'force'      => [ false, false, true ],
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
	 * Test dismiss_notification().
	 *
	 * @return void
	 */
	public function test_dismiss_notification() {
		$user_id = 1;

		wp_set_current_user( $user_id );

		$action   = Notifications::DISMISS_NOTIFICATION_ACTION;
		$nonce    = wp_create_nonce( $action );
		$key      = 'some-notification';
		$die_arr  = [];
		$expected = [
			'',
			'',
			[ 'response' => null ],
		];

		$_REQUEST['action'] = $action;
		$_REQUEST['nonce']  = $nonce;
		$_POST['id']        = $key;

		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter(
			'wp_die_ajax_handler',
			static function () use ( &$die_arr ) {
				return static function ( $message, $title, $args ) use ( &$die_arr ) {
					$die_arr = [ $message, $title, $args ];
				};
			}
		);

		$subject = new Notifications();

		ob_start();
		$subject->dismiss_notification();
		$json = ob_get_clean();

		$dismissed = get_user_meta( $user_id, Notifications::HCAPTCHA_DISMISSED_META_KEY, true );

		self::assertSame( [ $key ], $dismissed );
		self::assertSame( $expected, $die_arr );
		self::assertSame( '{"success":true}', $json );
	}

	/**
	 * Test dismiss_notification() with bad ajax referer.
	 *
	 * @return void
	 */
	public function test_dismiss_notification_with_bad_ajax_referer() {
		$die_arr  = [];
		$expected = [
			'',
			'',
			[ 'response' => null ],
		];

		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter(
			'wp_die_ajax_handler',
			static function () use ( &$die_arr ) {
				return static function ( $message, $title, $args ) use ( &$die_arr ) {
					$die_arr = [ $message, $title, $args ];
				};
			}
		);

		$subject = new Notifications();

		ob_start();
		$subject->dismiss_notification();
		$json = ob_get_clean();

		self::assertSame( $expected, $die_arr );
		self::assertSame(
			0,
			strpos(
				$json,
				'{"success":false,"data":"Your session has expired. Please reload the page."}'
			)
		);
	}

	/**
	 * Test dismiss_notification() when a user has no caps.
	 *
	 * @return void
	 */
	public function test_dismiss_notification_when_user_has_no_caps() {
		$action = Notifications::DISMISS_NOTIFICATION_ACTION;
		$nonce  = wp_create_nonce( $action );

		$_REQUEST['action'] = $action;
		$_REQUEST['nonce']  = $nonce;

		$die_arr  = [];
		$expected = [
			'',
			'',
			[ 'response' => null ],
		];

		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter(
			'wp_die_ajax_handler',
			static function () use ( &$die_arr ) {
				return static function ( $message, $title, $args ) use ( &$die_arr ) {
					$die_arr = [ $message, $title, $args ];
				};
			}
		);

		$subject = new Notifications();

		ob_start();
		$subject->dismiss_notification();
		$json = ob_get_clean();

		self::assertSame( $expected, $die_arr );
		self::assertSame(
			0,
			strpos(
				$json,
				'{"success":false,"data":"You are not allowed to perform this action."}'
			)
		);
	}

	/**
	 * Test dismiss_notification() when there is an update error.
	 *
	 * @return void
	 */
	public function test_dismiss_notification_when_update_error() {
		$user_id = 1;

		wp_set_current_user( $user_id );

		$action = Notifications::DISMISS_NOTIFICATION_ACTION;
		$nonce  = wp_create_nonce( $action );

		$_REQUEST['action'] = $action;
		$_REQUEST['nonce']  = $nonce;

		$die_arr  = [];
		$expected = [
			'',
			'',
			[ 'response' => null ],
		];

		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter(
			'wp_die_ajax_handler',
			static function () use ( &$die_arr ) {
				return static function ( $message, $title, $args ) use ( &$die_arr ) {
					$die_arr = [ $message, $title, $args ];
				};
			}
		);

		$subject = new Notifications();

		// Test the case when no notification id was sent.
		ob_start();
		$subject->dismiss_notification();
		$json = ob_get_clean();

		self::assertSame( $expected, $die_arr );
		self::assertSame(
			0,
			strpos(
				$json,
				'{"success":false,"data":"Error dismissing notification."}'
			)
		);

		$key         = 'some-notification';
		$_POST['id'] = $key;

		// Test the case when the notification was already dismissed.
		update_user_meta( $user_id, Notifications::HCAPTCHA_DISMISSED_META_KEY, [ $key ] );

		ob_start();
		$subject->dismiss_notification();
		$json = ob_get_clean();

		self::assertSame( $expected, $die_arr );
		self::assertSame(
			0,
			strpos(
				$json,
				'{"success":false,"data":"Error dismissing notification."}'
			)
		);

		// Test the case when it is unable to write to user_meta.
		delete_user_meta( $user_id, Notifications::HCAPTCHA_DISMISSED_META_KEY );
		add_filter( 'update_user_metadata', '__return_false' );

		ob_start();
		$subject->dismiss_notification();
		$json = ob_get_clean();

		self::assertSame( $expected, $die_arr );
		self::assertSame(
			0,
			strpos(
				$json,
				'{"success":false,"data":"Error dismissing notification."}'
			)
		);
	}

	/**
	 * Test reset_notifications().
	 *
	 * @return void
	 */
	public function test_reset_notifications() {
		$die_arr  = [];
		$expected = [
			'',
			'',
			[ 'response' => null ],
		];

		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter(
			'wp_die_ajax_handler',
			static function () use ( &$die_arr ) {
				return static function ( $message, $title, $args ) use ( &$die_arr ) {
					$die_arr = [ $message, $title, $args ];
				};
			}
		);

		$subject = new Notifications();

		// Test the case when bad admin referer.
		ob_start();
		$subject->reset_notifications();
		$json = ob_get_clean();

		self::assertSame( $expected, $die_arr );
		self::assertSame(
			0,
			strpos(
				$json,
				'{"success":false,"data":"Your session has expired. Please reload the page."}'
			)
		);

		$action = Notifications::RESET_NOTIFICATIONS_ACTION;
		$nonce  = wp_create_nonce( $action );

		$_REQUEST['action'] = $action;
		$_REQUEST['nonce']  = $nonce;

		// Test the case when a user has no caps.
		ob_start();
		$subject->reset_notifications();
		$json = ob_get_clean();

		self::assertSame( $expected, $die_arr );
		self::assertSame(
			0,
			strpos(
				$json,
				'{"success":false,"data":"You are not allowed to perform this action."}'
			)
		);

		$user_id = 1;

		wp_set_current_user( $user_id );

		$nonce = wp_create_nonce( $action );

		$_REQUEST['nonce'] = $nonce;

		// Test the case when we cannot delete user meta.
		ob_start();
		$subject->reset_notifications();
		$json = ob_get_clean();

		add_filter( 'delete_user_metadata', '__return_false' );

		self::assertSame( $expected, $die_arr );
		self::assertSame(
			0,
			strpos(
				$json,
				'{"success":false,"data":"Error removing dismissed notifications."}'
			)
		);

		update_user_meta( $user_id, Notifications::HCAPTCHA_DISMISSED_META_KEY, [ 'some-key' ] );
		remove_all_filters( 'delete_user_metadata' );

		// Test successful case.
		ob_start();
		$subject->reset_notifications();
		$json = ob_get_clean();

		$dismissed = get_user_meta( $user_id, Notifications::HCAPTCHA_DISMISSED_META_KEY, true );

		self::assertSame( '', $dismissed );
		self::assertSame( $expected, $die_arr );
		self::assertSame( '{"success":true,"data":""}', $json );
	}

	/**
	 * Test make_key_first().
	 *
	 * @return void
	 */
	public function test_make_key_first() {
		$subject = Mockery::mock( Notifications::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();

		$notifications = [
			'first'  => 'first',
			'second' => 'second',
			'third'  => 'third',
		];
		$expected      = [
			'third'  => 'third',
			'first'  => 'first',
			'second' => 'second',
		];

		self::assertSame( $notifications, $subject->make_key_first( $notifications, 'some' ) );
		self::assertSame( $expected, $subject->make_key_first( $notifications, 'third' ) );
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
