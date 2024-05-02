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

	/**
	 * Tear down test.
	 */
	public function tearDown(): void { // phpcs:ignore PHPCompatibility.FunctionDeclarations.NewReturnTypeDeclarations.voidFound
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		unset( $_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], $_POST );

		parent::tearDown();
	}

	/**
	 * Test init() and init_hooks().
	 *
	 * @return void
	 */
	public function test_init_and_init_hooks() {
		$subject = new AutoVerify();

		WP_Mock::expectActionAdded( 'init', [ $subject, 'verify_form' ], -PHP_INT_MAX );
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
	public function test_content_filter_on_frontend() {
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
	public function test_content_filter_not_on_frontend() {
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
	public function test_widget_block_content_filter_on_frontend() {
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
	 * Test verify_form() not on frontend.
	 */
	public function test_verify_form_not_on_frontend() {
		FunctionMocker::replace(
			'\HCaptcha\Helpers\Request::is_frontend',
			false
		);

		$subject = new AutoVerify();
		$subject->verify_form();
	}

	/**
	 * Test verify_form() when not POST request.
	 */
	public function test_verify_form_when_not_post_request() {
		FunctionMocker::replace(
			'\HCaptcha\Helpers\Request::is_frontend',
			true
		);

		WP_Mock::passthruFunction( 'wp_unslash' );

		$_SERVER['REQUEST_METHOD'] = '';

		$subject = new AutoVerify();
		$subject->verify_form();
	}

	/**
	 * Test verify_form() when no path.
	 */
	public function test_verify_form_when_no_path() {
		$url  = '';
		$path = '';

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

		$subject->verify_form();
	}

	/**
	 * Test verify_form() when form is not registered.
	 */
	public function test_verify_form_when_form_is_not_registered() {
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
		$subject->shouldReceive( 'is_form_registered' )->with( $path )->once()->andReturn( false );

		$subject->verify_form();
	}

	/**
	 * Test verify_form() when the form is verified.
	 */
	public function test_verify_form_when_the_form_is_verified() {
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
		WP_Mock::userFunction( 'hcaptcha_verify_post' )->with()->once()->andReturn( null );

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['REQUEST_URI']    = $url;

		$subject = Mockery::mock( AutoVerify::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'is_form_registered' )->with( $path )->once()->andReturn( true );

		$subject->verify_form();
	}

	/**
	 * Test verify_form() when the form is not verified.
	 */
	public function test_verify_form_when_the_form_is_not_verified() {
		$url    = 'https://test.test/auto-verify?test=1';
		$result = 'Some hCaptcha error.';

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
		WP_Mock::userFunction( 'hcaptcha_verify_post' )->with()->once()->andReturn( $result );
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
		$subject->shouldReceive( 'is_form_registered' )->with( $path )->once()->andReturn( true );

		$subject->verify_form();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		self::assertSame( [], $_POST );
	}

	/**
	 * Test register_forms() with empty forms.
	 *
	 * @return void
	 */
	public function test_register_forms_with_empty_forms() {
		$subject = Mockery::mock( AutoVerify::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'update_transient' )->with( [] )->once();

		$subject->register_forms( [] );
	}

	/**
	 * Test register_forms().
	 *
	 * @return void
	 */
	public function test_register_forms() {
		$forms    = [ $this->get_test_form() ];
		$action   = '/action-page';
		$expected = [
			[
				'action' => $action,
				'inputs' => [ 'test_input' ],
				'auto'   => true,
			],
		];

		$expected_without_inputs              = $expected;
		$expected_without_inputs[0]['inputs'] = [];

		$expected_without_auto            = $expected_without_inputs;
		$expected_without_auto[0]['auto'] = false;

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

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'update_transient' )->with( [] )->once();
		$subject->shouldReceive( 'update_transient' )->with( $expected )->once();
		$subject->shouldReceive( 'update_transient' )->with( $expected_without_inputs )->once();
		$subject->shouldReceive( 'update_transient' )->with( $expected_without_auto )->once();

		$subject->register_forms( $forms );

		// Add action to form.
		$forms[0] = str_replace( '<form ', '<form action="' . $action . '" ', $forms[0] );

		$subject->register_forms( $forms );

		// Remove inputs from the form.
		$forms[0] = preg_replace( '/<input .+>/', '', $forms[0] );

		$subject->register_forms( $forms );

		// Remove auto from the form.
		$forms[0] = str_replace( 'data-auto="true"', '', $forms[0] );

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
	public function test_update_transient( $transient, array $forms_data, array $expected ) {
		$day_in_seconds = 24 * 60 * 60;

		FunctionMocker::replace(
			'constant',
			static function ( $name ) {
				return 'DAY_IN_SECONDS' === $name;
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
		$test_forms_data = [
			[
				'action' => '/autoverify',
				'inputs' => [ 'test_input' ],
				'auto'   => true,
			],
		];
		$test_transient  = [
			'/autoverify' =>
				[
					[ 'test_input' ],
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
						'auto'   => true,
					],
				],
				'expected'   => [
					'/autoverify' => [
						[ 'test_input', 'test_input2' ],
					],
				],
			],
			'Add forms with same action'       => [
				'transient'  => $test_transient,
				'forms_data' => [
					[
						'action' => '/autoverify',
						'inputs' => [ 'test_input', 'test_input2' ],
						'auto'   => true,
					],
					[
						'action' => '/autoverify',
						'inputs' => [ 'test_input3', 'test_input4' ],
						'auto'   => true,
					],
				],
				'expected'   => [
					'/autoverify' => [
						[ 'test_input' ],
						[ 'test_input', 'test_input2' ],
						[ 'test_input3', 'test_input4' ],
					],
				],
			],
			'Add forms with different actions' => [
				'transient'  => $test_transient,
				'forms_data' => [
					[
						'action' => '/autoverify',
						'inputs' => [ 'test_input', 'test_input2' ],
						'auto'   => true,
					],
					[
						'action' => '/autoverify2',
						'inputs' => [ 'test_input3', 'test_input4' ],
						'auto'   => true,
					],
				],
				'expected'   => [
					'/autoverify'  => [
						[ 'test_input' ],
						[ 'test_input', 'test_input2' ],
					],
					'/autoverify2' => [
						[ 'test_input3', 'test_input4' ],
					],
				],
			],
			'Remove form'                      => [
				'transient'  => $test_transient,
				'forms_data' => [
					[
						'action' => '/autoverify',
						'inputs' => [ 'test_input' ],
						'auto'   => false,
					],
					[
						'action' => '/autoverify2',
						'inputs' => [ 'test_input3', 'test_input4' ],
						'auto'   => true,
					],
				],
				'expected'   => [
					'/autoverify'  => [],
					'/autoverify2' => [
						[ 'test_input3', 'test_input4' ],
					],
				],
			],
		];
	}

	/**
	 * Test is_form_registered().
	 *
	 * @param mixed  $transient Transient.
	 * @param string $path      Path.
	 * @param array  $post      Post.
	 * @param bool   $expected  Expected.
	 *
	 * @dataProvider dp_test_is_form_registered
	 * @return void
	 */
	public function test_is_form_registered( $transient, string $path, array $post, bool $expected ) {
		$_POST = $post;

		WP_Mock::userFunction( 'get_transient' )
			->with( AutoVerify::TRANSIENT )
			->once()
			->andReturn( $transient );

		$subject = Mockery::mock( AutoVerify::class )->makePartial();
		$method  = 'is_form_registered';

		self::assertSame( $expected, $subject->$method( $path ) );
	}

	/**
	 * Data provider for test_is_form_registered().
	 *
	 * @return array
	 */
	public function dp_test_is_form_registered(): array {
		return [
			'Empty transient'               => [
				'transient' => false,
				'path'      => '/autoverify',
				'post'      => [],
				'expected'  => false,
			],
			'Path not in transient'         => [
				'transient' => [
					'/some' =>
						[
							[ 'test_input' ],
						],
				],
				'path'      => '/autoverify',
				'post'      => [],
				'expected'  => false,
			],
			'Path in transient, other keys' => [
				'transient' => [
					'/autoverify' =>
						[
							[ 'test_input' ],
							[ 'test_input2', 'test_input3' ],
						],
				],
				'path'      => '/autoverify',
				'post'      => [ 'test_input4' => 'some' ],
				'expected'  => false,
			],
			'Path in transient, same keys'  => [
				'transient' => [
					'/autoverify' =>
						[
							[ 'test_input' ],
							[ 'test_input2', 'test_input3' ],
						],
				],
				'path'      => '/autoverify',
				'post'      => [
					'test_input2' => 'some',
					'test_input3' => 'some',
				],
				'expected'  => true,
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
