<?php
/**
 * LoginTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\UM;

use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use HCaptcha\UM\Login;
use Mockery;
use ReflectionClass;

/**
 * Class LoginTest.
 *
 * @group um-login
 * @group um
 */
class LoginTest extends HCaptchaPluginWPTestCase {

	/**
	 * Plugin relative path.
	 *
	 * @var string
	 */
	protected static $plugin = 'ultimate-member/ultimate-member.php';

	/**
	 * Hooks to replay after loading Ultimate Member.
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
	 * Test a live Ultimate Member login form.
	 */
	public function test_live_login_form(): void {
		wp_set_current_user( 0 );

		$form_id = wp_insert_post(
			[
				'post_title'  => 'hCaptcha integration login',
				'post_status' => 'publish',
				'post_type'   => 'um_form',
			]
		);

		self::assertIsInt( $form_id );

		foreach ( UM()->config()->core_form_meta['login'] as $meta_key => $meta_value ) {
			update_post_meta( $form_id, $meta_key, $meta_value );
		}

		update_post_meta( $form_id, '_um_template', 'login' );

		$integration = new Login();
		$class_file  = wp_normalize_path( ( new ReflectionClass( \um\core\Shortcodes::class ) )->getFileName() );
		$form_data   = UM()->query()->post_data( $form_id );
		$form_args   = array_merge( $form_data, UM()->shortcodes()->get_css_args( $form_data ) );

		ob_start();
		UM()->shortcodes()->template_load( $form_data['template'], $form_args );
		$html = ob_get_clean();

		self::assertTrue( is_plugin_active( static::$plugin ) );
		self::assertStringStartsWith( wp_normalize_path( WP_PLUGIN_DIR . '/ultimate-member/' ), $class_file );
		self::assertTrue( shortcode_exists( 'ultimatemember' ) );
		self::assertSame( \um\core\Shortcodes::class, get_class( $GLOBALS['shortcode_tags']['ultimatemember'][0] ) );
		self::assertSame( 'ultimatemember', $GLOBALS['shortcode_tags']['ultimatemember'][1] );
		self::assertSame( 'um_form', get_post_type( $form_id ) );
		self::assertSame( 'publish', get_post_status( $form_id ) );
		self::assertSame( 'login', $form_data['mode'] );
		self::assertSame( 'login', $form_data['template'] );
		self::assertTrue( UM()->shortcodes()->template_exists( $form_data['mode'] ) );
		self::assertSame( 100, has_filter( 'um_get_form_fields', [ $integration, 'add_um_captcha' ] ) );
		self::assertStringContainsString( 'class="um um-login ', $html );
		self::assertStringContainsString( 'name="username-' . $form_id . '"', $html );
		self::assertStringContainsString( 'name="user_password-' . $form_id . '"', $html );
		self::assertStringContainsString( 'class="h-captcha"', $html );
		self::assertStringContainsString( 'hcaptcha_um_login_nonce', $html );
	}

	/**
	 * Tear down the test.
	 *
	 * @return void
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function tearDown(): void {
		UM()->form()->errors = null;

		parent::tearDown();
	}

	/**
	 * Test constructor and init_hooks().
	 */
	public function test_constructor_and_init_hooks(): void {
		$subject = $this->get_subject();

		self::assertSame(
			100,
			has_action( 'um_get_form_fields', [ $subject, 'add_um_captcha' ] )
		);
		self::assertSame(
			10,
			has_action( 'um_hcaptcha_form_edit_field', [ $subject, 'display_captcha' ] )
		);
		self::assertSame(
			10,
			has_action( 'um_submit_form_errors_hook_login', [ $subject, 'verify' ] )
		);
		self::assertSame(
			10,
			has_action( 'login_errors', [ $subject, 'mute_login_hcaptcha_notice' ] )
		);
	}

	/**
	 * Test add_um_captcha().
	 *
	 * @param array $fields   Fields.
	 * @param array $expected Expected.
	 *
	 * @dataProvider dp_test_add_um_captcha
	 * @return void
	 */
	public function test_add_um_captcha( array $fields, array $expected ): void {
		$subject = $this->get_subject();

		self::assertSame( $expected, $subject->add_um_captcha( $fields ) );
	}

