<?php
/**
 * PasswordCookieProofTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedFunctionInspection */
/** @noinspection PhpUndefinedNamespaceInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Integration\WP;

use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use HCaptcha\WP\PasswordProtected;
use PasswordHash;
use WP_Post;
use WP_REST_Request;
use WP_REST_Server;
use function Patchwork\redefine;

/**
 * Test the native password-cookie authorization boundary.
 *
 * @group wp-password-cookie-proof
 * @group wp-password-protected
 * @group wp
 */
class PasswordCookieProofTest extends HCaptchaWPTestCase {

	/**
	 * Protected post password.
	 */
	private const PASSWORD = 'native-password-proof-test';

	/**
	 * Protected content marker.
	 */
	private const CONTENT = 'Password cookie protected content marker.';

	/**
	 * Protected excerpt marker.
	 */
	private const EXCERPT = 'Password cookie protected excerpt marker.';

	/**
	 * Integration under test.
	 *
	 * @var PasswordProtected
	 * @noinspection PhpPropertyOnlyWrittenInspection
	 */
	private PasswordProtected $subject;

	/**
	 * REST registry that existed before the test.
	 *
	 * @var WP_REST_Server|null
	 */
	private ?WP_REST_Server $previous_server = null;

	/**
	 * Set up the native cookie boundary.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		wp_set_current_user( 0 );
		unset( $_COOKIE[ $this->native_cookie_name() ], $_COOKIE[ $this->proof_cookie_name() ] );

		$this->previous_server = $GLOBALS['wp_rest_server'] ?? null;
		$this->subject         = new PasswordProtected();
	}

	/**
	 * Restore cookie and REST state.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		unset( $_COOKIE[ $this->native_cookie_name() ], $_COOKIE[ $this->proof_cookie_name() ] );
		wp_set_current_user( 0 );

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restore the native REST registry.
		$GLOBALS['wp_rest_server'] = $this->previous_server;

		parent::tearDown();
	}

	/**
	 * A locally generated correct hash cannot authorize native content alone.
	 *
	 * @return void
	 */
	public function test_native_password_cookie_requires_captcha_proof(): void {
		$post = $this->create_post();

		$this->set_native_cookie( self::PASSWORD );

		self::assertTrue( post_password_required( $post ) );
	}

	/**
	 * Native content renders its password form for an unproven correct cookie.
	 *
	 * @return void
	 */
	public function test_native_content_does_not_accept_an_unproven_cookie(): void {
		$post = $this->create_post();

		$this->set_native_cookie( self::PASSWORD );

		$content = get_the_content( '', false, $post );

		self::assertStringContainsString( 'post-password-form', $content );
		self::assertStringNotContainsString( self::CONTENT, $content );
	}

	/**
	 * Native excerpts do not disclose text for an unproven correct cookie.
	 *
	 * @return void
	 */
	public function test_native_excerpt_does_not_accept_an_unproven_cookie(): void {
		$post = $this->create_post();

		$this->set_native_cookie( self::PASSWORD );

		self::assertStringNotContainsString( self::EXCERPT, get_the_excerpt( $post ) );
	}

	/**
	 * Native REST response rendering does not trust an unproven correct cookie.
	 *
	 * @return void
	 */
	public function test_native_rest_rendering_requires_cookie_proof(): void {
		$post = $this->create_post();

		$this->set_native_cookie( self::PASSWORD );

		$response = $this->rest_server()->dispatch( new WP_REST_Request( 'GET', '/wp/v2/posts/' . $post->ID ) );
		$data     = $response->get_data();

		self::assertSame( 200, $response->get_status() );
		self::assertTrue( $data['content']['protected'] );
		self::assertSame( '', $data['content']['rendered'] );
		self::assertSame( '', $data['excerpt']['rendered'] );
	}

	/**
	 * Successful postpass verification issues proof accepted by native readers.
	 *
	 * @return void
	 */
	public function test_verified_postpass_authorizes_native_content_and_excerpt(): void {
		$post   = $this->create_post();
		$cookie = $this->issue_proof( self::PASSWORD );

		$_COOKIE[ $this->proof_cookie_name() ] = wp_slash( $cookie['value'] );

		$this->set_native_cookie( self::PASSWORD );

		self::assertFalse( post_password_required( $post ) );
		self::assertSame( self::CONTENT, get_the_content( '', false, $post ) );
		self::assertSame( self::EXCERPT, get_the_excerpt( $post ) );
	}

