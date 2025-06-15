<?php
/**
 * LoginTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\UM;

use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use HCaptcha\UM\Login;
use Mockery;

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
	 * Test add_um_captcha() when login limit is not exceeded.
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
	 * Test add_um_captcha() with wrong mode.
	 *
	 * @return void
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

		$args['mode'] = $subject::UM_MODE;

		$subject->verify( $args );

		self::assertTrue( UM()->form()->has_error( 'hcaptcha' ) );
		self::assertSame( 'The hCaptcha is invalid.', UM()->form()->errors['hcaptcha'] );
	}

	/**
	 * Test verify() when login limit is not exceeded.
	 *
	 * @return void
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
