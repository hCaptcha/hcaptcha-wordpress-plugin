<?php
/**
 * FormIdentityVerificationTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\Helpers;

use HCaptcha\Helpers\API;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\ProtectContent\ProtectContent;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use Mockery;
use ReflectionException;

/**
 * Test form identity at the shared verification boundary.
 *
 * @group helpers
 * @group helpers-form-identity
 */
class FormIdentityVerificationTest extends HCaptchaWPTestCase {

	/**
	 * Protected form identity.
	 */
	private const PROTECTED_ID = [
		'source'  => [ 'WordPress' ],
		'form_id' => 'login',
	];

	/**
	 * Public form whose protection is disabled independently.
	 */
	private const EXEMPT_ID = [
		'source'  => [ 'WordPress' ],
		'form_id' => 'register',
	];

	/**
	 * Siteverify request bodies.
	 *
	 * @var array
	 */
	private array $remote_requests = [];

	/**
	 * Identities passed to the form protection filter.
	 *
	 * @var array
	 */
	private array $protection_checks = [];

	/**
	 * Set up real form policy and an observable siteverify response.
	 */
	public function setUp(): void {
		parent::setUp();

		wp_set_current_user( 0 );
		$_POST = [];
		$this->prepare_verify_request( '' );

		add_filter(
			'hcap_protect_form',
			function ( $protect, $source, $form_id ) {
				$id                        = [
					'source'  => $source,
					'form_id' => $form_id,
				];
				$this->protection_checks[] = $id;

				return self::EXEMPT_ID === $id ? false : $protect;
			},
			PHP_INT_MAX,
			3
		);

		add_filter(
			'pre_http_request',
			function ( $preempt, $parsed_args, $url ) {
				if ( hcaptcha()->get_verify_url() !== $url ) {
					return $preempt;
				}

				$this->remote_requests[] = $parsed_args['body'];

				return [
					'body' => wp_json_encode(
						[
							'success'  => true,
							'hostname' => 'test.test',
						]
					),
				];
			},
			PHP_INT_MAX,
			3
		);
	}

	/**
	 * An unbound helper cannot borrow a public form's exemption.
	 *
	 * @param string $helper Verification helper.
	 *
	 * @dataProvider dp_verification_helpers
	 */
	public function test_unbound_helper_rejects_foreign_exemption( string $helper ): void {
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value( self::EXEMPT_ID );

		self::assertSame( $this->error_for_helper( $helper, 'empty' ), $this->verify_with_helper( $helper ) );
		self::assertContains( self::EXEMPT_ID, $this->protection_checks );
		self::assertCount( 0, $this->remote_requests );
	}

	/**
	 * A bound helper rejects a valid signature belonging to another form.
	 *
	 * @param string $helper Verification helper.
	 *
	 * @dataProvider dp_verification_helpers
	 */
	public function test_bound_helper_rejects_foreign_exemption( string $helper ): void {
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value( self::EXEMPT_ID );

		self::assertSame(
			$this->error_for_helper( $helper, 'bad-signature' ),
			$this->verify_with_helper( $helper, self::PROTECTED_ID )
		);
		self::assertCount( 0, $this->remote_requests );
	}

	/**
	 * An exemption is honored for the exact form the server expects.
	 *
	 * @param string $helper Verification helper.
	 *
	 * @dataProvider dp_verification_helpers
	 */
	public function test_bound_helper_honors_matching_exemption( string $helper ): void {
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value( self::EXEMPT_ID );

		self::assertNull( $this->verify_with_helper( $helper, self::EXEMPT_ID ) );
		self::assertCount( 0, $this->remote_requests );
	}

	/**
	 * An unbound helper still verifies a normal response without a widget id.
	 *
	 * @param string $helper Verification helper.
	 *
	 * @dataProvider dp_verification_helpers
	 */
	public function test_unbound_helper_verifies_normal_token( string $helper ): void {
		$_POST['h-captcha-response'] = 'valid-response';

		self::assertNull( $this->verify_with_helper( $helper ) );
		self::assertCount( 1, $this->remote_requests );
		self::assertSame( 'valid-response', $this->remote_requests[0]['response'] );
	}

	/**
	 * A foreign exempt id cannot suppress a logged-in user's nonce check.
	 *
	 * @param string $helper Verification helper.
	 *
	 * @dataProvider dp_nonce_helpers
	 */
	public function test_unbound_helper_checks_nonce_despite_foreign_exemption( string $helper ): void {
		wp_set_current_user( 1 );
		$_POST[ HCAPTCHA_NONCE ]               = 'invalid-nonce';
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value( self::EXEMPT_ID );

		self::assertSame( $this->error_for_helper( $helper, 'bad-nonce' ), $this->verify_with_helper( $helper ) );
		self::assertCount( 0, $this->remote_requests );
	}

