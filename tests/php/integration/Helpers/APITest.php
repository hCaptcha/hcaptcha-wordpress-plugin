<?php
/**
 * APITest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\Helpers;

use HCaptcha\Helpers\API;
use HCaptcha\Helpers\FormSubmitTime;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use ReflectionException;
use WP_Error;

/**
 * Test API class.
 *
 * @group helpers
 * @group helpers-api
 */
class APITest extends HCaptchaWPTestCase {
	/**
	 * Test verify_post_html().
	 */
	public function test_verify_post_html(): void {
		$nonce_field_name  = 'some nonce field';
		$nonce_action_name = 'some nonce action';

		$this->prepare_verify_post_html( $nonce_field_name, $nonce_action_name );

		self::assertNull( API::verify_post_html( $nonce_field_name, $nonce_action_name ) );
	}

	/**
	 * Test verify_post_html() not verified.
	 */
	public function test_verify_post_html_not_verified(): void {
		$nonce_field_name  = 'some nonce field';
		$nonce_action_name = 'some nonce action';

		$this->prepare_verify_post_html( $nonce_field_name, $nonce_action_name, false );

		self::assertSame(
			'<strong>hCaptcha error:</strong> The hCaptcha is invalid.',
			API::verify_post_html( $nonce_field_name, $nonce_action_name )
		);
	}

	/**
	 * Test verify_post_html() not verified with empty POST.
	 */
	public function test_verify_post_html_not_verified_empty_POST(): void {
		$nonce_field_name  = 'some nonce field';
		$nonce_action_name = 'some nonce action';

		$this->prepare_verify_post_html( $nonce_field_name, $nonce_action_name, null );

		self::assertSame(
			'<strong>hCaptcha error:</strong> Please complete the hCaptcha.',
			API::verify_post_html( $nonce_field_name, $nonce_action_name )
		);
	}

	/**
	 * Test verify_post() with no argument.
	 */
	public function test_verify_post_default_success(): void {
		$hcaptcha_response = 'some response';

		$this->prepare_verify_request( $hcaptcha_response );

		self::assertNull( API::verify_post() );
	}

	/**
	 * Test verify_post() with no argument.
	 */
	public function test_verify_post_default_empty(): void {
		$this->prepare_verify_request( '', false );

		self::assertSame( 'Please complete the hCaptcha.', API::verify_post() );
	}

	/**
	 * Test verify_post() checks an empty hCaptcha response before FST.
	 */
	public function test_verify_post_empty_checks_hcaptcha_before_fst(): void {
		$settings                        = (array) get_option( 'hcaptcha_settings', [] );
		$settings['set_min_submit_time'] = 'on';
		$settings['min_submit_time']     = '0';

		update_option( 'hcaptcha_settings', $settings );

		$this->prepare_verify_request( '', false );

		remove_filter( 'hcap_verify_fst_token', '__return_true' );
		add_filter(
			'hcap_verify_fst_token',
			static function () {
				return new WP_Error( 'fst-replayed-or-expired', 'Token replayed or expired.' );
			},
			9
		);

		self::assertSame( 'Please complete the hCaptcha.', API::verify_post() );
	}
	/**
	 * Test verify_post().
	 */
	public function test_verify_post(): void {
		$nonce_field_name  = 'some nonce field';
		$nonce_action_name = 'some nonce action';

		// Not logged in.
		$this->prepare_verify_post( $nonce_field_name, $nonce_action_name );

		self::assertNull( API::verify_post( $nonce_field_name, $nonce_action_name ) );

		// Logged in.
		wp_set_current_user( 1 );

		$this->prepare_verify_post( $nonce_field_name, $nonce_action_name );

		self::assertNull( API::verify_post( $nonce_field_name, $nonce_action_name ) );
	}

	/**
	 * Test verify_post() not verified.
	 */
	public function test_verify_post_not_verified(): void {
		$nonce_field_name  = 'some nonce field';
		$nonce_action_name = 'some nonce action';

		$this->prepare_verify_post( $nonce_field_name, $nonce_action_name, false );

		self::assertSame( 'The hCaptcha is invalid.', API::verify_post( $nonce_field_name, $nonce_action_name ) );
	}

