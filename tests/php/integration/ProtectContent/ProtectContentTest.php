<?php
/**
 * ProtectContentTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\ProtectContent;

use HCaptcha\PasswordProtected\Protect;
use HCaptcha\ProtectContent\ProtectContent;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use Mockery;
use ReflectionException;
use tad\FunctionMocker\FunctionMocker;
use WP_Scripts;
use WP_Styles;

/**
 * Test ProtectContent class.
 *
 * @group protect-content
 */
class ProtectContentTest extends HCaptchaWPTestCase {

	/**
	 * Tear down.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		unset( $_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD'], $_COOKIE['hcaptcha_content_protection'] );

		parent::tearDown();
	}

	/**
	 * Test init().
	 *
	 * @return void
	 */
	public function test_init_and_init_hooks(): void {
		$subject = new ProtectContent();

		add_filter( 'wp_doing_ajax', '__return_true' );

		// Not a frontend request.
		$subject->init();

		self::assertFalse( has_action( 'template_redirect', [ $subject, 'protect_content' ] ) );

		// A frontend request, but feature not activated.
		add_filter( 'wp_doing_ajax', '__return_false' );

		$subject->init();

		self::assertFalse( has_action( 'template_redirect', [ $subject, 'protect_content' ] ) );

		// The feature is activated, but the request uri is not in the list.
		update_option(
			'hcaptcha_settings',
			[
				'protect_content' => [ 'on' ],
				'protected_urls'  => '/some-url',
			]
		);
		hcaptcha()->init_hooks();

		$_SERVER['REQUEST_URI'] = '/protected-content';

		$subject->init();

		self::assertFalse( has_action( 'template_redirect', [ $subject, 'protect_content' ] ) );

		// The request uri is in the list.
		update_option(
			'hcaptcha_settings',
			[
				'protect_content' => [ 'on' ],
				'protected_urls'  => "/some-url\n/protected-content",
			]
		);
		hcaptcha()->init_hooks();

		$_SERVER['REQUEST_URI'] = '/protected-content';

		$subject->init();

		self::assertSame( -PHP_INT_MAX, has_action( 'template_redirect', [ $subject, 'protect_content' ] ) );

		// The list is empty.
		remove_action( 'template_redirect', [ $subject, 'protect_content' ], -PHP_INT_MAX );
		update_option(
			'hcaptcha_settings',
			[
				'protect_content' => [ 'on' ],
			]
		);
		hcaptcha()->init_hooks();

		$_SERVER['REQUEST_URI'] = '/protected-content';

		$subject->init();

		self::assertSame( -PHP_INT_MAX, has_action( 'template_redirect', [ $subject, 'protect_content' ] ) );
	}

	/**
	 * Test protect_content().
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_protect_content(): void {
		$is_valid_cookie = true;

		$subject = Mockery::mock( ProtectContent::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'is_valid_cookie' )->andReturnUsing(
			static function () use ( &$is_valid_cookie ) {
				return $is_valid_cookie;
			}
		);

		// The cookie is valid.
		ob_start();

		$subject->protect_content();

		self::assertSame( '', ob_get_clean() );

		// No valid cookie found; GET request.
		$is_valid_cookie = false;
		$error_message   = 'Some error message';
		$page_content    = 'Some page content';

		$subject->shouldReceive( 'verify' )->andReturn( $error_message );
		$subject->shouldReceive( 'show_protection_page' )->andReturnUsing(
			static function () use ( $page_content ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $page_content;
			}
		);

		ob_start();

		$subject->protect_content();

		self::assertSame( $page_content, ob_get_clean() );
		self::assertSame( '', $this->get_protected_property( $subject, 'error_message' ) );

		// No valid cookie found; POST request.
		$_SERVER['REQUEST_METHOD'] = 'POST';

		ob_start();

		$subject->protect_content();

		self::assertSame( $page_content, ob_get_clean() );
		self::assertSame( $error_message, $this->get_protected_property( $subject, 'error_message' ) );
	}

	/**
	 * Test verify().
	 *
	 * @param bool $verified Verified or not.
	 *
	 * @return void
	 * @dataProvider dp_test_verify
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_verify( bool $verified ): void {
		$action = 'hcaptcha_protect_content';
		$nonce  = 'hcaptcha_protect_content_nonce';

		$this->prepare_verify_post( $nonce, $action, $verified );

		$subject = Mockery::mock( ProtectContent::class )->makePartial();

		if ( $verified ) {
			$time              = time();
			$uri               = '/protected-content';
			$redirect_location = '';
			$expected_location = $uri;

			FunctionMocker::replace( 'time', $time );

			$cookie = $time . '|' . wp_hash( $time );

			$this->set_protected_property( $subject, 'request_uri', $uri );
			$subject->shouldAllowMockingProtectedMethods();
			$subject->shouldReceive( 'setcookie' )->with( 'hcaptcha_content_protection', $cookie, $time + 300, '/' );

			add_filter(
				'wp_redirect',
				static function ( $location ) use ( &$redirect_location ) {
					$redirect_location = $location;

					return '';
				}
			);

			self::assertSame( '', $subject->verify() );
			self::assertSame( $expected_location, $redirect_location );
		} else {
			$subject->shouldAllowMockingProtectedMethods();

			self::assertEquals( 'The hCaptcha is invalid.', $subject->verify() );
		}
	}

	/**
	 * Data provider for test_verify().
	 *
	 * @return array
	 */
	public function dp_test_verify(): array {
		return [
			[ 'not verified' => false ],
			[ 'verified' => true ],
		];
	}

