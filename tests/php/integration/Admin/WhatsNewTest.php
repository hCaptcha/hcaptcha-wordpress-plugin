<?php
/**
 * WhatsNewTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\Admin;

use HCaptcha\Admin\OnboardingWizard;
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
	 *
	 * @param bool $wizard_completed Whether the wizard has been completed.
	 *
	 * @dataProvider dp_test_init_and_init_hooks
	 */
	public function test_init_and_init_hooks( bool $wizard_completed ): void {
		if ( $wizard_completed ) {
			update_option( 'hcaptcha_settings', [ OnboardingWizard::OPTION_NAME => 'completed' ] );
		}

		hcaptcha()->init_hooks();

		$subject = new WhatsNew();

		$subject->init();

		if ( $wizard_completed ) {
			self::assertSame( 10, has_action( 'kagg_settings_tab', [ $subject, 'action_settings_tab' ] ) );
			self::assertSame( 9, has_action( 'admin_print_footer_scripts', [ $subject, 'enqueue_assets' ] ) );
			self::assertSame( 10, has_action( 'admin_footer', [ $subject, 'maybe_show_popup' ] ) );
			self::assertSame( 10, has_action( 'wp_ajax_hcaptcha-mark-shown', [ $subject, 'mark_shown' ] ) );
			self::assertSame( 1010, has_filter( 'update_footer', [ $subject, 'update_footer' ] ) );
		} else {
			self::assertFalse( has_action( 'kagg_settings_tab', [ $subject, 'action_settings_tab' ] ) );
			self::assertFalse( has_action( 'admin_print_footer_scripts', [ $subject, 'enqueue_assets' ] ) );
			self::assertFalse( has_action( 'admin_footer', [ $subject, 'maybe_show_popup' ] ) );
			self::assertFalse( has_action( 'wp_ajax_hcaptcha-mark-shown', [ $subject, 'mark_shown' ] ) );
			self::assertFalse( has_filter( 'update_footer', [ $subject, 'update_footer' ] ) );
		}
	}

	/**
	 * Data provider for test_init_and_init_hooks().
	 *
	 * @return array
	 */
	public function dp_test_init_and_init_hooks(): array {
		return [
			'wizard not completed' => [ false ],
			'wizard completed'     => [ true ],
		];
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
			'whatsNewParam'   => 'whats_new',
		];
		$expected_extra = [
			'group' => 1,
			// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
			'data'  => 'var HCaptchaWhatsNewObject = ' . json_encode( $params, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES ) . ';',
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
			<div class="hcaptcha-whats-new-text">
				<div class="hcaptcha-whats-new-badge">
					New Feature
				</div>
				<h2> Site Content Protection </h2>
				<div class="hcaptcha-whats-new-message">
					<p>Protect selected site URLs from bots with hCaptcha. Works best with <a href="https://dashboard.hcaptcha.com/?r=wp&amp;utm_source=wordpress&amp;utm_medium=wpplugin&amp;utm_campaign=not" target="_blank">Pro</a> 99.9% passive mode.</p><p>Set up protected URLs to prevent these pages from being accessed by bots.</p>
				</div>
				<div class="hcaptcha-whats-new-button">
					<a
							href="http://test.test/wp-admin/options-general.php?page=hcaptcha&#038;tab=general#protect_content_1" class="button button-primary"
							target="_blank">
						Protect Content
					</a>
				</div>
			</div>
			<div class="hcaptcha-whats-new-image">
				<a href="http://test.test/wp-content/plugins/hcaptcha-wordpress-plugin/assets/images/demo/protect-content.gif" class="hcaptcha-lightbox">
					<img src="http://test.test/wp-content/plugins/hcaptcha-wordpress-plugin/assets/images/demo/protect-content.gif" alt="What's New block image">
				</a>
			</div>
		</div>
		<div class="hcaptcha-whats-new-block center">
			<div class="hcaptcha-whats-new-text">
				<div class="hcaptcha-whats-new-badge">
					New Feature
				</div>
				<h2> Friction-Free “No CAPTCHA” &amp; 99.9% Passive Modes </h2>
				<div class="hcaptcha-whats-new-message">
					<a href="https://dashboard.hcaptcha.com/?r=wp&amp;utm_source=wordpress&amp;utm_medium=wpplugin&amp;utm_campaign=not" target="_blank">Upgrade to Pro</a> and use <a href="http://test.test/wp-admin/options-general.php?page=hcaptcha&amp;tab=general#size" target="_blank">Invisible Size</a>. The hCaptcha widget will not appear, and the Challenge popup will be shown only to bots.
				</div>
				<div class="hcaptcha-whats-new-button">
					<a
							href="https://dashboard.hcaptcha.com/?r=wp&#038;utm_source=wordpress&#038;utm_medium=wpplugin&#038;utm_campaign=not" class="button button-primary"
							target="_blank">
						Upgrade to Pro
					</a>
				</div>
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

		self::assertSame( self::normalize_html( $expected ), self::normalize_html( ob_get_clean() ) );
	}

	/**
	 * Test whats_new_4_18_0().
	 *
	 * @return void
	 * @noinspection HtmlUnknownAnchorTarget
	 */
	public function test_whats_new_4_18_0(): void {
		$expected = <<<'HTML'
		<div class="hcaptcha-whats-new-block center">
			<div class="hcaptcha-whats-new-text">
						<div class="hcaptcha-whats-new-badge">
			New Feature			</div>
						<h2>
				Honeypot and Minimum Submit Time				</h2>
				<div class="hcaptcha-whats-new-message">
					<p>Added a hidden <a href="http://test.test/wp-admin/options-general.php?page=hcaptcha&amp;tab=general#honeypot_1" target="_blank">honeypot</a> field for bot detection before processing hCaptcha.</p><p>Added minimum form <a href="http://test.test/wp-admin/options-general.php?page=hcaptcha&amp;tab=general#set_min_submit_time_1" target="_blank">submit time</a> for bot detection before processing hCaptcha.</p><p>Currently supported for WordPress Core, Protect Content feature, and all integrations having more than 100,000 installs: Avada theme, Blocksy, Brevo, CoBlocks, Contact Form 7, Divi Builder, Divi theme, Download Manager, Elementor, Essential Addons for Elementor, Essential Blocks, Extra theme, Fluent Forms, Formidable Forms, Forminator, GiveWP Form, Gravity Forms, Jetpack, Kadence, MailPoet, Mailchimp, Ninja Forms, Otter, Password Protected, Protect Content feature, Spectra, Ultimate Addons for Elementor, WPForms, WooCommerce, and Wordfence.</p>				</div>
				<div class="hcaptcha-whats-new-button">
					<a
							href="http://test.test/wp-admin/options-general.php?page=hcaptcha&#038;tab=general#honeypot_1" class="button button-primary"
							target="_blank">
						Turn on honeypot					</a>
				</div>
			</div>
			<div class="hcaptcha-whats-new-image">
											<a href="http://test.test/wp-content/plugins/hcaptcha-wordpress-plugin/assets/images/demo/honeypot.png" class="hcaptcha-lightbox">
							<img src="http://test.test/wp-content/plugins/hcaptcha-wordpress-plugin/assets/images/demo/honeypot.png" alt="What's New block image">
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

		$subject->whats_new_4_18_0();

		self::assertSame( self::normalize_html( $expected ), self::normalize_html( ob_get_clean() ) );
	}

	/**
	 * Test whats_new_4_20_0().
	 *
	 * @return void
	 * @noinspection HtmlUnknownAnchorTarget
	 */
	public function test_whats_new_4_20_0(): void {
		$expected = <<<'HTML'
		<div class="hcaptcha-whats-new-block center">
			<div class="hcaptcha-whats-new-text">
				<div class="hcaptcha-whats-new-badge">
					New Feature
				</div>
				<h2> Onboarding Wizard </h2>
				<div class="hcaptcha-whats-new-message">
					<p>Added an onboarding wizard for new users.</p><p>You can restart it anytime by adding the <code>&amp;onboarding</code> parameter to the browser URL.</p>
				</div>
				<div class="hcaptcha-whats-new-button">
					<a
							href="http://test.test/wp-admin/options-general.php?page=hcaptcha&#038;tab=general&#038;onboarding" class="button button-primary"
							target="_blank">
						Restart wizard
					</a>
				</div>
			</div>
			<div class="hcaptcha-whats-new-image">
				<a href="http://test.test/wp-content/plugins/hcaptcha-wordpress-plugin/assets/images/demo/onboarding.gif" class="hcaptcha-lightbox">
					<img src="http://test.test/wp-content/plugins/hcaptcha-wordpress-plugin/assets/images/demo/onboarding.gif" alt="What's New block image">
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

		$subject->whats_new_4_20_0();

		self::assertSame( self::normalize_html( $expected ), self::normalize_html( ob_get_clean() ) );
	}

	/**
	 * Test whats_new_4_21_0().
	 *
	 * @return void
	 * @noinspection HtmlUnknownAnchorTarget
	 */
	public function test_whats_new_4_21_0(): void {
		$expected = <<<'HTML'
		<div class="hcaptcha-whats-new-block left">
			<div class="hcaptcha-whats-new-text">
				<div class="hcaptcha-whats-new-badge">
					New Feature
				</div>
				<h2> AI-Ready Security Actions </h2>
				<div class="hcaptcha-whats-new-message">
					<p>hCaptcha for WordPress now exposes selected security capabilities via the WordPress Abilities API — a machine-readable interface designed for automation tools and AI agents.</p><p>This enables programmatic threat monitoring and response workflows without relying on custom REST endpoints or UI automation.</p><p>Two initial abilities are included:</p><ul><li>Threat snapshot (aggregated metrics and top offenders)</li><li>Privacy-safe blocking based on hashed offender identifiers</li></ul>
				</div>
				<div class="hcaptcha-whats-new-button">
					<a
							href="https://wordpress.org/plugins/hcaptcha-for-forms-and-more/#how%20do%20i%20use%20the%20new%20ai%20/%20abilities%20features%3F" class="button button-primary"
							target="_blank">
						Read documentation
					</a>
				</div>
			</div>
			<div class="hcaptcha-whats-new-image">
				<a href="http://test.test/wp-content/plugins/hcaptcha-wordpress-plugin/assets/images/ai-abilities.png" class="hcaptcha-lightbox">
					<img src="http://test.test/wp-content/plugins/hcaptcha-wordpress-plugin/assets/images/ai-abilities.png" alt="What's New block image">
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

		$subject->whats_new_4_21_0();

		self::assertSame( self::normalize_html( $expected ), self::normalize_html( ob_get_clean() ) );
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
	 * Normalize HTML output for stable comparisons.
	 *
	 * @param string $html HTML.
	 *
	 * @return string
	 */
	private static function normalize_html( string $html ): string {
		return preg_replace( '/\s+/', ' ', trim( $html ) );
	}
}
