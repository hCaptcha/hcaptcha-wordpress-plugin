<?php
/**
 * LoginTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\UM;

use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use HCaptcha\UM\Login;

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
	 * @noinspection PhpLanguageLevelInspection
	 * @noinspection PhpUndefinedClassInspection
	 */
	public function tearDown(): void { // phpcs:ignore PHPCompatibility.FunctionDeclarations.NewReturnTypeDeclarations.voidFound
		UM()->form()->errors = null;

		parent::tearDown();
	}

	/**
	 * Test constructor and init_hooks().
	 */
	public function test_constructor_and_init_hooks() {
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
	public function test_add_um_captcha( array $fields, array $expected ) {
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
			'empty fields' => [
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
			'login fields' => [
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
		];
	}

	/**
	 * Test display_captcha().
	 *
	 * @return void
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function test_display_captcha() {
		$subject = $this->get_subject();

		$mode   = 'wrong mode';
		$output = '';

		self::assertSame( $output, $subject->display_captcha( $output, $mode ) );

		$mode   = $subject::UM_MODE;
		$output = 'some output';

		self::assertSame( $output, $subject->display_captcha( $output, $mode ) );

		$mode     = $subject::UM_MODE;
		$output   = '';
		$expected =
			'<div class="um-field um-field-hcaptcha">' .
			$this->get_hcap_form() .
			wp_nonce_field( "hcaptcha_um_$mode", "hcaptcha_um_{$mode}_nonce", true, false ) .
			'</div>';

		self::assertSame( $expected, $subject->display_captcha( $output, $mode ) );

		$error_message = 'message';

		UM()->form()->errors = [ 'hcaptcha' => $error_message ];

		$expected .= "<div class=\"um-field-error\"><span class=\"um-field-arrow\"><i class=\"um-faicon-caret-up\"></i></span>$error_message</div>";

		self::assertSame( $expected, $subject->display_captcha( $output, $mode ) );
	}

	/**
	 * Test verify().
	 *
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function test_verify() {
		$subject = $this->get_subject();
		$mode    = $subject::UM_MODE;
		$args    = [];

		$this->prepare_hcaptcha_get_verify_message( "hcaptcha_um_{$mode}_nonce", "hcaptcha_um_$mode" );
		$subject->verify( $args );

		self::assertFalse( UM()->form()->has_error( 'hcaptcha' ) );

		$args['mode'] = 'wrong mode';

		$subject->verify( $args );

		self::assertFalse( UM()->form()->has_error( 'hcaptcha' ) );

		$args['mode'] = $subject::UM_MODE;

		$subject->verify( $args );

		self::assertFalse( UM()->form()->has_error( 'hcaptcha' ) );
	}

	/**
	 * Test verify() not verified.
	 *
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function test_verify_not_verified() {
		$subject = $this->get_subject();
		$mode    = $subject::UM_MODE;

		$this->prepare_hcaptcha_get_verify_message( "hcaptcha_um_{$mode}_nonce", "hcaptcha_um_$mode", false );

		$args['mode'] = $subject::UM_MODE;

		$subject->verify( $args );

		self::assertTrue( UM()->form()->has_error( 'hcaptcha' ) );
		self::assertSame( 'The hCaptcha is invalid.', UM()->form()->errors['hcaptcha'] );
	}

	/**
	 * Test mute_login_hcaptcha_notice().
	 *
	 * @return void
	 */
	public function test_mute_login_hcaptcha_notice() {
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