	/**
	 * Test is_valid_cookie().
	 *
	 * @return void
	 */
	public function test_is_valid_cookie(): void {
		$subject = Mockery::mock( ProtectContent::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();

		// Some cookie.
		$_COOKIE['hcaptcha_content_protection'] = '1|1';

		self::assertFalse( $subject->is_valid_cookie() );

		// Valid cookie, not expired.
		$time = time();

		FunctionMocker::replace( 'time', $time );

		$_COOKIE['hcaptcha_content_protection'] = $time . '|' . wp_hash( $time );

		self::assertTrue( $subject->is_valid_cookie() );

		// Valid cookie, expired.
		$time = time();

		FunctionMocker::replace( 'time', $time );

		$_COOKIE['hcaptcha_content_protection'] = $time - 301 . '|' . wp_hash( $time );

		self::assertFalse( $subject->is_valid_cookie() );
	}

	/**
	 * Test show_protection_page().
	 *
	 * @return void
	 */
	public function test_show_protection_page(): void {
		global $wp_scripts, $wp_styles;

		$current_version = HCAPTCHA_VERSION;

		// Clean all scripts and styles left registered in other tests.

		// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited
		$wp_scripts = new WP_Scripts();
		$wp_styles  = new WP_Styles();
		// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited

		$hcap_form = $this->get_hcap_form(
			[
				'action' => 'hcaptcha_protect_content',
				'name'   => 'hcaptcha_protect_content_nonce',
				'force'  => true,
				'theme'  => 'auto',
				'size'   => 'normal',
				'id'     => [
					'source'  => [ 'hCaptcha for WP' ],
					'form_id' => 'protect',
				],
			]
		);

		// phpcs:disable WordPress.WP.EnqueuedResources.NonEnqueuedScript
		$expected = <<<HTML
		<html lang="en-US" dir="ltr">
		<head>
			<title>Content Protection</title>
			<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
			<meta http-equiv="X-UA-Compatible" content="IE=Edge">
			<meta name="robots" content="noindex,nofollow">
			<meta name="viewport" content="width=device-width,initial-scale=1">
			<meta http-equiv="refresh" content="300">
			<style>
				<style>
*{box-sizing:border-box;margin:0;padding:0}html{line-height:1.15;-webkit-text-size-adjust:100%;color:#5c6f8a;font-family:system-ui,-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica Neue,Arial,Noto Sans,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol,Noto Color Emoji}body{display:flex;flex-direction:column;height:100vh;min-height:100vh;margin-top:0;margin-bottom:0}.main-content{margin:8rem auto;max-width:60rem;padding-left:1.5rem}@media (width <=720px){.main-content{margin-top:4rem}}.h2{font-size:1.5rem;font-weight:500;line-height:2.25rem}@media (width <=720px){.h2{font-size:1.25rem;line-height:1.5rem}}body.theme-dark{background-color:#1b1b1d;color:#e3e3e3}body.theme-dark a{color:#00bcb7}body.theme-dark a:hover{color:#00bcb7;text-decoration:underline}body.theme-dark .footer-inner{border-top:1px solid #e3e3e3}body.theme-light{background-color:#fff;color:#5c6f8a}body.theme-light a{color:#0075ab}body.theme-light a:hover{color:#0075ab;text-decoration:underline}body.theme-light .footer-inner{border-top:1px solid #5c6f8a}a{background-color:#fff0;color:#0075ab;text-decoration:none;transition:color .15s ease}a:hover{color:#0075ab;text-decoration:underline}.main-content{margin:8rem auto;max-width:60rem;padding-left:1.5rem;padding-right:1.5rem;width:100%}.spacer{margin:2rem 0}.spacer-top{margin-top:2rem}.spacer-bottom{margin-bottom:2rem}@media (width <=720px){.main-content{margin-top:4rem}}.main-wrapper{align-items:center;display:flex;flex:1;flex-direction:column}.h1{font-size:2.5rem;font-weight:500;line-height:3.75rem}.h2{font-weight:500}.core-msg,.h2{font-size:1.5rem;line-height:2.25rem}.core-msg{font-weight:400}@media (width <=720px){.h1{font-size:1.5rem;line-height:1.75rem}.h2{font-size:1.25rem}.core-msg,.h2{line-height:1.5rem}.core-msg{font-size:1rem}}.text-center{text-align:center}.footer{font-size:.75rem;line-height:1.125rem;margin:0 auto;max-width:60rem;padding-left:1.5rem;padding-right:1.5rem;width:100%}.footer-inner{border-top:1px solid #5c6f8a;padding-bottom:1rem;padding-top:1rem}.clearfix:after{clear:both;content:"";display:table}.footer-text{margin-bottom:.5rem}.core-msg,.zone-name-title{overflow-wrap:break-word}@media (width <=720px){.zone-name-title{margin-bottom:1rem}}@media (prefers-color-scheme:dark){body{background-color:#1b1b1d;color:#e3e3e3}body a{color:#00bcb7}body a:hover{color:#00bcb7;text-decoration:underline}.footer-inner{border-top:1px solid #e3e3e3}}.main-content .h-captcha{margin-bottom:0}#hcaptcha-submit{display:none}
</style>
<style>
.h-captcha{position:relative;display:block;margin-bottom:2rem;padding:0;clear:both}.h-captcha[data-size="normal"]{width:303px;height:78px}.h-captcha[data-size="compact"]{width:164px;height:144px}.h-captcha[data-size="invisible"]{display:none}.h-captcha iframe{z-index:1}.h-captcha::before{content:"";display:block;position:absolute;top:0;left:0;background:url(http://test.test/wp-content/plugins/hcaptcha-wordpress-plugin/assets/images/hcaptcha-div-logo.svg) no-repeat;border:1px solid #fff0;border-radius:4px;box-sizing:border-box}.h-captcha::after{content:"If you see this message, hCaptcha failed to load due to site errors.";font:13px/1.35 system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;display:block;position:absolute;top:0;left:0;box-sizing:border-box;color:red;opacity:0}.h-captcha:not(:has(iframe))::after{animation:hcap-msg-fade-in .3s ease forwards;animation-delay:2s}.h-captcha:has(iframe)::after{animation:none;opacity:0}@keyframes hcap-msg-fade-in{to{opacity:1}}.h-captcha[data-size="normal"]::before{width:300px;height:74px;background-position:94% 28%}.h-captcha[data-size="normal"]::after{padding:19px 75px 16px 10px}.h-captcha[data-size="compact"]::before{width:156px;height:136px;background-position:50% 79%}.h-captcha[data-size="compact"]::after{padding:10px 10px 16px 10px}.h-captcha[data-theme="light"]::before,body.is-light-theme .h-captcha[data-theme="auto"]::before,.h-captcha[data-theme="auto"]::before{background-color:#fafafa;border:1px solid #e0e0e0}.h-captcha[data-theme="dark"]::before,body.is-dark-theme .h-captcha[data-theme="auto"]::before,html.wp-dark-mode-active .h-captcha[data-theme="auto"]::before,html.drdt-dark-mode .h-captcha[data-theme="auto"]::before{background-image:url(http://test.test/wp-content/plugins/hcaptcha-wordpress-plugin/assets/images/hcaptcha-div-logo-white.svg);background-repeat:no-repeat;background-color:#333;border:1px solid #f5f5f5}@media (prefers-color-scheme:dark){.h-captcha[data-theme="auto"]::before{background-image:url(http://test.test/wp-content/plugins/hcaptcha-wordpress-plugin/assets/images/hcaptcha-div-logo-white.svg);background-repeat:no-repeat;background-color:#333;border:1px solid #f5f5f5}}.h-captcha[data-theme="custom"]::before{background-color:initial}.h-captcha[data-size="invisible"]::before,.h-captcha[data-size="invisible"]::after{display:none}.h-captcha iframe{position:relative}div[style*="z-index: 2147483647"] div[style*="border-width: 11px"][style*="position: absolute"][style*="pointer-events: none"]{border-style:none}
</style>
			</style>
		</head>
		<body>
		<div class="main-wrapper" role="main">
			<div class="main-content">
				<h1 class="zone-name-title h1">
					test.test				</h1>

				<p class="h2 spacer-bottom">
					Verifying you are human. This may take a few seconds.				</p>

				<form method="post" action="">
				$hcap_form				<p id="hcaptcha-error"></p>
				<input type="submit" id="hcaptcha-submit" value="Submit">
				</form>

				<div class="core-msg spacer spacer-top">
					test.test needs to review the security of your connection before proceeding.				</div>
			</div>
		</div>
		<div class="footer text-center" role="contentinfo">
			<div class="footer-inner">
				<div class="clearfix footer-text">
					<div>
						The hCaptcha plugin					</div>
				</div>
				<div>
					Privacy and security by <a href="https://www.hcaptcha.com/?r=wp&amp;utm_source=wordpress&amp;utm_medium=wpplugin&amp;utm_campaign=sk" target="_blank" rel="noopener noreferrer">hCaptcha</a>				</div>
			</div>
		</div>
		<script>
			document.addEventListener( 'hCaptchaLoaded', function() {
				if ( document.getElementById( 'hcaptcha-error' ).innerText.length === 0 ) {
					document.getElementById( 'hcaptcha-submit' ).click();
				}
			} );
		</script>
		<script>
(()=>{'use strict';let loaded=!1,scrolled=!1,timerId;function load(){if(loaded){return}
loaded=!0;clearTimeout(timerId);window.removeEventListener('touchstart',load);document.body.removeEventListener('mouseenter',load);document.body.removeEventListener('click',load);window.removeEventListener('keydown',load);window.removeEventListener('scroll',scrollHandler);const t=document.getElementsByTagName('script')[0];const s=document.createElement('script');s.type='text/javascript';s.id='hcaptcha-api';s.src='https://js.hcaptcha.com/1/api.js?onload=hCaptchaOnLoad&render=explicit';s.async=!0;t.parentNode.insertBefore(s,t)}
function scrollHandler(){if(!scrolled){scrolled=!0;return}
load()}
document.addEventListener('hCaptchaBeforeAPI',function(){const delay=-100;if(delay>=0){timerId=setTimeout(load,delay)}
window.addEventListener('touchstart',load);document.body.addEventListener('mouseenter',load);document.body.addEventListener('click',load);window.addEventListener('keydown',load);window.addEventListener('scroll',scrollHandler)})})()
</script>
<script type="text/javascript" src="http://test.test/wp-includes/js/dist/hooks.min.js?ver=4d63a3d491d11ffd8ac6" id="wp-hooks-js"></script>
<script type="text/javascript" id="hcaptcha-js-extra">
/* <![CDATA[ */
var HCaptchaMainObject = {"params":"{\"sitekey\":\"\",\"theme\":\"\",\"size\":\"\",\"hl\":\"en\"}"};
/* ]]> */
</script>
<script type="text/javascript" src="http://test.test/wp-content/plugins/hcaptcha-wordpress-plugin/assets/js/apps/hcaptcha.js?ver=$current_version" id="hcaptcha-js"></script>
		</body>
		</html>
		
HTML;
		// phpcs:enable WordPress.WP.EnqueuedResources.NonEnqueuedScript

		$expected = str_replace( 'http://test.test', home_url(), $expected );

		$subject = Mockery::mock( ProtectContent::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'exit' )->once();

		ob_start();

		$subject->show_protection_page();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test add_hcaptcha().
	 *
	 * @return void
	 */
	public function est_add_hcaptcha(): void {
		$form_id   = 'protect';
		$hcap_form = $this->get_hcap_form(
			[
				'action' => 'hcaptcha_password_protected',
				'name'   => 'hcaptcha_password_protected_nonce',
				'id'     => [
					'source'  => [ 'password-protected/password-protected.php' ],
					'form_id' => $form_id,
				],
			]
		);

		$subject = new Protect();

		ob_start();

		$subject->add_hcaptcha();

		self::assertSame( $hcap_form, ob_get_clean() );
	}
}