	/**
	 * Test verify_post() not verified with empty POST.
	 */
	public function test_verify_post_not_verified_empty_POST(): void {
		$nonce_field_name  = 'some nonce field';
		$nonce_action_name = 'some nonce action';

		$this->prepare_verify_post( $nonce_field_name, $nonce_action_name, null );

		self::assertSame( 'Please complete the hCaptcha.', API::verify_post( $nonce_field_name, $nonce_action_name ) );
	}

	/**
	 * Test verify_post() not verified with a logged-in user.
	 */
	public function test_verify_post_not_verified_logged_in(): void {
		$nonce_field_name  = 'some nonce field';
		$nonce_action_name = 'some nonce action';

		$_POST[ $nonce_field_name ]  = 'wrong nonce';
		$_POST['h-captcha-response'] = 'some response';

		wp_set_current_user( 1 );

		self::assertSame( 'Bad hCaptcha nonce!', API::verify_post( $nonce_field_name, $nonce_action_name ) );
	}

	/**
	 * Test verify_request().
	 */
	public function test_verify_request(): void {
		$hcaptcha_response = 'some response';

		$this->prepare_verify_request( $hcaptcha_response );

		self::assertNull( API::verify_request( $hcaptcha_response ) );
	}

	/**
	 * Test verify() with expected widget id.
	 */
	public function test_verify_with_expected_widget_id(): void {
		$nonce_field_name  = 'some nonce field';
		$nonce_action_name = 'some nonce action';
		$expected_id       = [
			'source'  => [ 'test/source' ],
			'form_id' => 'test-form',
		];

		$this->prepare_verify_post( $nonce_field_name, $nonce_action_name );

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value( $expected_id );

		self::assertNull(
			API::verify(
				[
					'nonce_name'   => $nonce_field_name,
					'nonce_action' => $nonce_action_name,
					'expected_id'  => $expected_id,
				]
			)
		);
	}

	/**
	 * Test verify() with unexpected widget id.
	 */
	public function test_verify_with_unexpected_widget_id(): void {
		$nonce_field_name  = 'some nonce field';
		$nonce_action_name = 'some nonce action';
		$expected_id       = [
			'source'  => [ 'test/source' ],
			'form_id' => 'test-form',
		];
		$actual_id         = [
			'source'  => [ 'test/source' ],
			'form_id' => 'other-form',
		];

		$filtered_expected_id = null;

		add_filter(
			'hcap_verify_request',
			static function ( $result, $deprecated, $error_info ) use ( &$filtered_expected_id ) {
				$filtered_expected_id = $error_info->expected_id;

				return $result;
			},
			10,
			3
		);

		$this->prepare_verify_post( $nonce_field_name, $nonce_action_name );

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value( $actual_id );

		self::assertSame(
			'Bad hCaptcha signature!',
			API::verify(
				[
					'nonce_name'   => $nonce_field_name,
					'nonce_action' => $nonce_action_name,
					'expected_id'  => $expected_id,
				]
			)
		);
		self::assertSame( $expected_id, $filtered_expected_id );
	}

	/**
	 * Test verify() with missing expected widget id.
	 */
	public function test_verify_with_missing_expected_widget_id(): void {
		$nonce_field_name  = 'some nonce field';
		$nonce_action_name = 'some nonce action';
		$expected_id       = [
			'source'  => [],
			'form_id' => 0,
		];

		$this->prepare_verify_post( $nonce_field_name, $nonce_action_name );

		self::assertSame(
			'Bad hCaptcha signature!',
			API::verify(
				[
					'nonce_name'   => $nonce_field_name,
					'nonce_action' => $nonce_action_name,
					'expected_id'  => $expected_id,
				]
			)
		);
	}

