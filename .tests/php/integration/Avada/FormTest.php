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

namespace HCaptcha\Tests\Integration\Avada;

use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use HCaptcha\Avada\Form;

/**
 * Test FormTest class.
 *
 * @group avada
 */
class FormTest extends HCaptchaWPTestCase {

	/**
	 * Tear down test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		unset( $_POST['formData'], $_POST['hcaptcha-widget-id'] );

		parent::tearDown();
	}

	/**
	 * Test init_hooks().
	 *
	 * @return void
	 */
	public function test_init_hooks(): void {
		$subject = new Form();

		self::assertSame( 10, has_action( 'fusion_form_after_open', [ $subject, 'form_after_open' ] ) );
		self::assertSame( 10, has_action( 'fusion_element_button_content', [ $subject, 'add_hcaptcha' ] ) );
		self::assertSame( 10, has_filter( 'fusion_form_demo_mode', [ $subject, 'verify' ] ) );
	}

	/**
	 * Test add_hcaptcha().
	 *
	 * @return void
	 */
	public function test_add_hcaptcha(): void {
		$form_id    = 5;
		$args       = [
			'id' =>
				[
					'source'  => [ 'Avada' ],
					'form_id' => $form_id,
				],
		];
		$params     = [ 'id' => $form_id ];
		$wrong_html = 'some html';
		$html       = '<button type="submit">';
		$form       = $this->get_hcap_form( $args );
		$expected   = $form . $html;

		$subject = new Form();

		$subject->form_after_open( $args, $params );
		self::assertSame( $wrong_html, $subject->add_hcaptcha( $wrong_html, $args ) );
		self::assertSame( $expected, $subject->add_hcaptcha( $html, $args ) );
	}

	/**
	 * Test verify().
	 *
	 * @return void
	 * @noinspection PhpConditionAlreadyCheckedInspection
	 */
	public function test_verify(): void {
		$demo_mode         = true;
		$hcaptcha_response = 'some_response';
		$form_data         = "h-captcha-response=$hcaptcha_response";

		$this->prepare_hcaptcha_request_verify( $hcaptcha_response );

		$_POST['formData'] = $form_data;

		$subject = new Form();

		self::assertSame( $demo_mode, $subject->verify( $demo_mode ) );
	}

	/**
	 * Test verify() when not verified.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function test_verify_not_verified(): void {
		$hcaptcha_response = 'some_response';
		$die_arr           = [];
		$expected          = [
			'{"status":"error","info":{"hcaptcha":"Please complete the hCaptcha."}}',
			'',
			[],
		];

		$this->prepare_hcaptcha_request_verify( $hcaptcha_response, false );

		$subject = new Form();

		add_filter(
			'wp_die_handler',
			static function ( $name ) use ( &$die_arr ) {
				return static function ( $message, $title, $args ) use ( &$die_arr ) {
					$die_arr = [ $message, $title, $args ];
				};
			}
		);

		$subject->verify( true );

		self::assertSame( $expected, $die_arr );
	}
}
