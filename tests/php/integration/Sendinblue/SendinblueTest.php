<?php
/**
 * SendinblueTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */

namespace HCaptcha\Tests\Integration\Sendinblue;

use HCaptcha\AutoVerify\AutoVerify;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Sendinblue\Sendinblue;
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use ReflectionMethod;
use SIB_Forms;
use SIB_Manager;
use SIB_Widget_Subscribe;
use WP_Widget;

/**
 * Test Sendinblue class.
 *
 * @group sendinblue
 */
class SendinblueTest extends HCaptchaPluginWPTestCase {

	/**
	 * Plugin relative path.
	 *
	 * @var string
	 */
	protected static $plugin = 'mailin/sendinblue.php';

	/**
	 * Hooks to replay after loading Brevo.
	 *
	 * @var string[]
	 */
	protected static array $plugin_load_hooks = [
		'plugins_loaded',
		'init',
	];

	/**
	 * Force lifecycle hook replay after WPTestCase resets action counters.
	 *
	 * @var bool
	 */
	protected static bool $force_plugin_load_hooks = true;

	/**
	 * Configure Brevo to register its frontend form shortcode.
	 *
	 * @return void
	 */
	protected function before_load_test_plugins(): void {
		update_option( 'sib_api_key_v3', 'integration-test-key' );
		update_option( 'sib_main_option', [ 'access_key' => 'integration-test-key' ] );
		update_option(
			'sib_home_option',
			[
				'activate_email' => 'no',
				'activate_ma'    => 'no',
			]
		);
		update_option( 'sib_use_apiv2', '1' );
		set_transient(
			'sib_list_' . md5( 'integration-test-key' ),
			[
				[
					'id'   => 1,
					'name' => 'Integration List',
				],
			],
			900
		);
	}

	/**
	 * Test constructor and init_hooks().
	 *
	 * @return void
	 */
	public function test_constructor_and_init_hooks(): void {
		$subject = new Sendinblue();

		self::assertSame(
			10,
			has_filter( 'do_shortcode_tag', [ $subject, 'add_hcaptcha' ] )
		);
		self::assertSame(
			10,
			has_filter( 'hcap_verify_request', [ $subject, 'verify_request' ] )
		);
		self::assertSame(
			9,
			has_action( 'wp_print_footer_scripts', [ $subject, 'enqueue_scripts' ] )
		);
		self::assertSame(
			10,
			has_filter( 'script_loader_tag', [ $subject, 'add_type_module' ] )
		);
	}

	/**
	 * Test add_hcaptcha() with the wrong shortcode tag.
	 *
	 * @return void
	 */
	public function test_add_hcaptcha_wrong_tag(): void {
		$subject = new Sendinblue();
		$output  = '<form><button type="submit">Send</button></form>';

		self::assertSame( $output, $subject->add_hcaptcha( $output, 'some_shortcode', [], [] ) );
	}

	/**
	 * Test add_hcaptcha() with the correct shortcode tag.
	 *
	 * @return void
	 */
	public function test_add_hcaptcha(): void {
		$subject = new Sendinblue();
		$output  = '<form><button class="sib" type="submit">Send</button></form>';

		$result = $subject->add_hcaptcha( $output, 'sibwp_form', [ 'id' => 5 ], [] );

		self::assertStringContainsString( 'h-captcha', $result );
		self::assertStringContainsString( 'hcaptcha_sendinblue_nonce', $result );
		self::assertStringContainsString( '<button class="sib" type="submit">Send</button>', $result );
	}