	/**
	 * A bound direct request rejects an invalid identity before contacting siteverify.
	 *
	 * @param string $widget_id Missing, altered, or another signed widget id.
	 *
	 * @dataProvider dp_invalid_identity
	 */
	public function test_bound_request_rejects_invalid_identity_without_remote_call( string $widget_id ): void {
		$_POST['h-captcha-response'] = 'valid-response';
		$this->post_invalid_identity( $widget_id );

		self::assertSame(
			hcap_get_error_messages()['bad-signature'],
			API::verify_request( null, [ 'expected_id' => self::PROTECTED_ID ] )
		);
		self::assertCount( 0, $this->remote_requests );
	}

	/**
	 * Cached remote success cannot substitute for a bound form's identity.
	 *
	 * @param string $helper    Verification helper.
	 * @param string $widget_id Missing, altered, or another signed widget id.
	 *
	 * @dataProvider dp_invalid_identity_after_success
	 */
	public function test_bound_helper_checks_identity_before_cached_success( string $helper, string $widget_id ): void {
		$_POST['h-captcha-response']           = 'valid-response';
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value( self::PROTECTED_ID );

		self::assertNull( $this->verify_with_helper( $helper, self::PROTECTED_ID ) );
		self::assertCount( 1, $this->remote_requests );

		$this->post_invalid_identity( $widget_id );

		self::assertSame(
			$this->error_for_helper( $helper, 'bad-signature' ),
			$this->verify_with_helper( $helper, self::PROTECTED_ID )
		);
		self::assertCount( 1, $this->remote_requests );
	}

	/**
	 * Cached exemption success does not exempt a later unbound verification.
	 *
	 * @param string $helper Verification helper.
	 *
	 * @dataProvider dp_verification_helpers
	 */
	public function test_cached_exemption_does_not_exempt_unbound_helper( string $helper ): void {
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value( self::EXEMPT_ID );

		self::assertNull( API::verify( [ 'expected_id' => self::EXEMPT_ID ] ) );
		self::assertSame( $this->error_for_helper( $helper, 'empty' ), $this->verify_with_helper( $helper ) );
		self::assertCount( 0, $this->remote_requests );
	}

	/**
	 * Cached exemption success does not exempt a different expected form.
	 */
	public function test_cached_exemption_does_not_exempt_different_form(): void {
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value( self::EXEMPT_ID );

		self::assertNull( API::verify( [ 'expected_id' => self::EXEMPT_ID ] ) );

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value( self::PROTECTED_ID );

		self::assertSame(
			hcap_get_error_messages()['empty'],
			API::verify_request( null, [ 'expected_id' => self::PROTECTED_ID ] )
		);
		self::assertCount( 0, $this->remote_requests );
	}

	/**
	 * Signed boolean honeypot metadata remains valid for the matching form.
	 *
	 * @param bool $honeypot Signed honeypot override.
	 *
	 * @dataProvider dp_signed_honeypot
	 */
	public function test_matching_identity_preserves_signed_honeypot( bool $honeypot ): void {
		$_POST['h-captcha-response']           = 'valid-response';
		$_POST['hcap_hp_test']                 = 'bot-value';
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			array_merge( self::PROTECTED_ID, [ 'honeypot' => $honeypot ] )
		);

		$result = API::verify_request( null, [ 'expected_id' => self::PROTECTED_ID ] );

