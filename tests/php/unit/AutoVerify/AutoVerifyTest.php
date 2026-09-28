<?php
/**
 * AutoVerifyTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedMethodInspection */
/** @noinspection PhpLanguageLevelInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Unit\AutoVerify;

use HCaptcha\AutoVerify\AutoVerify;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Tests\Unit\HCaptchaTestCase;
use tad\FunctionMocker\FunctionMocker;
use Mockery;
use ReflectionException;
use WP_Mock;

/**
 * Test AutoVerify class.
 *
 * @group auto-verify
 */
class AutoVerifyTest extends HCaptchaTestCase {
	private const WIDGET_ID_VALUE = 'some_widget_id_value';

	/**
	 * Tear down the test.
	 */
	public function tearDown(): void {
		unset( $GLOBALS['wpdb'], $_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'] );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		foreach ( array_keys( $_POST ) as $key ) {
			unset( $_POST[ $key ] );
		}

		parent::tearDown();
	}

	/**
	 * Test init() and init_hooks().
	 *
	 * @return void
	 */
	public function test_init_and_init_hooks(): void {
		$subject = new AutoVerify();

		WP_Mock::expectActionAdded( 'init', [ $subject, 'verify' ], -PHP_INT_MAX );
		WP_Mock::expectFilterAdded( 'hcap_form_args', [ $subject, 'add_default_id' ] );
		WP_Mock::expectFilterAdded( 'the_content', [ $subject, 'content_filter' ], PHP_INT_MAX );
		WP_Mock::expectFilterAdded(
			'widget_block_content',
			[ $subject, 'widget_block_content_filter' ],
			PHP_INT_MAX,
			3
		);
		WP_Mock::expectActionAdded( 'hcap_auto_verify_register', [ $subject, 'content_filter' ] );
		WP_Mock::expectActionAdded( 'hcap_register_form', [ $subject, 'register_hcaptcha' ] );
		WP_Mock::expectActionAdded( 'wp_print_footer_scripts', [ $subject, 'enqueue_scripts' ], 9 );

		$subject->init();
	}

	/**
	 * Test add_default_id() without an auto mode.
	 */
	public function test_add_default_id_without_auto_mode(): void {
		$args = [
			'auto' => false,
		];

		$subject = new AutoVerify();

		self::assertSame( $args, $subject->add_default_id( $args ) );
	}

	/**
	 * Test add_default_id() with auto mode.
	 */
	public function test_add_default_id_with_auto_mode(): void {
		$args = [
			'auto' => true,
		];

		WP_Mock::userFunction( 'get_the_ID' )->with()->once()->andReturn( 7 );

		$subject = new AutoVerify();
		$result  = $subject->add_default_id( $args );

		self::assertSame(
			[
				'auto' => true,
				'id'   => [
					'source'  => [ AutoVerify::class ],
					'form_id' => 7,
				],
			],
			$result
		);
	}

	/**
	 * Test add_default_id() with a honeypot override.
	 */
	public function test_add_default_id_with_honeypot_override(): void {
		$args = [
			'auto'     => true,
			'honeypot' => 'false',
		];

		WP_Mock::userFunction( 'get_the_ID' )->with()->once()->andReturn( 7 );

		$subject = new AutoVerify();
		$result  = $subject->add_default_id( $args );

		self::assertSame(
			[
				'auto'     => true,
				'honeypot' => 'false',
				'id'       => [
					'source'   => [ AutoVerify::class ],
					'form_id'  => 7,
					'honeypot' => false,
				],
			],
			$result
		);
	}

	/**
	 * Test content_filter() on the frontend.
	 */
	public function test_content_filter_on_frontend(): void {
		FunctionMocker::replace(
			'\HCaptcha\Helpers\Request::is_frontend',
			true
		);

		$content = $this->get_test_content();
		$forms   = [ $this->get_test_form() ];

		$subject = Mockery::mock( AutoVerify::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'register_forms' )->with( $forms )->once();

		self::assertSame( $content, $subject->content_filter( $content ) );
	}

	/**
	 * Test content_filter() not on the frontend.
	 */
	public function test_content_filter_not_on_frontend(): void {
		FunctionMocker::replace(
			'\HCaptcha\Helpers\Request::is_frontend',
			false
		);

		$content = $this->get_test_content();

		$subject = new AutoVerify();

		self::assertSame( $content, $subject->content_filter( $content ) );
	}

	/**
	 * Test widget_block_content_filter() on the frontend.
	 */
	public function test_widget_block_content_filter_on_frontend(): void {
		FunctionMocker::replace(
			'HCaptcha\Helpers\Request::is_frontend',
			true
		);

		$content = $this->get_test_content();
		$forms   = [ $this->get_test_form() ];

		$widget  = Mockery::mock( 'WP_Widget_Block' );
		$subject = Mockery::mock( AutoVerify::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'register_forms' )->with( $forms )->once();

		self::assertSame( $content, $subject->widget_block_content_filter( $content, [], $widget ) );
	}