	/**
	 * Data provider for test_add_um_captcha().
	 *
	 * @return array
	 */
	public function dp_test_add_um_captcha(): array {
		return [
			'empty fields'                                 => [
				[],
				[
					'hcaptcha' => [
						'title'        => 'hCaptcha',
						'metakey'      => 'hcaptcha',
						'type'         => 'hcaptcha',
						'label'        => 'hCaptcha',
						'required'     => 0,
						'public'       => 0,
						'editable'     => 0,
						'account_only' => true,
						'position'     => '1',
						'in_row'       => '_um_row_1',
						'in_sub_row'   => '0',
						'in_column'    => '1',
						'in_group'     => '',
					],
				],
			],
			'login fields'                                 => [
				[
					'username'      =>
						[
							'title'      => 'Username or E-mail',
							'metakey'    => 'username',
							'type'       => 'text',
							'label'      => 'Username or E-mail',
							'required'   => 1,
							'public'     => 1,
							'editable'   => 0,
							'validate'   => 'unique_username_or_email',
							'position'   => '1',
							'in_row'     => '_um_row_1',
							'in_sub_row' => '0',
							'in_column'  => '1',
							'in_group'   => '',
						],
					'user_password' =>
						[
							'title'              => 'Password',
							'metakey'            => 'user_password',
							'type'               => 'password',
							'label'              => 'Password',
							'required'           => 1,
							'public'             => 1,
							'editable'           => 1,
							'min_chars'          => 8,
							'max_chars'          => 30,
							'force_good_pass'    => 1,
							'force_confirm_pass' => 1,
							'position'           => '2',
							'in_row'             => '_um_row_1',
							'in_sub_row'         => '0',
							'in_column'          => '1',
							'in_group'           => '',
						],
					'_um_row_1'     =>
						[
							'type'     => 'row',
							'id'       => '_um_row_1',
							'sub_rows' => '1',
							'cols'     => '1',
						],
				],
				[
					'username'      =>
						[
							'title'      => 'Username or E-mail',
							'metakey'    => 'username',
							'type'       => 'text',
							'label'      => 'Username or E-mail',
							'required'   => 1,
							'public'     => 1,
							'editable'   => 0,
							'validate'   => 'unique_username_or_email',
							'position'   => '1',
							'in_row'     => '_um_row_1',
							'in_sub_row' => '0',
							'in_column'  => '1',
							'in_group'   => '',
						],
					'user_password' =>
						[
							'title'              => 'Password',
							'metakey'            => 'user_password',
							'type'               => 'password',
							'label'              => 'Password',
							'required'           => 1,
							'public'             => 1,
							'editable'           => 1,
							'min_chars'          => 8,
							'max_chars'          => 30,
							'force_good_pass'    => 1,
							'force_confirm_pass' => 1,
							'position'           => '2',
							'in_row'             => '_um_row_1',
							'in_sub_row'         => '0',
							'in_column'          => '1',
							'in_group'           => '',
						],
					'_um_row_1'     =>
						[
							'type'     => 'row',
							'id'       => '_um_row_1',
							'sub_rows' => '1',
							'cols'     => '1',
						],
					'hcaptcha'      =>
						[
							'title'        => 'hCaptcha',
							'metakey'      => 'hcaptcha',
							'type'         => 'hcaptcha',
							'label'        => 'hCaptcha',
							'required'     => 0,
							'public'       => 0,
							'editable'     => 0,
							'account_only' => true,
							'position'     => '3',
							'in_row'       => '_um_row_1',
							'in_sub_row'   => '0',
							'in_column'    => '1',
							'in_group'     => '',
						],
				],
			],
			'login fields with wrong field position order' => [
				[
					'username'      =>
						[
							'title'      => 'Username or E-mail',
							'metakey'    => 'username',
							'type'       => 'text',
							'label'      => 'Username or E-mail',
							'required'   => 1,
							'public'     => 1,
							'editable'   => 0,
							'validate'   => 'unique_username_or_email',
							'position'   => '2',
							'in_row'     => '_um_row_1',
							'in_sub_row' => '0',
							'in_column'  => '1',
							'in_group'   => '',
						],
					'user_password' =>
						[
							'title'              => 'Password',
							'metakey'            => 'user_password',
							'type'               => 'password',
							'label'              => 'Password',
							'required'           => 1,
							'public'             => 1,
							'editable'           => 1,
							'min_chars'          => 8,
							'max_chars'          => 30,
							'force_good_pass'    => 1,
							'force_confirm_pass' => 1,
							'position'           => '1',
							'in_row'             => '_um_row_1',
							'in_sub_row'         => '0',
							'in_column'          => '1',
							'in_group'           => '',
						],
					'_um_row_1'     =>
						[
							'type'     => 'row',
							'id'       => '_um_row_1',
							'sub_rows' => '1',
							'cols'     => '1',
						],
				],
				[
					'username'      =>
						[
							'title'      => 'Username or E-mail',
							'metakey'    => 'username',
							'type'       => 'text',
							'label'      => 'Username or E-mail',
							'required'   => 1,
							'public'     => 1,
							'editable'   => 0,
							'validate'   => 'unique_username_or_email',
							'position'   => '2',
							'in_row'     => '_um_row_1',
							'in_sub_row' => '0',
							'in_column'  => '1',
							'in_group'   => '',
						],
					'user_password' =>
						[
							'title'              => 'Password',
							'metakey'            => 'user_password',
							'type'               => 'password',
							'label'              => 'Password',
							'required'           => 1,
							'public'             => 1,
							'editable'           => 1,
							'min_chars'          => 8,
							'max_chars'          => 30,
							'force_good_pass'    => 1,
							'force_confirm_pass' => 1,
							'position'           => '1',
							'in_row'             => '_um_row_1',
							'in_sub_row'         => '0',
							'in_column'          => '1',
							'in_group'           => '',
						],
					'_um_row_1'     =>
						[
							'type'     => 'row',
							'id'       => '_um_row_1',
							'sub_rows' => '1',
							'cols'     => '1',
						],
					'hcaptcha'      =>
						[
							'title'        => 'hCaptcha',
							'metakey'      => 'hcaptcha',
							'type'         => 'hcaptcha',
							'label'        => 'hCaptcha',
							'required'     => 0,
							'public'       => 0,
							'editable'     => 0,
							'account_only' => true,
							'position'     => '3',
							'in_row'       => '_um_row_1',
							'in_sub_row'   => '0',
							'in_column'    => '1',
							'in_group'     => '',
						],
				],
			],
		];
	}

