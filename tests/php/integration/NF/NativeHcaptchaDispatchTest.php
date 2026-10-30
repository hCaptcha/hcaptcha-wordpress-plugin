<?php
/**
 * NativeHcaptchaDispatchTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\NF;

use HCaptcha\NF\NF;
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use NF_AJAX_Controllers_Submission;
use NF_Fields_Hcaptcha;
use Ninja_Forms;
use RuntimeException;

/**
 * Test legacy native Ninja Forms hCaptcha validation through its dispatcher.
 *
 * @group nf
 */
class NativeHcaptchaDispatchTest extends HCaptchaPluginWPTestCase {

	/**
	 * Plugin relative path.
	 *
	 * @var string
	 */
	protected static $plugin = 'ninja-forms/ninja-forms.php';

	/**
	 * Hooks to replay after loading the plugin.
	 *
	 * @var string[]
	 */
	protected static array $plugin_load_hooks = [
		'plugins_loaded',
		'init',
	];

	/**
	 * Test a stored native field rejects absent and invalid proofs and accepts a valid one.
	 */
	public function test_native_field_dispatch(): void {
		new NF();
		Ninja_Forms::instance()->instantiateTranslatableObjects();

		self::assertInstanceOf( NF_Fields_Hcaptcha::class, Ninja_Forms()->fields['hcaptcha'] );

		$field      = $this->create_native_field();
		$old_secret = Ninja_Forms()->get_setting( 'hcaptcha_secret_key' );
		$calls      = 0;
		$success    = false;
		$siteverify = static function ( $response, $args, $url ) use ( &$calls, &$success ) {
			if ( 'https://api.hcaptcha.com/siteverify' !== $url ) {
				return $response;
			}

			++$calls;

			return [
				'response' => [
					'code'    => 200,
					'message' => 'OK',
				],
				'headers'  => [],
				'body'     => wp_json_encode( [ 'success' => $success ] ),
				'cookies'  => [],
			];
		};

		Ninja_Forms()->update_setting( 'hcaptcha_secret_key', 'sec010-test-secret', true );
		add_filter( 'pre_http_request', $siteverify, 10, 3 );
		$native_error = esc_html( "Please verify you're human, then submit again." );

		try {
			$missing = $this->dispatch_native_field( $field );
			self::assertTrue( $missing['rejected'] );
			self::assertSame( $native_error, $missing['errors']['fields'][ $field['id'] ] );
			self::assertSame( 0, $calls );

			$field['value'] = 'invalid-proof';
			$invalid        = $this->dispatch_native_field( $field );
			self::assertTrue( $invalid['rejected'] );
			self::assertSame( $native_error, $invalid['errors']['fields'][ $field['id'] ] );
			self::assertSame( 1, $calls );

			$success = true;
			$valid   = $this->dispatch_native_field( $field );
			self::assertFalse( $valid['rejected'] );
			self::assertSame( [], $valid['errors'] );
			self::assertSame( 2, $calls );
		} finally {
			remove_filter( 'pre_http_request', $siteverify, 10 );
			Ninja_Forms()->update_setting( 'hcaptcha_secret_key', $old_secret, true );
		}
	}

	/**
	 * Create and reload a native hCaptcha field stored on a published form.
	 *
	 * @return array
	 */
	private function create_native_field(): array {
		$form = Ninja_Forms()->form()->get();
		$form->update_setting( 'title', 'SEC010 native hCaptcha form' );
		$form->update_setting( 'publish_state', 1 );
		$form->save();

		$field = Ninja_Forms()->form( $form->get_id() )->field()->get();
		$field->update_setting( 'label', 'hCaptcha' );
		$field->update_setting( 'key', 'native_hcaptcha' );
		$field->update_setting( 'type', 'hcaptcha' );
		$field->save();

		$stored = Ninja_Forms()->form( $form->get_id() )->get_field( $field->get_id() )->get_settings();
		self::assertSame( 'hcaptcha', $stored['type'] );

		$stored['id']    = $field->get_id();
		$stored['value'] = '';

		return $stored;
	}

	/**
	 * Dispatch one field through Ninja Forms' installed submission controller.
	 *
	 * @param array $field Field settings and submitted value.
	 *
	 * @return array
	 */
	private function dispatch_native_field( array $field ): array {
		$controller = new class() extends NF_AJAX_Controllers_Submission {
			/**
			 * Run the native field dispatch.
			 *
			 * @param array $field Field.
			 */
			public function validate_test_field( array $field ): void {
				$this->validate_field( $field );
			}

			/**
			 * Return field errors captured before a response.
			 *
			 * @return array
			 */
			public function get_test_errors(): array {
				return $this->_errors;
			}

			// phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
			/**
			 * Stop without exiting the test process.
			 *
			 * @param array $data Response data.
			 *
			 * @throws RuntimeException When validation rejects the field.
			 */
			protected function _respond( $data = [] ) {
				throw new RuntimeException( 'native field rejected' );
			}
			// phpcs:enable PSR2.Methods.MethodDeclaration.Underscore
		};

		$rejected = false;

		try {
			$controller->validate_test_field( $field );
		} catch ( RuntimeException $error ) {
			self::assertSame( 'native field rejected', $error->getMessage() );
			$rejected = true;
		}

		return [
			'rejected' => $rejected,
			'errors'   => $controller->get_test_errors(),
		];
	}
}
