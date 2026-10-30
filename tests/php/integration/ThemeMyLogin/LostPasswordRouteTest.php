<?php
/**
 * LostPasswordRouteTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\ThemeMyLogin;

use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use HCaptcha\ThemeMyLogin\LostPassword;
use WP_Error;

/**
 * Test native Theme My Login recovery boundaries without an optional button field.
 *
 * @group theme-my-login
 */
class LostPasswordRouteTest extends HCaptchaWPTestCase {
	/**
	 * Render a challenge only while Theme My Login renders its recovery form.
	 */
	public function test_add_captcha_only_during_native_render(): void {
		$subject = new LostPassword();

		ob_start();
		$subject->add_captcha();
		self::assertSame( '', ob_get_clean() );

		do_action( 'tml_render_form' );
		ob_start();
		$subject->add_captcha();

		self::assertStringContainsString( 'name="hcaptcha-widget-id"', ob_get_clean() );
	}

	/**
	 * Native recovery actions require a CAPTCHA without a submit field.
	 *
	 * @dataProvider dp_native_recovery_actions
	 *
	 * @param string $action Native action.
	 */
	public function test_missing_submit_requires_captcha( string $action ): void {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST['user_login']       = 'synthetic-recovery-user';
		new LostPassword();
		$errors = new WP_Error();

		$this->run_recovery_callback( $action, $errors );

		self::assertSame( 'bad-signature', $errors->get_error_code() );
	}

	/**
	 * A signed successful proof still passes without a submit field.
	 *
	 * @dataProvider dp_native_recovery_actions
	 *
	 * @param string $action Native action.
	 */
	public function test_valid_proof_without_submit( string $action ): void {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$this->prepare_verify_post( 'hcaptcha_theme_my_login_lost_password_nonce', 'hcaptcha_theme_my_login_lost_password' );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'theme-my-login/theme-my-login.php' ],
				'form_id' => 'lost_password',
			]
		);
		$_POST['user_login']                   = 'synthetic-recovery-user';
		new LostPassword();
		$errors = new WP_Error();

		$this->run_recovery_callback( $action, $errors );

		self::assertFalse( $errors->has_errors() );
	}

	/**
	 * Unrelated WordPress recovery and GET requests are not owned by TML.
	 */
	public function test_other_routes_are_unaffected(): void {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST['user_login']       = 'synthetic-recovery-user';
		new LostPassword();
		$other_errors = new WP_Error();
		do_action( 'lostpassword_post', $other_errors, null );

		$_SERVER['REQUEST_METHOD'] = 'GET';
		$get_errors                = new WP_Error();
		$this->run_recovery_callback( 'tml_action_lostpassword', $get_errors );

		self::assertFalse( $other_errors->has_errors() );
		self::assertFalse( $get_errors->has_errors() );
	}

	/**
	 * Native normal and AJAX recovery actions.
	 *
	 * @return array
	 */
	public function dp_native_recovery_actions(): array {
		return [
			'normal' => [ 'tml_action_lostpassword' ],
			'AJAX'   => [ 'tml_action_ajax_lostpassword' ],
		];
	}

	/**
	 * Call the WordPress callback in the native TML action context.
	 *
	 * @param string   $action Native action.
	 * @param WP_Error $errors Callback errors.
	 */
	private function run_recovery_callback( string $action, WP_Error $errors ): void {
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wp_current_filter'][] = $action;

		try {
			do_action( 'lostpassword_post', $errors, null );
		} finally {
			array_pop( $GLOBALS['wp_current_filter'] );
			unset( $_SERVER['REQUEST_METHOD'] );
		}
	}
}