	/**
	 * Native password authorization remains necessary after a successful challenge.
	 *
	 * @return void
	 */
	public function test_valid_proof_does_not_authorize_a_wrong_native_password(): void {
		$post   = $this->create_post();
		$cookie = $this->issue_proof( self::PASSWORD );

		$_COOKIE[ $this->proof_cookie_name() ] = $cookie['value'];

		$this->set_native_cookie( 'incorrect-password' );

		self::assertTrue( post_password_required( $post ) );
		self::assertStringNotContainsString( self::CONTENT, get_the_content( '', false, $post ) );
	}

	/**
	 * Proof for one password cannot authorize an independently guessed password.
	 *
	 * @return void
	 */
	public function test_proof_is_bound_to_the_verified_password_scope(): void {
		$post   = $this->create_post();
		$cookie = $this->issue_proof( 'another-verified-password' );

		$_COOKIE[ $this->proof_cookie_name() ] = $cookie['value'];

		$this->set_native_cookie( self::PASSWORD );

		self::assertTrue( post_password_required( $post ) );
	}

	/**
	 * Posts sharing one password keep native shared-cookie access.
	 *
	 * @return void
	 */
	public function test_proof_preserves_access_to_posts_sharing_a_password(): void {
		$first  = $this->create_post();
		$second = $this->create_post();
		$cookie = $this->issue_proof( self::PASSWORD );

		$_COOKIE[ $this->proof_cookie_name() ] = $cookie['value'];

		$this->set_native_cookie( self::PASSWORD );

		self::assertFalse( post_password_required( $first ) );
		self::assertFalse( post_password_required( $second ) );
	}

	/**
	 * Neither public posts nor absent posts require a proof cookie.
	 *
	 * @return void
	 */
	public function test_public_and_absent_posts_do_not_require_proof(): void {
		foreach ( [ '', '0' ] as $password ) {
			$post = $this->create_post( $password );

			self::assertFalse( post_password_required( $post ) );
			self::assertSame( self::CONTENT, get_the_content( '', false, $post ) );
		}

		self::assertFalse( post_password_required( -1 ) );
	}

	/**
	 * Malformed proof and any modification of an issued proof fail closed.
	 *
	 * @return void
	 */
	public function test_malformed_and_tampered_proof_cookies_are_rejected(): void {
		$post   = $this->create_post();
		$cookie = $this->issue_proof( self::PASSWORD );

		$this->set_native_cookie( self::PASSWORD );

		$parts             = explode( ':', $cookie['value'] );
		$changed_signature = ( 'a' === $parts[1][0] ? 'b' : 'a' ) . substr( $parts[1], 1 );
		$invalid_proofs    = [
			'',
			'legacy-cookie',
			[ $cookie['value'] ],
			str_repeat( 'a', 700 ),
			$cookie['value'] . 'a',
			'0' . $cookie['value'],
			$parts[0] . ':' . $changed_signature,
			( (int) $parts[0] + HOUR_IN_SECONDS ) . ':' . $parts[1],
			'9223372036854775808:' . $parts[1],
		];

		foreach ( $invalid_proofs as $proof ) {
			$_COOKIE[ $this->proof_cookie_name() ] = $proof;

			self::assertTrue( post_password_required( $post ) );
		}
	}

	/**
	 * Expired proof cannot extend a manually replayed native cookie.
	 *
	 * @return void
	 */
	public function test_expired_proof_cookie_is_rejected(): void {
		$post    = $this->create_post();
		$expires = time() + 60;
		$cookie  = $this->issue_proof( self::PASSWORD, $expires );

		$_COOKIE[ $this->proof_cookie_name() ] = $cookie['value'];

		$this->set_native_cookie( self::PASSWORD );

		self::assertFalse( post_password_required( $post ) );

		redefine(
			'time',
			static function () use ( $expires ) {
				return $expires + 1;
			}
		);

		self::assertTrue( post_password_required( $post ) );
	}

	/**
	 * A changed database password invalidates proof for the previous password.
	 *
	 * @return void
	 */
	public function test_changing_the_post_password_invalidates_previous_proof(): void {
		$post   = $this->create_post();
		$cookie = $this->issue_proof( self::PASSWORD );

		$_COOKIE[ $this->proof_cookie_name() ] = $cookie['value'];

		wp_update_post(
			[
				'ID'            => $post->ID,
				'post_password' => 'changed-password',
			]
		);
		$this->set_native_cookie( 'changed-password' );

		self::assertTrue( post_password_required( $post->ID ) );
	}

