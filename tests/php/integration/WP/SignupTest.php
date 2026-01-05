<?php
/**
 * SignupTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\WP;

use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use HCaptcha\WP\Signup;
use ReflectionException;
use ReflectionProperty;
use WP_Error;

/**
 * Class SignupTest.
 *
 * @group wp-signup
 * @group wp
 */
class SignupTest extends HCaptchaWPTestCase {

	/**
	 * Tear down the test.
	 */
	public function tearDown(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		unset( $_POST['stage'], $GLOBALS['wp_actions']['before_signup_form'] );

		parent::tearDown();
	}

	/**
	 * Test constructor and init_hooks().
	 */
	public function test_constructor_and_init_hooks(): void {
		$subject = new Signup();

		self::assertSame( 10, has_action( 'before_signup_form', [ $subject, 'before_signup_form' ] ) );
		self::assertSame( 10, has_action( 'after_signup_form', [ $subject, 'add_captcha' ] ) );

		self::assertSame( 10, has_filter( 'wpmu_validate_user_signup', [ $subject, 'verify' ] ) );
		self::assertSame( 10, has_filter( 'wpmu_validate_blog_signup', [ $subject, 'verify' ] ) );
	}

	/**
	 * Test add_captcha().
	 */
	public function test_add_captcha(): void {
		$args    = [
			'action' => 'hcaptcha_signup',
			'name'   => 'hcaptcha_signup_nonce',
			'id'     => [
				'source'  => HCaptcha::get_class_source( Signup::class ),
				'form_id' => 'signup',
			],
		];
		$search  = '<p class="submit">';
		$content = '<div>Some content</div>\n' . $search;

		$subject = new Signup();

		$form     = $this->get_hcap_form( $args );
		$expected = str_replace( $search, $subject->get_error_html() . $form . $search, $content );

		ob_start();

		$subject->before_signup_form();

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $content;

		$subject->add_captcha();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test verify() when it is not a WP signup form.
	 */
	public function test_verify_when_NOT_wp_signup_form(): void {
		$_POST['stage'] = 'validate-user-signup';

		$input = [
			'errors' => new WP_Error( 'some_error', 'Some error.' ),
		];

		new Signup();

		$result = apply_filters( 'wpmu_validate_user_signup', $input );

		self::assertEquals( $input, $result );
	}

	/**
	 * Test verify() when the stage doesn't match the current filter.
	 */
	public function test_verify_when_stage_does_not_match_filter(): void {
		do_action( 'before_signup_form' );

		$_POST['stage'] = 'validate-blog-signup';

		$input = [
			'errors' => new WP_Error( 'some_error', 'Some error.' ),
		];

		new Signup();

		$result = apply_filters( 'wpmu_validate_user_signup', $input );

		self::assertEquals( $input, $result );
	}

	/**
	 * Test verify() success.
	 */
	public function test_verify(): void {
		do_action( 'before_signup_form' );

		$_POST['stage'] = 'validate-user-signup';

		$this->prepare_verify_post_html( 'hcaptcha_signup_nonce', 'hcaptcha_signup' );

		$subject = new Signup();

		$result = apply_filters( 'wpmu_validate_user_signup', [ 'errors' => '' ] );

		self::assertArrayHasKey( 'errors', $result );
		self::assertInstanceOf( WP_Error::class, $result['errors'] );
		self::assertSame( [], $result['errors']->errors );
		self::assertSame( '', $subject->get_error_html() );
	}

	/**
	 * Test verify() not verified.
	 */
	public function test_verify_not_verified(): void {
		do_action( 'before_signup_form' );
		$_POST['stage'] = 'validate-user-signup';

		$this->prepare_verify_post_html( 'hcaptcha_signup_nonce', 'hcaptcha_signup', false );

		$subject = new Signup();

		$result = apply_filters( 'wpmu_validate_user_signup', [ 'errors' => '' ] );

		self::assertArrayHasKey( 'errors', $result );
		self::assertInstanceOf( WP_Error::class, $result['errors'] );
		self::assertSame( 'The hCaptcha is invalid.', $result['errors']->get_error_message( 'fail' ) );
		self::assertSame( '<p class="error" id="wp-signup-hcaptcha-error">The hCaptcha is invalid.</p>', $subject->get_error_html() );
	}

	/**
	 * Test get_error_html() when an error message is set.
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_get_error_html(): void {
		$subject = new Signup();

		self::assertSame( '', $subject->get_error_html() );

		$this->set_protected_property( $subject, 'error_message', 'Some error message.' );

		self::assertSame( '<p class="error" id="wp-signup-hcaptcha-error">Some error message.</p>', $subject->get_error_html() );
	}
}
