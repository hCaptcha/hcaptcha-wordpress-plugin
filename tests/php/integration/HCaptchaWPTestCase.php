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
use HCaptcha\Settings\General;
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
	public function setUp(): void {
		FunctionMocker::setUp();
		parent::setUp();

		hcaptcha()->has_result = false;
		hcaptcha()->form_shown = false;

		$_SERVER['REQUEST_URI'] = 'http://test.test/';

		// Do not randomize honeypot name for tests.
		FunctionMocker::replace( '\HCaptcha\Helpers\HCaptcha::get_hp_name', 'hcap_hp_test' );

		// Do not check the Form Submit Time token for tests.
		add_filter( 'hcap_verify_fst_token', '__return_true' );

		// Set min submit time and honeypot for tests.
		add_filter(
			'hcap_form_fields',
			static function ( $form_fields, $instance ) {
				if ( $instance instanceof General ) {
					$form_fields['set_min_submit_time']['default'] = 'on';
					$form_fields['honeypot']['default']            = 'on';
				}

				return $form_fields;
			},
			10,
			2
		);
	}

	/**
	 * End test
	 */
	public function tearDown(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		unset( $_POST, $_SERVER['REQUEST_URI'], $_SERVER['HTTP_CLIENT_IP'] );

		delete_option( 'hcaptcha_settings' );
		hcaptcha()->init_hooks();

		Mockery::close();
		parent::tearDown();
		FunctionMocker::tearDown();
	}

	/**
	 * Get protected property of an object.
	 *
	 * @param object $subject       Object.
	 * @param string $property_name Property name.
	 *
	 * @return mixed
	 *
	 * @throws ReflectionException Reflection exception.
	 */
	protected function get_protected_property( object $subject, string $property_name ) {
		$property = ( new ReflectionClass( $subject ) )->getProperty( $property_name );

		$property->setAccessible( true );

		$value = $property->getValue( $subject );

		$property->setAccessible( false );

		return $value;
	}

	/**
	 * Set protected property of an object.
	 *
	 * @param object $subject       Object.
	 * @param string $property_name Property name.
	 * @param mixed  $value         Property vale.
	 *
	 * @throws ReflectionException Reflection exception.
	 */
	protected function set_protected_property( object $subject, string $property_name, $value ): void {
		$property = ( new ReflectionClass( $subject ) )->getProperty( $property_name );

		$property->setAccessible( true );
		$property->setValue( $subject, $value );
		$property->setAccessible( false );
	}

	/**
	 * Set accessibility of protected method.
	 *
	 * @param object $subject     Object.
	 * @param string $method_name Property name.
	 * @param bool   $accessible  Property vale.
	 *
	 * @return ReflectionMethod
	 *
	 * @throws ReflectionException Reflection exception.
	 */
	protected function set_method_accessibility( object $subject, string $method_name, bool $accessible = true ): ReflectionMethod {
		$method = ( new ReflectionClass( $subject ) )->getMethod( $method_name );

		$method->setAccessible( $accessible );

		return $method;
	}

	/**
	 * Return HCaptcha::get_widget() content.
	 *
	 * @param array $id The hCaptcha widget id.
	 *
	 * @return string
	 */
	protected function get_hcap_widget( array $id ): string {
		$id['source']  = (array) ( $id['source'] ?? [] );
		$id['form_id'] = $id['form_id'] ?? 0;

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$encoded_id = base64_encode( wp_json_encode( $id ) );
		$widget_id  = $encoded_id . '-' . wp_hash( $encoded_id );

		return '		<input
				type="hidden"
				class="hcaptcha-widget-id"
				name="hcaptcha-widget-id"
				value="' . $widget_id . '">';
	}

	/**
	 * Return HCaptcha::form_display() content.
	 *
	 * @param array $args Arguments.
	 *
	 * @return string
	 */
	protected function get_hcap_form( array $args = [] ): string {
		$args = $this->prepare_hcap_form_args( $args );

		$nonce_field = '';

		if ( ! empty( $args['action'] ) && ! empty( $args['name'] ) ) {
			$nonce_field = wp_nonce_field( $args['action'], $args['name'], true, false );
		}

		$hp_name  = 'hcap_hp_test';
		$hp_sig   = wp_create_nonce( $hp_name );
		$hp_field = <<<HTML
		<label for="$hp_name"></label>
		<input
				type="text" id="$hp_name" name="$hp_name" value=""
				readonly inputmode="none" autocomplete="new-password" tabindex="-1" aria-hidden="true"
				style="position:absolute; left:-9999px; top:auto; height:0; width:0; opacity:0;"/>
		<input type="hidden" name="hcap_hp_sig" value="$hp_sig"/>
		
HTML;

		return $this->get_hcap_widget( $args['id'] ) . '
				<h-captcha
			class="h-captcha"
			data-sitekey="' . $args['sitekey'] . '"
			data-theme="' . $args['theme'] . '"
			data-size="' . $args['size'] . '"
			data-auto="' . ( $args['auto'] ? 'true' : 'false' ) . '"
			data-ajax="' . ( $args['ajax'] ? 'true' : 'false' ) . '"
			data-force="' . ( $args['force'] ? 'true' : 'false' ) . '">
		</h-captcha>
		' . $nonce_field . $hp_field;
	}

	/**
	 * Prepare HCaptcha::form_display() arguments.
	 *
	 * @param array $args Arguments.
	 *
	 * @return array
	 */
	private function prepare_hcap_form_args( array $args ): array {
		$args = array_merge(
			[
				'sitekey' => '',
				'action'  => '',
				'name'    => '',
				'auto'    => false,
				'ajax'    => false,
				'force'   => false,
				'theme'   => '',
				'size'    => '',
				'id'      => [],
				'protect' => true,
			],
			$args
		);

		$args['id'] = array_merge(
			[
				'source'  => [],
				'form_id' => 0,
			],
			$args['id']
		);

		return $args;
	}

	/**
	 * Prepare response from \HCaptcha\Helpers\API::verify_request().
	 *
	 * @param string    $hcaptcha_response hCaptcha response.
	 * @param bool|null $result            Desired result.
	 *
	 * @noinspection PhpMissingParamTypeInspection
	 */
	protected function prepare_verify_request( string $hcaptcha_response, $result = true ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_POST['h-captcha-response'] ) ) {
			$_POST[ HCAPTCHA_NONCE ]     = wp_create_nonce( HCAPTCHA_ACTION );
			$_POST['h-captcha-response'] = $hcaptcha_response;
		}

		$_POST['hcap_hp_test'] = '';
		$_POST['hcap_hp_sig']  = wp_create_nonce( 'hcap_hp_test' );

		$test_token              = 'test_token';
		$_POST['hcap_fst_token'] = $test_token;

		add_filter(
			'hcap_fst_token',
			static function () use ( $test_token ) {
				return $test_token;
			}
		);

		$raw_response = wp_json_encode( [ 'success' => $result ] );

		if ( null === $result ) {
			$raw_response = '';
		}

		$hcaptcha_secret_key = 'some secret key';

		$hcaptcha_settings = (array) get_option( 'hcaptcha_settings', [] );
		$hcaptcha_settings = array_merge(
			$hcaptcha_settings,
			[ 'secret_key' => $hcaptcha_secret_key ]
		);

		update_option( 'hcaptcha_settings', $hcaptcha_settings );
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
	 * Prepare a response for \HCaptcha\Helpers\API::verify_post().
	 *
	 * @param string    $nonce_field_name  Nonce field name.
	 * @param string    $nonce_action_name Nonce action name.
	 * @param bool|null $result            Desired result.
	 *
	 * @noinspection PhpMissingParamTypeInspection
	 */
	protected function prepare_verify_post( string $nonce_field_name, string $nonce_action_name, $result = true ): void {
		$_POST['hcap_hp_test'] = '';
		$_POST['hcap_hp_sig']  = wp_create_nonce( 'hcap_hp_test' );

		if ( null === $result ) {
			return;
		}

		$hcaptcha_response = 'some response';

		$_POST[ $nonce_field_name ]  = wp_create_nonce( $nonce_action_name );
		$_POST['h-captcha-response'] = $hcaptcha_response;

		$this->prepare_verify_request( $hcaptcha_response, $result );
	}

	/**
	 * Prepare a response from \HCaptcha\Helpers\API::verify_post_html().
	 *
	 * @param string    $nonce_field_name  Nonce field name.
	 * @param string    $nonce_action_name Nonce action name.
	 * @param bool|null $result            Desired result.
	 *
	 * @noinspection PhpMissingParamTypeInspection
	 */
	protected function prepare_verify_post_html( string $nonce_field_name, string $nonce_action_name, $result = true ): void {
		$this->prepare_verify_post( $nonce_field_name, $nonce_action_name, $result );
	}

	/**
	 * Get encoded signature.
	 *
	 * @param string[]   $source         Signature source.
	 * @param int|string $form_id        Form id.
	 * @param bool       $hcaptcha_shown The hCaptcha was shown.
	 *
	 * @return string
	 */
	protected function get_encoded_signature( array $source, $form_id, bool $hcaptcha_shown ): string {
		$id = [
			'source'         => $source,
			'form_id'        => $form_id,
			'hcaptcha_shown' => $hcaptcha_shown,
		];

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$encoded_id = base64_encode( wp_json_encode( $id ) );

		return $encoded_id . '-' . wp_hash( $encoded_id );
	}
}
