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
	 * Clearance cookie name.
	 */
	private const COOKIE_NAME = 'hcaptcha_content_protection';

	/**
	 * Browser session cookie name.
	 */
	private const SESSION_COOKIE_NAME = 'hcaptcha_content_protection_session';

	/**
	 * Tear down.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		unset(
			$_SERVER['REQUEST_URI'],
			$_SERVER['REQUEST_METHOD'],
			$_SERVER['HTTPS'],
			$_COOKIE[ self::COOKIE_NAME ],
			$_COOKIE[ self::SESSION_COOKIE_NAME ]
		);
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
		self::assertSame( '/protected-content', $this->get_protected_property( $subject, 'resource_scope' ) );

		// A percent-encoded path resolving to a protected URL is also protected.
		remove_action( 'template_redirect', [ $subject, 'protect_content' ], -PHP_INT_MAX );

		$_SERVER['REQUEST_URI'] = '/protected-%63ontent';

		$subject->init();

		self::assertSame( -PHP_INT_MAX, has_action( 'template_redirect', [ $subject, 'protect_content' ] ) );
		self::assertSame( '/protected-content', $this->get_protected_property( $subject, 'resource_scope' ) );

		// The most specific matching configured rule defines the resource set.
		remove_action( 'template_redirect', [ $subject, 'protect_content' ], -PHP_INT_MAX );
		update_option(
			'hcaptcha_settings',
			[
				'protect_content' => [ 'on' ],
				'protected_urls'  => "/members\n/members/admin",
			]
		);
		hcaptcha()->init_hooks();

		$_SERVER['REQUEST_URI'] = '/members/admin/dashboard';

		$subject->init();

		self::assertSame( '/members/admin', $this->get_protected_property( $subject, 'resource_scope' ) );

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
	 * Test normalize_url() with a protocol-relative request URI.
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_normalize_url_forces_site_origin(): void {
		$subject = new ProtectContent();
		$method  = $this->set_method_accessibility( $subject, 'normalize_url' );

		self::assertSame(
			home_url( '/anything?foo=bar' ),
			$method->invoke( $subject, '//attacker.example/anything?foo=bar' )
		);

		$method->setAccessible( false );
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
		$subject->shouldReceive( 'ensure_session_cookie' )->twice();

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
			$scope             = '/protected-content';
			$session_id        = 'v1.' . str_repeat( 'a', 64 );
			$token             = str_repeat( 'b', 64 );
			$cookie            = $this->create_clearance_cookie( $token, $session_id, $scope, $time + 300 );
			$redirect_location = '';
			$expected_location = $uri;
			$_SERVER['HTTPS']  = 'on';

			FunctionMocker::replace( 'time', $time );

			$this->set_protected_property( $subject, 'request_uri', $uri );
			$this->set_protected_property( $subject, 'resource_scope', $scope );
			$_COOKIE[ self::SESSION_COOKIE_NAME ] = $session_id;
			$subject->shouldAllowMockingProtectedMethods();
			$subject->shouldReceive( 'generate_token' )->once()->andReturn( $token );
			$subject->shouldReceive( 'setcookie' )->once()->with(
				self::COOKIE_NAME,
				$cookie,
				[
					'expires'  => $time + 300,
					'path'     => '/',
					'secure'   => true,
					'httponly' => true,
					'samesite' => 'Lax',
				]
			)->andReturnTrue();

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
	 * Test that a successful challenge requires the pre-existing session cookie.
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_verify_requires_preexisting_session_cookie(): void {
		$this->prepare_verify_post( 'hcaptcha_protect_content_nonce', 'hcaptcha_protect_content', true );

		$subject = Mockery::mock( ProtectContent::class )->makePartial();

		$this->set_protected_property( $subject, 'resource_scope', '/members' );
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldNotReceive( 'generate_token' );
		$subject->shouldNotReceive( 'setcookie' );

		self::assertSame(
			'Your browser session could not be established. Please try again.',
			$subject->verify()
		);
	}

	/**
	 * Test creating the independent browser session cookie.
	 *
	 * @return void
	 */
	public function test_ensure_session_cookie(): void {
		$_SERVER['HTTPS'] = 'on';

		$token   = str_repeat( 'c', 64 );
		$subject = Mockery::mock( ProtectContent::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'generate_token' )->once()->andReturn( $token );
		$subject->shouldReceive( 'setcookie' )->once()->with(
			self::SESSION_COOKIE_NAME,
			'v1.' . $token,
			[
				'expires'  => 0,
				'path'     => '/',
				'secure'   => true,
				'httponly' => true,
				'samesite' => 'Lax',
			]
		)->andReturnTrue();

		$subject->ensure_session_cookie();

		$_COOKIE[ self::SESSION_COOKIE_NAME ] = 'v1.' . $token;

		$subject->ensure_session_cookie();
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
		$this->set_protected_property( $subject, 'resource_scope', '/members' );

		// Malformed and legacy unbound cookies fail closed.
		$_COOKIE[ self::COOKIE_NAME ] = 'not-a-clearance';

		self::assertFalse( $subject->is_valid_cookie() );

		$time = time();
		FunctionMocker::replace( 'time', $time );

		$_COOKIE[ self::COOKIE_NAME ] = $time . '|' . wp_hash( $time );

		self::assertFalse( $subject->is_valid_cookie() );

		$_COOKIE[ self::COOKIE_NAME ]         = [ 'v2.invalid' ];
		$_COOKIE[ self::SESSION_COOKIE_NAME ] = 'v1.' . str_repeat( 'a', 64 );

		self::assertFalse( $subject->is_valid_cookie() );

		// A valid, unexpired token works in its bound session and scope.
		$token      = str_repeat( 'b', 64 );
		$session_id = 'v1.' . str_repeat( 'a', 64 );
		$cookie     = $this->create_clearance_cookie( $token, $session_id, '/members', $time + 300 );

		$_COOKIE[ self::COOKIE_NAME ]         = $cookie;
		$_COOKIE[ self::SESSION_COOKIE_NAME ] = $session_id;

		self::assertTrue( $subject->is_valid_cookie() );

		// A well-formed but tampered token fails the stateless integrity check.
		$_COOKIE[ self::COOKIE_NAME ] = str_replace( $token, str_repeat( 'd', 64 ), $cookie );

		self::assertFalse( $subject->is_valid_cookie() );

		// Expired stateless clearance is rejected.
		$_COOKIE[ self::COOKIE_NAME ] = $this->create_clearance_cookie(
			$token,
			$session_id,
			'/members',
			$time - 1
		);

		self::assertFalse( $subject->is_valid_cookie() );

		// Even an intact signature cannot extend the fixed five-minute lifetime.
		$_COOKIE[ self::COOKIE_NAME ] = $this->create_clearance_cookie(
			$token,
			$session_id,
			'/members',
			$time + 301
		);

		self::assertFalse( $subject->is_valid_cookie() );
	}

	/**
	 * Test that copying the complete clearance response does not cross sessions.
	 *
	 * The session cookie is established on the earlier challenge response. A
	 * successful clearance response contains only the clearance cookie, so moving
	 * that complete response cookie set to another client lacks the binding. If
	 * both cookies are stolen, that is session theft outside this protection model.
	 *
	 * @return void
	 */
	public function test_clearance_response_cannot_be_replayed_in_another_session(): void {
		$this->prepare_verify_post( 'hcaptcha_protect_content_nonce', 'hcaptcha_protect_content', true );

		$time             = time();
		$token            = str_repeat( 'e', 64 );
		$session_a        = 'v1.' . str_repeat( 'a', 64 );
		$session_b        = 'v1.' . str_repeat( 'b', 64 );
		$cookie           = $this->create_clearance_cookie( $token, $session_a, '/members', $time + 300 );
		$response_cookies = [];
		$subject          = Mockery::mock( ProtectContent::class )->makePartial();

		FunctionMocker::replace( 'time', $time );
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'generate_token' )->once()->andReturn( $token );
		$subject->shouldReceive( 'setcookie' )->once()->andReturnUsing(
			static function ( string $name, string $value ) use ( &$response_cookies ): bool {
				$response_cookies[ $name ] = $value;

				return true;
			}
		);
		$this->set_protected_property( $subject, 'resource_scope', '/members' );
		$this->set_protected_property( $subject, 'request_uri', '/members' );
		$_COOKIE[ self::SESSION_COOKIE_NAME ] = $session_a;

		add_filter( 'wp_redirect', '__return_empty_string' );

		self::assertSame( '', $subject->verify() );
		self::assertSame( [ self::COOKIE_NAME => $cookie ], $response_cookies );

		$_COOKIE = array_merge( $_COOKIE, $response_cookies );

		self::assertTrue( $subject->is_valid_cookie() );

		// Fresh client receives the complete successful clearance response.
		$_COOKIE = $response_cookies;

		self::assertFalse( $subject->is_valid_cookie() );

		// Even an independently established session cannot use session A's token.
		$_COOKIE[ self::SESSION_COOKIE_NAME ] = $session_b;

		self::assertFalse( $subject->is_valid_cookie() );

		$_COOKIE[ self::SESSION_COOKIE_NAME ] = $session_a;

		self::assertTrue( $subject->is_valid_cookie() );
	}

	/**
	 * Test navigation within one configured set and isolation from another set.
	 *
	 * @return void
	 */
	public function test_clearance_is_limited_to_configured_resource_set(): void {
		$session_id = 'v1.' . str_repeat( 'a', 64 );
		$cookie     = $this->create_clearance_cookie(
			str_repeat( 'f', 64 ),
			$session_id,
			'/members',
			time() + 300
		);

		update_option(
			'hcaptcha_settings',
			[
				'protect_content' => [ 'on' ],
				'protected_urls'  => "/members\n/private",
			]
		);
		hcaptcha()->init_hooks();

		$_COOKIE[ self::COOKIE_NAME ]         = $cookie;
		$_COOKIE[ self::SESSION_COOKIE_NAME ] = $session_id;

		$subject = $this->init_subject_for_uri( '/members/welcome' );

		self::assertTrue( $subject->is_valid_cookie() );

		$subject = $this->init_subject_for_uri( '/members/account' );

		self::assertTrue( $subject->is_valid_cookie() );

		$subject = $this->init_subject_for_uri( '/private/welcome' );

		self::assertFalse( $subject->is_valid_cookie() );
	}

	/**
	 * Create a signed clearance test fixture.
	 *
	 * @param string $token      Random clearance token.
	 * @param string $session_id Browser session ID.
	 * @param string $scope      Resource scope.
	 * @param int    $expires    Expiration timestamp.
	 *
	 * @return string
	 */
	private function create_clearance_cookie( string $token, string $session_id, string $scope, int $expires ): string {
		$key     = wp_salt( 'auth' );
		$payload = implode(
			'.',
			[
				'v2',
				(string) $expires,
				$token,
				hash_hmac( 'sha256', 'session|' . $session_id, $key ),
				hash_hmac( 'sha256', 'scope|' . $scope, $key ),
			]
		);

		return $payload . '.' . hash_hmac( 'sha256', 'clearance|' . $payload, $key );
	}

	/**
	 * Initialize a subject for a protected request URI.
	 *
	 * @param string $uri Request URI.
	 *
	 * @return ProtectContent
	 */
	private function init_subject_for_uri( string $uri ): ProtectContent {
		$_SERVER['REQUEST_URI'] = $uri;

		$subject = Mockery::mock( ProtectContent::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();
		$subject->init();

		return $subject;
	}

	/**
	 * Test show_protection_page().
	 *
	 * @return void
	 * @noinspection ES6ConvertVarToLetConst
	 */
	public function test_show_protection_page(): void {
		global $wp_scripts, $wp_styles;

		$current_version = HCAPTCHA_VERSION;

		// Clean all scripts and styles left registered in other tests.

		// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited
		$wp_scripts = new WP_Scripts();
		$wp_styles  = new WP_Styles();
		// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited

		$wp_hooks_version = $wp_scripts->registered['wp-hooks']->ver;

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

		ob_start();
		hcaptcha()->print_inline_styles();
		$hcaptcha_styles = (string) ob_get_clean();

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
$hcaptcha_styles			</style>
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
HTML;

		$expected .= "\n";

		if ( version_compare( $GLOBALS['wp_version'], '7.0-RC1', '>=' ) ) {
			$expected .= <<<'HTML'
		<script>
var HCaptchaMainObject = {"params":"{\"sitekey\":\"10000000-ffff-ffff-ffff-000000000001\",\"theme\":\"\",\"size\":\"\",\"hl\":\"en\"}"};
</script>
HTML;
		} else {
			$expected .= <<<'HTML'
		<script type="text/javascript">
/* <![CDATA[ */
var HCaptchaMainObject = {"params":"{\"sitekey\":\"10000000-ffff-ffff-ffff-000000000001\",\"theme\":\"\",\"size\":\"\",\"hl\":\"en\"}"};
/* ]]> */
</script>
HTML;
		}

		$expected .= "\n";

		$expected .= <<<'HTML'
<script>
(()=>{'use strict';let loaded=!1,scrolled=!1,timerId;function load(){if(loaded){return}
loaded=!0;clearTimeout(timerId);window.removeEventListener('touchstart',load);document.body.removeEventListener('mouseenter',load);document.body.removeEventListener('click',load);window.removeEventListener('keydown',load);window.removeEventListener('scroll',scrollHandler);const t=document.getElementsByTagName('script')[0];const s=document.createElement('script');s.type='text/javascript';s.id='hcaptcha-api';s.src='https://js.hcaptcha.com/1/api.js?onload=hCaptchaOnLoad&render=explicit';s.async=!0;t.parentNode.insertBefore(s,t)}
function scrollHandler(){if(!scrolled){scrolled=!0;return}
load()}
document.addEventListener('hCaptchaBeforeAPI',function(){const delay=-100;if(delay>=0){timerId=setTimeout(load,delay)}
const options={passive:!0};window.addEventListener('touchstart',load,options);document.body.addEventListener('mouseenter',load);document.body.addEventListener('click',load);window.addEventListener('keydown',load);window.addEventListener('scroll',scrollHandler,options)})})()
</script>
HTML;

		$expected .= "\n";

		if ( version_compare( $GLOBALS['wp_version'], '7.0-RC1', '>=' ) ) {
			$expected .= <<<HTML
<script id="wp-hooks-js" src="http://test.test/wp-includes/js/dist/hooks.min.js?ver=$wp_hooks_version"></script>
<script id="hcaptcha-js" src="http://test.test/wp-content/plugins/hcaptcha-wordpress-plugin/assets/js/apps/hcaptcha.js?ver=$current_version"></script>
		</body>
		</html>
		
HTML;
		} else {
			$expected .= <<<HTML
<script type="text/javascript" src="http://test.test/wp-includes/js/dist/hooks.min.js?ver=$wp_hooks_version" id="wp-hooks-js"></script>
<script type="text/javascript" src="http://test.test/wp-content/plugins/hcaptcha-wordpress-plugin/assets/js/apps/hcaptcha.js?ver=$current_version" id="hcaptcha-js"></script>
		</body>
		</html>
		
HTML;
		}

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
