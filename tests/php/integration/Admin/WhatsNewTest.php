<?php
/**
 * WhatsNewTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\Admin;

use HCaptcha\Admin\Notifications;
use HCaptcha\Admin\WhatsNew;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use Mockery;
use ReflectionClass;
use ReflectionException;

/**
 * Test WhatsNew class.
 *
 * @group whats-new
 */
class WhatsNewTest extends HCaptchaWPTestCase {

	/**
	 * Test init() and init_hooks().
	 */
	public function test_init_and_init_hooks(): void {
		$subject = new WhatsNew();

		$subject->init();

		self::assertSame( 10, has_action( 'kagg_settings_tab', [ $subject, 'action_settings_tab' ] ) );
		self::assertSame( 10, has_action( 'admin_print_footer_scripts', [ $subject, 'enqueue_assets' ] ) );
		self::assertSame( 10, has_action( 'admin_footer', [ $subject, 'maybe_show_popup' ] ) );
		self::assertSame( 10, has_action( 'wp_ajax_hcaptcha-mark-shown', [ $subject, 'mark_shown' ] ) );
		self::assertSame( 1010, has_filter( 'update_footer', [ $subject, 'update_footer' ] ) );
	}

	/**
	 * Test action_settings_tab().
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_action_settings_tab(): void {
		$subject = new WhatsNew();

		$subject->action_settings_tab();

		self::AssertTrue( $this->get_protected_property( $subject, 'allowed' ) );
	}

	/**
	 * Test enqueue_assets().
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_enqueue_assets(): void {
		$handle         = 'hcaptcha-whats-new';
		$action         = 'hcaptcha-mark-shown';
		$params         = [
			'ajaxUrl'         => 'http://test.test/wp-admin/admin-ajax.php',
			'markShownAction' => $action,
			'markShownNonce'  => wp_create_nonce( $action ),
		];
		$expected_extra = [
			'group' => 1,
			// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
			'data'  => 'var HCaptchaWhatsNewObject = ' . json_encode( $params ) . ';',
		];

		$subject = new WhatsNew();

		// Not allowed.
		$subject->enqueue_assets();

		self::assertFalse( wp_style_is( $handle ) );

		// Allowed.
		$this->set_protected_property( $subject, 'allowed', true );
		$subject->enqueue_assets();

		self::assertTrue( wp_style_is( $handle ) );
		$style = wp_styles()->registered[ $handle ];
		self::assertSame( HCAPTCHA_URL . '/assets/css/whats-new.min.css', $style->src );
		self::assertSame( [], $style->deps );
		self::assertSame( HCAPTCHA_VERSION, $style->ver );

		$script = wp_scripts()->registered[ $handle ];
		self::assertSame( HCAPTCHA_URL . '/assets/js/whats-new.min.js', $script->src );
		self::assertSame( [ 'jquery' ], $script->deps );
		self::assertSame( HCAPTCHA_VERSION, $script->ver );
		self::assertSame( $expected_extra, $script->extra );
	}

	/**
	 * Test maybe_show_popup().
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_maybe_show_popup(): void {
		$subject = Mockery::mock( WhatsNew::class )->makePartial();

		// Not allowed.
		$subject->maybe_show_popup();

		// Allowed.
		$this->set_protected_property( $subject, 'allowed', true );
		update_option(
			'hcaptcha_settings',
			[ 'whats_new_last_shown_version' => '4.0.0-RC1' ]
		);

		$prefix     = 'whats_new_';
		$reflection = new ReflectionClass( WhatsNew::class );
		$methods    = array_filter(
			array_map(
				static function ( $reflection_method ) {
					return $reflection_method->getName();
				},
				$reflection->getMethods()
			),
			static function ( $method ) use ( $prefix ) {
				return 0 === strpos( $method, $prefix );
			}
		);
		$versions   = array_map(
			static function ( $method ) use ( $prefix ) {
				return str_replace( [ $prefix, '_' ], [ '', '.' ], $method );
			},
			$methods
		);

		usort( $versions, 'version_compare' );

		$versions = array_reverse( $versions );
		$version  = $versions[0];
		$method   = $prefix . str_replace( '.', '_', $version );

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'render_popup' )->once()->with( $method, true );

		$subject->maybe_show_popup();
	}

	/**
	 * Test mark_shown().
	 *
	 * @return void
	 */
	public function test_mark_shown(): void {
		$user_id = 1;

		wp_set_current_user( $user_id );

		$action   = 'hcaptcha-mark-shown';
		$nonce    = wp_create_nonce( $action );
		$version  = '4.13.0';
		$die_arr  = [];
		$expected = [
			'',
			'',
			[ 'response' => null ],
		];

		$_REQUEST['action'] = $action;
		$_REQUEST['nonce']  = $nonce;
		$_POST['version']   = $version;

		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter(
			'wp_die_ajax_handler',
			static function () use ( &$die_arr ) {
				return static function ( $message, $title, $args ) use ( &$die_arr ) {
					$die_arr = [ $message, $title, $args ];
				};
			}
		);

		$subject = Mockery::mock( WhatsNew::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();

		$subject->shouldReceive( 'update_whats_new' )->once()->with( $version );

		ob_start();
		$subject->mark_shown();
		$json = ob_get_clean();

		self::assertSame( $expected, $die_arr );
		self::assertSame( '{"success":true}', $json );
	}

	/**
	 * Test mark_shown() with bad ajax referer.
	 *
	 * @return void
	 */
	public function test_mark_shown_with_bad_ajax_referer(): void {
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

		$subject = new WhatsNew();

		ob_start();
		$subject->mark_shown();
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
	 * Test mark_shown() when a user has no caps.
	 *
	 * @return void
	 */
	public function test_mark_shown_when_user_has_no_caps(): void {
		$action = 'hcaptcha-mark-shown';
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

		$subject = new WhatsNew();

		ob_start();
		$subject->mark_shown();
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
	 * Test update_footer().
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_update_footer(): void {
		$content = 'Some content';

		$subject = Mockery::mock( WhatsNew::class )->makePartial();

		// Not allowed.
		self::assertSame( $content, $subject->update_footer( $content ) );

		// Allowed.
		$this->set_protected_property( $subject, 'allowed', true );

		$link     = '<a href="#" id="hcaptcha-whats-new-link" rel="noopener noreferrer">See the new features!</a>';
		$expected = $content . ' - ' . $link;

		self::assertSame( $expected, $subject->update_footer( $content ) );
	}

	/**
	 * Test render_popup().
	 *
	 * @return void
	 */
	public function test_render_popup(): void {
		$non_existing_method = 'non_existing_method';
		$method              = 'whats_new_4_13_0';

		$subject = Mockery::mock( WhatsNew::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();

		$subject->shouldReceive( $non_existing_method )->never();
		$subject->shouldReceive( $method )->once();

		// Non-existing method.
		ob_start();

		$subject->render_popup( $non_existing_method, true );

		self::assertSame( '', ob_get_clean() );

		// Mocked method.
		$version  = '4.13.0';
		$expected = <<<HTML
		<div
				id="hcaptcha-whats-new-modal" class="hcaptcha-whats-new-modal"
				style="display: flex;">
			<div class="hcaptcha-whats-new-modal-bg"></div>
			<div class="hcaptcha-whats-new-modal-popup">
				<button id="hcaptcha-whats-new-close" class="hcaptcha-whats-new-close"></button>
				<div class="hcaptcha-whats-new-header">
					<div class="hcaptcha-whats-new-icon">
						<img
								src="http://test.test/wp-content/plugins/hcaptcha-wordpress-plugin/assets/images/hcaptcha-icon-animated.svg"
								alt="Icon">
					</div>
					<div class="hcaptcha-whats-new-title">
						<h1>
							What&#039;s New in hCaptcha							<span id="hcaptcha-whats-new-version">$version</span>
						</h1>
					</div>
				</div>
				<div class="hcaptcha-whats-new-content">
									</div>
			</div>
		</div>
		<div id="hcaptcha-lightbox-modal">
			<img id="hcaptcha-lightbox-img" src="" alt="lightbox-image">
		</div>
		
HTML;

		ob_start();

		$subject->render_popup( $method, true );

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test whats_new_4_13_0().
	 *
	 * @return void
	 * @noinspection HtmlUnknownAnchorTarget
	 */
	public function test_whats_new_4_13_0(): void {
		$expected = <<<'HTML'
		<div class="hcaptcha-whats-new-block center">
						<div class="hcaptcha-whats-new-badge">
				New Feature			</div>
						<h2>
				Site Content Protection			</h2>
			<div class="hcaptcha-whats-new-message">
				<p>Protect selected site URLs from bots with hCaptcha. Works best with <a href="https://dashboard.hcaptcha.com/?r=wp&amp;utm_source=wordpress&amp;utm_medium=wpplugin&amp;utm_campaign=not" target="_blank">Pro</a> 99.9% passive mode.</p><p>Set up protected URLs to prevent these pages from being accessed by bots.</p>			</div>
			<div class="hcaptcha-whats-new-button">
				<a
						href="http://test.test/wp-admin/options-general.php?page=hcaptcha&#038;tab=general#protect_content_1" class="button button-primary"
						target="_blank">
					Protect Content				</a>
			</div>
			<div class="hcaptcha-whats-new-image">
															<a href="http://test.test/wp-content/plugins/hcaptcha-wordpress-plugin/assets/images/demo/protect-content.gif" class="hcaptcha-lightbox">
							<img src="http://test.test/wp-content/plugins/hcaptcha-wordpress-plugin/assets/images/demo/protect-content.gif" alt="What's New block image">
						</a>
												</div>
		</div>
				<div class="hcaptcha-whats-new-block center">
						<div class="hcaptcha-whats-new-badge">
				New Feature			</div>
						<h2>
				Friction-Free “No CAPTCHA” &amp; 99.9% Passive Modes			</h2>
			<div class="hcaptcha-whats-new-message">
				<a href="https://dashboard.hcaptcha.com/?r=wp&amp;utm_source=wordpress&amp;utm_medium=wpplugin&amp;utm_campaign=not" target="_blank">Upgrade to Pro</a> and use <a href="http://test.test/wp-admin/options-general.php?page=hcaptcha&amp;tab=general#size" target="_blank">Invisible Size</a>. The hCaptcha widget will not appear, and the Challenge popup will be shown only to bots.			</div>
			<div class="hcaptcha-whats-new-button">
				<a
						href="https://dashboard.hcaptcha.com/?r=wp&#038;utm_source=wordpress&#038;utm_medium=wpplugin&#038;utm_campaign=not" class="button button-primary"
						target="_blank">
					Upgrade to Pro				</a>
			</div>
			<div class="hcaptcha-whats-new-image">
															<a href="http://test.test/wp-content/plugins/hcaptcha-wordpress-plugin/assets/images/demo/passive-mode.gif" class="hcaptcha-lightbox">
							<img src="http://test.test/wp-content/plugins/hcaptcha-wordpress-plugin/assets/images/demo/passive-mode.gif" alt="What's New block image">
						</a>
												</div>
		</div>
		
HTML;

		add_filter(
			'hcap_settings_init_args',
			static function ( $args ) {
				$args['mode'] = 'tabs';

				return $args;
			}
		);

		unset( $current_user );
		wp_set_current_user( 1 );
		hcaptcha()->init_hooks();
		set_current_screen( 'hcaptcha' );
		do_action( 'admin_menu' );

		$subject = Mockery::mock( WhatsNew::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();

		ob_start();

		$subject->whats_new_4_13_0();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test update_whats_new().
	 *
	 * @return void
	 */
	public function test_update_whats_new(): void {
		$key      = 'whats_new_last_shown_version';
		$settings = hcaptcha()->settings();

		$subject = Mockery::mock( WhatsNew::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();

		// Non-valid version.
		$subject->update_whats_new( 'wrong-version' );

		self::assertSame( '', $settings->get( $key ) );

		// Valid version.
		$version = '4.13.0';

		$subject->update_whats_new( $version );

		self::assertSame( $version, $settings->get( $key ) );
	}


	/**
	 * Test show().
	 *
	 * @return void
	 * @noinspection HtmlUnknownAttribute
	 */
	public function est_show(): void {
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
			   class="button button-primary " target="_blank">
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
				href="https://www.hcaptcha.com/pro/?r=wp&amp;utm_source=wordpress&amp;utm_medium=wpplugin&amp;utm_campaign=not"
				target="_blank">hCaptcha Pro</a> is for you. <a
				href="https://dashboard.hcaptcha.com/?r=wp&amp;utm_source=wordpress&amp;utm_medium=wpplugin&amp;utm_campaign=not"
				target="_blank">Start a free trial in your dashboard</a>, no credit card required.</p>
		<div class="hcaptcha-notification-buttons hidden">
			<a href="https://www.hcaptcha.com/pro/?r=wp&#038;utm_source=wordpress&#038;utm_medium=wpplugin&#038;utm_campaign=not"
			   class="button button-primary " target="_blank">
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
			   class="button button-primary " target="_blank">
				Read post </a>
		</div>
	</div>
	<div id="hcaptcha-notifications-footer">
		<div id="hcaptcha-navigation">
			<span>
				<span id="hcaptcha-navigation-page">1</span> of <span id="hcaptcha-navigation-pages">3</span>
			</span>
			<a class="prev button disabled"></a>
			<a class="next button "></a>
		</div>
	</div>
</div>
';

		$expected = $this->trim_tags( $expected );

		$subject = new Notifications();

		ob_start();
		$subject->show();
		$actual = $this->trim_tags( ob_get_clean() );

		$header  = '<div id="hcaptcha-notifications"> <div id="hcaptcha-notifications-header"> Notifications </div>';
		$body    = '<div .+</div>';
		$footer  = '<div id="hcaptcha-notifications-footer"> <div id="hcaptcha-navigation"> <span> <span id="hcaptcha-navigation-page">1</span> of <span id="hcaptcha-navigation-pages">x</span> </span> <a class="prev button disabled"></a> <a class="next button "></a> </div> </div> </div>';
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
		update_user_meta( $user_id, Notifications::HCAPTCHA_DISMISSED_META_KEY, [ 'pro-free-trial' ] );

		$dismissed_notification = '
<div
		class="hcaptcha-notification notice notice-info is-dismissible inline"
		data-id="pro-free-trial">
	<div class="hcaptcha-notification-title">
		Try Pro for free
	</div>
	<p>Want low friction and custom themes? <a
			href="https://www.hcaptcha.com/pro/?r=wp&amp;utm_source=wordpress&amp;utm_medium=wpplugin&amp;utm_campaign=not"
			target="_blank">hCaptcha Pro</a> is for you. <a
			href="https://dashboard.hcaptcha.com/?r=wp&amp;utm_source=wordpress&amp;utm_medium=wpplugin&amp;utm_campaign=not"
			target="_blank">Start a free trial in your dashboard</a>, no credit card required.</p>
	<div class="hcaptcha-notification-buttons hidden">
		<a href="https://www.hcaptcha.com/pro/?r=wp&#038;utm_source=wordpress&#038;utm_medium=wpplugin&#038;utm_campaign=not"
		   class="button button-primary " target="_blank">
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
	 */
	public function est_show_without_notifications(): void {
		global $current_user;

		$user_id  = 1;
		$expected = '';

		$subject = Mockery::mock( Notifications::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();

		$notifications = $subject->get_notifications();
		$dismissed     = array_keys( $notifications );

		unset( $current_user );
		wp_set_current_user( $user_id );
		update_user_meta( $user_id, Notifications::HCAPTCHA_DISMISSED_META_KEY, $dismissed );

		ob_start();
		$subject->show();
		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test dismiss_notification() when there is an update error.
	 *
	 * @return void
	 */
	public function est_dismiss_notification_when_update_error(): void {
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
	public function est_reset_notifications(): void {
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

		$subject = Mockery::mock( Notifications::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();

		// Test the case when a bad admin referer.
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

		// Test a successful case.
		add_filter( 'hcap_shuffle_notifications', '__return_false' );

		ob_start();
		$subject->show();
		$notifications = wp_json_encode( wp_kses_post( ob_get_clean() ) );

		ob_start();
		$subject->reset_notifications();
		$json = ob_get_clean();

		$dismissed = get_user_meta( $user_id, Notifications::HCAPTCHA_DISMISSED_META_KEY, true );

		self::assertSame( '', $dismissed );
		self::assertSame( $expected, $die_arr );
		self::assertSame( '{"success":true,"data":' . $notifications . '}', $json );
	}

	/**
	 * Test make_key_first().
	 *
	 * @return void
	 */
	public function est_make_key_first(): void {
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
	 * Cut pages span as it may contain different numbers of pages.
	 *
	 * @param string $html Html.
	 *
	 * @return string
	 */
	private function trim_tags( string $html ): string {
		return preg_replace(
			[ '/\s+/', '#(<span id="hcaptcha-navigation-pages">)\d+?(</span>)#' ],
			[ ' ', '$1x$2' ],
			trim( $html )
		);
	}
}
