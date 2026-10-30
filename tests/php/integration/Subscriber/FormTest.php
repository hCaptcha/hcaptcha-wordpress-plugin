<?php
/**
 * FormTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\Subscriber;

use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Subscriber\Form;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;

/**
 * Test Form class.
 *
 * @group subscriber
 */
class FormTest extends HCaptchaWPTestCase {

	/**
	 * Set up the test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		hcaptcha()->settings()->set( 'honeypot', 'on' );
		hcaptcha()->settings()->set( 'set_min_submit_time', 'on' );
		hcaptcha()->settings()->set( 'subscriber_status', [ 'form' ] );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );
	}

	/**
	 * Tests add_captcha().
	 */
	public function test_add_captcha(): void {
		hcaptcha()->init_hooks();

		$content  = '<!--some form content-->';
		$args     = [
			'action' => 'hcaptcha_subscriber_form',
			'name'   => 'hcaptcha_subscriber_form_nonce',
			'id'     => [
				'source'  => [ 'subscriber/subscriber.php' ],
				'form_id' => 'form',
			],
		];
		$expected = $content . $this->get_hcap_form( $args );
		$subject  = new Form();

		$output = $subject->add_captcha( $content );

		self::assertSame( $expected, $output );
		self::assertStringContainsString( 'name="hcap_hp_test"', $output );
		self::assertStringContainsString( 'name="hcap_hp_sig"', $output );
	}

	/**
	 * Test verify().
	 */
	public function test_verify(): void {
		$this->prepare_verify_post( 'hcaptcha_subscriber_form_nonce', 'hcaptcha_subscriber_form' );

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'subscriber/subscriber.php' ],
				'form_id' => 'form',
			]
		);

		$subject = new Form();

		self::assertTrue( $subject->verify( true ) );
		self::assertFalse( $subject->verify( false ) );
	}

	/**
	 * Test verify() not verified.
	 */
	public function test_verify_not_verified(): void {
		$this->prepare_verify_post( 'hcaptcha_subscriber_form_nonce', 'hcaptcha_subscriber_form', false );

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'subscriber/subscriber.php' ],
				'form_id' => 'form',
			]
		);

		$subject = new Form();

		self::assertSame( 'The hCaptcha is invalid.', $subject->verify( true ) );
	}

	/**
	 * Test verify() with a filled honeypot.
	 *
	 * @return void
	 */
	public function test_verify_filled_honeypot(): void {
		$this->prepare_verify_post( 'hcaptcha_subscriber_form_nonce', 'hcaptcha_subscriber_form' );

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'subscriber/subscriber.php' ],
				'form_id' => 'form',
			]
		);
		$_POST['hcap_hp_test']                 = 'bot';

		self::assertSame( 'Anti-spam check failed.', ( new Form() )->verify( true ) );
	}

	/**
	 * Test that an exemption applies only to the expected form identity.
	 *
	 * @param array $submitted_id Submitted form identity.
	 * @param bool  $matches      Whether the identity belongs to this endpoint.
	 *
	 * @dataProvider dp_test_exempt_form_identity
	 * @return void
	 */
	public function test_verify_exempt_form_identity( array $submitted_id, bool $matches ): void {
		$this->prepare_verify_post( 'hcaptcha_subscriber_form_nonce', 'hcaptcha_subscriber_form' );

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value( $submitted_id );
		$_POST['hcap_hp_test']                 = 'bot';
		$_POST['h-captcha-response']           = '';

		add_filter(
			'hcap_protect_form',
			static function ( bool $protect, array $source, $form_id ) use ( $submitted_id ): bool {
				return $submitted_id['source'] === $source && $submitted_id['form_id'] === $form_id ? false : $protect;
			},
			10,
			3
		);

		$http_requests = did_filter( 'pre_http_request' );
		$expected      = $matches ? true : hcap_get_error_messages()['bad-signature'];

		self::assertSame( $expected, ( new Form() )->verify( true ) );
		self::assertSame( $http_requests, did_filter( 'pre_http_request' ) );
	}

	/**
	 * Provide signed form identities with a protection exemption.
	 *
	 * @return array
	 */
	public function dp_test_exempt_form_identity(): array {
		return [
			'expected exempt form' => [
				[
					'source'  => [ 'subscriber/subscriber.php' ],
					'form_id' => 'form',
				],
				true,
			],
			'foreign exempt form'  => [
				[
					'source'  => [ 'easy-digital-downloads/easy-digital-downloads.php' ],
					'form_id' => 'checkout',
				],
				false,
			],
		];
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
		$_POST['sbscrbr_email'] = $email;
		$_POST['email']         = 'safe@example.com';
		$this->prepare_verify_post( 'hcaptcha_subscriber_form_nonce', 'hcaptcha_subscriber_form' );
		hcaptcha()->settings()->set( 'disposable_email', [ 'on' ] );
		hcaptcha()->settings()->set( 'subscriber_status', [ 'form' ] );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );
		self::assertTrue( hcaptcha()->settings()->is_on( 'disposable_email' ) );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'subscriber/subscriber.php' ],
				'form_id' => 'form',
			]
		);

		$result = ( new Form() )->verify( true );

		self::assertSame( $blocked ? hcap_get_error_messages()['disposable-email'] : true, $result );
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