	/**
	 * Test widget_block_content_filter() not on the frontend.
	 */
	public function test_widget_block_content_filter_not_on_frontend(): void {
		FunctionMocker::replace(
			'HCaptcha\Helpers\Request::is_frontend',
			false
		);

		$content = $this->get_test_content();

		$widget  = Mockery::mock( 'WP_Widget_Block' );
		$subject = Mockery::mock( AutoVerify::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldNotReceive( 'register_forms' );

		self::assertSame( $content, $subject->widget_block_content_filter( $content, [], $widget ) );
	}

	/**
	 * Test register_hcaptcha().
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 * @noinspection JsonEncodingApiUsageInspection
	 */
	public function test_register_hcaptcha(): void {
		$wrong_registry = [ 'wrong registry' ];

		WP_Mock::userFunction( 'wp_json_encode' )->andReturnUsing(
			static function ( $value ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
				return json_encode( $value );
			}
		);
		WP_Mock::passthruFunction( 'wp_hash' );
		WP_Mock::userFunction( 'get_the_ID' )->andReturn( 7 );

		$subject = Mockery::mock( AutoVerify::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();
		$this->set_protected_property( $subject, 'registry', $wrong_registry );

		// Args are not an array.
		$subject->register_hcaptcha( '' );

		self::assertSame( $wrong_registry, $this->get_protected_property( $subject, 'registry' ) );

		// Args are an empty array.
		$this->set_protected_property( $subject, 'registry', [] );

		$args = [
			'id' => [
				'source'  => [ AutoVerify::class ],
				'form_id' => 7,
			],
		];

		$subject->register_hcaptcha( [] );

		$widget_id = HCaptcha::widget_id_value( $args['id'] );
		$registry  = $this->get_protected_property( $subject, 'registry' );

		self::assertSame( $args, $registry[ $widget_id ] );

		// Args have id.
		$args = [
			'id' => [
				'source'  => [ 'some_source' ],
				'form_id' => 5,
			],
		];

		$subject->register_hcaptcha( $args );

		$widget_id = HCaptcha::widget_id_value( $args['id'] );
		$registry  = $this->get_protected_property( $subject, 'registry' );

		self::assertSame( $args, $registry[ $widget_id ] );
	}

	/**
	 * Test enqueue_scripts().
	 *
	 * @param array $registry The hCaptcha forms registry..
	 * @param int   $times    Number of times to call functions.
	 *
	 * @dataProvider dp_test_enqueue_scripts
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_enqueue_scripts( array $registry, int $times ): void {
		$plugin_url     = 'http://test.test/wp-content/plugins/hcaptcha-wordpress-plugin';
		$plugin_version = '1.0.0';
		$min            = '.min';

		$subject = Mockery::mock( AutoVerify::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();

		$this->set_protected_property( $subject, 'registry', $registry );

		FunctionMocker::replace(
			'constant',
			static function ( $name ) use ( $plugin_url, $plugin_version ) {
				if ( 'HCAPTCHA_URL' === $name ) {
					return $plugin_url;
				}

				if ( 'HCAPTCHA_VERSION' === $name ) {
					return $plugin_version;
				}

				return '';
			}
		);
		WP_Mock::userFunction( 'hcap_min_suffix' )->andReturn( $min );
		WP_Mock::userFunction( '__' )->andReturnUsing(
			static function ( string $message ): string {
				return $message;
			}
		);

		WP_Mock::userFunction( 'wp_enqueue_script' )
			->with(
				AutoVerify::HANDLE,
				$plugin_url . "/assets/js/hcaptcha-auto-verify$min.js",
				[ 'jquery' ],
				$plugin_version,
				true
			)
			->times( $times );

		WP_Mock::userFunction( 'wp_localize_script' )
			->with(
				AutoVerify::HANDLE,
				AutoVerify::OBJECT,
				[
					'successMsg'      => 'The form was submitted successfully.',
					'submittingMsg'   => 'Submitting the form...',
					'errorMsg'        => 'The form could not be submitted. Please try again.',
					'networkErrorMsg' => 'Could not confirm whether the form was submitted. Check before trying again.',
				]
			)
			->times( $times );

		WP_Mock::userFunction( 'wp_enqueue_script' )
			->with( 'hcaptcha' )
			->times( $times );

		$subject->enqueue_scripts();
	}

	/**
	 * Data provider for test_enqueue_scripts().
	 *
	 * @return array
	 */
	public function dp_test_enqueue_scripts(): array {
		$registry_no_ajax                        = [
			'some widget id' =>
				[
					'action'  => 'action1',
					'name'    => 'name1',
					'auto'    => true,
					'ajax'    => false,
					'force'   => false,
					'theme'   => 'dark',
					'size'    => 'invisible',
					'id'      =>
						[
							'source'  => [],
							'form_id' => 0,
						],
					'protect' => true,
				],
		];
		$registry_ajax                           = $registry_no_ajax;
		$registry_ajax['some widget id']['ajax'] = true;

		return [
			'Empty registry'       => [ [], 0 ],
			'No ajax in registry'  => [ $registry_no_ajax, 0 ],
			'Has ajax in registry' => [ $registry_ajax, 1 ],
		];
	}

	/**
	 * Test verify() not on frontend.
	 */
	public function test_verify_not_on_frontend(): void {
		FunctionMocker::replace(
			'\HCaptcha\Helpers\Request::is_frontend',
			false
		);

		$subject = new AutoVerify();
		$subject->verify();
	}

	/**
	 * Test verify() when not POST request.
	 */
	public function test_verify_when_not_post_request(): void {
		FunctionMocker::replace(
			'\HCaptcha\Helpers\Request::is_frontend',
			true
		);

		WP_Mock::passthruFunction( 'wp_unslash' );

		$_SERVER['REQUEST_METHOD'] = '';

		$subject = new AutoVerify();
		$subject->verify();
	}

	/**
	 * Test verify() when no path.
	 */
	public function test_verify_when_no_path(): void {
		FunctionMocker::replace(
			'\HCaptcha\Helpers\Request::is_frontend',
			true
		);

		WP_Mock::passthruFunction( 'wp_unslash' );
		WP_Mock::userFunction( 'wp_parse_url' )->andReturnUsing(
			static function ( $url, $component ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
				return parse_url( $url, $component );
			}
		);
		WP_Mock::userFunction( 'untrailingslashit' )->andReturnUsing(
			static function ( $value ) {
				return rtrim( $value, '/\\' );
			}
		);

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['REQUEST_URI']    = '';

		$subject = new AutoVerify();

		$subject->verify();
	}

	/**
	 * Test verify() when the form is not registered.
	 */
	public function test_verify_when_form_is_not_registered(): void {
		$url = 'https://test.test/auto-verify?test=1';

		// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
		$path = parse_url( $url, PHP_URL_PATH );

		FunctionMocker::replace(
			'\HCaptcha\Helpers\Request::is_frontend',
			true
		);
		FunctionMocker::replace(
			'\HCaptcha\Helpers\Request::current_url',
			$url
		);

		WP_Mock::passthruFunction( 'wp_unslash' );
		WP_Mock::userFunction( 'wp_parse_url' )->andReturnUsing(
			static function ( $url, $component ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
				return parse_url( $url, $component );
			}
		);
		WP_Mock::userFunction( 'untrailingslashit' )->andReturnUsing(
			static function ( $value ) {
				return rtrim( $value, '/\\' );
			}
		);
		WP_Mock::userFunction( 'url_to_postid' )->with( $url )->once()->andReturn( 0 );

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['REQUEST_URI']    = $url;

		$subject = Mockery::mock( AutoVerify::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'get_registered_form' )->with( $path )->once()->andReturn( null );

		$subject->verify();
	}

	/**
	 * Test verify() when the form is verified.
	 */
	public function test_verify_when_the_form_is_verified(): void {
		$url = 'https://test.test/auto-verify?test=1';

		// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
		$path = parse_url( $url, PHP_URL_PATH );

		$registered_form = [
			'action' => $path,
			'inputs' => [
				'some_input',
			],
			'args'   => [
				'action' => 'hcaptcha_action',
				'name'   => 'hcaptcha_nonce',
				'auto'   => true,
			],
		];

		FunctionMocker::replace(
			'HCaptcha\Helpers\Request::is_frontend',
			true
		);

		WP_Mock::passthruFunction( 'wp_unslash' );
		WP_Mock::userFunction( 'wp_parse_url' )->andReturnUsing(
			static function ( $url, $component ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
				return parse_url( $url, $component );
			}
		);
		WP_Mock::userFunction( 'untrailingslashit' )->andReturnUsing(
			static function ( $value ) {
				return rtrim( $value, '/\\' );
			}
		);
		FunctionMocker::replace( '\HCaptcha\Helpers\API::verify' );
		FunctionMocker::replace(
			'\HCaptcha\Helpers\Request::filter_input',
			static function ( int $type, string $var_name ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
				return $_POST[ $var_name ] ?? '';
			}
		);

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['REQUEST_URI']    = $url;

		$subject = Mockery::mock( AutoVerify::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'get_registered_form' )->with( $path )->once()->andReturn( $registered_form );

		$subject->verify();
	}

	/**
	 * Test verify() when the form is not verified.
	 */
	public function test_verify_when_the_form_is_not_verified(): void {
		$url    = 'https://test.test/auto-verify?test=1';
		$result = 'Some hCaptcha error.';

		// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
		$path = parse_url( $url, PHP_URL_PATH );

		$registered_form = [
			'action' => $path,
			'inputs' => [
				'some_input',
			],
			'args'   => [
				'action' => 'hcaptcha_action',
				'name'   => 'hcaptcha_nonce',
				'auto'   => true,
				'ajax'   => true,
			],
		];

		FunctionMocker::replace(
			'\HCaptcha\Helpers\Request::is_frontend',
			true
		);

		WP_Mock::passthruFunction( 'wp_unslash' );
		WP_Mock::userFunction( 'wp_parse_url' )->andReturnUsing(
			static function ( $url, $component ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
				return parse_url( $url, $component );
			}
		);
		WP_Mock::userFunction( 'untrailingslashit' )->andReturnUsing(
			static function ( $value ) {
				return rtrim( $value, '/\\' );
			}
		);
		FunctionMocker::replace( '\HCaptcha\Helpers\API::verify', $result );
		FunctionMocker::replace(
			'\HCaptcha\Helpers\Request::filter_input',
			static function ( int $type, string $var_name ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
				return $_POST[ $var_name ] ?? '';
			}
		);
		WP_Mock::passthruFunction( 'esc_html' );
		WP_Mock::userFunction( 'wp_die' )
			->with(
				$result,
				'hCaptcha',
				[
					'back_link' => true,
					'response'  => 403,
				]
			)
			->once();
		WP_Mock::expectFilterAdded( 'wp_doing_ajax', '__return_true' );

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['REQUEST_URI']    = $url;
		$_POST['test']             = 'some';

		$subject = Mockery::mock( AutoVerify::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'get_registered_form' )->with( $path )->once()->andReturn( $registered_form );

		$subject->verify();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		self::assertSame( [], $_POST );
	}

	/**
	 * Test verify() when the form is not verified and not ajax.
	 */
	public function test_verify_when_the_form_is_not_verified_and_not_ajax(): void {
		$url    = 'https://test.test/auto-verify?test=1';
		$result = 'Some hCaptcha error.';

		// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
		$path = parse_url( $url, PHP_URL_PATH );

		$registered_form = [
			'action' => $path,
			'inputs' => [ 'some_input' ],
			'args'   => [
				'action' => 'hcaptcha_action',
				'name'   => 'hcaptcha_nonce',
				'auto'   => true,
				'ajax'   => false,
			],
		];

		FunctionMocker::replace( '\HCaptcha\Helpers\Request::is_frontend', true );
		WP_Mock::passthruFunction( 'wp_unslash' );
		WP_Mock::userFunction( 'wp_parse_url' )->andReturnUsing(
			static function ( $parsed_url, $component ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
				return parse_url( $parsed_url, $component );
			}
		);
		WP_Mock::userFunction( 'untrailingslashit' )->andReturnUsing(
			static function ( $value ) {
				return rtrim( $value, '/\\' );
			}
		);
		FunctionMocker::replace( '\HCaptcha\Helpers\API::verify', $result );
		FunctionMocker::replace(
			'\HCaptcha\Helpers\Request::filter_input',
			static function ( int $type, string $var_name ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
				return $_POST[ $var_name ] ?? '';
			}
		);
		WP_Mock::passthruFunction( 'esc_html' );
		WP_Mock::userFunction( 'wp_die' )
			->with(
				$result,
				'hCaptcha',
				[
					'back_link' => true,
					'response'  => 403,
				]
			)
			->once();

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['REQUEST_URI']    = $url;
		$_POST['test']             = 'some';

		$subject = Mockery::mock( AutoVerify::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'get_registered_form' )->with( $path )->once()->andReturn( $registered_form );

		$subject->verify();
	}

	/**
	 * Test verify() builds entry without excluded keys.
	 */
	public function test_verify_builds_entry_without_excluded_keys(): void {
		$url = 'https://test.test/auto-verify?test=1';

		// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
		$path = parse_url( $url, PHP_URL_PATH );

		$registered_form = [
			'action' => $path,
			'inputs' => [ 'field' ],
			'args'   => [
				'action' => 'hcaptcha_action',
				'name'   => 'hcaptcha_nonce',
				'auto'   => true,
			],
		];

		FunctionMocker::replace( '\HCaptcha\Helpers\Request::is_frontend', true );
		WP_Mock::passthruFunction( 'wp_unslash' );
		WP_Mock::userFunction( 'wp_parse_url' )->andReturnUsing(
			static function ( $parsed_url, $component ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
				return parse_url( $parsed_url, $component );
			}
		);
		WP_Mock::userFunction( 'untrailingslashit' )->andReturnUsing(
			static function ( $value ) {
				return rtrim( $value, '/\\' );
			}
		);
		FunctionMocker::replace(
			'\HCaptcha\Helpers\API::verify',
			static function ( array $entry ) {
				self::assertSame( 'hcaptcha_nonce', $entry['nonce_name'] );
				self::assertSame( 'hcaptcha_action', $entry['nonce_action'] );
				self::assertSame( 'response-token', $entry['h-captcha-response'] );
				self::assertSame(
					[
						'field' => 'value',
					],
					$entry['data']
				);

				return null;
			}
		);
		FunctionMocker::replace(
			'\HCaptcha\Helpers\Request::filter_input',
			static function ( int $type, string $var_name ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
				return $_POST[ $var_name ] ?? '';
			}
		);

		$_SERVER['REQUEST_METHOD']   = 'POST';
		$_SERVER['REQUEST_URI']      = $url;
		$_POST['field']              = 'value';
		$_POST['hcap_token']         = 'exclude';
		$_POST['hcaptcha-widget-id'] = 'exclude';
		$_POST['hcaptcha_nonce']     = 'exclude';
		$_POST['h-captcha-response'] = 'response-token';
		$_POST['_wp_http_referer']   = '/ref';

		$subject = Mockery::mock( AutoVerify::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'get_registered_form' )->with( $path )->once()->andReturn( $registered_form );

		$subject->verify();
	}

	/**
	 * Test register_forms() with empty forms.
	 *
	 * @return void
	 */
	public function test_register_forms_with_empty_forms(): void {
		$subject = Mockery::mock( AutoVerify::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'update_transient' )->with( [] )->once();

		$subject->register_forms( [] );
	}

	/**
	 * Test register_forms().
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_register_forms(): void {
		$forms    = [ $this->get_test_form() ];
		$args     = [
			'action' => 'hcaptcha_action',
			'name'   => 'hcaptcha_nonce',
			'auto'   => true,
		];
		$registry = [ self::WIDGET_ID_VALUE => $args ];
		$action   = '/action-page';
		$expected = [
			[
				'action'    => $action,
				'inputs'    => [ 'test_input' ],
				'widget_id' => self::WIDGET_ID_VALUE,
				'source'    => 'post:123',
				'args'      => $args,
			],
		];

		$expected_without_inputs              = $expected;
		$expected_without_inputs[0]['inputs'] = [];

		$expected_without_auto                    = $expected_without_inputs;
		$expected_without_auto[0]['args']['auto'] = false;

		WP_Mock::userFunction( 'untrailingslashit' )->andReturnUsing(
			static function ( $value ) {
				return rtrim( $value, '/\\' );
			}
		);
		WP_Mock::userFunction( 'wp_parse_url' )->andReturnUsing(
			static function ( $url, $component ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
				return parse_url( $url, $component );
			}
		);
		WP_Mock::userFunction( 'get_queried_object_id' )->andReturn( 123 );

		$subject = Mockery::mock( AutoVerify::class )->makePartial();

		$this->set_protected_property( $subject, 'registry', $registry );

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'update_transient' )->with( [] )->once(); // Case 1.
		$subject->shouldReceive( 'update_transient' )->with( $expected )->once(); // Case 2.
		$subject->shouldReceive( 'update_transient' )->with( $expected_without_inputs )->once(); // Case 3.
		$subject->shouldReceive( 'update_transient' )->with( $expected_without_auto )->once(); // Case 4.

		// Case 1. Update transient to be called with [].
		$subject->register_forms( $forms );

		// Add action to form.
		$forms[0] = str_replace( '<form ', '<form action="' . $action . '" ', $forms[0] );

		// Case 2. Update transient to be called with $expected.
		$subject->register_forms( $forms );

		// Replace the visible input with a textarea.
		$forms[0] = str_replace(
			'<input type="text" name="test_input">',
			'<textarea name="test_input"></textarea>',
			$forms[0]
		);

		// Case 3. Update transient to be called with $expected_without_inputs.
		$subject->register_forms( $forms );

		// Remove auto from the form.
		$args['auto']                      = false;
		$registry[ self::WIDGET_ID_VALUE ] = $args;

		$this->set_protected_property( $subject, 'registry', $registry );

		// Case 4. Update transient to be called with $expected_without_auto.
		$subject->shouldAllowMockingProtectedMethods();
		$subject->register_forms( $forms );
	}

	/**
	 * Test update_transient().
	 *
	 * @param mixed $transient  Transient.
	 * @param array $forms_data Forms data.
	 * @param array $expected   Expected.
	 *
	 * @return void
	 * @dataProvider dp_test_update_transient
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_update_transient( $transient, array $forms_data, array $expected ): void {
		$day_in_seconds = 24 * 60 * 60;

		FunctionMocker::replace(
			'constant',
			static function ( $name ) use ( $day_in_seconds ) {
				return 'DAY_IN_SECONDS' === $name ? $day_in_seconds : 0;
			}
		);
		WP_Mock::userFunction( 'wp_parse_args' )->andReturnUsing(
			static function ( $args, $defaults ) {
				return array_merge( $defaults, $args );
			}
		);
		WP_Mock::userFunction( 'get_transient' )
			->with( AutoVerify::TRANSIENT )
			->once()
			->andReturn( $transient );
		WP_Mock::userFunction( 'get_option' )->andReturn( false );
		WP_Mock::userFunction( 'update_option' )->andReturn( true );
		WP_Mock::userFunction( 'delete_option' )->andReturn( true );
		WP_Mock::userFunction( 'set_transient' )
			->with( AutoVerify::TRANSIENT, $expected, $day_in_seconds )
			->once();

		$subject = Mockery::mock( AutoVerify::class )->makePartial();
		$method  = 'update_transient';

		$subject->$method( $forms_data );
	}

	/**
	 * Test update_transient() limits the size and preserves recently used actions.
	 *
	 * @return void
	 */
	public function test_update_transient_limits_size_with_lru_eviction(): void {
		$day_in_seconds = 24 * 60 * 60;
		$args           = [
			'action' => 'hcaptcha_action',
			'name'   => 'hcaptcha_nonce',
			'auto'   => true,
		];
		$action_forms   = [
			[
				'inputs' => [ 'test_input' ],
				'args'   => $args,
			],
		];
		$transient      = [
			'/oldest' => $action_forms,
			'/middle' => $action_forms,
			'/newest' => $action_forms,
		];
		$forms_data     = [
			[
				'action' => '/oldest',
				'inputs' => [ 'test_input' ],
				'args'   => $args,
			],
		];
		$expected       = [
			'/newest' => $action_forms,
			'/oldest' => $action_forms,
		];

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		$max_size = strlen( serialize( $expected ) );

		FunctionMocker::replace(
			'constant',
			static function ( $name ) use ( $day_in_seconds ) {
				return 'DAY_IN_SECONDS' === $name ? $day_in_seconds : 0;
			}
		);
		WP_Mock::userFunction( 'wp_parse_args' )->andReturnUsing(
			static function ( $values, $defaults ) {
				return array_merge( $defaults, $values );
			}
		);
		WP_Mock::userFunction( 'get_transient' )
			->with( AutoVerify::TRANSIENT )
			->once()
			->andReturn( $transient );
		WP_Mock::userFunction( 'get_option' )->andReturn( false );
		WP_Mock::userFunction( 'update_option' )->andReturn( true );
		WP_Mock::onFilter( 'hcap_auto_verify_transient_max_size' )
			->with( AutoVerify::MAX_TRANSIENT_SIZE )
			->reply( $max_size );
		WP_Mock::userFunction( 'set_transient' )
			->with( AutoVerify::TRANSIENT, $expected, $day_in_seconds )
			->once();

		$subject = Mockery::mock( AutoVerify::class )->makePartial();
		$method  = 'update_transient';

		$subject->shouldAllowMockingProtectedMethods();
		$subject->$method( $forms_data );
	}

	/**
	 * Data provider for test_update_transient().
	 *
	 * @return array
	 */
	public function dp_test_update_transient(): array {
		$args            = [
			'action' => 'hcaptcha_action',
			'name'   => 'hcaptcha_nonce',
			'auto'   => true,
		];
		$test_forms_data = [
			[
				'action' => '/autoverify',
				'inputs' => [ 'test_input' ],
				'args'   => $args,
			],
		];
		$test_transient  = [
			'/autoverify' =>
				[
					[
						'inputs' => [ 'test_input' ],
						'args'   => $args,
					],
				],
		];

		return [
			'Empty transient and forms_data'        => [
				'transient'  => false,
				'forms_data' => [],
				'expected'   => [],
			],
			'Empty forms_data'                      => [
				'transient'  => $test_transient,
				'forms_data' => [],
				'expected'   => $test_transient,
			],
			'Add new form'                          => [
				'transient'  => [],
				'forms_data' => $test_forms_data,
				'expected'   => $test_transient,
			],
			'Add form with multiple inputs'         => [
				'transient'  => [],
				'forms_data' => [
					[
						'action' => '/autoverify',
						'inputs' => [ 'test_input', 'test_input2' ],
						'args'   => $args,
					],
				],
				'expected'   => [
					'/autoverify' => [
						[
							'inputs' => [ 'test_input', 'test_input2' ],
							'args'   => $args,
						],
					],
				],
			],
			'Add forms with same action'            => [
				'transient'  => $test_transient,
				'forms_data' => [
					[
						'action' => '/autoverify',
						'inputs' => [ 'test_input' ],
						'args'   => $args,
					],
					[
						'action' => '/autoverify',
						'inputs' => [ 'test_input1', 'test_input2' ],
						'args'   => $args,
					],
					[
						'action' => '/autoverify',
						'inputs' => [ 'test_input3', 'test_input4' ],
						'args'   => $args,
					],
				],
				'expected'   => [
					'/autoverify' => [
						[
							'inputs' => [ 'test_input' ],
							'args'   => $args,
						],
						[
							'inputs' => [ 'test_input1', 'test_input2' ],
							'args'   => $args,
						],
						[
							'inputs' => [ 'test_input3', 'test_input4' ],
							'args'   => $args,
						],
					],
				],
			],
			'Preserve forms with different widgets' => [
				'transient'  => [],
				'forms_data' => [
					[
						'action'    => '/autoverify',
						'inputs'    => [ 'test_input' ],
						'widget_id' => 'first-widget-id',
						'args'      => $args,
					],
					[
						'action'    => '/autoverify',
						'inputs'    => [ 'test_input' ],
						'widget_id' => 'second-widget-id',
						'args'      => $args,
					],
				],
				'expected'   => [
					'/autoverify' => [
						[
							'inputs'    => [ 'test_input' ],
							'args'      => $args,
							'widget_id' => 'first-widget-id',
						],
						[
							'inputs'    => [ 'test_input' ],
							'args'      => $args,
							'widget_id' => 'second-widget-id',
						],
					],
				],
			],
			'Add forms with different actions'      => [
				'transient'  => $test_transient,
				'forms_data' => [
					[
						'action' => '/autoverify',
						'inputs' => [ 'test_input', 'test_input2' ],
						'args'   => $args,
					],
					[
						'action' => '/autoverify2',
						'inputs' => [ 'test_input3', 'test_input4' ],
						'args'   => $args,
					],
				],
				'expected'   => [
					'/autoverify'  => [
						[
							'inputs' => [ 'test_input' ],
							'args'   => $args,
						],
						[
							'inputs' => [ 'test_input', 'test_input2' ],
							'args'   => $args,
						],
					],
					'/autoverify2' => [
						[
							'inputs' => [ 'test_input3', 'test_input4' ],
							'args'   => $args,
						],
					],
				],
			],
			'Remove form'                           => [
				'transient'  => $test_transient,
				'forms_data' => [
					[
						'action' => '/autoverify',
						'inputs' => [ 'test_input' ],
						'args'   => [ 'auto' => false ],
					],
					[
						'action' => '/autoverify2',
						'inputs' => [ 'test_input3', 'test_input4' ],
						'args'   => $args,
					],
				],
				'expected'   => [
					'/autoverify2' => [
						[
							'inputs' => [ 'test_input3', 'test_input4' ],
							'args'   => $args,
						],
					],
				],
			],
		];
	}

	/**
	 * Test get_registered_form().
	 *
	 * @param mixed      $transient Transient.
	 * @param string     $path      Path.
	 * @param array      $post      Post.
	 * @param array|null $expected  Expected.
	 *
	 * @dataProvider dp_test_get_registered_form
	 * @return void
	 */
	public function test_get_registered_form( $transient, string $path, array $post, ?array $expected ): void {
		$_POST = $post;

		WP_Mock::userFunction( 'get_transient' )
			->with( AutoVerify::TRANSIENT )
			->once()
			->andReturn( $transient );
		WP_Mock::userFunction( 'get_option' )->andReturn( false );
		FunctionMocker::replace(
			'\HCaptcha\Helpers\Request::filter_input',
			static function ( int $type, string $var_name ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
				return $_POST[ $var_name ] ?? '';
			}
		);

		$subject = Mockery::mock( AutoVerify::class )->makePartial();
		$method  = 'get_registered_form';

		self::assertSame( $expected, $subject->$method( $path ) );
	}

	/**
	 * Data provider for test_get_registered_form().
	 *
	 * @return array
	 */
	public function dp_test_get_registered_form(): array {
		$args = [
			'action' => 'hcaptcha_action',
			'name'   => 'hcaptcha_nonce',
			'auto'   => true,
		];

		return [
			'Empty transient'                     => [
				'transient' => false,
				'path'      => '/autoverify',
				'post'      => [],
				'expected'  => null,
			],
			'Path not in transient'               => [
				'transient' => [
					'/some' =>
						[
							[
								'inputs' => [ 'test_input' ],
								'args'   => $args,
							],
						],
				],
				'path'      => '/autoverify',
				'post'      => [],
				'expected'  => null,
			],
			'Unregistered structure fails closed' => [
				'transient' => [
					'/autoverify' =>
						[
							[
								'inputs'    => [ 'test_input' ],
								'widget_id' => 'first-widget-id',
								'args'      => $args,
							],
							[
								'inputs'    => [ 'test_input2', 'test_input3' ],
								'widget_id' => 'second-widget-id',
								'args'      => $args,
							],
						],
				],
				'path'      => '/autoverify',
				'post'      => [ 'test_input4' => 'some' ],
				'expected'  => [],
			],
			'Registered input omitted'            => [
				'transient' => [
					'/autoverify' =>
						[
							[
								'inputs'    => [ 'test_input' ],
								'widget_id' => 'first-widget-id',
								'args'      => $args,
							],
							[
								'inputs'    => [ 'test_input2', 'test_input3' ],
								'widget_id' => 'second-widget-id',
								'args'      => $args,
							],
						],
				],
				'path'      => '/autoverify',
				'post'      => [
					HCaptcha::HCAPTCHA_WIDGET_ID => 'second-widget-id',
					'test_input2'                => 'some',
				],
				'expected'  => [],
			],
			'Form without visible inputs'         => [
				'transient' => [
					'/autoverify' => [
						[
							'inputs'    => [],
							'widget_id' => 'textarea-widget-id',
							'args'      => $args,
						],
					],
				],
				'path'      => '/autoverify',
				'post'      => [ HCaptcha::HCAPTCHA_WIDGET_ID => 'textarea-widget-id' ],
				'expected'  => [
					'inputs'    => [],
					'widget_id' => 'textarea-widget-id',
					'args'      => $args,
				],
			],
			'Widget selects longer form'          => [
				'transient' => [
					'/autoverify' => [
						[
							'inputs'    => [ 'email', 'name' ],
							'widget_id' => 'first-widget-id',
							'args'      => $args,
						],
						[
							'inputs'    => [ 'email', 'name', 'company' ],
							'widget_id' => 'second-widget-id',
							'args'      => $args,
						],
					],
				],
				'path'      => '/autoverify',
				'post'      => [
					HCaptcha::HCAPTCHA_WIDGET_ID => 'second-widget-id',
					'email'                      => 'user@example.com',
					'name'                       => 'User',
					'company'                    => 'Example',
				],
				'expected'  => [
					'inputs'    => [ 'email', 'name', 'company' ],
					'widget_id' => 'second-widget-id',
					'args'      => $args,
				],
			],
		];
	}

	/**
	 * Get test request URI.
	 *
	 * @return string
	 */
	private function get_test_request_uri(): string {
		return '/hcaptcha-arbitrary-form/';
	}

	/**
	 * Get test nonce.
	 *
	 * @return string
	 */
	private function get_test_nonce(): string {
		return '5e9f1e63ed';
	}

	/**
	 * Get test content.
	 *
	 * @return string
	 */
	private function get_test_content(): string {
		return '
' . $this->get_test_form() . '

<form role="search" method="get" action="http://test.test/"
	  class="wp-block-search__button-outside wp-block-search__text-button wp-block-search">
	<label for="wp-block-search__input-1" class="wp-block-search__label">Search</label>
	<div class="wp-block-search__inside-wrapper">
		<input type="search" id="wp-block-search__input-1"
			   class="wp-block-search__input" name="s" value="" placeholder=""
			   required/>
		<button type="submit" class="wp-block-search__button ">Search</button>
	</div>
</form>
';
	}

	/**
	 * Get a test form.
	 *
	 * @return string
	 */
	private function get_test_form(): string {
		$request_uri = $this->get_test_request_uri();
		$nonce       = $this->get_test_nonce();

		return '<form method="post">
	<input type="text" name="test_input">
	<input type="checkbox" name="optional_checkbox">
	<input type="text" name="hcap_hp_random">
	<input type="text" name="disabled_input" disabled>
	<input type="submit" value="Send">
	<input
				type="hidden"
				class="hcaptcha-widget-id"
				name="hcaptcha-widget-id"
				value="' . self::WIDGET_ID_VALUE . '">
	<div
			class="h-captcha"
			data-sitekey="some key"
			data-theme="light"
			data-size="normal"
			data-auto="true">
	</div>
	<input type="hidden" id="hcaptcha_nonce" name="hcaptcha_nonce" value="' . $nonce . '"/>
	<input type="hidden" name="_wp_http_referer" value="' . $request_uri . '"/>
</form>';
	}

	/**
	 * Call a private AutoVerify method.
	 *
	 * @param AutoVerify $subject Subject.
	 * @param string     $name    Method name.
	 * @param array      $args    Arguments.
	 *
	 * @return mixed
	 * @throws ReflectionException Reflection exception.
	 */
	private function call_private( AutoVerify $subject, string $name, array $args = [] ) {
		return $this->set_method_accessibility( $subject, $name )->invokeArgs( $subject, $args );
	}

	/**
	 * Test deleting persistent registrations and the transient.
	 */
	public function test_delete_all(): void {
		global $wpdb;

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$wpdb          = Mockery::mock( 'wpdb' );
		$wpdb->options = 'wp_options';
		$wpdb->shouldReceive( 'esc_like' )
			->once()
			->with( 'hcaptcha_auto_verify_form_' )
			->andReturn( 'escaped-prefix' );
		$wpdb->shouldReceive( 'prepare' )
			->once()
			->with( 'SELECT option_name FROM wp_options WHERE option_name LIKE %s', 'escaped-prefix%' )
			->andReturn( 'prepared-query' );
		$wpdb->shouldReceive( 'get_col' )
			->once()
			->with( 'prepared-query' )
			->andReturn( [ 'first-option', 'second-option' ] );
		WP_Mock::userFunction( 'delete_option' )->with( 'first-option' )->once();
		WP_Mock::userFunction( 'delete_option' )->with( 'second-option' )->once();
		WP_Mock::userFunction( 'delete_transient' )->with( AutoVerify::TRANSIENT )->once();

		AutoVerify::delete_all();
	}

	/**
	 * Mock WordPress URL handling for canonical path tests.
	 */
	private function mock_url_helpers(): void {
		WP_Mock::userFunction( 'wp_parse_url' )
			->andReturnUsing(
				static function ( $url, $component ) {
					// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
					return parse_url( $url, $component );
				}
			);
		WP_Mock::userFunction( 'untrailingslashit' )
			->andReturnUsing(
				static function ( $path ) {
					return rtrim( $path, '/' );
				}
			);
	}

	/**
	 * Find a registered form through the canonical page permalink.
	 *
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_registered_form_uses_canonical_path(): void {
		$_SERVER['REQUEST_URI'] = '/raw-path/';
		$this->mock_url_helpers();
		WP_Mock::passthruFunction( 'wp_unslash' );
		FunctionMocker::replace( '\HCaptcha\Helpers\Request::current_url', 'https://test.test/raw-path/' );
		FunctionMocker::replace(
			'\HCaptcha\Helpers\Request::filter_input',
			static function ( $type ) {
				return INPUT_GET === $type ? 'page-slug' : 'widget-id';
			}
		);
		WP_Mock::userFunction( 'url_to_postid' )->with( 'https://test.test/raw-path/' )->once()->andReturn( 0 );
		WP_Mock::userFunction( 'get_page_by_path' )->with( 'page-slug' )->once()->andReturn( (object) [ 'ID' => 123 ] );
		WP_Mock::userFunction( 'get_permalink' )->with( 123 )->once()->andReturn( 'https://test.test/canonical/' );

		$form = [
			'args'      => [ 'auto' => true ],
			'inputs'    => [],
			'widget_id' => 'widget-id',
		];

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = 'widget-id';
		WP_Mock::userFunction( 'get_option' )
			->with( 'hcaptcha_auto_verify_form_' . hash( 'sha256', '/raw-path' ), false )
			->once()
			->andReturn( false );
		WP_Mock::userFunction( 'get_option' )
			->with( 'hcaptcha_auto_verify_form_' . hash( 'sha256', '/canonical' ), false )
			->once()
			->andReturn( [ $form ] );
		WP_Mock::userFunction( 'get_transient' )->with( AutoVerify::TRANSIENT )->once()->andReturn( false );

		self::assertSame( $form, $this->call_private( new AutoVerify(), 'get_registered_form_for_request' ) );
	}

	/**
	 * Ignore a canonical page with an invalid permalink.
	 *
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_canonical_path_rejects_non_string_permalink(): void {
		FunctionMocker::replace( '\HCaptcha\Helpers\Request::current_url', 'https://test.test/page/' );
		WP_Mock::userFunction( 'url_to_postid' )->with( 'https://test.test/page/' )->once()->andReturn( 123 );
		WP_Mock::userFunction( 'get_permalink' )->with( 123 )->once()->andReturn( false );

		self::assertSame( '', $this->call_private( new AutoVerify(), 'get_canonical_request_path' ) );
	}

	/**
	 * Reject an empty registered form with the bad-signature error.
	 *
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_verify_submission_rejects_empty_registration(): void {
		WP_Mock::userFunction( 'hcap_get_error_messages' )
			->once()
			->andReturn( [ 'bad-signature' => 'Bad signature' ] );
		FunctionMocker::replace(
			'\HCaptcha\Helpers\API::filtered_result',
			static function ( $message, $codes ) {
				self::assertSame( 'Bad signature', $message );
				self::assertSame( [ 'bad-signature' ], $codes );

				return 'Rejected';
			}
		);

		self::assertSame( 'Rejected', $this->call_private( new AutoVerify(), 'verify_submission', [ [] ] ) );
	}

	/**
	 * Normalize a protocol-relative URL as a path.
	 *
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_get_path_normalizes_protocol_relative_url(): void {
		$this->mock_url_helpers();

		self::assertSame( '/example', $this->call_private( new AutoVerify(), 'get_path', [ '//example/' ] ) );
	}

	/**
	 * Handle forms without input fields and inputs without a type or name.
	 *
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_visible_input_names_with_missing_attributes(): void {
		$subject = new AutoVerify();

		self::assertSame( [], $this->call_private( $subject, 'get_visible_input_names', [ '<form></form>' ] ) );
		self::assertSame(
			[ 'email' ],
			$this->call_private( $subject, 'get_visible_input_names', [ '<input name="email"><input type="text">' ] )
		);
	}

	/**
	 * Keep registrations that do not match the current source.
	 *
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_remove_form_registration_preserves_other_source(): void {
		$subject          = new AutoVerify();
		$registered_forms = [ '/form' => [ [ 'source' => 'post:1' ] ] ];
		$action_forms     = $registered_forms['/form'];
		$method           = $this->set_method_accessibility( $subject, 'remove_form_registration' );

		$method->invokeArgs( $subject, [ &$registered_forms, [ 'source' => 'post:2' ], '/form', $action_forms, 0 ] );

		self::assertSame( [ '/form' => [ [ 'source' => 'post:1' ] ] ], $registered_forms );
	}

	/**
	 * Persist remaining registrations after one is removed.
	 *
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_remove_form_registration_updates_remaining_forms(): void {
		$subject          = new AutoVerify();
		$registered_forms = [ '/form' => [ [ 'source' => 'post:1' ], [ 'source' => 'post:2' ] ] ];
		$action_forms     = $registered_forms['/form'];
		$option_name      = 'hcaptcha_auto_verify_form_' . hash( 'sha256', '/form' );
		WP_Mock::userFunction( 'update_option' )
			->with( $option_name, [ [ 'source' => 'post:2' ] ], false )
			->once();

		$method = $this->set_method_accessibility( $subject, 'remove_form_registration' );
		$method->invokeArgs( $subject, [ &$registered_forms, [ 'source' => 'post:1' ], '/form', $action_forms, 0 ] );

		self::assertSame( [ '/form' => [ [ 'source' => 'post:2' ] ] ], $registered_forms );
	}
}
