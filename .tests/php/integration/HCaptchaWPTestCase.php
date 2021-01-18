<?php
/**
 * HCaptchaWPTestCase class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration;

use Codeception\TestCase\WPTestCase;
use Mockery;
use tad\FunctionMocker\FunctionMocker;

/**
 * Class HCaptchaWPTestCase
 */
class HCaptchaWPTestCase extends WPTestCase {

	/**
	 * Setup test
	 */
	public function setUp(): void {
		FunctionMocker::setUp();
		parent::setUp();
	}

	/**
	 * End test
	 */
	public function tearDown(): void {
		Mockery::close();
		parent::tearDown();
		FunctionMocker::tearDown();
	}

	/**
	 * Return hcap_form_display() content.
	 *
	 * @return string
	 */
	protected function get_hcap_form() {
		return '	<div
		class="h-captcha"
		data-sitekey=""
		data-theme=""
		data-size="">
	</div>
	';
	}

	/**
	 * Prepare response from hcaptcha_request_verify().
	 *
	 * @param string    $hcaptcha_response hCaptcha response.
	 * @param bool|null $result            Desired result.
	 */
	protected function prepare_hcaptcha_request_verify( $hcaptcha_response, $result = true ) {
		$raw_response = wp_json_encode( [ 'success' => $result ] );
		if ( null === $result ) {
			$raw_response = '';
		}

		$hcaptcha_secret_key = 'some secret key';

		update_option( 'hcaptcha_secret_key', $hcaptcha_secret_key );

		add_filter(
			'pre_http_request',
			function ( $preempt, $parsed_args, $url ) use ( $hcaptcha_secret_key, $hcaptcha_response, $raw_response ) {
				$expected_url =
					'https://hcaptcha.com/siteverify?secret=' . $hcaptcha_secret_key .
					'&response=' . $hcaptcha_response;

				if ( $expected_url === $url ) {
					return [
						'body' => $raw_response,
					];
				}

				return null;
			},
			10,
			3
		);
	}

	/**
	 * Prepare response for hcaptcha_verify_POST().
	 *
	 * @param string    $nonce_field_name  Nonce field name.
	 * @param string    $nonce_action_name Nonce action name.
	 * @param bool|null $result            Desired result.
	 */
	protected function prepare_hcaptcha_verify_POST( $nonce_field_name, $nonce_action_name, $result = true ) {
		if ( null === $result ) {
			return;
		}

		$hcaptcha_response = 'some response';

		$_POST[ $nonce_field_name ]  = wp_create_nonce( $nonce_action_name );
		$_POST['h-captcha-response'] = $hcaptcha_response;

		$this->prepare_hcaptcha_request_verify( $hcaptcha_response, $result );
	}

	/**
	 * Prepare response from hcaptcha_get_verify_message().
	 *
	 * @param string $nonce_field_name  Nonce field name.
	 * @param string $nonce_action_name Nonce action name.
	 * @param bool   $result            Desired result.
	 */
	protected function prepare_hcaptcha_get_verify_message( $nonce_field_name, $nonce_action_name, $result = true ) {
		$this->prepare_hcaptcha_verify_POST( $nonce_field_name, $nonce_action_name, $result );
	}

	/**
	 * Prepare response from hcaptcha_get_verify_message_html().
	 *
	 * @param string $nonce_field_name  Nonce field name.
	 * @param string $nonce_action_name Nonce action name.
	 * @param bool   $result            Desired result.
	 */
	protected function prepare_hcaptcha_get_verify_message_html( $nonce_field_name, $nonce_action_name, $result = true ) {
		$this->prepare_hcaptcha_get_verify_message( $nonce_field_name, $nonce_action_name, $result );
	}
}
