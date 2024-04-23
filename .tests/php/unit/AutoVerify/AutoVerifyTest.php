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
		unset( $_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], $_POST['test'] );

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

		$_SERVER['REQUEST_METHOD'] = 'some';

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
		WP_Mock::userFunction( 'wp_parse_url' )->with( $url, PHP_URL_PATH )->andReturnUsing(
			static function ( $url, $component ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
				return parse_url( $url, $component );
			}
		);
		WP_Mock::userFunction( 'untrailingslashit' )->with( $path )->andReturnUsing(
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
		WP_Mock::userFunction( 'wp_parse_url' )->with( $url, PHP_URL_PATH )->andReturnUsing(
			static function ( $url, $component ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
				return parse_url( $url, $component );
			}
		);
		WP_Mock::userFunction( 'untrailingslashit' )->with( $path )->andReturnUsing(
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
		WP_Mock::userFunction( 'wp_parse_url' )->with( $url, PHP_URL_PATH )->andReturnUsing(
			static function ( $url, $component ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
				return parse_url( $url, $component );
			}
		);
		WP_Mock::userFunction( 'untrailingslashit' )->with( $path )->andReturnUsing(
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
		WP_Mock::userFunction( 'wp_parse_url' )->with( $url, PHP_URL_PATH )->andReturnUsing(
			static function ( $url, $component ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
				return parse_url( $url, $component );
			}
		);
		WP_Mock::userFunction( 'untrailingslashit' )->with( $path )->andReturnUsing(
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