	/**
	 * Test verify() cleans post data after unexpected widget id.
	 */
	public function test_verify_cleans_post_data_after_unexpected_widget_id(): void {
		$nonce_field_name  = 'some nonce field';
		$nonce_action_name = 'some nonce action';
		$expected_id       = [
			'source'  => [ 'test/source' ],
			'form_id' => 'test-form',
		];
		$actual_id         = [
			'source'  => [ 'test/source' ],
			'form_id' => 'other-form',
		];
		$post_data         = [
			$nonce_field_name            => wp_create_nonce( $nonce_action_name ),
			'h-captcha-response'         => 'some response',
			HCaptcha::HCAPTCHA_WIDGET_ID => HCaptcha::widget_id_value( $actual_id ),
		];

		self::assertSame(
			'Bad hCaptcha signature!',
			API::verify(
				[
					'nonce_name'   => $nonce_field_name,
					'nonce_action' => $nonce_action_name,
					'post_data'    => $post_data,
					'expected_id'  => $expected_id,
				]
			)
		);

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		self::assertArrayNotHasKey( HCaptcha::HCAPTCHA_WIDGET_ID, $_POST );
	}

	/**
	 * Test verify_request() when protection is not enabled.
	 */
	public function test_verify_request_when_protection_not_enabled(): void {
		$hcaptcha_response = 'some response';

		add_filter( 'hcap_protect_form', '__return_false' );

		self::assertNull( API::verify_request( $hcaptcha_response ) );
	}

	/**
	 * Test verify_request() with missing keys.
	 */
	public function test_verify_request_with_missing_keys(): void {
		$request_count = 0;
		$empty_key     = static function () {
			return '';
		};

		add_filter( 'hcap_site_key', $empty_key );
		add_filter( 'hcap_secret_key', $empty_key );
		add_filter(
			'pre_http_request',
			static function ( $preempt ) use ( &$request_count ) {
				++$request_count;

				return $preempt;
			}
		);

		self::assertSame(
			'Site Key and Secret Key are required.',
			API::verify_request( 'some response' )
		);
		self::assertSame( 0, $request_count );
	}

	/**
	 * Test verify_request() with an empty string as an argument.
	 */
	public function test_verify_request_empty(): void {
		$this->prepare_verify_request( '', false );

		self::assertSame(
			'Please complete the hCaptcha.',
			API::verify_request( '' )
		);
	}

	/**
	 * Test verify_request() not verified.
	 */
	public function test_verify_request_not_verified(): void {
		$hcaptcha_response = 'some response';

		$this->prepare_verify_request( $hcaptcha_response, false );

		self::assertSame( 'The hCaptcha is invalid.', API::verify_request( $hcaptcha_response ) );
	}

