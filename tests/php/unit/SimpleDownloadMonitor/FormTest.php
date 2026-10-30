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
		WP_Mock::expectActionAdded( 'sdm_download_via_direct_post', [ $instance, 'verify' ], 0 );
		WP_Mock::expectActionAdded( 'wp_print_footer_scripts', [ $instance, 'enqueue_scripts' ], 9 );
		WP_Mock::expectFilterAdded( 'script_loader_tag', [ $instance, 'add_type_module' ], 10, 3 );

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

		WP_Mock::userFunction( 'absint' )->with( '00123' )->andReturn( 123 );
		FunctionMocker::replace( '\HCaptcha\Helpers\HCaptcha::get_class_source', [ 'source' ] );
		FunctionMocker::replace(
			'\HCaptcha\Helpers\HCaptcha::form',
			static function ( array $form_args ) use ( &$args ): string {
				$args = $form_args;

				return 'captcha';
			}
		);

		self::assertSame( 'captcha' . $output, $subject->add_captcha( $output, [ 'id' => '00123' ] ) );
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
	 * Test verify().
	 *
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_verify(): void {
		$get_data = [
			'sdm_process_download'                   => '1.0',
			'download_id'                            => '00123',
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
		WP_Mock::userFunction( 'absint' )->with( '00123' )->andReturn( 123 );
		FunctionMocker::replace( '\HCaptcha\Helpers\HCaptcha::get_class_source', [ 'source' ] );
		FunctionMocker::replace(
			'\HCaptcha\Helpers\API::verify_post_data',
			static function ( string $name, string $action, array $data, array $expected_id ) use ( &$actual ): ?string {
				$actual = [ $name, $action, $data, $expected_id ];

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
				[
					'source'  => [ 'source' ],
					'form_id' => 123,
				],
			],
			$actual
		);
	}

	/**
	 * Missing download IDs still carry a nonempty expected widget identity.
	 *
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_verify_without_download_id_checks_widget_identity(): void {
		$_GET     = [];
		$_REQUEST = [];
		$actual   = [];

		FunctionMocker::replace( '\HCaptcha\Helpers\HCaptcha::get_class_source', [ 'source' ] );
		FunctionMocker::replace(
			'\HCaptcha\Helpers\API::verify_post_data',
			static function ( string $name, string $action, array $data, array $expected_id ) use ( &$actual ): ?string {
				$actual = $expected_id;

				return null;
			}
		);

		$subject = ( new ReflectionClass( Form::class ) )->newInstanceWithoutConstructor();
		$subject->verify();

		self::assertSame(
			[
				'source'  => [ 'source' ],
				'form_id' => 0,
			],
			$actual
		);
	}

	/**
	 * Bind verification to the native request target when it differs from GET.
	 *
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_verify_uses_native_request_target(): void {
		$_GET = [
			'sdm_process_download' => '1',
			'download_id'          => '00123',
			'hcaptcha-widget-id'   => 'widget-for-download-123',
		];
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$_REQUEST = array_merge( $_GET, [ 'download_id' => '00456' ] );
		$actual   = [];

		WP_Mock::passthruFunction( 'sanitize_text_field' );
		WP_Mock::passthruFunction( 'wp_unslash' );
		WP_Mock::userFunction( 'absint' )->with( '00456' )->andReturn( 456 );
		FunctionMocker::replace( '\HCaptcha\Helpers\HCaptcha::get_class_source', [ 'source' ] );
		FunctionMocker::replace(
			'\HCaptcha\Helpers\API::verify_post_data',
			static function ( string $name, string $action, array $data, array $expected_id ) use ( &$actual ): ?string {
				$actual = [ $data, $expected_id ];

				return null;
			}
		);

		$subject = ( new ReflectionClass( Form::class ) )->newInstanceWithoutConstructor();
		$subject->verify();

		self::assertSame(
			[
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$_GET,
				[
					'source'  => [ 'source' ],
					'form_id' => 456,
				],
			],
			$actual
		);
	}

	/**
	 * Bind malformed array IDs to the same numeric ID as the native handler.
	 *
	 * @throws ReflectionException Reflection exception.
	 * @noinspection PhpArrayWriteIsNotUsedInspection
	 */
	public function test_verify_binds_array_target_to_native_id(): void {
		$_GET = [
			'sdm_process_download' => '1.0',
			'download_id'          => '00456',
		];
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$_REQUEST = [
			'sdm_process_download' => '1.0',
			'download_id'          => [ '00123' ],
		];
		$actual   = [];

		WP_Mock::userFunction( 'absint' )->with( [ '00123' ] )->andReturn( 1 );
		FunctionMocker::replace( '\HCaptcha\Helpers\HCaptcha::get_class_source', [ 'source' ] );
		FunctionMocker::replace(
			'\HCaptcha\Helpers\API::verify_post_data',
			static function ( string $name, string $action, array $data, array $expected_id ) use ( &$actual ): ?string {
				$actual = $expected_id;

				return null;
			}
		);

		$subject = ( new ReflectionClass( Form::class ) )->newInstanceWithoutConstructor();
		$subject->verify();

		self::assertSame(
			[
				'source'  => [ 'source' ],
				'form_id' => 1,
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
		$_REQUEST    = [ 'smd_process_download' => '1.0' ];
		$actual_data = [];

		WP_Mock::passthruFunction( 'sanitize_text_field' );
		WP_Mock::passthruFunction( 'wp_unslash' );
		FunctionMocker::replace( '\HCaptcha\Helpers\HCaptcha::get_class_source', [ 'source' ] );
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
			static function ( string $name, string $action, array $data, array $expected_id ) use ( $error, &$actual_data ): string {
				$actual_data = [ $name, $action, $data, $expected_id ];

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
				[
					'source'  => [ 'source' ],
					'form_id' => 0,
				],
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

	/**
	 * The source script needs module loading, while the bundled script stays classic.
	 *
	 * @noinspection JSUnresolvedLibraryURL
	 */
	public function test_add_type_module(): void {
		$subject = ( new ReflectionClass( Form::class ) )->newInstanceWithoutConstructor();
		// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- This is a script tag fixture.
		$tag = '<script src="https://example.com/hcaptcha-simple-download-monitor.js"></script>';

		WP_Mock::userFunction( 'hcap_min_suffix' )->with()->andReturn( '' );

		self::assertSame(
			// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- This is the expected script tag.
			'<script type="module" src="https://example.com/hcaptcha-simple-download-monitor.js"></script>',
			$subject->add_type_module( $tag, 'hcaptcha-simple-download-monitor', '' )
		);
		self::assertSame( $tag, $subject->add_type_module( $tag, 'other', '' ) );
	}

	/**
	 * The bundled script does not need module loading.
	 *
	 * @noinspection JSUnresolvedLibraryURL
	 */
	public function test_add_type_module_minified(): void {
		$subject = ( new ReflectionClass( Form::class ) )->newInstanceWithoutConstructor();
		// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- This is a script tag fixture.
		$tag = '<script type="module" src="https://example.com/hcaptcha-simple-download-monitor.min.js"></script>';

		WP_Mock::userFunction( 'hcap_min_suffix' )->with()->andReturn( '.min' );

		self::assertSame( $tag, $subject->add_type_module( $tag, 'hcaptcha-simple-download-monitor', '' ) );
	}
}
