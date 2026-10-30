<?php
/**
 * EmailPolicyVerificationTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\Helpers;

use HCaptcha\Helpers\API;
use HCaptcha\Helpers\EntryData;
use HCaptcha\Helpers\FormSubmitTime;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use ReflectionException;

/**
 * Test email policy independently of reusable remote challenge proof.
 *
 * @group helpers
 * @group helpers-email-policy
 */
class EmailPolicyVerificationTest extends HCaptchaWPTestCase {

	/**
	 * Number of remote challenge verification requests.
	 *
	 * @var int
	 */
	private int $remote_requests = 0;

	/**
	 * Prepare real policy checks and an observable remote response.
	 */
	public function setUp(): void {
		parent::setUp();
		wp_set_current_user( 0 );
		$this->prepare_verify_request( 'sec063-challenge' );
		hcaptcha()->settings()->set( 'disposable_email', [ 'on' ] );
		set_transient( 'hcaptcha_disposable_email_blocklist', [ 'sec063-disposable.test' => true ] );
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) {
				if ( hcaptcha()->get_verify_url() === $url ) {
					++$this->remote_requests;
				}

				return $preempt;
			},
			20,
			3
		);
	}

	/**
	 * Cached remote success must still reject the current disposable email.
	 */
	public function test_reused_challenge_rechecks_current_email(): void {
		self::assertNull( API::verify_request( null, [ 'data' => [ 'email' => 'person@example.com' ] ] ) );
		self::assertSame(
			'Please use a permanent email address.',
			API::verify_request( null, [ 'data' => [ 'email' => 'person@sec063-disposable.test' ] ] )
		);
		self::assertSame( 1, $this->remote_requests );
	}

	/**
	 * An email rejection has no remote proof to reuse for corrected data.
	 */
	public function test_corrected_email_does_not_reuse_local_rejection(): void {
		self::assertSame(
			'Please use a permanent email address.',
			API::verify_request( null, [ 'data' => [ 'email' => 'person@sec063-disposable.test' ] ] )
		);
		self::assertSame( 0, $this->remote_requests );
		self::assertNull( API::verify_request( null, [ 'data' => [ 'email' => 'person@example.com' ] ] ) );
		self::assertSame( 1, $this->remote_requests );
	}

	/**
	 * A legitimate email-free form cannot exempt a later email submission.
	 */
	public function test_email_free_result_cannot_exempt_disposable_email(): void {
		self::assertNull( API::verify_request( null, [ 'data' => [ 'message' => 'Hello' ] ] ) );
		self::assertSame(
			'Please use a permanent email address.',
			API::verify_request( null, [ 'data' => [ 'email' => 'person@sec063-disposable.test' ] ] )
		);
		self::assertSame( 1, $this->remote_requests );
	}

	/**
	 * Rechecking permanent addresses must preserve one-time challenge reuse.
	 */
	public function test_permanent_emails_reuse_remote_proof(): void {
		self::assertNull( API::verify_request( null, [ 'data' => [ 'email' => 'one@example.com' ] ] ) );
		self::assertNull( API::verify_request( null, [ 'data' => [ 'email' => 'two@example.com' ] ] ) );
		self::assertSame( 1, $this->remote_requests );
	}

	/**
	 * Every authoritative email is checked, even after a successful earlier handler.
	 */
	public function test_all_native_emails_are_checked_after_remote_success(): void {
		$allowed = 'reader@example.com';
		$blocked = 'person@sec063-disposable.test';
		self::assertNull( API::verify_request( null, EntryData::with_emails( [], [ $allowed ] ) ) );

		foreach ( [ [ $allowed, $blocked ], [ $blocked, $allowed ] ] as $emails ) {
			self::assertSame(
				'Please use a permanent email address.',
				API::verify_request( null, EntryData::with_emails( [ 'data' => [ 'email' => $allowed ] ], $emails ) )
			);
		}

		self::assertSame( 1, $this->remote_requests );
	}

	/**
	 * An explicit email-free schema overrides an ordinary field labelled email.
	 */
	public function test_email_free_schema_does_not_guess_email_fields(): void {
		self::assertNull(
			API::verify_request(
				null,
				EntryData::with_emails( [ 'data' => [ 'email' => 'text@sec063-disposable.test' ] ], [] )
			)
		);
		self::assertNull( API::verify_request( null, [ 'data' => [ 'email' => [ 'text@sec063-disposable.test' ] ] ] ) );
		self::assertSame( 1, $this->remote_requests );
	}

	/**
	 * Each wrapper carries authoritative email data while its identity argument remains binding.
	 *
	 * @param string $helper Public verification wrapper.
	 * @dataProvider dp_email_wrappers
	 */
	public function test_wrappers_carry_native_emails( string $helper ): void {
		$id                                    = [
			'source'  => [ 'WordPress' ],
			'form_id' => 'register',
		];
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value( $id );
		$entry                                 = EntryData::with_emails(
			[
				'expected_id' => [
					'source'  => [ 'foreign' ],
					'form_id' => 'exempt',
				],
			],
			[ 'person@sec063-disposable.test' ]
		);

		if ( 'data' === $helper ) {
			// API::verify_post_data() validates the nonce in the submitted transport.
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$result = API::verify_post_data( HCAPTCHA_NONCE, HCAPTCHA_ACTION, $_POST, $id, $entry );
		} elseif ( 'html' === $helper ) {
			$result = API::verify_post_html( HCAPTCHA_NONCE, HCAPTCHA_ACTION, $id, $entry );
		} else {
			$result = API::verify_post( HCAPTCHA_NONCE, HCAPTCHA_ACTION, $id, $entry );
		}

		self::assertSame(
			'html' === $helper ? '<strong>hCaptcha error:</strong> Please use a permanent email address.' : 'Please use a permanent email address.',
			$result
		);
		self::assertSame( 0, $this->remote_requests );
	}

	/**
	 * Public wrappers supporting native entry data.
	 *
	 * @return array
	 */
	public function dp_email_wrappers(): array {
		return [ [ 'post' ], [ 'html' ], [ 'data' ] ];
	}

	/**
	 * Result filters run once for each current policy decision without poisoning proof reuse.
	 */
	public function test_result_filters_observe_current_email_decision_once(): void {
		$results = [];
		add_filter(
			'hcap_verify_request',
			static function ( $result, array $codes ) use ( &$results ) {
				$results[] = $codes;

				return $result;
			},
			99999,
			2
		);
		self::assertNull( API::verify_request( null, EntryData::with_emails( [], [ 'one@example.com' ] ) ) );
		self::assertNotNull( API::verify_request( null, EntryData::with_emails( [], [ 'person@sec063-disposable.test' ] ) ) );
		self::assertNull( API::verify_request( null, EntryData::with_emails( [], [ 'two@example.com' ] ) ) );
		self::assertSame( [ [], [ 'disposable-email' ], [] ], $results );
		self::assertSame( 1, $this->remote_requests );
	}

	/**
	 * A matching trusted exemption remains exempt after an unbound remote proof check.
	 */
	public function test_matching_exemption_survives_unbound_proof_reuse(): void {
		$id                                    = [
			'source'  => [ 'WordPress' ],
			'form_id' => 'register',
		];
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value( $id );
		add_filter(
			'hcap_protect_form',
			static function ( bool $protect, array $source, $form_id ) use ( $id ): bool {
				return $source === $id['source'] && $form_id === $id['form_id'] ? false : $protect;
			},
			10,
			3
		);
		self::assertNull( API::verify_request() );
		$entry = EntryData::with_emails( [ 'expected_id' => $id ], [ 'person@sec063-disposable.test' ] );
		self::assertNull( API::verify_request( null, $entry ) );
		self::assertSame( 1, $this->remote_requests );
	}

	/**
	 * Correcting an address within a handler chain must consume a real timing token once.
	 *
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_corrected_email_preserves_consumed_timing_proof(): void {
		hcaptcha()->settings()->set( 'set_min_submit_time', [ 'on' ] );
		hcaptcha()->settings()->set( 'min_submit_time', 0 );
		remove_filter( 'hcap_verify_fst_token', '__return_true' );
		$previous                         = $this->get_protected_property( hcaptcha(), 'loaded_classes' );
		$classes                          = $previous;
		$classes[ FormSubmitTime::class ] = new FormSubmitTime();
		$this->set_protected_property( hcaptcha(), 'loaded_classes', $classes );
		$payload = [
			'post_id'   => 0,
			'issued_at' => time() - 10,
			'ttl'       => 600,
			'token_id'  => str_repeat( 's', 32 ),
		];
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$data                    = base64_encode( wp_json_encode( $payload ) );
		$signature               = wp_hash( $data );
		$_POST['hcap_fst_token'] = $data . '-' . $signature;
		set_transient( 'hcap_fst_nonce_' . $signature, $payload, 600 );

		try {
			self::assertSame(
				'Please use a permanent email address.',
				API::verify_request( null, [ 'data' => [ 'email' => 'person@sec063-disposable.test' ] ] )
			);
			self::assertFalse( get_transient( 'hcap_fst_nonce_' . $signature ) );
			self::assertSame( 0, $this->remote_requests );
			self::assertNull( API::verify_request( null, [ 'data' => [ 'email' => 'person@example.com' ] ] ) );
			self::assertSame( 1, $this->remote_requests );
		} finally {
			$this->set_protected_property( hcaptcha(), 'loaded_classes', $previous );
		}
	}
}
