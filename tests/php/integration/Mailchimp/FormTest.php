<?php
/**
 * FormTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */

namespace HCaptcha\Tests\Integration\Mailchimp;

use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Mailchimp\Form;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use MC4WP_Form;
use MC4WP_Form_Element;
use Mockery;

/**
 * Test Form class.
 *
 * @group mailchimp
 */
class FormTest extends HCaptchaWPTestCase {

	/**
	 * Teardown test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		unset( $_REQUEST['action'], $_REQUEST['nonce'] );

		parent::tearDown();
	}

	/**
	 * Test init and init hooks.
	 */
	public function test_init_and_init_hooks(): void {
		$subject = new Form();

		self::assertSame( 10, has_filter( 'mc4wp_form_messages', [ $subject, 'add_hcap_error_messages' ] ) );
		self::assertSame( 20, has_filter( 'mc4wp_form_content', [ $subject, 'add_hcaptcha' ] ) );
		self::assertSame( 10, has_filter( 'mc4wp_form_errors', [ $subject, 'verify' ] ) );
		self::assertSame( 9, has_action( 'wp_print_footer_scripts', [ $subject, 'preview_scripts' ] ) );
	}

	/**
	 * Test add_hcap_error_messages().
	 */
	public function test_add_hcap_error_messages(): void {
		$form = Mockery::mock( MC4WP_Form::class );

		$messages = [
			'foo' => [
				'type' => 'notice',
				'text' => 'bar',
			],
		];

		$hcap_errors = [
			'missing-input-secret'     => [
				'type' => 'error',
				'text' => 'Your secret key is missing.',
			],
			'invalid-input-secret'     => [
				'type' => 'error',
				'text' => 'Your secret key is invalid or malformed.',
			],
			'missing-input-response'   => [
				'type' => 'error',
				'text' => 'The response parameter (verification token) is missing.',
			],
			'invalid-input-response'   => [
				'type' => 'error',
				'text' => 'The response parameter (verification token) is invalid or malformed.',
			],
			'expired-input-response'   => [
				'type' => 'error',
				'text' => 'The response parameter (verification token) is expired. (120s default)',
			],
			'already-seen-response'    => [
				'type' => 'error',
				'text' => 'The response parameter (verification token) was already verified once.',
			],
			'bad-request'              => [
				'type' => 'error',
				'text' => 'The request is invalid or malformed.',
			],
			'missing-remoteip'         => [
				'type' => 'error',
				'text' => 'The remoteip parameter is missing.',
			],
			'invalid-remoteip'         => [
				'type' => 'error',
				'text' => 'The remoteip parameter is not a valid IP address or blinded value.',
			],
			'not-using-dummy-passcode' => [
				'type' => 'error',
				'text' => 'You have used a testing sitekey but have not used its matching secret.',
			],
			'sitekey-secret-mismatch'  => [
				'type' => 'error',
				'text' => 'The sitekey is not registered with the provided secret.',
			],
			'empty'                    => [
				'type' => 'error',
				'text' => 'Please complete the hCaptcha.',
			],
			'fail'                     => [
				'type' => 'error',
				'text' => 'The hCaptcha is invalid.',
			],
			'bad-nonce'                => [
				'type' => 'error',
				'text' => 'Bad hCaptcha nonce!',
			],
			'bad-signature'            => [
				'type' => 'error',
				'text' => 'Bad hCaptcha signature!',
			],
			'spam'                     => [
				'type' => 'error',
				'text' => 'Anti-spam check failed.',
			],
			'fst-no-object'            => [
				'type' => 'error',
				'text' => 'FST object does not exist.',
			],
			'fst-too-fast'             => [
				'type' => 'error',
				'text' => 'Form submitted too quickly.',
			],
			'fst-replayed-or-expired'  => [
				'type' => 'error',
				'text' => 'Token replayed or expired.',
			],
			'fst-expired'              => [
				'type' => 'error',
				'text' => 'Token expired.',
			],
		];

		$expected = array_merge( $messages, $hcap_errors );
		$subject  = new Form();

		self::assertSame( $expected, $subject->add_hcap_error_messages( $messages, $form ) );
	}

