<?php
/**
 * HCaptchaWPTestCase class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpLanguageLevelInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Integration;

use Codeception\TestCase\WPTestCase;
use Mockery;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use tad\FunctionMocker\FunctionMocker;

/**
 * Class HCaptchaWPTestCase
 */
class HCaptchaWPTestCase extends WPTestCase {

	/**
	 * Setup test
	 */
	public function setUp(): void { // phpcs:ignore PHPCompatibility.FunctionDeclarations.NewReturnTypeDeclarations.voidFound
		FunctionMocker::setUp();
		parent::setUp();

		hcaptcha()->has_result  = false;
		$_SERVER['REQUEST_URI'] = 'http://test.test/';
	}

	/**
	 * End test
	 */
	public function tearDown(): void { // phpcs:ignore PHPCompatibility.FunctionDeclarations.NewReturnTypeDeclarations.voidFound
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		unset( $_POST, $_SERVER['REQUEST_URI'], $_SERVER['HTTP_CLIENT_IP'] );

		delete_option( 'hcaptcha_settings' );

		Mockery::close();
		parent::tearDown();
		FunctionMocker::tearDown();
	}

	/**
	 * Get an object protected property.
	 *
	 * @param object $subject       Object.
	 * @param string $property_name Property name.
	 *
	 * @return mixed
	 *
	 * @throws ReflectionException Reflection exception.
	 */
	protected function get_protected_property( $subject, string $property_name ) {
		$property = ( new ReflectionClass( $subject ) )->getProperty( $property_name );

		$property->setAccessible( true );

		$value = $property->getValue( $subject );

		$property->setAccessible( false );

		return $value;
	}

	/**
	 * Set an object protected property.
	 *
	 * @param object $subject       Object.
	 * @param string $property_name Property name.
	 * @param mixed  $value         Property vale.
	 *
	 * @throws ReflectionException Reflection exception.
	 */
	protected function set_protected_property( $subject, string $property_name, $value ) {
		$property = ( new ReflectionClass( $subject ) )->getProperty( $property_name );

		$property->setAccessible( true );
		$property->setValue( $subject, $value );
		$property->setAccessible( false );
	}

	/**
	 * Set an object protected method accessibility.
	 *
	 * @param object $subject     Object.
	 * @param string $method_name Property name.
	 * @param bool   $accessible  Property vale.
	 *
	 * @return ReflectionMethod
	 *
	 * @throws ReflectionException Reflection exception.
	 */
	protected function set_method_accessibility( $subject, string $method_name, bool $accessible = true ): ReflectionMethod {
		$method = ( new ReflectionClass( $subject ) )->getMethod( $method_name );

		$method->setAccessible( $accessible );

		return $method;
	}

	/**
	 * Return HCaptcha::form_display() content.
	 *
	 * @param string $action    Action name for wp_nonce_field.
	 * @param string $name      Nonce name for wp_nonce_field.
	 * @param bool   $auto      This form has to be auto-verified.
	 * @param string $invisible This form is invisible.
	 *
	 * @return string
	 */
	protected function get_hcap_form( string $action = '', string $name = '', bool $auto = false, $invisible = false ): string {
		$nonce_field = '';

		if ( ! empty( $action ) && ! empty( $name ) ) {
			$nonce_field = wp_nonce_field( $action, $name, true, false );
		}

		$data_size = $invisible ? 'invisible' : '';
		$data_auto = $auto ? 'true' : 'false';

		return '		<div
			class="h-captcha"
			data-sitekey=""
			data-theme=""
			data-size="' . $data_size . '"
			data-auto="' . $data_auto . '">
		</div>
		' . $nonce_field;
	}

	/**
	 * Prepare response from hcaptcha_request_verify().
	 *
	 * @param string    $hcaptcha_response hCaptcha response.
	 * @param bool|null $result            Desired result.
	 *
	 * @noinspection PhpMissingParamTypeInspection
	 */
	protected function prepare_hcaptcha_request_verify( string $hcaptcha_response, $result = true ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_POST['h-captcha-response'] ) ) {
			$_POST[ HCAPTCHA_NONCE ]     = wp_create_nonce( HCAPTCHA_ACTION );
			$_POST['h-captcha-response'] = $hcaptcha_response;
		}

		$raw_response = wp_json_encode( [ 'success' => $result ] );

		if ( null === $result ) {
			$raw_response = '';
		}

		$hcaptcha_secret_key = 'some secret key';

		update_option( 'hcaptcha_settings', [ 'secret_key' => $hcaptcha_secret_key ] );
		hcaptcha()->init_hooks();

		$ip                        = '7.7.7.7';
		$_SERVER['HTTP_CLIENT_IP'] = $ip;

		add_filter(
			'pre_http_request',
			static function ( $preempt, $parsed_args, $url ) use ( $hcaptcha_secret_key, $hcaptcha_response, $raw_response, $ip ) {
				$expected_url  =
					'https://api.hcaptcha.com/siteverify';
				$expected_body = [
					'secret'   => $hcaptcha_secret_key,
					'response' => $hcaptcha_response,
					'remoteip' => $ip,
				];

				if ( $expected_url === $url && $expected_body === $parsed_args['body'] ) {
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
	 *
	 * @noinspection PhpMissingParamTypeInspection*/
	protected function prepare_hcaptcha_verify_POST( string $nonce_field_name, string $nonce_action_name, $result = true ) {
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
	 * @param string    $nonce_field_name  Nonce field name.
	 * @param string    $nonce_action_name Nonce action name.
	 * @param bool|null $result            Desired result.
	 *
	 * @noinspection PhpMissingParamTypeInspection
	 */
	protected function prepare_hcaptcha_get_verify_message( string $nonce_field_name, string $nonce_action_name, $result = true ) {
		$this->prepare_hcaptcha_verify_POST( $nonce_field_name, $nonce_action_name, $result );
	}

	/**
	 * Prepare response from hcaptcha_get_verify_message_html().
	 *
	 * @param string    $nonce_field_name  Nonce field name.
	 * @param string    $nonce_action_name Nonce action name.
	 * @param bool|null $result            Desired result.
	 *
	 * @noinspection PhpMissingParamTypeInspection
	 */
	protected function prepare_hcaptcha_get_verify_message_html( string $nonce_field_name, string $nonce_action_name, $result = true ) {
		$this->prepare_hcaptcha_get_verify_message( $nonce_field_name, $nonce_action_name, $result );
	}
}