	/**
	 * Test hCaptcha in a form rendered by the live Brevo shortcode.
	 *
	 * @return void
	 */
	public function test_live_form_shortcode(): void {
		SIB_Forms::createTable();

		$forms = SIB_Forms::getForms();

		if ( ! $forms ) {
			SIB_Forms::createDefaultForm();
			$forms = SIB_Forms::getForms();
		}

		$form_id = (int) $forms[0]['id'];
		$subject = new Sendinblue();
		$method  = new ReflectionMethod( SIB_Manager::class, 'sibwp_form_shortcode' );
		$html    = do_shortcode( '[sibwp_form id="' . $form_id . '"]' );

		self::assertTrue( is_plugin_active( static::$plugin ) );
		self::assertStringStartsWith(
			wp_normalize_path( WP_PLUGIN_DIR . '/mailin/' ),
			wp_normalize_path( (string) $method->getFileName() )
		);
		self::assertTrue( shortcode_exists( 'sibwp_form' ) );
		self::assertStringContainsString( 'id="sib_signup_form_' . $form_id . '"', $html );
		self::assertStringContainsString( 'name="sib_form_action"', $html );
		self::assertStringContainsString( 'name="hcaptcha_sendinblue_nonce"', $html );
		self::assertSame( 10, has_filter( 'do_shortcode_tag', [ $subject, 'add_hcaptcha' ] ) );
	}

	/**
	 * Omitted and legacy shortcode attributes must sign the form actually rendered by Brevo.
	 *
	 * @dataProvider dp_test_legacy_shortcode_identity
	 *
	 * @param string $shortcode Native shortcode spelling.
	 */
	public function test_legacy_shortcode_identity( string $shortcode ): void {
		SIB_Forms::createTable();
		$forms   = SIB_Forms::getForms();
		$form_id = (int) $forms[0]['id'];
		update_option( 'sib_old_form_id', $form_id );
		new Sendinblue();
		$html = do_shortcode( $shortcode );

		self::assertStringContainsString( 'name="sib_form_id" value="' . $form_id . '"', $html );
		self::assertStringContainsString(
			'value="' . esc_attr(
				HCaptcha::widget_id_value(
					[
						'source'  => [ static::$plugin ],
						'form_id' => $form_id,
					]
				)
			) . '"',
			$html
		);
		self::assertSame( 1, substr_count( $html, 'name="hcaptcha_sendinblue_nonce"' ) );
	}

	/**
	 * Native default shortcode selectors.
	 *
	 * @return array
	 */
	public function dp_test_legacy_shortcode_identity(): array {
		return [
			'omitted ID'  => [ '[sibwp_form]' ],
			'legacy name' => [ '[sibwp_form id="oldForm"]' ],
		];
	}

	/**
	 * Native sidebar widgets need the same challenge and canonical identity as shortcodes.
	 */
	public function test_native_widget_renders_protected_form(): void {
		SIB_Forms::createTable();
		$forms   = SIB_Forms::getForms();
		$form_id = (int) $forms[0]['id'];
		new Sendinblue();
		$widget = new SIB_Widget_Subscribe();
		update_option(
			$widget->option_name,
			[
				2              => [
					'sib_form_list' => (string) $form_id,
					'widget_title'  => 'SEC006 newsletter',
				],
				'_multiwidget' => 1,
			]
		);
		$args = [
			'before_widget' => '<section>',
			'after_widget'  => '</section>',
			'before_title'  => '<h2>',
			'after_title'   => '</h2>',
		];
		ob_start();
		$widget->display_callback( $args, 2 );
		$html = (string) ob_get_clean();

		self::assertStringContainsString( '<h2>SEC006 newsletter</h2>', $html );
		self::assertStringContainsString( 'name="sib_form_id" value="' . $form_id . '"', $html );
		self::assertStringContainsString(
			'value="' . esc_attr(
				HCaptcha::widget_id_value(
					[
						'source'  => [ static::$plugin ],
						'form_id' => $form_id,
					]
				)
			) . '"',
			$html
		);
		self::assertSame( 1, substr_count( $html, 'name="hcaptcha_sendinblue_nonce"' ) );
	}

	/**
	 * Preview rendering restores WordPress's cache insertion state.
	 *
	 * @noinspection PhpParamsInspection
	 */
	public function test_preview_widget_restores_cache_state(): void {
		$widget   = new class() extends SIB_Widget_Subscribe {
			/**
			 * Simulate a Customizer preview.
			 *
			 * @return bool
			 */
			public function is_preview(): bool {
				return true;
			}

			/**
			 * Render the native form shape without a remote list lookup.
			 *
			 * @param array $args     Widget arguments.
			 * @param array $instance Widget instance.
			 *
			 * @noinspection PhpMissingParamTypeInspection
			 * @noinspection PhpUnusedParameterInspection
			 */
			public function widget( $args, $instance ): void {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Test fixture.
				echo '<form><button type="submit">Subscribe</button></form>';
			}
		};
		$previous = wp_suspend_cache_addition();
		wp_suspend_cache_addition( false );

		try {
			ob_start();
			self::assertFalse( ( new Sendinblue() )->add_hcaptcha_widget( [], $widget, [] ) );
			$output = (string) ob_get_clean();

			self::assertStringContainsString( 'name="hcaptcha_sendinblue_nonce"', $output );
			self::assertFalse( wp_suspend_cache_addition() );
		} finally {
			wp_suspend_cache_addition( $previous );
		}
	}