	/**
	 * Reader exemptions use the server-selected password-form identity.
	 *
	 * @return void
	 */
	public function test_password_form_exemption_preserves_native_cookie_access(): void {
		$post     = $this->create_post();
		$observed = [];

		add_filter(
			'hcap_protect_form',
			static function ( $protect, $source, $form_id ) use ( &$observed ) {
				$observed[] = [ $source, $form_id ];

				return 'password_protected' === $form_id ? false : $protect;
			},
			20,
			3
		);

		$this->set_native_cookie( self::PASSWORD );

		self::assertFalse( post_password_required( $post ) );
		self::assertContains( [ [ 'WordPress' ], 'password_protected' ], $observed );
	}

	/**
	 * A submitted identity for another exempt form cannot disable reader protection.
	 *
	 * @return void
	 */
	public function test_reader_does_not_use_a_submitted_exempt_form_identity(): void {
		$post = $this->create_post();

		add_filter(
			'hcap_protect_form',
			static function ( $protect, $source, $form_id ) {
				return 'login' === $form_id ? false : $protect;
			},
			20,
			3
		);

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'WordPress' ],
				'form_id' => 'login',
			]
		);

		$this->set_native_cookie( self::PASSWORD );

		self::assertTrue( post_password_required( $post ) );
	}

	/**
	 * The native expiry filter alone cannot issue successful CAPTCHA proof.
	 *
	 * @return void
	 */
	public function test_expiry_hook_cannot_issue_proof_without_verified_postpass(): void {
		$cookies = [];
		$expires = time() + 60;

		$this->capture_proof_cookies( $cookies );

		$_POST['post_password'] = self::PASSWORD;

		self::assertSame( $expires, apply_filters( 'post_password_expires', $expires ) );
		self::assertSame( [], $cookies );
	}

	/**
	 * Failed challenge verification never issues a proof cookie.
	 *
	 * @return void
	 */
	public function test_failed_postpass_verification_does_not_issue_proof(): void {
		$cookies = [];

		$this->capture_proof_cookies( $cookies );
		$this->prepare_postpass( self::PASSWORD, false );

		add_filter(
			'wp_die_handler',
			static function () {
				return static function () {};
			}
		);

		do_action( 'login_form_postpass' );
		apply_filters( 'post_password_expires', time() + 60 );

		self::assertSame( [], $cookies );
	}

	/**
	 * The companion cookie follows a custom native cookie lifetime.
	 *
	 * @return void
	 */
	public function test_proof_cookie_uses_the_filtered_native_cookie_lifetime(): void {
		$expires = time() + 120;

		add_filter(
			'post_password_expires',
			static function () use ( $expires ) {
				return $expires;
			}
		);

		$cookie = $this->issue_proof( self::PASSWORD );

		self::assertSame( $expires, $cookie['options']['expires'] );
		self::assertSame( COOKIEPATH, $cookie['options']['path'] );
		self::assertSame( COOKIE_DOMAIN, $cookie['options']['domain'] );
		self::assertTrue( $cookie['options']['httponly'] );
	}

	/**
	 * A native session-cookie setting remains a session-cookie setting.
	 *
	 * @return void
	 */
	public function test_proof_cookie_preserves_native_session_cookie_lifetime(): void {
		$post   = $this->create_post();
		$cookie = $this->issue_proof( self::PASSWORD, 0 );

		self::assertSame( 0, $cookie['options']['expires'] );

		$_COOKIE[ $this->proof_cookie_name() ] = $cookie['value'];

		$this->set_native_cookie( self::PASSWORD );

		self::assertFalse( post_password_required( $post ) );
	}

	/**
	 * Native authentication-cookie clearing also clears companion proof.
	 *
	 * @return void
	 */
	public function test_native_cookie_clear_hook_clears_companion_proof(): void {
		$cookies = [];

		$this->capture_proof_cookies( $cookies );
		// WordPress's test bootstrap vetoes cookies; exercise the native allowed branch.
		add_filter( 'send_auth_cookies', '__return_true', PHP_INT_MAX );

		wp_clear_auth_cookie();

		self::assertCount( 1, $cookies );
		self::assertSame( '', $cookies[0]['value'] );
		self::assertLessThan( time(), $cookies[0]['options']['expires'] );
		self::assertSame( COOKIEPATH, $cookies[0]['options']['path'] );
		self::assertSame( COOKIE_DOMAIN, $cookies[0]['options']['domain'] );
	}

	/**
	 * WordPress's cookie veto also preserves the companion proof cookie.
	 *
	 * @return void
	 */
	public function test_native_cookie_veto_preserves_companion_proof(): void {
		$cookies = [];

		$this->capture_proof_cookies( $cookies );
		add_filter( 'send_auth_cookies', '__return_false', PHP_INT_MAX );

		wp_clear_auth_cookie();

		self::assertSame( [], $cookies );
	}

	/**
	 * The WordPress 6.0 cookie-send hook signature remains supported.
	 *
	 * @return void
	 */
	public function test_legacy_cookie_send_hook_clears_proof_once(): void {
		$cookies = [];

		$this->capture_proof_cookies( $cookies );
		add_filter( 'send_auth_cookies', '__return_true', PHP_INT_MAX );
		do_action( 'clear_auth_cookie' );

		self::assertTrue( apply_filters( 'send_auth_cookies', true ) );
		self::assertCount( 1, $cookies );
		self::assertTrue( apply_filters( 'send_auth_cookies', true ) );
		self::assertCount( 1, $cookies );
	}

	/**
	 * Native REST accepts verified cookie proof without a password parameter.
	 *
	 * @return void
	 */
	public function test_native_rest_accepts_verified_cookie_proof(): void {
		$post   = $this->create_post();
		$cookie = $this->issue_proof( self::PASSWORD );

		$_COOKIE[ $this->proof_cookie_name() ] = $cookie['value'];

		$this->set_native_cookie( self::PASSWORD );

		$response = $this->rest_server()->dispatch( new WP_REST_Request( 'GET', '/wp/v2/posts/' . $post->ID ) );
		$data     = $response->get_data();

		self::assertSame( 200, $response->get_status() );
		self::assertStringContainsString( self::CONTENT, $data['content']['rendered'] );
		self::assertStringContainsString( self::EXCERPT, $data['excerpt']['rendered'] );
	}

	/**
	 * REST password attempts are rejected before core compares the candidate.
	 *
	 * @return void
	 */
	public function test_native_rest_rejects_a_correct_explicit_password(): void {
		$post    = $this->create_post();
		$request = new WP_REST_Request( 'GET', '/wp/v2/posts/' . $post->ID );

		$request->set_query_params( [ 'password' => self::PASSWORD ] );

		$response = $this->rest_server()->dispatch( $request );
		self::assertSame( 403, $response->get_status() );
		self::assertSame( 'hcaptcha_rest_password_forbidden', $response->get_data()['code'] );
	}

	/**
	 * Incorrect REST password attempts should receive the same response.
	 *
	 * @return void
	 */
	public function test_native_rest_rejects_an_incorrect_explicit_password(): void {
		$post    = $this->create_post();
		$request = new WP_REST_Request( 'GET', '/wp/v2/posts/' . $post->ID );

		$request->set_query_params( [ 'password' => 'incorrect-password' ] );

		$response = $this->rest_server()->dispatch( $request );

		self::assertSame( 403, $response->get_status() );
		self::assertSame( 'hcaptcha_rest_password_forbidden', $response->get_data()['code'] );
	}

	/**
	 * Pages, collections, and JSON input cannot bypass the REST password guard.
	 *
	 * @return void
	 */
	public function test_rest_password_guard_covers_pages_collections_and_json(): void {
		$page   = $this->factory()->post->create_and_get(
			[
				'post_type'     => 'page',
				'post_status'   => 'publish',
				'post_password' => self::PASSWORD,
				'post_content'  => self::CONTENT,
			]
		);
		$post   = $this->create_post();
		$server = $this->rest_server();

		foreach ( [ '/wp/v2/pages/' . $page->ID, '/wp/v2/posts', '/wp/v2/pages' ] as $route ) {
			$request = new WP_REST_Request( 'GET', $route );
			$request->set_query_params( [ 'password' => self::PASSWORD ] );
			$response = $server->dispatch( $request );

			self::assertSame( 403, $response->get_status() );
			self::assertSame( 'hcaptcha_rest_password_forbidden', $response->get_data()['code'] );
		}

		$request = new WP_REST_Request( 'GET', '/wp/v2/posts/' . $post->ID );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( [ 'password' => self::PASSWORD ] ) );
		$response = $server->dispatch( $request );

		self::assertSame( 403, $response->get_status() );
		self::assertSame( 'hcaptcha_rest_password_forbidden', $response->get_data()['code'] );
	}

	/**
	 * Comment reads and creation cannot use the REST password override.
	 *
	 * @return void
	 */
	public function test_rest_password_guard_covers_comment_creation(): void {
		$post    = $this->create_post();
		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );
		$request->set_body_params(
			[
				'post'     => $post->ID,
				'content'  => 'Password override comment.',
				'password' => self::PASSWORD,
			]
		);
		$response = $this->rest_server()->dispatch( $request );

		self::assertSame( 403, $response->get_status() );
		self::assertSame( 'hcaptcha_rest_password_forbidden', $response->get_data()['code'] );

		$request = new WP_REST_Request( 'GET', '/wp/v2/comments' );
		$request->set_query_params(
			[
				'post'     => $post->ID,
				'password' => self::PASSWORD,
			]
		);
		$response = $this->rest_server()->dispatch( $request );

		self::assertSame( 403, $response->get_status() );
		self::assertSame( 'hcaptcha_rest_password_forbidden', $response->get_data()['code'] );
	}

	/**
	 * Editors may still change a post password through REST.
	 *
	 * @return void
	 */
	public function test_rest_password_guard_preserves_editor_updates(): void {
		$post = $this->create_post();
		wp_set_current_user( $this->factory()->user->create( [ 'role' => 'editor' ] ) );
		$request = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $post->ID );
		$request->set_body_params( [ 'password' => 'updated-rest-password' ] );
		$response = $this->rest_server()->dispatch( $request );

		self::assertSame( 200, $response->get_status() );
		self::assertSame( 'updated-rest-password', get_post( $post->ID )->post_password );
	}

	/**
	 * An exempt password form preserves native explicit REST authorization.
	 *
	 * @return void
	 */
	public function test_rest_password_guard_respects_form_exemption(): void {
		$post = $this->create_post();

		add_filter(
			'hcap_protect_form',
			static function ( $protect, $source, $form_id ) {
				return 'password_protected' === $form_id ? false : $protect;
			},
			20,
			3
		);

		$request = new WP_REST_Request( 'GET', '/wp/v2/posts/' . $post->ID );
		$request->set_query_params( [ 'password' => self::PASSWORD ] );
		$response = $this->rest_server()->dispatch( $request );

		self::assertSame( 200, $response->get_status() );
		self::assertStringContainsString( self::CONTENT, $response->get_data()['content']['rendered'] );
	}

	/**
	 * Editors retain core REST access to protected content in edit context.
	 *
	 * @return void
	 */
	public function test_native_rest_editor_authorization_is_preserved(): void {
		$post    = $this->create_post();
		$request = new WP_REST_Request( 'GET', '/wp/v2/posts/' . $post->ID );

		wp_set_current_user( $this->factory()->user->create( [ 'role' => 'editor' ] ) );
		$request->set_query_params( [ 'context' => 'edit' ] );

		$response = $this->rest_server()->dispatch( $request );
		$data     = $response->get_data();

		self::assertSame( 200, $response->get_status() );
		self::assertSame( self::CONTENT, $data['content']['raw'] );
		self::assertStringContainsString( self::CONTENT, $data['content']['rendered'] );
	}

	/**
	 * Native slashing does not change the password scope of issued proof.
	 *
	 * @return void
	 */
	public function test_proof_preserves_passwords_with_quotes_and_backslashes(): void {
		$password = "native'password\\scope";
		$post     = $this->create_post( wp_slash( $password ) );
		$cookie   = $this->issue_proof( $password );

		$_COOKIE[ $this->proof_cookie_name() ] = $cookie['value'];

		$this->set_native_cookie( $password );

		self::assertFalse( post_password_required( $post ) );
	}

	/**
	 * Feed content inherits the same native password-cookie protection.
	 *
	 * @return void
	 */
	public function test_native_feed_content_rejects_an_unproven_cookie(): void {
		$post          = $this->create_post();
		$previous_post = $GLOBALS['post'] ?? null;

		$this->set_native_cookie( self::PASSWORD );

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Set native feed context.
		$GLOBALS['post'] = $post;

		try {
			self::assertStringNotContainsString( self::CONTENT, get_the_content_feed( 'rss2' ) );
		} finally {
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restore native feed context.
			$GLOBALS['post'] = $previous_post;
		}
	}

	/**
	 * Native REST comments reject an unproven cookie and explicit passwords.
	 *
	 * @return void
	 */
	public function test_native_rest_comment_password_authorization_is_preserved(): void {
		$post       = $this->create_post();
		$comment_id = $this->factory()->comment->create(
			[
				'comment_post_ID'  => $post->ID,
				'comment_approved' => '1',
				'comment_content'  => 'Native protected comment marker.',
			]
		);

		$this->set_native_cookie( self::PASSWORD );

		$server   = $this->rest_server();
		$request  = new WP_REST_Request( 'GET', '/wp/v2/comments/' . $comment_id );
		$response = $server->dispatch( $request );

		self::assertSame( 401, $response->get_status() );
		self::assertSame( 'rest_cannot_read', $response->get_data()['code'] );

		$request->set_query_params( [ 'password' => self::PASSWORD ] );
		$response = $server->dispatch( $request );

		self::assertSame( 403, $response->get_status() );
		self::assertSame( 'hcaptcha_rest_password_forbidden', $response->get_data()['code'] );
	}

	/**
	 * Create a published post with recognizable protected text.
	 *
	 * @param string $password Post password.
	 *
	 * @return WP_Post
	 */
	private function create_post( string $password = self::PASSWORD ): WP_Post {
		return $this->factory()->post->create_and_get(
			[
				'post_status'   => 'publish',
				'post_password' => $password,
				'post_content'  => self::CONTENT,
				'post_excerpt'  => self::EXCERPT,
			]
		);
	}

	/**
	 * Supply a real, independently generated native portable password hash.
	 *
	 * @param string $password Candidate password.
	 *
	 * @return void
	 */
	private function set_native_cookie( string $password ): void {
		require_once ABSPATH . WPINC . '/class-phpass.php';

		$hasher = new PasswordHash( 8, true );

		$_COOKIE[ $this->native_cookie_name() ] = wp_slash( $hasher->HashPassword( $password ) );
	}

	/**
	 * Get the native password cookie name.
	 *
	 * @return string
	 */
	private function native_cookie_name(): string {
		return 'wp-postpass_' . COOKIEHASH;
	}

	/**
	 * Get the companion CAPTCHA proof cookie name.
	 *
	 * @return string
	 */
	private function proof_cookie_name(): string {
		return 'hcaptcha_postpass_' . COOKIEHASH;
	}

	/**
	 * Prepare the actual postpass verification fields and mocked remote response.
	 *
	 * @param string $password Password submitted to core.
	 * @param bool   $verified Remote challenge result.
	 *
	 * @return void
	 */
	private function prepare_postpass( string $password, bool $verified = true ): void {
		$this->prepare_verify_post( 'hcaptcha_password_protected_nonce', 'hcaptcha_password_protected', $verified );

		$_POST['post_password']                = wp_slash( $password );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'WordPress' ],
				'form_id' => 'password_protected',
			]
		);
	}

	/**
	 * Obtain proof through the verifier and native expiry hooks.
	 *
	 * @param string   $password Submitted password.
	 * @param int|null $expires  Native cookie expiry.
	 *
	 * @return array
	 */
	private function issue_proof( string $password, ?int $expires = null ): array {
		$cookies = [];
		$expires = $expires ?? time() + 10 * DAY_IN_SECONDS;

		$this->capture_proof_cookies( $cookies );
		$this->prepare_postpass( $password );

		do_action( 'login_form_postpass' );

		$filtered_expires = apply_filters( 'post_password_expires', $expires );

		self::assertCount( 1, $cookies );
		self::assertSame( $filtered_expires, $cookies[0]['options']['expires'] );
		self::assertNotSame( '', $cookies[0]['value'] );

		// Simulate the subsequent content request, which carries cookies without POST fields.
		$_POST = [];

		return $cookies[0];
	}

	/**
	 * Capture production cookie issuance without emitting test response headers.
	 *
	 * @param array $cookies Captured companion-cookie calls.
	 *
	 * @return void
	 */
	private function capture_proof_cookies( array &$cookies ): void {
		$proof_name = $this->proof_cookie_name();

		redefine(
			'setcookie',
			static function ( $name, $value = '', $options = 0, $path = '', $domain = '', $secure = false, $httponly = false ) use ( &$cookies, $proof_name ) {
				if ( $proof_name === $name ) {
					$cookies[] = [
						'value'   => $value,
						'options' => is_array( $options ) ? $options : [
							'expires'  => $options,
							'path'     => $path,
							'domain'   => $domain,
							'secure'   => $secure,
							'httponly' => $httponly,
						],
					];
				}

				return true;
			}
		);
	}

	/**
	 * Register the actual WordPress REST routes in an isolated registry.
	 *
	 * @return WP_REST_Server
	 */
	private function rest_server(): WP_REST_Server {
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Isolate the native REST route registry.
		$GLOBALS['wp_rest_server'] = null;

		return rest_get_server();
	}
}
