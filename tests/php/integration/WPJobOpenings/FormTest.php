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

use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use HCaptcha\WPJobOpenings\Form;
use ReflectionException;

/**
 * Test FormTest class.
 *
 * @group job-openings
 */
class FormTest extends HCaptchaWPTestCase {
	/**
	 * Set up the test.
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function setUp(): void {
		parent::setUp();

		hcaptcha()->settings()->set( 'honeypot', 'on' );
		hcaptcha()->settings()->set( 'set_min_submit_time', 'on' );
		hcaptcha()->settings()->set( 'wp_job_openings_status', [ 'form' ] );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );
	}

	/**
	 * Tear down the test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		unset( $GLOBALS['awsm_response'] );

		parent::tearDown();
	}

	/**
	 * Test init_hooks().
	 *
	 * @return void
	 */
	public function test_init_hooks(): void {
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
	public function test_add_captcha(): void {
		$form_id    = 5;
		$form_attrs = [
			'job_id' => '0005',
		];
		$html       = '<div class="awsm-job-form-group">' . "\n" . '<input type="submit">';
		$args       = [
			'action' => Form::ACTION,
			'name'   => Form::NONCE,
			'id'     => [
				'source'  => [ 'wp-job-openings/wp-job-openings.php' ],
				'form_id' => $form_id,
			],
		];
		$expected   = '<div class="awsm-job-form-group">' . $this->get_hcap_form( $args ) . "</div>\n" . $html;

		$subject = new Form();

		ob_start();

		$subject->before_application_form( $form_attrs );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $html;

		$subject->add_captcha( $form_attrs );
		$output = (string) ob_get_clean();

		self::assertSame( $expected, $output );
		self::assertStringContainsString( 'name="hcap_hp_test"', $output );
		self::assertStringContainsString( 'name="hcap_hp_sig"', $output );
		self::assertTrue( wp_script_is( 'hcaptcha-wp-job-openings' ) );
	}

	/**
	 * Test verify().
	 *
	 * @return void
	 */
	public function test_verify(): void {
		global $awsm_response;

		$awsm_response = [];

		$this->prepare_verify_post( Form::NONCE, Form::ACTION );
		$this->prepare_job_identity();

		$subject = new Form();

		$subject->verify();

		self::assertSame( [], $awsm_response );
	}

	/**
	 * Test verify() when not verified.
	 *
	 * @return void
	 */
	public function test_verify_not_verified(): void {
		global $awsm_response;

		$awsm_response = [];

		$this->prepare_verify_post( Form::NONCE, Form::ACTION, false );
		$this->prepare_job_identity();

		$subject = new Form();

		$subject->verify();

		self::assertSame( [ 'error' => [ 'The hCaptcha is invalid.' ] ], $awsm_response );
	}

	/**
	 * Test verify() with a filled honeypot.
	 *
	 * @return void
	 */
	public function test_verify_filled_honeypot(): void {
		global $awsm_response;

		$awsm_response = [];

		$this->prepare_verify_post( Form::NONCE, Form::ACTION );
		$this->prepare_job_identity();

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'wp-job-openings/wp-job-openings.php' ],
				'form_id' => 5,
			]
		);
		$_POST['hcap_hp_test']                 = 'bot';

		( new Form() )->verify();

		self::assertSame( [ 'error' => [ 'Anti-spam check failed.' ] ], $awsm_response );
	}

	/**
	 * A missing native job ID cannot disable widget identity verification.
	 */
	public function test_missing_job_id_rejects_foreign_widget(): void {
		global $awsm_response;

		$awsm_response = [];
		$this->prepare_verify_post( Form::NONCE, Form::ACTION );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'wp-job-openings/wp-job-openings.php' ],
				'form_id' => 5,
			]
		);

		( new Form() )->verify();

		self::assertSame( [ 'error' => [ hcap_get_error_messages()['bad-signature'] ] ], $awsm_response );
	}

	/**
	 * Prepare the native job selector and matching rendered widget.
	 */
	private function prepare_job_identity(): void {
		$_POST['awsm_job_id']                  = '0005';
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'wp-job-openings/wp-job-openings.php' ],
				'form_id' => 5,
			]
		);
	}

	/**
	 * Check the authoritative native email through the production API.
	 *
	 * @dataProvider dp_test_native_email_policy
	 *
	 * @param string $email   Native address.
	 * @param bool   $blocked Whether disposal policy rejects it.
	 */
	public function test_native_email_policy( string $email, bool $blocked ): void {
		add_filter(
			'hcap_disposable_email_domains',
			static function (): array {
				return [ 'disposable.example' => true ];
			}
		);
		global $awsm_response;

		$awsm_response                 = [ 'error' => [] ];
		$_POST['awsm_job_id']          = '5';
		$_POST['awsm_applicant_email'] = $email;
		$_POST['email']                = 'safe@example.com';
		$this->prepare_verify_post( 'hcaptcha_wp_job_openings_nonce', 'hcaptcha_wp_job_openings' );
		hcaptcha()->settings()->set( 'disposable_email', [ 'on' ] );
		hcaptcha()->settings()->set( 'wp_job_openings_status', [ 'form' ] );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );
		self::assertTrue( hcaptcha()->settings()->is_on( 'disposable_email' ) );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'wp-job-openings/wp-job-openings.php' ],
				'form_id' => 5,
			]
		);

		( new Form() )->verify();

		self::assertSame( $blocked ? [ hcap_get_error_messages()['disposable-email'] ] : [], $awsm_response['error'] );
	}

	/**
	 * Native email policy controls.
	 *
	 * @return array
	 */
	public function dp_test_native_email_policy(): array {
		return [
			'disposable email' => [ 'user@disposable.example', true ],
			'permanent email'  => [ 'safe@example.com', false ],
			'email-free data'  => [ '', false ],
		];
	}
}