	/**
	 * Invalid native selectors stay unmatched, while Brevo's new selector uses ID zero.
	 */
	public function test_native_selector_boundaries(): void {
		$subject = new Sendinblue();

		self::assertNull( $subject->adapt_unmatched_form( 'invalid' ) );
		$_POST['sib_form_action'] = 'subscribe_form_submit';
		$_POST['sib_security']    = wp_create_nonce( 'sib_front_ajax_nonce' );
		$_POST['sib_form_id']     = [];
		$_POST['email']           = 'subscriber@example.com';
		self::assertSame( [ 'other' => true ], $subject->adapt_auto_entry( [ 'other' => true ] ) );

		$_POST['sib_form_id'] = 'new';
		$entry                = $subject->adapt_auto_entry( [] );

		self::assertSame( 0, $entry['expected_id']['form_id'] );
		self::assertSame( [ 'subscriber@example.com' ], $entry['email_addresses'] );
	}

	/**
	 * Later native widget suppression must prevent both HTML and automatic form registration.
	 */
	public function test_native_widget_respects_later_suppression(): void {
		SIB_Forms::createTable();
		$forms   = SIB_Forms::getForms();
		$form_id = (int) $forms[0]['id'];
		AutoVerify::delete_all();
		( new AutoVerify() )->init();
		new Sendinblue();
		$_SERVER['REQUEST_URI'] = '/sec006-suppressed-widget/';
		add_filter(
			'widget_display_callback',
			static function ( $instance, WP_Widget $widget ) {
				return $widget instanceof SIB_Widget_Subscribe ? false : $instance;
			},
			20,
			2
		);

		try {
			$html = $this->render_native_widget( $form_id );

			self::assertSame( '', $html );
			self::assertFalse( get_option( 'hcaptcha_auto_verify_form_' . hash( 'sha256', '/sec006-suppressed-widget' ) ) );
		} finally {
			AutoVerify::delete_all();
		}
	}

	/**
	 * Later instance changes must determine the native title, form, and signed challenge identity.
	 */
	public function test_native_widget_respects_later_instance_changes(): void {
		global $wpdb;

		SIB_Forms::createTable();
		$forms             = SIB_Forms::getForms();
		$form_id           = (int) $forms[0]['id'];
		$changed_id        = $form_id + 1000;
		$changed           = SIB_Forms::getForm( $form_id );
		$changed['id']     = $changed_id;
		$changed['listID'] = maybe_serialize( $changed['listID'] );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Populate another real native form row.
		$wpdb->replace( $wpdb->prefix . SIB_Forms::TABLE_NAME, $changed );
		new Sendinblue();
		add_filter(
			'widget_display_callback',
			static function ( $instance, WP_Widget $widget ) use ( $changed_id ) {
				if ( ! $widget instanceof SIB_Widget_Subscribe ) {
					return $instance;
				}

				return [
					'sib_form_list' => (string) $changed_id,
					'widget_title'  => 'Later native title',
				];
			},
			20,
			2
		);
		$html = $this->render_native_widget( $form_id );

		self::assertSame( 1, substr_count( $html, 'name="sib_form_id"' ) );
		self::assertStringContainsString( '<h2>Later native title</h2>', $html );
		self::assertStringContainsString( 'name="sib_form_id" value="' . $changed_id . '"', $html );
		self::assertStringContainsString(
			'value="' . esc_attr(
				HCaptcha::widget_id_value(
					[
						'source'  => [ static::$plugin ],
						'form_id' => $changed_id,
					]
				)
			) . '"',
			$html
		);
		self::assertSame( 1, substr_count( $html, 'name="hcaptcha_sendinblue_nonce"' ) );
	}

