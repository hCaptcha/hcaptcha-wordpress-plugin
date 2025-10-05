<?php
/**
 * FormTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedFunctionInspection */

namespace HCaptcha\Tests\Integration\WPForms;

use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use HCaptcha\WPForms\Form;
use Mockery;
use ReflectionException;
use tad\FunctionMocker\FunctionMocker;

/**
 * Test Forms class.
 *
 * @group wpforms
 */
class FormTest extends HCaptchaPluginWPTestCase {

	/**
	 * Plugin relative path.
	 *
	 * @var string
	 */
	protected static $plugin = 'wpforms-lite/wpforms.php';

	/**
	 * Test init_hooks().
	 *
	 * @param bool $mode_auto  Mode auto.
	 * @param bool $mode_embed Mode embed.
	 *
	 * @return void
	 * @dataProvider dp_test_init_hooks
	 */
	public function test_init_hooks( bool $mode_auto, bool $mode_embed ): void {
		$status = [];

		if ( $mode_auto ) {
			$status[] = 'form';
		}

		if ( $mode_embed ) {
			$status[] = 'embed';

			add_filter(
				'wpforms_setting',
				static function ( $value, $key ) {
					if ( 'captcha-provider' === $key ) {
						return 'hcaptcha';
					}

					return $value;
				},
				10,
				2
			);
		}

		update_option( 'hcaptcha_settings', [ 'wpforms_status' => $status ] );

		hcaptcha()->init_hooks();

		$subject = new Form();

		if ( ! $mode_auto && ! $mode_embed ) {
			self::assertFalse( has_filter( 'wpforms_admin_settings_captcha_enqueues_disable', [ $subject, 'wpforms_admin_settings_captcha_enqueues_disable' ] ) );
			self::assertFalse( has_filter( 'hcap_print_hcaptcha_scripts', [ $subject, 'hcap_print_hcaptcha_scripts' ] ) );
			self::assertFalse( has_filter( 'wpforms_settings_fields', [ $subject, 'wpforms_settings_fields' ] ) );

			self::assertFalse( has_action( 'wp_head', [ $subject, 'print_inline_styles' ] ) );
			self::assertFalse( has_action( 'wpforms_wp_footer', [ $subject, 'block_assets_recaptcha' ] ) );

			self::assertFalse( has_action( 'wpforms_frontend_output', [ $subject, 'wpforms_frontend_output' ] ) );
			self::assertFalse( has_filter( 'wpforms_process_bypass_captcha', '__return_true' ) );
			self::assertFalse( has_action( 'wpforms_process', [ $subject, 'verify' ] ) );

			return;
		}

		if ( $mode_embed ) {
			self::assertSame( 10, has_filter( 'wpforms_admin_settings_captcha_enqueues_disable', [ $subject, 'wpforms_admin_settings_captcha_enqueues_disable' ] ) );
			self::assertSame( 0, has_filter( 'hcap_print_hcaptcha_scripts', [ $subject, 'hcap_print_hcaptcha_scripts' ] ) );
			self::assertSame( 10, has_filter( 'wpforms_settings_fields', [ $subject, 'wpforms_settings_fields' ] ) );
		}

		self::assertSame( 20, has_action( 'wp_head', [ $subject, 'print_inline_styles' ] ) );
		self::assertSame( 0, has_action( 'wpforms_wp_footer', [ $subject, 'block_assets_recaptcha' ] ) );

		self::assertSame( 19, has_action( 'wpforms_frontend_output', [ $subject, 'wpforms_frontend_output' ] ) );
		self::assertSame( 10, has_filter( 'wpforms_process_bypass_captcha', '__return_true' ) );
		self::assertSame( 10, has_action( 'wpforms_process', [ $subject, 'verify' ] ) );
	}

	/**
	 * Data provider for test_init_hooks().
	 *
	 * @return array
	 */
	public function dp_test_init_hooks(): array {
		return [
			[ false, false ],
			[ false, true ],
			[ true, false ],
			[ true, true ],
		];
	}

