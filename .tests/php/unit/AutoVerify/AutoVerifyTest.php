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
		unset( $_GET['rest_route'] );

		parent::tearDown();
	}

	/**
	 * Test content_filter() in CLI.
	 */
	public function test_content_filter_in_cli() {
		FunctionMocker::replace(
			'defined',
			static function ( $constant_name ) {
				return 'WP_CLI' === $constant_name;
			}
		);

		FunctionMocker::replace(
			'constant',
			static function ( $name ) {
				return 'WP_CLI' === $name;
			}
		);

		$content = $this->get_test_content();

		$subject = new AutoVerify();

		self::assertSame( $content, $subject->content_filter( $content ) );
	}

	/**
	 * Test verify_form() in CLI.
	 */
	public function test_verify_form_in_cli() {
		FunctionMocker::replace(
			'defined',
			static function ( $constant_name ) {
				return 'WP_CLI' === $constant_name;
			}
		);

		FunctionMocker::replace(
			'constant',
			static function ( $name ) {
				return 'WP_CLI' === $name;
			}
		);

		$subject = new AutoVerify();
		$subject->verify_form();
	}

	/**
	 * Test verify_form() in rest, case 2.
	 */
	public function test_verify_form_in_rest_case_2() {
		WP_Mock::userFunction( 'is_admin' )->with()->once()->andReturn( false );
		WP_Mock::userFunction( 'wp_doing_ajax' )->with()->once()->andReturn( false );

		$subject = new AutoVerify();
		$subject->verify_form();
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
		$request_uri = $this->get_test_request_uri();
		$nonce       = $this->get_test_nonce();

		return '
<form method="post">
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
</form>

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
}