	/**
	 * Test verify_request() not verified with an empty body.
	 */
	public function test_verify_request_not_verified_empty_body(): void {
		$hcaptcha_response = 'some response';

		$this->prepare_verify_request( $hcaptcha_response, null );

		self::assertSame( 'The hCaptcha is invalid.', API::verify_request( $hcaptcha_response ) );
	}
	/**
	 * Test verify_post_data().
	 */
	public function test_verify_post_data(): void {
		$hcaptcha_response = 'some response';

		$this->prepare_verify_request( $hcaptcha_response );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$post_data = $_POST;
		$_POST     = [];

		self::assertNull( API::verify_post_data( HCAPTCHA_NONCE, HCAPTCHA_ACTION, $post_data ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		self::assertArrayNotHasKey( 'h-captcha-response', $_POST );
	}

	/**
	 * Test verify() without nonce and expected widget id.
	 */
	public function test_verify_without_nonce_and_expected_widget_id(): void {
		$hcaptcha_response = 'some response';

		$this->prepare_verify_request( $hcaptcha_response );

		self::assertNull(
			API::verify(
				[
					'nonce_name'         => null,
					'nonce_action'       => null,
					'h-captcha-response' => $hcaptcha_response,
				]
			)
		);
	}

	/**
	 * Test verify_request() for a denylisted IP.
	 */
	public function test_verify_request_denylisted_ip(): void {
		add_filter( 'hcap_blacklist_ip', '__return_true' );

		self::assertSame( 'The hCaptcha is invalid.', API::verify_request( 'some response' ) );
	}

	/**
	 * Test verify_request() with a failed honeypot.
	 */
	public function test_verify_request_honeypot_failure(): void {
		$hcaptcha_response = 'some response';

		$this->prepare_verify_request( $hcaptcha_response );

		$_POST['hcap_hp_test'] = 'bot value';

		self::assertSame( 'Anti-spam check failed.', API::verify_request( $hcaptcha_response ) );

		hcaptcha()->has_result = false;
		$_POST['hcap_hp_test'] = [ 'nested' ];

		self::assertSame( 'Anti-spam check failed.', API::verify_request( $hcaptcha_response ) );
	}

	/**
	 * Test verify_request() without a Form Submit Time object.
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_verify_request_without_fst_object(): void {
		$hcaptcha_response = 'some response';
		$settings          = (array) get_option( 'hcaptcha_settings', [] );

		$settings['set_min_submit_time'] = [ 'on' ];

		update_option( 'hcaptcha_settings', $settings );
		$this->prepare_verify_request( $hcaptcha_response );

		remove_filter( 'hcap_verify_fst_token', '__return_true' );

		$loaded_classes = $this->get_protected_property( hcaptcha(), 'loaded_classes' );
		$no_fst_classes = $loaded_classes;

		unset( $no_fst_classes[ FormSubmitTime::class ] );

		$this->set_protected_property( hcaptcha(), 'loaded_classes', $no_fst_classes );

		try {
			self::assertSame( 'FST object does not exist.', API::verify_request( $hcaptcha_response ) );
		} finally {
			$this->set_protected_property( hcaptcha(), 'loaded_classes', $loaded_classes );
		}
	}

	/**
	 * Test verify_request() with a failed Form Submit Time token.
	 */
	public function test_verify_request_fst_token_failure(): void {
		$hcaptcha_response = 'some response';
		$settings          = (array) get_option( 'hcaptcha_settings', [] );

		$settings['set_min_submit_time'] = [ 'on' ];

		update_option( 'hcaptcha_settings', $settings );
		$this->prepare_verify_request( $hcaptcha_response );

		remove_filter( 'hcap_verify_fst_token', '__return_true' );
		add_filter(
			'hcap_verify_fst_token',
			static function () {
				return new WP_Error( 'fst-replayed-or-expired', 'Token replayed or expired.' );
			}
		);

		self::assertSame( 'Token replayed or expired.', API::verify_request( $hcaptcha_response ) );
	}

	/**
	 * Test verify_request() with a disposable email.
	 */
	public function test_verify_request_disposable_email_failure(): void {
		$hcaptcha_response = 'some response';
		$settings          = (array) get_option( 'hcaptcha_settings', [] );

		$settings['disposable_email'] = [ 'on' ];

		update_option( 'hcaptcha_settings', $settings );
		$this->prepare_verify_request( $hcaptcha_response );

		add_filter( 'hcap_is_disposable_email', '__return_true' );

		self::assertSame(
			'Please use a permanent email address.',
			API::verify_request(
				$hcaptcha_response,
				[
					'data' => [ 'email' => 'test@example.com' ],
				]
			)
		);
	}

	/**
	 * Test verify_request() with a remote request error.
	 */
	public function test_verify_request_remote_error(): void {
		$hcaptcha_response = 'some response';
		$hcaptcha_settings = (array) get_option( 'hcaptcha_settings', [] );
		$filter            = static function () {
			return new WP_Error( 'remote-error', 'Remote failed.' );
		};

		$_POST['h-captcha-response'] = $hcaptcha_response;
		$_POST['hcap_hp_test']       = '';
		$_POST['hcap_hp_sig']        = wp_create_nonce( 'hcap_hp_test' );

		$hcaptcha_settings['secret_key']              = 'test secret';
		$hcaptcha_settings['trusted_address_headers'] = [ 'HTTP_CLIENT_IP' ];

		update_option( 'hcaptcha_settings', $hcaptcha_settings );
		hcaptcha()->init_hooks();

		$_SERVER['HTTP_CLIENT_IP'] = '7.7.7.7';

		add_filter( 'pre_http_request', $filter );

		try {
			self::assertSame( 'Remote failed.', API::verify_request( $hcaptcha_response ) );
		} finally {
			remove_filter( 'pre_http_request', $filter );
		}
	}
}