	/**
	 * Test add_hcaptcha().
	 */
	public function test_add_hcaptcha(): void {
		$form_id               = 5;
		$content               = '<input type="submit">';
		$content_with_hcaptcha = 'Some content with hCaptcha <h-captcha ... >...</h-captcha>';
		$args                  = [
			'action' => 'hcaptcha_mailchimp',
			'name'   => 'hcaptcha_mailchimp_nonce',
			'id'     => [
				'source'  => [ 'mailchimp-for-wp/mailchimp-for-wp.php' ],
				'form_id' => $form_id,
			],
		];
		$expected              = $this->get_hcap_form( $args ) . $content;

		$mc4wp_form     = Mockery::mock( MC4WP_Form::class );
		$mc4wp_form->ID = $form_id;

		$element = Mockery::mock( MC4WP_Form_Element::class );

		$subject = new Form();

		self::assertSame( $content_with_hcaptcha, $subject->add_hcaptcha( $content_with_hcaptcha, $mc4wp_form, $element ) );
		self::assertSame( $expected, $subject->add_hcaptcha( $content, $mc4wp_form, $element ) );
	}

	/**
	 * Test verify().
	 */
	public function test_verify(): void {
		$this->prepare_verify_post( 'hcaptcha_mailchimp_nonce', 'hcaptcha_mailchimp' );

		$mc4wp_form = Mockery::mock( MC4WP_Form::class );

		$subject = new Form();

		self::assertSame( [], $subject->verify( [], $mc4wp_form ) );
	}

	/**
	 * Test verify() with a shortcode.
	 */
	public function test_verify_with_shortcode(): void {
		$name   = 'some_nonce';
		$action = 'some';

		$this->prepare_verify_post( $name, $action );

		$mc4wp_form = Mockery::mock( MC4WP_Form::class );

		$mc4wp_form->content = sprintf( '[hcaptcha name="%s" action="%s"]', $name, $action );

		$subject = new Form();

		self::assertSame( [], $subject->verify( [], $mc4wp_form ) );
	}

	/**
	 * Test verify() not verified.
	 */
	public function test_verify_not_verified(): void {
		$this->prepare_verify_post( 'hcaptcha_mailchimp_nonce', 'hcaptcha_mailchimp', false );

		$mc4wp_form = Mockery::mock( MC4WP_Form::class );

		$subject = new Form();

		self::assertSame( [ 'fail' ], $subject->verify( [], $mc4wp_form ) );
	}

	/**
	 * Test preview_scripts().
	 *
	 * @return void
	 */
	public function test_preview_scripts(): void {
		$form_id        = 123;
		$action         = 'hcaptcha_mailchimp';
		$name           = 'hcaptcha_mailchimp_nonce';
		$admin_handle   = 'admin-mailchimp';
		$id             = [
			'source'  => [ 'mailchimp-for-wp/mailchimp-for-wp.php' ],
			'form_id' => $form_id,
		];
		$params         = [
			'action'     => $action,
			'name'       => $name,
			'nonceField' => wp_nonce_field( $action, $name, true, false ),
			'widget'     => HCaptcha::get_widget( $id ),
		];
		$expected_extra = [
			'group' => 1,
			// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
			'data'  => 'var HCaptchaMailchimpObject = ' . json_encode( $params ) . ';',
		];

		$subject = Mockery::mock( Form::class )->makePartial();

		self::assertFalse( wp_script_is( $admin_handle ) );

		$subject->preview_scripts();

		self::assertFalse( wp_script_is( $admin_handle ) );

		$_GET['mc4wp_preview_form'] = $form_id;

		$subject->preview_scripts();

		self::assertTrue( wp_script_is( $admin_handle ) );

		$script = wp_scripts()->registered[ $admin_handle ];

		self::assertSame( HCAPTCHA_URL . '/assets/js/admin-mailchimp.min.js', $script->src );
		self::assertSame( [], $script->deps );
		self::assertSame( HCAPTCHA_VERSION, $script->ver );
		self::assertSame( $expected_extra, $script->extra );
	}
}