		self::assertSame( $honeypot ? hcap_get_error_messages()['spam'] : null, $result );
		self::assertCount( $honeypot ? 0 : 1, $this->remote_requests );
	}

	/**
	 * Repeated handlers for the same form reuse one successful remote request.
	 */
	public function test_successful_bound_verification_chain_reuses_siteverify(): void {
		$_POST['h-captcha-response']           = 'valid-response';
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value( self::PROTECTED_ID );

		self::assertNull( API::verify( [ 'expected_id' => self::PROTECTED_ID ] ) );
		self::assertNull( API::verify_post( HCAPTCHA_NONCE, HCAPTCHA_ACTION, self::PROTECTED_ID ) );
		self::assertNull( API::verify_request( null, [ 'expected_id' => self::PROTECTED_ID ] ) );
		self::assertCount( 1, $this->remote_requests );
		self::assertTrue( API::get_siteverify_response()['success'] );
	}

	/**
	 * A legacy unbound hook reuses genuine proof for the same widget and response.
	 */
	public function test_verified_token_can_be_reused_by_unbound_hook(): void {
		$_POST['h-captcha-response']           = 'valid-response';
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value( self::PROTECTED_ID );

		self::assertNull( API::verify( [ 'expected_id' => self::PROTECTED_ID ] ) );
		self::assertNull( API::verify_post() );
		self::assertCount( 1, $this->remote_requests );
		self::assertTrue( API::get_siteverify_response()['success'] );
	}

	/**
	 * A public form's exemption cannot mint a content protection clearance.
	 *
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_protected_content_rejects_foreign_exemption(): void {
		$subject = Mockery::mock( ProtectContent::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$cookies = [];

		$subject->shouldReceive( 'setcookie' )->andReturnUsing(
			static function ( $name, $value ) use ( &$cookies ) {
				$cookies[ $name ] = $value;

				return true;
			}
		);
		$this->set_protected_property( $subject, 'resource_scope', '/members' );
		$this->set_protected_property( $subject, 'request_uri', '/members' );
		$_COOKIE['hcaptcha_content_protection_session'] = 'v1.' . str_repeat( 'a', 64 );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ]          = HCaptcha::widget_id_value( self::EXEMPT_ID );

		add_filter( 'wp_redirect', '__return_empty_string' );

		try {
			$method = $this->set_method_accessibility( $subject, 'verify' );

			self::assertSame( hcap_get_error_messages()['bad-signature'], $method->invoke( $subject ) );
			self::assertSame( [], $cookies );
			self::assertCount( 0, $this->remote_requests );
		} finally {
			unset( $_COOKIE['hcaptcha_content_protection_session'] );
			remove_filter( 'wp_redirect', '__return_empty_string' );
		}
	}

	/**
	 * Verification helpers with and without nonce handling.
	 *
	 * @return array
	 */
	public function dp_verification_helpers(): array {
		return array_merge( $this->dp_nonce_helpers(), [ 'direct request' => [ 'verify_request' ] ] );
	}

	/**
	 * Verification helpers that accept nonce arguments.
	 *
	 * @return array
	 */
	public function dp_nonce_helpers(): array {
		return [
			'POST'      => [ 'verify_post' ],
			'POST HTML' => [ 'verify_post_html' ],
			'POST data' => [ 'verify_post_data' ],
		];
	}

	/**
	 * Identity failures after a successful verification.
	 *
	 * @return array
	 */
	public function dp_invalid_identity_after_success(): array {
		$cases = [];

		foreach ( $this->dp_verification_helpers() as $label => $arguments ) {
			foreach ( $this->dp_invalid_identity() as $identity_label => $identity_arguments ) {
				$cases[ $label . ': ' . $identity_label ] = [ $arguments[0], $identity_arguments[0] ];
			}
		}

		return $cases;
	}

	/**
	 * Missing, altered, and foreign form identities.
	 *
	 * @return array
	 */
	public function dp_invalid_identity(): array {
		return [
			'missing'    => [ 'missing' ],
			'tampered'   => [ 'tampered' ],
			'other form' => [ 'other form' ],
		];
	}

	/**
	 * Signed honeypot policy values.
	 *
	 * @return array
	 */
	public function dp_signed_honeypot(): array {
		return [
			'enabled'  => [ true ],
			'disabled' => [ false ],
		];
	}

	/**
	 * Invoke a verification helper with its public arguments.
	 *
	 * @param string $helper      Verification helper.
	 * @param array  $expected_id Server's expected identity, or empty for an unbound call.
	 *
	 * @return string|null
	 */
	private function verify_with_helper( string $helper, array $expected_id = [] ): ?string {
		if ( 'verify_post' === $helper ) {
			return API::verify_post( HCAPTCHA_NONCE, HCAPTCHA_ACTION, $expected_id );
		}

		if ( 'verify_post_html' === $helper ) {
			return API::verify_post_html( HCAPTCHA_NONCE, HCAPTCHA_ACTION, $expected_id );
		}

		if ( 'verify_post_data' === $helper ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$post_data = $_POST;
			$_POST     = [];

			try {
				return API::verify_post_data( HCAPTCHA_NONCE, HCAPTCHA_ACTION, $post_data, $expected_id );
			} finally {
				$_POST = $post_data;
			}
		}

		return API::verify_request( null, [ 'expected_id' => $expected_id ] );
	}

	/**
	 * Format a public error for the HTML wrapper.
	 *
	 * @param string $helper Verification helper.
	 * @param string $code   Error code.
	 *
	 * @return string
	 */
	private function error_for_helper( string $helper, string $code ): string {
		$error = hcap_get_error_messages()[ $code ];

		return 'verify_post_html' === $helper ? '<strong>hCaptcha error:</strong> ' . $error : $error;
	}

	/**
	 * Post one of the externally observable identity failures.
	 *
	 * @param string $widget_id Missing, altered, or another signed widget id.
	 */
	private function post_invalid_identity( string $widget_id ): void {
		if ( 'missing' === $widget_id ) {
			unset( $_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] );

			return;
		}

		$id = 'tampered' === $widget_id ? self::PROTECTED_ID : self::EXEMPT_ID;

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value( $id );

		if ( 'tampered' === $widget_id ) {
			$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] .= 'tampered';
		}
	}
}
