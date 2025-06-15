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
	 * Tear down test.
	 */
	public function tearDown(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		unset( $_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], $_POST );

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
		WP_Mock::expectFilterAdded( 'the_content', [ $subject, 'content_filter' ], PHP_INT_MAX );
		WP_Mock::expectFilterAdded(
			'widget_block_content',
			[ $subject, 'widget_block_content_filter' ],
			PHP_INT_MAX,
			3
		);
		WP_Mock::expectActionAdded( 'hcap_auto_verify_register', [ $subject, 'content_filter' ] );

		$subject->init();
	}

	/**
	 * Test content_filter() on frontend.
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
	 * Test content_filter() not on frontend.
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
	 * Test widget_block_content_filter() on frontend.
	 */
	public function test_widget_block_content_filter_on_frontend(): void {
		FunctionMocker::replace(
			'\HCaptcha\Helpers\Request::is_frontend',
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
	 * Test register_hcaptcha().
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_register_hcaptcha(): void {
		$wrong_registry = 'wrong registry';

		WP_Mock::userFunction( 'wp_json_encode' )->andReturnUsing(
			static function ( $value ) {

				// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
				return json_encode( $value );
			}
		);
		WP_Mock::passthruFunction( 'wp_hash' );

		$subject = Mockery::mock( AutoVerify::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();
		$this->set_protected_property( $subject, 'registry', $wrong_registry );

		// Args are not an array.
		$subject->register_hcaptcha( '' );

		self::assertSame( $wrong_registry, $this->get_protected_property( $subject, 'registry' ) );

		// Args are an empty array.
		$this->set_protected_property( $subject, 'registry', [] );

		$args = [];

		$subject->register_hcaptcha( $args );

		$widget_id = HCaptcha::widget_id_value( [] );
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
	public function test_enqueue_scripts( $registry, $times ): void {
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
					'successMsg' => 'The form was submitted successfully.',
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
	 * Test verify() when form is not registered.
	 */
	public function test_verify_when_form_is_not_registered(): void {
		$url = 'https://test.test/auto-verify?test=1';

		// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
		$path = parse_url( $url, PHP_URL_PATH );

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

		$action = $registered_form['args']['action'] ?? '';
		$name   = $registered_form['args']['name'] ?? '';

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
		FunctionMocker::replace( '\HCaptcha\Helpers\API::verify_post' );

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

		$action = $registered_form['args']['action'] ?? '';
		$name   = $registered_form['args']['name'] ?? '';

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
		FunctionMocker::replace( '\HCaptcha\Helpers\API::verify_post', $result );
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
				'action' => $action,
				'inputs' => [ 'test_input' ],
				'args'   => $args,
			],
		];

		$expected_without_inputs              = $expected;
		$expected_without_inputs[0]['inputs'] = [];
		$expected_without_inputs[0]['args']   = [];

		$expected_without_auto              = $expected;
		$expected_without_auto[0]['inputs'] = [];
		$expected_without_auto[0]['args']   = [];

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

		// Remove inputs from the form.
		$forms[0] = preg_replace( '/<input[\s\S]+?>/', '', $forms[0] );

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
		WP_Mock::userFunction( 'set_transient' )
			->with( AutoVerify::TRANSIENT, $expected, $day_in_seconds )
			->once();

		$subject = Mockery::mock( AutoVerify::class )->makePartial();
		$method  = 'update_transient';

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
			'Empty transient and forms_data'   => [
				'transient'  => false,
				'forms_data' => [],
				'expected'   => [],
			],
			'Empty forms_data'                 => [
				'transient'  => $test_transient,
				'forms_data' => [],
				'expected'   => $test_transient,
			],
			'Add new form'                     => [
				'transient'  => [],
				'forms_data' => $test_forms_data,
				'expected'   => $test_transient,
			],
			'Add form with multiple inputs'    => [
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
			'Add forms with same action'       => [
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
			'Add forms with different actions' => [
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
			'Remove form'                      => [
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
					'/autoverify'  => [],
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
			'Empty transient'               => [
				'transient' => false,
				'path'      => '/autoverify',
				'post'      => [],
				'expected'  => null,
			],
			'Path not in transient'         => [
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
			'Path in transient, other keys' => [
				'transient' => [
					'/autoverify' =>
						[
							[
								'inputs' => [ 'test_input' ],
								'args'   => $args,
							],
							[
								'inputs' => [ 'test_input2', 'test_input3' ],
								'args'   => $args,
							],
						],
				],
				'path'      => '/autoverify',
				'post'      => [ 'test_input4' => 'some' ],
				'expected'  => null,
			],
			'Path in transient, same keys'  => [
				'transient' => [
					'/autoverify' =>
						[
							[
								'inputs' => [ 'test_input' ],
								'args'   => $args,
							],
							[
								'inputs' => [ 'test_input2', 'test_input3' ],
								'args'   => $args,
							],
						],
				],
				'path'      => '/autoverify',
				'post'      => [
					'test_input2' => 'some',
					'test_input3' => 'some',
				],
				'expected'  => [
					'inputs' => [ 'test_input2', 'test_input3' ],
					'args'   => $args,
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
	<input type="submit" value="Send">
	<input
				type="hidden"
				class="hcaptcha-widget-id"
				name="hcaptcha-widget-id"
				value="' . self::WIDGET_ID_VALUE . '">
	<div
			class="h-captcha"
			data-sitekey="95d60c5a-68cf-4db1-a583-6a22bdd558f2"
			data-theme="light"
			data-size="normal"
			data-auto="true">
	</div>
	<input type="hidden" id="hcaptcha_nonce" name="hcaptcha_nonce" value="' . $nonce . '"/>
	<input type="hidden" name="_wp_http_referer" value="' . $request_uri . '"/>
</form>';
	}
}