	/**
	 * Test add_um_captcha() when the login limit is not exceeded.
	 *
	 * @return void
	 */
	public function test_add_um_captcha_when_login_limit_is_not_exceeded(): void {
		$fields  = [ 'some fields' ];
		$subject = Mockery::mock( Login::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'is_login_limit_exceeded' )
			->once()
			->andReturnFalse();

		self::assertSame( $fields, $subject->add_um_captcha( $fields ) );
	}

	/**
	 * Test add_um_captcha() with the wrong mode.
	 *
	 * @return void
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function test_add_um_captcha_with_wrong_mode(): void {
		$fields  = [ 'some fields' ];
		$subject = Mockery::mock( Login::class )->makePartial();

		UM()->fields()->set_mode = 'wrong mode';

		self::assertSame( $fields, $subject->add_um_captcha( $fields ) );
	}

	/**
	 * Test display_captcha().
	 *
	 * @return void
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function test_display_captcha(): void {
		$subject = $this->get_subject();

		$mode   = 'wrong mode';
		$output = '';

		self::assertSame( $output, $subject->display_captcha( $output, $mode ) );

		$mode   = $subject::UM_MODE;
		$output = 'some output';

		self::assertSame( $output, $subject->display_captcha( $output, $mode ) );

		$mode     = $subject::UM_MODE;
		$output   = '';
		$args     = [
			'action' => "hcaptcha_um_$mode",
			'name'   => "hcaptcha_um_{$mode}_nonce",
			'id'     => [
				'source'  => [ 'ultimate-member/ultimate-member.php' ],
				'form_id' => 'login',
			],
		];
		$expected =
			'<div class="um-field um-field-hcaptcha">' .
			$this->get_hcap_form( $args ) .
			'</div>';

		self::assertSame( $expected, $subject->display_captcha( $output, $mode ) );

		$error_message = 'message';

		UM()->form()->errors = [ 'hcaptcha' => $error_message ];

		$expected .= "<div class=\"um-field-error\" id=\"um-error-for-hcaptcha\"><span class=\"um-field-arrow\"><i class=\"um-faicon-caret-up\"></i></span>$error_message</div>";

		self::assertSame( $expected, $subject->display_captcha( $output, $mode ) );
	}

	/**
	 * Test verify().
	 *
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function test_verify(): void {
		$submitted_data = [];

		$subject = $this->get_subject();

		// Wrong mode.
		$form_data['mode'] = 'wrong mode';

		$subject->verify( $submitted_data, $form_data );

		self::assertFalse( UM()->form()->has_error( 'hcaptcha' ) );

		// Login mode.
		$mode = $subject::UM_MODE;

		$this->prepare_verify_post( "hcaptcha_um_{$mode}_nonce", "hcaptcha_um_$mode" );
		$this->prepare_widget_id( $mode );
		$subject->verify( $submitted_data );

		self::assertFalse( UM()->form()->has_error( 'hcaptcha' ) );
	}

	/**
	 * Test verify() with submitted form id.
	 *
	 * @return void
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function test_verify_with_submitted_form_id(): void {
		$submitted_data = [];
		$subject        = $this->get_subject();
		$mode           = $subject::UM_MODE;
		$form_id        = 3140;

		$this->prepare_verify_post( "hcaptcha_um_{$mode}_nonce", "hcaptcha_um_$mode" );
		$_POST['form_id'] = (string) $form_id;
		$this->prepare_widget_id( $form_id );

		$subject->verify( $submitted_data );

		self::assertFalse( UM()->form()->has_error( 'hcaptcha' ) );
	}
	/**
	 * Test verify() not verified.
	 *
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function test_verify_not_verified(): void {
		$subject = $this->get_subject();
		$mode    = $subject::UM_MODE;

		$this->prepare_verify_post( "hcaptcha_um_{$mode}_nonce", "hcaptcha_um_$mode", false );
		$this->prepare_widget_id( $mode );

		$args['mode'] = $subject::UM_MODE;

		$subject->verify( $args );

		self::assertTrue( UM()->form()->has_error( 'hcaptcha' ) );
		self::assertSame( 'The hCaptcha is invalid.', UM()->form()->errors['hcaptcha'] );
	}

	/**
	 * Test verify() when widget id is missing.
	 *
	 * @return void
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function test_verify_missing_widget_id(): void {
		$subject = $this->get_subject();
		$mode    = $subject::UM_MODE;

		$this->prepare_verify_post( "hcaptcha_um_{$mode}_nonce", "hcaptcha_um_$mode" );

		$args['mode'] = $mode;

		$subject->verify( $args );

		self::assertTrue( UM()->form()->has_error( 'hcaptcha' ) );
		self::assertSame( 'Bad hCaptcha signature!', UM()->form()->errors['hcaptcha'] );
	}
	/**
	 * Test verify() when the login limit is not exceeded.
	 *
	 * @return void
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function test_verify_when_login_limit_is_not_exceeded(): void {
		$submitted_data = [ 'some submitted data' ];
		$form_data      = [ 'some form data' ];
		$subject        = Mockery::mock( Login::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'is_login_limit_exceeded' )
			->once()
			->andReturnFalse();

		$subject->verify( $submitted_data, $form_data );

		self::assertFalse( UM()->form()->has_error( 'hcaptcha' ) );
	}

	/**
	 * Test mute_login_hcaptcha_notice().
	 *
	 * @return void
	 */
	public function test_mute_login_hcaptcha_notice(): void {
		$subject = $this->get_subject();

		$message   = 'some error message';
		$error_key = 'wrong key';

		self::assertSame( $message, $subject->mute_login_hcaptcha_notice( $message, $error_key ) );

		$error_key = 'hcaptcha';

		self::assertSame( '', $subject->mute_login_hcaptcha_notice( $message, $error_key ) );
	}

	/**
	 * Prepare hCaptcha widget id.
	 *
	 * @param int|string $mode UM mode or form id.
	 *
	 * @return void
	 */
	private function prepare_widget_id( $mode ): void {
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'ultimate-member/ultimate-member.php' ],
				'form_id' => $mode,
			]
		);
	}
	/**
	 * Get subject.
	 *
	 * @return Login
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	private function get_subject(): Login {
		$subject = new Login();

		UM()->fields()->set_mode = $subject::UM_MODE;

		return $subject;
	}
}
