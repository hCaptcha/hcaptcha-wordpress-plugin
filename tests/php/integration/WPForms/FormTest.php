<?php
/**
 * FormTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */
/** @noinspection PhpUndefinedFunctionInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Integration\WPForms;

use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Settings\General;
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use HCaptcha\WPForms\Form;
use Mockery;
use ReflectionException;
use tad\FunctionMocker\FunctionMocker;
use WPForms_Field_Email;

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
	 * Load the native field formatter omitted by the late plugin lifecycle fixture.
	 *
	 * @return void
	 */
	private function load_native_email_field(): void {
		$path = WP_PLUGIN_DIR . '/' . dirname( static::$plugin ) . '/includes/fields/';
		if ( ! class_exists( 'WPForms_Field' ) ) {
			require_once $path . 'class-base.php';
		}
		if ( ! class_exists( 'WPForms_Field_Email' ) ) {
			require_once $path . 'class-email.php';
		}
	}

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
			self::assertFalse(
				has_filter(
					'wpforms_admin_settings_captcha_enqueues_disable',
					[ $subject, 'wpforms_admin_settings_captcha_enqueues_disable' ]
				)
			);
			self::assertFalse(
				has_filter(
					'hcap_print_hcaptcha_scripts',
					[ $subject, 'hcap_print_hcaptcha_scripts' ]
				)
			);
			self::assertFalse( has_filter( 'wpforms_settings_fields', [ $subject, 'wpforms_settings_fields' ] ) );

			self::assertFalse( has_action( 'wp_head', [ $subject, 'print_inline_styles' ] ) );
			self::assertFalse( has_action( 'wpforms_wp_footer', [ $subject, 'block_assets_recaptcha' ] ) );

			self::assertFalse( has_action( 'wpforms_frontend_output', [ $subject, 'wpforms_frontend_output' ] ) );
			self::assertFalse( has_filter( 'wpforms_process_captcha_providers', [ $subject, 'wpforms_process_captcha_providers' ] ) );
			self::assertFalse( has_action( 'wpforms_process', [ $subject, 'verify' ] ) );

			return;
		}

		if ( $mode_embed ) {
			self::assertSame(
				10,
				has_filter(
					'wpforms_admin_settings_captcha_enqueues_disable',
					[ $subject, 'wpforms_admin_settings_captcha_enqueues_disable' ]
				)
			);
			self::assertSame(
				0,
				has_filter(
					'hcap_print_hcaptcha_scripts',
					[ $subject, 'hcap_print_hcaptcha_scripts' ]
				)
			);
			self::assertSame( 10, has_filter( 'wpforms_settings_fields', [ $subject, 'wpforms_settings_fields' ] ) );
		}

		self::assertSame( 20, has_action( 'wp_head', [ $subject, 'print_inline_styles' ] ) );
		self::assertSame( 0, has_action( 'wpforms_wp_footer', [ $subject, 'block_assets_recaptcha' ] ) );

		self::assertSame( 19, has_action( 'wpforms_frontend_output', [ $subject, 'wpforms_frontend_output' ] ) );
		self::assertSame( 10, has_filter( 'wpforms_process_captcha_providers', [ $subject, 'wpforms_process_captcha_providers' ] ) );
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
	 * Keep native spam checks while replacing CAPTCHA only on protected forms.
	 *
	 * @return void
	 */
	public function test_sec058_native_spam_checks_remain_active(): void {
		add_filter(
			'wpforms_setting',
			static function ( $value, $key ) {
				return 'captcha-provider' === $key ? 'hcaptcha' : $value;
			},
			10,
			2
		);

		hcaptcha()->settings()->set( 'wpforms_status', [ 'embed' ] );
		wpforms()->objects();
		$subject   = new Form();
		$process   = wpforms()->obj( 'process' );
		$providers = [
			'hcaptcha'  => [ 'provider' => 'hCaptcha' ],
			'recaptcha' => [ 'provider' => 'reCAPTCHA' ],
		];

		$process->form_data = [ 'settings' => [ 'recaptcha' => '0' ] ];
		self::assertSame( $providers, $subject->wpforms_process_captcha_providers( $providers ) );

		$process->form_data = [ 'settings' => [ 'recaptcha' => '1' ] ];
		self::assertSame(
			[ 'recaptcha' => [ 'provider' => 'reCAPTCHA' ] ],
			$subject->wpforms_process_captcha_providers( $providers )
		);

		$is_bypass_spam_check = $this->set_method_accessibility( $process, 'is_bypass_spam_check' );
		self::assertFalse( $is_bypass_spam_check->invoke( $process, [] ) );
	}

	/**
	 * Test verify().
	 *
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function test_verify(): void {
		$id        = 5;
		$fields    = [ 'some field' ];
		$entry     = [
			'fields' =>
				[
					[
						'first' => 'John',
						'last'  => 'Doe',
					],
					'foo@bar.com',
					'Some message',
				],
			'id'     => (string) $id,
			'nonce'  => 'some nonce',
		];
		$form_data = [
			'id'     => $id,
			'fields' => [
				[
					'id'     => '0',
					'type'   => 'name',
					'label'  => 'Name',
					'format' => 'first-last',
				],
				[
					'id'    => '1',
					'type'  => 'email',
					'label' => 'Email',
				],
				[
					'id'    => '2',
					'type'  => 'textarea',
					'label' => 'Comment or Message',
				],
			],
		];

		hcaptcha()->settings()->set( 'wpforms_status', [ 'form' ] );

		$subject = new Form();

		$this->prepare_verify_post( 'hcaptcha_wpforms_nonce', 'hcaptcha_wpforms' );
		$this->prepare_widget_id( $id );

		wpforms()->objects();
		wpforms()->obj( 'process' )->errors = [];

		$subject->verify( $fields, $entry, $form_data );

		self::assertSame( [], wpforms()->obj( 'process' )->errors );
	}

	/**
	 * Test that custom fields with the same type and label stay in the entry.
	 *
	 * @return void
	 */
	public function test_get_entry_preserves_custom_fields(): void {
		$subject = new Form();
		$method  = $this->set_method_accessibility( $subject, 'get_entry' );
		$entry   = $method->invoke(
			$subject,
			[
				'id'     => '5',
				'fields' => [
					1 => 'First opinion',
					2 => 'Second opinion',
					3 => 'private-password',
					4 => [ 'Red', 'Blue' ],
				],
			],
			[
				'id'     => 5,
				'fields' => [
					1 => [
						'type'  => 'textarea',
						'label' => 'Ваше мнение',
					],
					2 => [
						'type'  => 'textarea',
						'label' => 'Ваше мнение',
					],
					3 => [
						'type'  => 'password',
						'label' => 'Other',
					],
					4 => [
						'type'  => 'checkbox',
						'label' => 'Preferences',
					],
					5 => [
						'type'  => 'text',
						'label' => 'Absent field',
					],
				],
			]
		);

		self::assertSame(
			[
				'Ваше мнение'     => 'First opinion',
				'Ваше мнение [2]' => 'Second opinion',
				'Preferences'     => [ 'Red', 'Blue' ],
			],
			$entry['data']
		);
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

		self::assertSame( [], wpforms()->obj( 'process' )->errors );
	}

	/**
	 * Test verify() not verified.
	 *
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function test_verify_not_verified(): void {
		$id        = 5;
		$fields    = [ 'some field' ];
		$entry     = [
			'fields' =>
				[
					[
						'first' => 'John',
						'last'  => 'Doe',
					],
					'foo@bar.com',
					'Some message',
				],
			'id'     => (string) $id,
			'nonce'  => 'some nonce',
		];
		$form_data = [
			'id'     => $id,
			'fields' => [
				[
					'id'     => '0',
					'type'   => 'name',
					'label'  => 'Name',
					'format' => 'first-last',
				],
				[
					'id'    => '1',
					'type'  => 'email',
					'label' => 'Email',
				],
				[
					'id'    => '2',
					'type'  => 'textarea',
					'label' => 'Comment or Message',
				],
			],
		];
		$expected  = 'The hCaptcha is invalid.';

		hcaptcha()->settings()->set( 'wpforms_status', [ 'form' ] );

		$subject = new Form();

		$this->prepare_verify_post( 'hcaptcha_wpforms_nonce', 'hcaptcha_wpforms', false );
		$this->prepare_widget_id( $id );

		wpforms()->objects();
		wpforms()->obj( 'process' )->errors = [];

		$subject->verify( $fields, $entry, $form_data );

		self::assertSame( $expected, wpforms()->obj( 'process' )->errors[ $form_data['id'] ]['footer'] );
	}

	/**
	 * Test verify() not verified with wpforms_settings.
	 *
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function test_verify_not_verified_with_wpforms_settings(): void {
		$id                    = 5;
		$fields                = [ 'some field' ];
		$entry                 = [
			'fields' =>
				[
					[
						'first' => 'John',
						'last'  => 'Doe',
					],
					'foo@bar.com',
					'Some message',
				],
			'id'     => (string) $id,
			'nonce'  => 'some nonce',
		];
		$form_data             = [
			'id'       => $id,
			'settings' => [ 'recaptcha' => '1' ],
			'fields'   => [
				[
					'id'     => '0',
					'type'   => 'name',
					'label'  => 'Name',
					'format' => 'first-last',
				],
				[
					'id'    => '1',
					'type'  => 'email',
					'label' => 'Email',
				],
				[
					'id'    => '2',
					'type'  => 'textarea',
					'label' => 'Comment or Message',
				],
			],
		];
		$wpforms_error_message = 'The hCaptcha is invalid.';

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
		$this->prepare_widget_id( $id );

		wpforms()->objects();
		wpforms()->obj( 'process' )->errors = [];

		$subject->verify( $fields, $entry, $form_data );

		self::assertSame( $wpforms_error_message, wpforms()->obj( 'process' )->errors[ $form_data['id'] ]['footer'] );
	}

	/**
	 * Test verify() when widget id is bad.
	 *
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function test_verify_bad_widget_id(): void {
		$id        = 5;
		$fields    = [ 'some field' ];
		$entry     = [
			'fields' =>
				[
					[
						'first' => 'John',
						'last'  => 'Doe',
					],
					'foo@bar.com',
					'Some message',
				],
			'id'     => (string) $id,
			'nonce'  => 'some nonce',
		];
		$form_data = [
			'id'     => $id,
			'fields' => [
				[
					'id'     => '0',
					'type'   => 'name',
					'label'  => 'Name',
					'format' => 'first-last',
				],
				[
					'id'    => '1',
					'type'  => 'email',
					'label' => 'Email',
				],
				[
					'id'    => '2',
					'type'  => 'textarea',
					'label' => 'Comment or Message',
				],
			],
		];
		$expected  = 'Bad hCaptcha signature!';

		hcaptcha()->settings()->set( 'wpforms_status', [ 'form' ] );

		$subject = new Form();

		$this->prepare_verify_post( 'hcaptcha_wpforms_nonce', 'hcaptcha_wpforms' );
		$this->prepare_widget_id(
			$id,
			[
				'source'  => [ 'WordPress' ],
				'form_id' => $id,
			]
		);

		wpforms()->objects();
		wpforms()->obj( 'process' )->errors = [];

		$subject->verify( $fields, $entry, $form_data );

		self::assertSame( $expected, wpforms()->obj( 'process' )->errors[ $form_data['id'] ]['footer'] );
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
		width: 302px;
		height: 76px;
	}
	
	div.wpforms-container-full .wpforms-form .h-captcha[data-size="compact"] {
		width: 158px;
		height: 138px;
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

		if ( version_compare( $GLOBALS['wp_version'], '7.0-RC1', '>=' ) ) {
			$expected = str_replace( '&#038;', '&amp;', $expected );
		}

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

		$captcha = wpforms()->obj( 'captcha' );

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
	 * Test wpforms_frontend_output() when the mode is embed.
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

		$captcha = wpforms()->obj( 'captcha' );

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
	 * Test wpforms_frontend_output() in auto mode with built-in form interaction.
	 *
	 * @return void
	 */
	public function test_wpforms_frontend_output_when_mode_auto_with_form_interaction(): void {
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

		add_filter( 'hcap_delay_api_event', '__return_true' );

		$hcap_form = str_replace(
			'class="h-captcha"',
			'class="h-captcha hcaptcha-api-delayed"',
			$this->get_hcap_form( $args )
		);
		$expected  = '<div class="wpforms-recaptcha-container wpforms-is-hcaptcha" >' . $hcap_form . '</div>';

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
		$actual = ob_get_clean();

		remove_filter( 'hcap_delay_api_event', '__return_true' );

		self::assertSame( $expected, $actual );
		self::assertStringContainsString( 'hcaptcha-api-delayed', $actual );
	}

	/**
	 * Test wpforms_frontend_output() when the mode is auto and form has hCaptcha.
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
		$site_key    = General::MODE_TEST_PUBLISHER_SITE_KEY;
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

		add_filter(
			'hcap_site_key',
			static function () use ( $site_key ) {
				return $site_key;
			},
			20
		);

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

	/**
	 * Prepare hCaptcha widget id.
	 *
	 * @param int   $form_id Form id.
	 * @param array $id      Widget id.
	 *
	 * @return void
	 */
	private function prepare_widget_id( int $form_id, array $id = [] ): void {
		$id = $id ?: [
			'source'  => [ 'wpforms/wpforms.php', 'wpforms-lite/wpforms.php' ],
			'form_id' => $form_id,
		];

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value( $id );
	}
	/**
	 * Reject a disposable address in a custom email field before native processing.
	 *
	 * @param bool $blocked Whether the trusted email is disposable.
	 * @dataProvider dp_sec063_email_policy
	 */
	public function test_sec063_custom_email_policy( bool $blocked ): void {
		$this->prepare_verify_post( 'hcaptcha_wpforms_nonce', 'hcaptcha_wpforms' );
		$this->prepare_widget_id( 5 );
		hcaptcha()->settings()->set( 'disposable_email', [ 'on' ] );
		set_transient( 'hcaptcha_disposable_email_blocklist', [ 'sec063-disposable.test' => true ] );
		hcaptcha()->settings()->set( 'wpforms_status', [ 'form' ] );
		wpforms()->objects();
		wpforms()->obj( 'process' )->errors = [];
		$form_data                          = [
			'id'     => 5,
			'fields' => [
				7 => [
					'type'         => 'email',
					'label'        => 'Payment contact',
					'confirmation' => '1',
				],
				8 => [
					'type'  => 'email',
					'label' => 'Другой адрес',
				],
				9 => [
					'type'  => 'text',
					'label' => 'email',
				],
			],
		];
		$entry                              = [
			'id'     => 5,
			'fields' => [
				7 => [
					'primary'   => $blocked ? 'person@sec063-disposable.test' : 'person@example.com',
					'secondary' => 'confirmation@sec063-disposable.test',
				],
				8 => 'reader@example.com',
				9 => 'spoof@sec063-disposable.test',
			],
		];

		$this->load_native_email_field();
		$field = new WPForms_Field_Email();
		$field->format( 7, $entry['fields'][7], $form_data );
		$field->format( 8, $entry['fields'][8], $form_data );
		( new Form() )->verify( wpforms()->obj( 'process' )->fields, $entry, $form_data );

		self::assertSame(
			$blocked ? 'Please use a permanent email address.' : null,
			wpforms()->obj( 'process' )->errors[5]['footer'] ?? null
		);
	}

	/**
	 * Check an accepted native international domain against its ASCII blocklist form.
	 *
	 * @param bool $blocked Whether the native domain is listed.
	 * @dataProvider dp_sec063_email_policy
	 */
	public function test_sec063_international_email_policy( bool $blocked ): void {
		$this->prepare_verify_post( 'hcaptcha_wpforms_nonce', 'hcaptcha_wpforms' );
		$this->prepare_widget_id( 5 );
		hcaptcha()->settings()->set( 'disposable_email', [ 'on' ] );
		hcaptcha()->settings()->set( 'wpforms_status', [ 'form' ] );
		set_transient( 'hcaptcha_disposable_email_blocklist', $blocked ? [ 'xn--bcher-kva.test' => true ] : [] );
		wpforms()->objects();
		wpforms()->obj( 'process' )->errors = [];
		$form_data                          = [
			'id'     => 5,
			'fields' => [
				7 => [
					'type'  => 'email',
					'label' => 'Адрес',
				],
			],
		];
		$entry                              = [
			'id'     => 5,
			'fields' => [ 7 => 'person@xn--bcher-kva.test' ],
		];
		$this->load_native_email_field();
		$field = new WPForms_Field_Email();
		$field->format( 7, $entry['fields'][7], $form_data );
		self::assertSame( 'person@bücher.test', wpforms()->obj( 'process' )->fields[7]['value'] );
		( new Form() )->verify( wpforms()->obj( 'process' )->fields, $entry, $form_data );
		self::assertSame(
			$blocked ? 'Please use a permanent email address.' : null,
			wpforms()->obj( 'process' )->errors[5]['footer'] ?? null
		);
	}

	/**
	 * Missing and malformed domains are handled before email policy checks.
	 */
	public function test_normalize_email_handles_missing_and_invalid_domains(): void {
		$subject = new Form();
		$method  = $this->set_method_accessibility( $subject, 'normalize_email' );

		self::assertSame( 'missing-at', $method->invoke( $subject, 'missing-at' ) );
		self::assertSame( '', $method->invoke( $subject, 'person@' ) );
	}
	/**
	 * Exercise both policy rejection and permanent address acceptance.
	 *
	 * @return array
	 */
	public function dp_sec063_email_policy(): array {
		return [
			'disposable' => [ true ],
			'permanent'  => [ false ],
		];
	}
}