	/**
	 * Test verify().
	 *
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function test_verify(): void {
		$fields    = [ 'some field' ];
		$form_data = [ 'id' => 5 ];

		hcaptcha()->settings()->set( 'wpforms_status', [ 'form' ] );

		$subject = new Form();

		$this->prepare_verify_post( 'hcaptcha_wpforms_nonce', 'hcaptcha_wpforms' );

		wpforms()->objects();
		wpforms()->get( 'process' )->errors = [];

		$subject->verify( $fields, [], $form_data );

		self::assertSame( [], wpforms()->get( 'process' )->errors );
	}

	/**
	 * Test verify() when not process hcaptcha.
	 *
	 * @return void
	 */
	public function test_verify_when_not_process_hcaptcha(): void {
		$fields    = [ 'some field' ];
		$form_data = [ 'id' => 5 ];

		$subject = new Form();

		$subject->verify( $fields, [], $form_data );

		self::assertSame( [], wpforms()->get( 'process' )->errors );
	}

	/**
	 * Test verify() not verified.
	 *
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function test_verify_not_verified(): void {
		$fields    = [ 'some field' ];
		$form_data = [ 'id' => 5 ];
		$expected  = 'The hCaptcha is invalid.';

		hcaptcha()->settings()->set( 'wpforms_status', [ 'form' ] );

		$subject = new Form();

		$this->prepare_verify_post( 'hcaptcha_wpforms_nonce', 'hcaptcha_wpforms', false );

		wpforms()->objects();
		wpforms()->get( 'process' )->errors = [];

		$subject->verify( $fields, [], $form_data );

		self::assertSame( $expected, wpforms()->get( 'process' )->errors[ $form_data['id'] ]['footer'] );
	}

	/**
	 * Test verify() not verified with wpforms_settings.
	 *
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function test_verify_not_verified_with_wpforms_settings(): void {
		$fields                = [ 'some field' ];
		$form_data             = [
			'id'       => 5,
			'settings' => [
				'recaptcha' => '1',
			],
		];
		$wpforms_error_message = 'Some WPForms hCaptcha error message.';

		hcaptcha()->settings()->set( 'wpforms_status', [ 'form' ] );

		add_filter(
			'wpforms_setting',
			static function ( $value, $key ) use ( $wpforms_error_message ) {
				if ( 'captcha-provider' === $key ) {
					return 'hcaptcha';
				}

				if ( 'hcaptcha-fail-msg' === $key ) {
					return $wpforms_error_message;
				}

				return $value;
			},
			10,
			2
		);

		$subject = new Form();

		$this->prepare_verify_post( 'hcaptcha_wpforms_nonce', 'hcaptcha_wpforms', false );

		wpforms()->objects();
		wpforms()->get( 'process' )->errors = [];

		$subject->verify( $fields, [], $form_data );

		self::assertSame( $wpforms_error_message, wpforms()->get( 'process' )->errors[ $form_data['id'] ]['footer'] );
	}

	/**
	 * Test print_inline_styles().
	 *
	 * @return void
	 * @noinspection CssUnusedSymbol
	 */
	public function test_print_inline_styles(): void {
		FunctionMocker::replace(
			'defined',
			static function ( $constant_name ) {
				return 'SCRIPT_DEBUG' === $constant_name;
			}
		);

		FunctionMocker::replace(
			'constant',
			static function ( $name ) {
				return 'SCRIPT_DEBUG' === $name;
			}
		);

		$expected = <<<'CSS'
	div.wpforms-container-full .wpforms-form .h-captcha {
		position: relative;
		display: block;
		margin-bottom: 0;
		padding: 0;
		clear: both;
	}

	div.wpforms-container-full .wpforms-form .h-captcha[data-size="normal"] {
		width: 303px;
		height: 78px;
	}
	
	div.wpforms-container-full .wpforms-form .h-captcha[data-size="compact"] {
		width: 164px;
		height: 144px;
	}
	
	div.wpforms-container-full .wpforms-form .h-captcha[data-size="invisible"] {
		display: none;
	}

	div.wpforms-container-full .wpforms-form .h-captcha iframe {
		position: relative;
	}
CSS;
		$expected = "<style>\n$expected\n</style>\n";

		$subject = new Form();

		ob_start();

		$subject->print_inline_styles();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test wpforms_settings_fields().
	 *
	 * @return void
	 */
	public function test_wpforms_settings_fields(): void {
		$fields = [
			'hcaptcha-heading'    => '<div>Some hCaptcha heading</div>',
			'hcaptcha-site-key'   => '<div><span class="wpforms-setting-field"><input type="text"></span></div>',
			'hcaptcha-secret-key' => '<div><span class="wpforms-setting-field"><input type="text"></span></div>',
			'hcaptcha-fail-msg'   => '<div><span class="wpforms-setting-field"><input type="text"></span></div>',
			'captcha-preview'     => '<div><div class="wpforms-captcha wpforms-captcha-hcaptcha" data-sitekey="some key" data-theme=""></div></div>',
		];

		$general_page_url = esc_url( admin_url( 'options-general.php?page=hcaptcha&tab=general' ) );

		$notice_content = '
<div
		id="wpforms-setting-row-hcaptcha-heading"
		class="wpforms-setting-row wpforms-setting-row-content wpforms-clear section-heading specific-note">
	<span class="wpforms-setting-field">
		<div class="wpforms-specific-note-wrap">
			<div class="wpforms-specific-note-lightbulb">
				<svg viewBox="0 0 14 20">
					<path d="M3.75 17.97c0 .12 0 .23.08.35l.97 1.4c.12.2.32.28.51.28H8.4c.2 0 .39-.08.5-.27l.98-1.41c.04-.12.08-.23.08-.35v-1.72H3.75v1.72Zm3.13-5.47c.66 0 1.25-.55 1.25-1.25 0-.66-.6-1.25-1.26-1.25-.7 0-1.25.59-1.25 1.25 0 .7.55 1.25 1.25 1.25Zm0-12.5A6.83 6.83 0 0 0 0 6.88c0 1.75.63 3.32 1.68 4.53.66.74 1.68 2.3 2.03 3.59H5.6c0-.16 0-.35-.08-.55-.2-.7-.86-2.5-2.42-4.25a5.19 5.19 0 0 1-1.21-3.32c-.04-2.86 2.3-5 5-5 2.73 0 5 2.26 5 5 0 1.2-.47 2.38-1.26 3.32a11.72 11.72 0 0 0-2.42 4.25c-.07.2-.07.35-.07.55H10a10.56 10.56 0 0 1 2.03-3.6A6.85 6.85 0 0 0 6.88 0Zm-.4 8.75h.75c.3 0 .58-.23.62-.55l.5-3.75a.66.66 0 0 0-.62-.7H5.98a.66.66 0 0 0-.63.7l.5 3.75c.05.32.32.55.63.55Z"></path>
				</svg>
			</div>
			<div class="wpforms-specific-note-content">
				<p><strong>hCaptcha plugin is active</strong></p>
				<p>When hCaptcha plugin is active and integration is on, hCaptcha settings must be modified on the <a href="' . $general_page_url . '" target="_blank">General settings page</a>.</p>
			</div>
		</div>
	</span>
</div>
';

		$hcap_form = $this->get_hcap_form();
		$expected  = [
			'hcaptcha-heading'    =>
				'<div>Some hCaptcha heading</div>' . $notice_content,
			'hcaptcha-site-key'   => '<div><span style="opacity: 0.4;" class="wpforms-setting-field"><input disabled type="text"></span></div>',
			'hcaptcha-secret-key' => '<div><span style="opacity: 0.4;" class="wpforms-setting-field"><input disabled type="text"></span></div>',
			'hcaptcha-fail-msg'   => '<div><span style="opacity: 0.4;" class="wpforms-setting-field"><input disabled type="text"></span></div>',
			'captcha-preview'     => '<div>' . $hcap_form . '</div>',
		];

		$subject = new Form();

		self::assertSame( $fields, $subject->wpforms_settings_fields( $fields, 'some_view' ) );
		self::assertSame( $expected, $subject->wpforms_settings_fields( $fields, 'captcha' ) );
	}

	/**
	 * Test hcap_print_hcaptcha_scripts().
	 *
	 * @return void
	 */
	public function test_hcap_print_hcaptcha_scripts(): void {
		$subject = new Form();

		// Not in admin.
		self::assertFalse( $subject->hcap_print_hcaptcha_scripts( false ) );
		self::assertTrue( $subject->hcap_print_hcaptcha_scripts( true ) );

		// Some screen.
		set_current_screen( 'some_screen' );

		self::assertFalse( $subject->hcap_print_hcaptcha_scripts( false ) );
		self::assertTrue( $subject->hcap_print_hcaptcha_scripts( true ) );

		// Not hCaptcha provider.
		set_current_screen( 'wpforms_page_wpforms-settings' );

		self::assertFalse( $subject->hcap_print_hcaptcha_scripts( false ) );
		self::assertTrue( $subject->hcap_print_hcaptcha_scripts( true ) );

		// Captcha provider is hCaptcha.
		add_filter(
			'wpforms_setting',
			static function ( $value, $key ) {
				if ( 'captcha-provider' === $key ) {
					return 'hcaptcha';
				}

				return $value;
			},
			10,
			2
		);

		self::assertTrue( $subject->hcap_print_hcaptcha_scripts( false ) );
		self::assertTrue( $subject->hcap_print_hcaptcha_scripts( true ) );
	}

	/**
	 * Test wpforms_admin_settings_captcha_enqueues_disable().
	 *
	 * @return void
	 */
	public function test_wpforms_admin_settings_captcha_enqueues_disable(): void {
		$subject = new Form();

		// Not in admin.
		self::assertFalse( $subject->wpforms_admin_settings_captcha_enqueues_disable( false ) );
		self::assertTrue( $subject->wpforms_admin_settings_captcha_enqueues_disable( true ) );

		// Some screen.
		set_current_screen( 'some_screen' );

		self::assertFalse( $subject->wpforms_admin_settings_captcha_enqueues_disable( false ) );
		self::assertTrue( $subject->wpforms_admin_settings_captcha_enqueues_disable( true ) );

		// Not hCaptcha provider.
		set_current_screen( 'wpforms_page_wpforms-settings' );

		self::assertFalse( $subject->wpforms_admin_settings_captcha_enqueues_disable( false ) );
		self::assertTrue( $subject->wpforms_admin_settings_captcha_enqueues_disable( true ) );

		// Captcha provider is hCaptcha.
		add_filter(
			'wpforms_setting',
			static function ( $value, $key ) {
				if ( 'captcha-provider' === $key ) {
					return 'hcaptcha';
				}

				return $value;
			},
			10,
			2
		);

		self::assertTrue( $subject->wpforms_admin_settings_captcha_enqueues_disable( false ) );
		self::assertTrue( $subject->wpforms_admin_settings_captcha_enqueues_disable( true ) );
	}

	/**
	 * Test block_assets_recaptcha().
	 *
	 * @return void
	 */
	public function test_block_assets_recaptcha(): void {
		wpforms()->register(
			[
				'name' => 'Frontend\Captcha',
				'id'   => 'captcha',
			]
		);

		do_action( 'wpforms_loaded' );

		$captcha = wpforms()->get( 'captcha' );

		add_action( 'wpforms_wp_footer', [ $captcha, 'assets_recaptcha' ] );

		$subject = new Form();

		self::assertSame( 10, has_action( 'wpforms_wp_footer', [ $captcha, 'assets_recaptcha' ] ) );

		// Captcha provider is not set.
		$subject->block_assets_recaptcha();

		self::assertSame( 10, has_action( 'wpforms_wp_footer', [ $captcha, 'assets_recaptcha' ] ) );

		// Captcha provider is hCaptcha.
		add_filter(
			'wpforms_setting',
			static function ( $value, $key ) {
				if ( 'captcha-provider' === $key ) {
					return 'hcaptcha';
				}

				return $value;
			},
			10,
			2
		);

		$subject->block_assets_recaptcha();

		self::assertFalse( has_action( 'wpforms_wp_footer', [ $captcha, 'assets_recaptcha' ] ) );
	}

	/**
	 * Test wpforms_frontend_output() when not processing hCaptcha.
	 *
	 * @return void
	 */
	public function test_wpforms_frontend_output_when_not_processing_hcaptcha(): void {
		$form_data   = [ 'id' => 5 ];
		$deprecated  = null;
		$title       = 'some title';
		$description = 'some description';
		$errors      = [ 'some errors' ];

		$subject = new Form();

		// The process_hcaptcha() is false.
		ob_start();
		$subject->wpforms_frontend_output( $form_data, $deprecated, $title, $description, $errors );
		self::assertSame( '', ob_get_clean() );
	}

	/**
	 * Test wpforms_frontend_output() when mode is embed.
	 *
	 * @return void
	 */
	public function test_wpforms_frontend_output_when_mode_embed(): void {
		$form_id     = 5;
		$form_data   = [
			'id'       => $form_id,
			'settings' => [
				'recaptcha' => '1',
			],
		];
		$deprecated  = null;
		$title       = 'some title';
		$description = 'some description';
		$errors      = [ 'some errors' ];
		$args        = [
			'action' => Form::ACTION,
			'name'   => Form::NAME,
			'id'     => [
				'source'  => [ 'wpforms/wpforms.php', 'wpforms-lite/wpforms.php' ],
				'form_id' => $form_id,
			],
		];
		$hcap_form   = $this->get_hcap_form( $args );
		$expected    = '<div class="wpforms-recaptcha-container wpforms-is-hcaptcha" >' . $hcap_form . '</div>';

		$classes   = [];
		$classes[] = [
			'name' => 'Frontend\Classic',
			'id'   => 'frontend_classic',
		];
		$classes[] = [
			'name' => 'Frontend\Frontend',
			'id'   => 'frontend',
		];

		wpforms()->register_bulk( $classes );

		$captcha = wpforms()->get( 'captcha' );

		do_action( 'wpforms_loaded' );
		add_action( 'wpforms_frontend_output', [ $captcha, 'recaptcha' ], 20 );

		add_filter(
			'wpforms_setting',
			static function ( $value, $key ) {
				if ( 'captcha-provider' === $key ) {
					return 'hcaptcha';
				}

				return $value;
			},
			10,
			2
		);

		// Embed mode.
		update_option( 'hcaptcha_settings', [ 'wpforms_status' => [ 'embed' ] ] );
		hcaptcha()->init_hooks();

		$subject = new Form();

		// The process_hcaptcha() is true.
		ob_start();
		$subject->wpforms_frontend_output( $form_data, $deprecated, $title, $description, $errors );
		self::assertSame( $expected, ob_get_clean() );
		self::assertFalse( has_action( 'wpforms_frontend_output', [ $captcha, 'recaptcha' ] ) );
	}

	/**
	 * Test wpforms_frontend_output() when mode is auto.
	 *
	 * @return void
	 */
	public function test_wpforms_frontend_output_when_mode_auto(): void {
		$form_id     = 5;
		$form_data   = [ 'id' => $form_id ];
		$deprecated  = null;
		$title       = 'some title';
		$description = 'some description';
		$errors      = [ 'some errors' ];
		$args        = [
			'action' => Form::ACTION,
			'name'   => Form::NAME,
			'id'     => [
				'source'  => [ 'wpforms/wpforms.php', 'wpforms-lite/wpforms.php' ],
				'form_id' => $form_id,
			],
		];
		$hcap_form   = $this->get_hcap_form( $args );
		$expected    = '<div class="wpforms-recaptcha-container wpforms-is-hcaptcha" >' . $hcap_form . '</div>';

		$classes   = [];
		$classes[] = [
			'name' => 'Frontend\Classic',
			'id'   => 'frontend_classic',
		];
		$classes[] = [
			'name' => 'Frontend\Frontend',
			'id'   => 'frontend',
		];

		wpforms()->register_bulk( $classes );

		do_action( 'wpforms_loaded' );

		// Embed mode.
		update_option( 'hcaptcha_settings', [ 'wpforms_status' => [ 'form' ] ] );

		hcaptcha()->init_hooks();

		$subject = new Form();

		// The process_hcaptcha() is true.
		ob_start();
		$subject->wpforms_frontend_output( $form_data, $deprecated, $title, $description, $errors );
		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test wpforms_frontend_output() when mode is auto and form has hCaptcha.
	 *
	 * @return void
	 */
	public function test_wpforms_frontend_output_when_mode_auto_and_form_has_hcaptcha(): void {
		$form_id     = 5;
		$form_data   = [
			'id'       => $form_id,
			'settings' => [
				'recaptcha' => '1',
			],
		];
		$deprecated  = null;
		$title       = 'some title';
		$description = 'some description';
		$errors      = [ 'some errors' ];
		$args        = [
			'action' => Form::ACTION,
			'name'   => Form::NAME,
			'id'     => [
				'source'  => [ 'wpforms/wpforms.php', 'wpforms-lite/wpforms.php' ],
				'form_id' => $form_id,
			],
			'theme'  => 'light',
		];
		$hcap_form   = $this->get_hcap_form( $args );
		$expected    = '<div class="wpforms-recaptcha-container wpforms-is-hcaptcha" >' . $hcap_form . '</div>';

		$classes   = [];
		$classes[] = [
			'name' => 'Frontend\Classic',
			'id'   => 'frontend_classic',
		];
		$classes[] = [
			'name' => 'Frontend\Frontend',
			'id'   => 'frontend',
		];

		wpforms()->register_bulk( $classes );

		do_action( 'wpforms_loaded' );

		add_filter(
			'wpforms_setting',
			static function ( $value, $key ) {
				if ( 'captcha-provider' === $key ) {
					return 'hcaptcha';
				}

				return $value;
			},
			10,
			2
		);

		// Embed mode.
		update_option( 'hcaptcha_settings', [ 'wpforms_status' => [ 'form' ] ] );

		hcaptcha()->init_hooks();

		$subject = new Form();

		// The process_hcaptcha() is true.
		ob_start();
		$subject->wpforms_frontend_output( $form_data, $deprecated, $title, $description, $errors );
		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test enqueue_scripts().
	 *
	 * @return void
	 */
	public function test_enqueue_scripts(): void {
		$subject = new Form();

		self::assertFalse( wp_script_is( 'hcaptcha-wpforms' ) );

		// Test when hCaptcha was not shown.
		$subject->enqueue_scripts();

		self::assertFalse( wp_script_is( 'hcaptcha-wpforms' ) );

		// Test when hCaptcha was shown.
		hcaptcha()->form_shown = true;

		$subject->enqueue_scripts();

		self::assertTrue( wp_script_is( 'hcaptcha-wpforms' ) );
	}

	/**
	 * Test process_hcaptcha().
	 *
	 * @param bool $mode_auto    Mode auto.
	 * @param bool $mode_embed   Mode embed.
	 * @param bool $has_hcaptcha Form has hCaptcha.
	 * @param bool $expected     Expected result.
	 *
	 * @return void
	 * @dataProvider dp_test_process_hcaptcha
	 * @throws ReflectionException ReflectionException.
	 * @noinspection UnusedFunctionResultInspection
	 */
	public function test_process_hcaptcha( bool $mode_auto, bool $mode_embed, bool $has_hcaptcha, bool $expected ): void {
		$form_data        = [ 'id' => 5 ];
		$status           = [];
		$init_hooks       = 'init_hooks';
		$process_hcaptcha = 'process_hcaptcha';

		if ( $mode_auto ) {
			$status[] = 'form';
		}

		if ( $mode_embed ) {
			$status[] = 'embed';
		}

		if ( $has_hcaptcha ) {
			add_filter(
				'wpforms_setting',
				static function ( $value, $key ) {
					if ( 'captcha-provider' === $key ) {
						return 'hcaptcha';
					}

					return $value;
				},
				10,
				2
			);

			$form_data['settings']['recaptcha'] = '1';
		}

		update_option( 'hcaptcha_settings', [ 'wpforms_status' => $status ] );

		hcaptcha()->init_hooks();

		$subject = Mockery::mock( Form::class )->makePartial();

		$this->set_method_accessibility( $subject, $init_hooks );
		$this->set_method_accessibility( $subject, $process_hcaptcha );
		$subject->$init_hooks();

		self::assertSame( $expected, $subject->$process_hcaptcha( $form_data ) );
	}

	/**
	 * Data provider for dp_test_process_hcaptcha().
	 *
	 * @return array
	 */
	public function dp_test_process_hcaptcha(): array {
		return [
			[ false, false, false, false ],
			[ false, false, true, false ],
			[ false, true, false, false ],
			[ false, true, true, true ],
			[ true, false, false, true ],
			[ true, false, true, true ],
			[ true, true, false, true ],
			[ true, true, true, true ],
		];
	}
}
