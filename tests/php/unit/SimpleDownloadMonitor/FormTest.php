<?php
/**
 * FormTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Unit\SimpleDownloadMonitor;

use HCaptcha\SimpleDownloadMonitor\Form;
use HCaptcha\Tests\Unit\HCaptchaTestCase;
use Mockery;
use ReflectionClass;
use ReflectionException;
use tad\FunctionMocker\FunctionMocker;
use WP_Mock;
use WP_Mock\Matcher\AnyInstance;

/**
 * Test the Simple Download Monitor form.
 *
 * @group simple-download-monitor
 */
class FormTest extends HCaptchaTestCase {
	/**
	 * Test constructor and init_hooks().
	 */
	public function test_constructor_and_init_hooks(): void {
		$instance = new AnyInstance( Form::class );

		WP_Mock::expectFilterAdded( 'sdm_download_shortcode_output', [ $instance, 'add_captcha' ], 10, 2 );
		WP_Mock::expectActionAdded( 'init', [ $instance, 'verify' ], 0 );
		WP_Mock::expectActionAdded( 'wp_print_footer_scripts', [ $instance, 'enqueue_scripts' ], 9 );

		new Form();
	}

	/**
	 * Tear down the test.
	 */
	public function tearDown(): void {
		$_GET     = [];
		$_REQUEST = [];

		parent::tearDown();
	}

	/**
	 * Test add_captcha().
	 *
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_add_captcha(): void {
		$output  = '<div class="sdm_download_link">Download</div>';
		$args    = [];
		$subject = ( new ReflectionClass( Form::class ) )->newInstanceWithoutConstructor();

		FunctionMocker::replace( '\HCaptcha\Helpers\HCaptcha::get_class_source', [ 'source' ] );
		FunctionMocker::replace(
			'\HCaptcha\Helpers\HCaptcha::form',
			static function ( array $form_args ) use ( &$args ): string {
				$args = $form_args;

				return 'captcha';
			}
		);

		self::assertSame( 'captcha' . $output, $subject->add_captcha( $output, [ 'id' => 123 ] ) );
		self::assertSame(
			[
				'action' => 'hcaptcha_simple_download_monitor',
				'name'   => 'hcaptcha_simple_download_monitor_nonce',
				'id'     => [
					'source'  => [ 'source' ],
					'form_id' => 123,
				],
			],
			$args
		);
	}

	/**
	 * Test verify() without a download request.
	 *
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_verify_without_download_request(): void {
		$verify_post_data = FunctionMocker::replace( '\HCaptcha\Helpers\API::verify_post_data' );
		$subject          = ( new ReflectionClass( Form::class ) )->newInstanceWithoutConstructor();

		$subject->verify();

		$verify_post_data->wasNotCalled();
	}

	/**
	 * Test verify().
	 *
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_verify(): void {
		$get_data = [
			'sdm_process_download'                   => '1',
			'h-captcha-response'                     => 'response-token',
			'hcaptcha-widget-id'                     => 'widget-id',
			'hcaptcha_simple_download_monitor_nonce' => 'nonce',
			'hcap_hp_dynamic'                        => '',
			'hcap_hp_sig'                            => 'honeypot-signature',
			'hcap_fst_token'                         => 'fst-token',
		];
		$_GET     = $get_data;
		$_REQUEST = $get_data;
		$actual   = [];

		WP_Mock::passthruFunction( 'sanitize_text_field' );
		WP_Mock::passthruFunction( 'wp_unslash' );
		FunctionMocker::replace(
			'\HCaptcha\Helpers\API::verify_post_data',
			static function ( string $name, string $action, array $data ) use ( &$actual ): ?string {
				$actual = [ $name, $action, $data ];

				return null;
			}
		);

		$subject = ( new ReflectionClass( Form::class ) )->newInstanceWithoutConstructor();
		$subject->verify();

		self::assertSame(
			[
				'hcaptcha_simple_download_monitor_nonce',
				'hcaptcha_simple_download_monitor',
				$get_data,
			],
			$actual
		);
	}

	/**
	 * Test verify() with an error and the legacy download parameter.
	 *
	 * @throws ReflectionException Reflection exception.
	 * @noinspection PhpArrayWriteIsNotUsedInspection
	 */
	public function test_verify_with_error(): void {
		$error       = 'Verification failed.';
		$get_data    = [ 'h-captcha-response' => 'response-token' ];
		$_GET        = $get_data;
		$_REQUEST    = [ 'smd_process_download' => '1' ];
		$actual_data = [];

		WP_Mock::passthruFunction( 'sanitize_text_field' );
		WP_Mock::passthruFunction( 'wp_unslash' );
		WP_Mock::userFunction( 'esc_html' )->with( $error )->once()->andReturn( $error );
		WP_Mock::userFunction( 'wp_die' )
			->with(
				$error,
				'hCaptcha',
				[
					'back_link' => true,
					'response'  => 403,
				]
			)
			->once();
		FunctionMocker::replace(
			'\HCaptcha\Helpers\API::verify_post_data',
			static function ( string $name, string $action, array $data ) use ( $error, &$actual_data ): string {
				$actual_data = [ $name, $action, $data ];

				return $error;
			}
		);

		$subject = ( new ReflectionClass( Form::class ) )->newInstanceWithoutConstructor();
		$subject->verify();

		self::assertSame(
			[
				'hcaptcha_simple_download_monitor_nonce',
				'hcaptcha_simple_download_monitor',
				$get_data,
			],
			$actual_data
		);
	}

	/**
	 * Test enqueue_scripts().
	 *
	 * @param bool $form_shown Whether an hCaptcha form has been shown.
	 * @param int  $times      Expected enqueue count.
	 *
	 * @dataProvider dp_test_enqueue_scripts
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_enqueue_scripts( bool $form_shown, int $times ): void {
		if ( ! defined( 'HCAPTCHA_URL' ) ) {
			define( 'HCAPTCHA_URL', 'https://example.com/wp-content/plugins/hcaptcha-wordpress-plugin' );
		}

		if ( ! defined( 'HCAPTCHA_VERSION' ) ) {
			define( 'HCAPTCHA_VERSION', '1.0.0' );
		}

		$main             = Mockery::mock();
		$main->form_shown = $form_shown;

		WP_Mock::userFunction( 'hcaptcha' )->with()->andReturn( $main );
		WP_Mock::userFunction( 'hcap_min_suffix' )->with()->times( $times )->andReturn( '.min' );
		WP_Mock::userFunction( 'wp_enqueue_script' )
			->with(
				'hcaptcha-simple-download-monitor',
				HCAPTCHA_URL . '/assets/js/hcaptcha-simple-download-monitor.min.js',
				[ 'jquery' ],
				HCAPTCHA_VERSION,
				true
			)
			->times( $times );

		$subject = ( new ReflectionClass( Form::class ) )->newInstanceWithoutConstructor();
		$subject->enqueue_scripts();
	}

	/**
	 * Data provider for test_enqueue_scripts().
	 *
	 * @return array
	 */
	public function dp_test_enqueue_scripts(): array {
		return [
			'not shown' => [ false, 0 ],
			'shown'     => [ true, 1 ],
		];
	}
}
