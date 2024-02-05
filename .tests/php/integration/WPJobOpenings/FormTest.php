<?php
/**
 * FormTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpLanguageLevelInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Integration\WPJobOpenings;

use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use HCaptcha\WPJobOpenings\Form;

/**
 * Test FormTest class.
 *
 * @group job-openings
 */
class FormTest extends HCaptchaWPTestCase {

	/**
	 * Tear down test.
	 *
	 * @return void
	 */
	public function tearDown(): void { // phpcs:ignore PHPCompatibility.FunctionDeclarations.NewReturnTypeDeclarations.voidFound
		unset( $GLOBALS['awsm_response'] );
	}

	/**
	 * Test init_hooks().
	 *
	 * @return void
	 */
	public function test_init_hooks() {
		$subject = new Form();

		self::assertSame( 10, has_action( 'before_awsm_application_form', [ $subject, 'before_application_form' ] ) );
		self::assertSame( 10, has_action( 'after_awsm_application_form', [ $subject, 'add_captcha' ] ) );
		self::assertSame( 10, has_action( 'awsm_job_application_submitting', [ $subject, 'verify' ] ) );
	}

	/**
	 * Test add_captcha().
	 *
	 * @return void
	 */
	public function test_add_captcha() {
		$form_attrs = [
			'job_id' => 5,
		];
		$html       = '<div class="awsm-job-form-group">' . "\n" . '<input type="submit">';
		$nonce      = wp_nonce_field( Form::ACTION, Form::NONCE, true, false );
		$expected   = '<div class="awsm-job-form-group">' . $this->get_hcap_form() . $nonce . "</div>\n" . $html;

		$subject = new Form();

		ob_start();

		$subject->before_application_form( $form_attrs );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $html;

		$subject->add_captcha( $form_attrs );
		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test verify().
	 *
	 * @return void
	 */
	public function test_verify() {
		global $awsm_response;

		$awsm_response = [];

		$this->prepare_hcaptcha_verify_post( Form::NONCE, Form::ACTION );

		$subject = new Form();

		$subject->verify();

		self::assertSame( $awsm_response, [] );
	}

	/**
	 * Test verify() when not verified.
	 *
	 * @return void
	 */
	public function test_verify_not_verified() {
		global $awsm_response;

		$awsm_response = [];

		$this->prepare_hcaptcha_verify_post( Form::NONCE, Form::ACTION, false );

		$subject = new Form();

		$subject->verify();

		self::assertSame( $awsm_response, [ 'error' => [ 'The hCaptcha is invalid.' ] ] );
	}
}