	/**
	 * Render through WordPress's actual native widget display lifecycle.
	 *
	 * @param int $form_id Original native form selector.
	 *
	 * @return string
	 */
	private function render_native_widget( int $form_id ): string {
		$widget = new SIB_Widget_Subscribe();
		update_option(
			$widget->option_name,
			[
				2              => [
					'sib_form_list' => (string) $form_id,
					'widget_title'  => 'Original native title',
				],
				'_multiwidget' => 1,
			]
		);
		$args = [
			'before_widget' => '<section>',
			'after_widget'  => '</section>',
			'before_title'  => '<h2>',
			'after_title'   => '</h2>',
		];
		ob_start();
		$widget->display_callback( $args, 2 );

		return (string) ob_get_clean();
	}

	/**
	 * Test verify_request() when not a Sendinblue request.
	 *
	 * @return void
	 */
	public function test_verify_request_not_sendinblue(): void {
		$subject = new Sendinblue();

		unset( $_POST['sib_form_action'] );

		self::assertSame( 'some_error', $subject->verify_request( 'some_error', [] ) );
	}

	/**
	 * Test verify_request() when verified (the result is null).
	 *
	 * @return void
	 */
	public function test_verify_request_verified(): void {
		$subject = new Sendinblue();

		$_POST['sib_form_action'] = 'submit';

		self::assertNull( $subject->verify_request( null, [] ) );
	}

	/**
	 * Test verify_request() when not verified (a result is error string).
	 *
	 * @return void
	 * @noinspection JsonEncodingApiUsageInspection
	 */
	public function test_verify_request_not_verified(): void {
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

		$_POST['sib_form_action'] = 'submit';

		$subject = new Sendinblue();

		ob_start();
		$subject->verify_request( 'Some hCaptcha error', [] );
		$json = ob_get_clean();

		$data = json_decode( $json, true );

		self::assertSame( 'failure', $data['status'] );
		self::assertSame( 'Some hCaptcha error', $data['msg']['errorMsg'] );
		self::assertSame( $expected, $die_arr );
	}

	/**
	 * Test enqueue_scripts() when form not shown.
	 *
	 * @return void
	 */
	public function test_enqueue_scripts_no_form(): void {
		$subject = new Sendinblue();

		hcaptcha()->form_shown = false;

		$subject->enqueue_scripts();

		self::assertFalse( wp_script_is( Sendinblue::HANDLE ) );
	}

	/**
	 * Test enqueue_scripts() when the form is shown.
	 *
	 * @return void
	 */
	public function test_enqueue_scripts(): void {
		$subject = new Sendinblue();

		hcaptcha()->form_shown = true;

		$subject->enqueue_scripts();

		self::assertTrue( wp_script_is( Sendinblue::HANDLE ) );

		$script = wp_scripts()->registered[ Sendinblue::HANDLE ];

		self::assertStringContainsString( 'hcaptcha-sendinblue', $script->src );
		self::assertContains( 'jquery', $script->deps );
		self::assertContains( 'hcaptcha', $script->deps );
		self::assertSame( HCAPTCHA_VERSION, $script->ver );
	}

	/**
	 * Test add_type_module() with a wrong handle.
	 *
	 * @return void
	 * @noinspection HtmlUnknownTarget
	 */
	public function test_add_type_module_wrong_handle(): void {
		$subject = new Sendinblue();

		// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript
		$tag = '<script src="some.js"></script>';

		self::assertSame( $tag, $subject->add_type_module( $tag, 'wrong-handle', 'some.js' ) );
	}

	/**
	 * Test add_type_module() with a correct handle.
	 *
	 * @return void
	 * @noinspection HtmlUnknownTarget
	 */
	public function test_add_type_module(): void {
		$subject = new Sendinblue();

		// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript
		$tag = '<script src="hcaptcha-sendinblue.js"></script>';

		$result = $subject->add_type_module( $tag, Sendinblue::HANDLE, 'hcaptcha-sendinblue.js' );

		self::assertStringContainsString( 'type="module"', $result );
	}
}
