<?php
/**
 * ProtectContentTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\ProtectContent;

use HCaptcha\PasswordProtected\Protect;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\ProtectContent\ProtectContent;
use HCaptcha\Settings\Advanced;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use Mockery;
use ReflectionException;
use tad\FunctionMocker\FunctionMocker;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;
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
	 * REST post and page responses use the selected resource's clearance scope.
	 *
	 * @return void
	 */
	public function test_rest_posts_and_pages_require_resource_clearance(): void {
		$post_id    = $this->factory()->post->create(
			[
				'post_status'  => 'publish',
				'post_content' => 'Protected REST post marker.',
			]
		);
		$page_id    = $this->factory()->post->create(
			[
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => 'Protected REST page marker.',
			]
		);
		$public_id  = $this->factory()->post->create( [ 'post_status' => 'publish' ] );
		$post_scope = get_permalink( $post_id );
		$page_scope = get_permalink( $page_id );

		$this->configure_protected_urls( $post_scope . "\n" . $page_scope );
		$_SERVER['REQUEST_URI'] = '/wp-json/wp/v2/posts';
		$subject                = new ProtectContent();
		$subject->init();

		$previous_server = $GLOBALS['wp_rest_server'] ?? null;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Isolate the native REST registry.
		$GLOBALS['wp_rest_server'] = null;

		try {
			$server          = rest_get_server();
			$post_response   = $server->dispatch( new WP_REST_Request( 'GET', '/wp/v2/posts/' . $post_id ) );
			$page_response   = $server->dispatch( new WP_REST_Request( 'GET', '/wp/v2/pages/' . $page_id ) );
			$public_response = $server->dispatch( new WP_REST_Request( 'GET', '/wp/v2/posts/' . $public_id ) );

			self::assertSame( 403, $post_response->get_status() );
			self::assertSame( 'hcaptcha_content_protected', $post_response->get_data()['code'] );
			self::assertSame( 403, $page_response->get_status() );
			self::assertSame( 'hcaptcha_content_protected', $page_response->get_data()['code'] );
			self::assertSame( 200, $public_response->get_status() );

			wp_set_current_user( $this->factory()->user->create( [ 'role' => 'editor' ] ) );
			$edit_request = new WP_REST_Request( 'GET', '/wp/v2/posts/' . $post_id );
			$edit_request->set_query_params( [ 'context' => 'edit' ] );
			$edit_response = $server->dispatch( $edit_request );

			self::assertSame( 200, $edit_response->get_status() );
			self::assertSame( 'Protected REST post marker.', $edit_response->get_data()['content']['raw'] );
			wp_set_current_user( 0 );

			$collection = new WP_REST_Request( 'GET', '/wp/v2/posts' );
			$collection->set_query_params( [ '_fields' => 'content' ] );
			$collection_response      = $server->dispatch( $collection );
			$page_collection_response = $server->dispatch( new WP_REST_Request( 'GET', '/wp/v2/pages' ) );

			self::assertSame( 200, $collection_response->get_status() );
			self::assertStringNotContainsString( 'Protected REST post marker.', wp_json_encode( $collection_response->get_data() ) );
			self::assertSame( 200, $page_collection_response->get_status() );
			self::assertStringNotContainsString( 'Protected REST page marker.', wp_json_encode( $page_collection_response->get_data() ) );

			$session_id                           = 'v1.' . str_repeat( 'a', 64 );
			$_COOKIE[ self::SESSION_COOKIE_NAME ] = $session_id;
			$_COOKIE[ self::COOKIE_NAME ]         = $this->create_clearance_cookie( str_repeat( 'b', 64 ), $session_id, $post_scope, time() + 300 );

			$cleared_response     = $server->dispatch( new WP_REST_Request( 'GET', '/wp/v2/posts/' . $post_id ) );
			$cleared_collection   = $server->dispatch( new WP_REST_Request( 'GET', '/wp/v2/posts' ) );
			$other_scope_response = $server->dispatch( new WP_REST_Request( 'GET', '/wp/v2/pages/' . $page_id ) );

			self::assertSame( 200, $cleared_response->get_status() );
			self::assertStringContainsString( 'Protected REST post marker.', $cleared_response->get_data()['content']['rendered'] );
			self::assertSame( 200, $cleared_collection->get_status() );
			self::assertStringContainsString( 'Protected REST post marker.', wp_json_encode( $cleared_collection->get_data() ) );
			self::assertSame( 403, $other_scope_response->get_status() );
		} finally {
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restore the native REST registry.
			$GLOBALS['wp_rest_server'] = $previous_server;
		}
	}

	/**
	 * REST search must not expose protected results or keyword match counts.
	 *
	 * @return void
	 */
	public function test_rest_search_excludes_uncleared_resources_before_pagination(): void {
		$protected_post = $this->factory()->post->create(
			[
				'post_status'  => 'publish',
				'post_title'   => 'Restindexneedle hidden post',
				'post_content' => 'Restindexsecretword',
			]
		);
		$protected_page = $this->factory()->post->create(
			[
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => 'Restindexneedle hidden page',
			]
		);
		$public_post    = $this->factory()->post->create(
			[
				'post_status' => 'publish',
				'post_title'  => 'Restindexneedle public post',
			]
		);
		$post_scope     = get_permalink( $protected_post );
		$page_scope     = get_permalink( $protected_page );

		$this->configure_protected_urls( $post_scope . "\n" . $page_scope );
		$_SERVER['REQUEST_URI'] = '/wp-json/wp/v2/search';
		$subject                = new ProtectContent();
		$subject->init();

		$previous_server = $GLOBALS['wp_rest_server'] ?? null;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Isolate the native REST registry.
		$GLOBALS['wp_rest_server'] = null;

		try {
			$server = rest_get_server();
			$search = new WP_REST_Request( 'GET', '/wp/v2/search' );
			$search->set_query_params(
				[
					'search'   => 'Restindexneedle',
					'per_page' => 1,
					'_fields'  => 'title',
				]
			);
			$response = $server->dispatch( $search );

			self::assertSame( 200, $response->get_status() );
			self::assertCount( 1, $response->get_data() );
			self::assertSame( 'Restindexneedle public post', $response->get_data()[0]['title'] );
			self::assertSame( 1, $response->get_headers()['X-WP-Total'] );
			self::assertSame( 1, $response->get_headers()['X-WP-TotalPages'] );

			$second_page = new WP_REST_Request( 'GET', '/wp/v2/search' );
			$second_page->set_query_params(
				[
					'search'   => 'Restindexneedle',
					'per_page' => 1,
					'page'     => 2,
				]
			);
			$second_page_response = $server->dispatch( $second_page );

			self::assertSame( 200, $second_page_response->get_status() );
			self::assertSame( [], $second_page_response->get_data() );
			self::assertSame( 0, $second_page_response->get_headers()['X-WP-Total'] );

			$secret = new WP_REST_Request( 'GET', '/wp/v2/search' );
			$secret->set_query_params( [ 'search' => 'Restindexsecretword' ] );
			$secret_response = $server->dispatch( $secret );

			self::assertSame( [], $secret_response->get_data() );
			self::assertSame( 0, $secret_response->get_headers()['X-WP-Total'] );

			$post_collection = new WP_REST_Request( 'GET', '/wp/v2/posts' );
			$post_collection->set_query_params(
				[
					'search'  => 'Restindexneedle',
					'_fields' => 'id',
				]
			);
			$post_collection_response = $server->dispatch( $post_collection );

			self::assertSame( 200, $post_collection_response->get_status() );
			self::assertSame( [ [ 'id' => $public_post ] ], $post_collection_response->get_data() );
			self::assertSame( 1, $post_collection_response->get_headers()['X-WP-Total'] );

			$post_collection->set_query_params( [ 'search' => 'Restindexsecretword' ] );
			$post_secret_response = $server->dispatch( $post_collection );

			self::assertSame( [], $post_secret_response->get_data() );
			self::assertSame( 0, $post_secret_response->get_headers()['X-WP-Total'] );

			$page_collection = new WP_REST_Request( 'GET', '/wp/v2/pages' );
			$page_collection->set_query_params( [ 'search' => 'Restindexneedle' ] );
			$page_collection_response = $server->dispatch( $page_collection );

			self::assertSame( [], $page_collection_response->get_data() );
			self::assertSame( 0, $page_collection_response->get_headers()['X-WP-Total'] );

			$slug_request = new WP_REST_Request( 'HEAD', '/wp/v2/posts' );
			$slug_request->set_query_params( [ 'slug' => get_post_field( 'post_name', $protected_post ) ] );
			$slug_response = $server->dispatch( $slug_request );

			self::assertSame( 200, $slug_response->get_status() );
			self::assertSame( 0, $slug_response->get_headers()['X-WP-Total'] );

			$include = new WP_REST_Request( 'GET', '/wp/v2/search' );
			$include->set_query_params(
				[
					'include' => [ $protected_post, $public_post ],
					'_fields' => 'id',
				]
			);
			$include_response = $server->dispatch( $include );

			self::assertSame( [ [ 'id' => $public_post ] ], $include_response->get_data() );
			self::assertSame( 1, $include_response->get_headers()['X-WP-Total'] );

			$include->set_query_params( [ 'include' => [ $protected_post ] ] );
			$hidden_include_response = $server->dispatch( $include );

			self::assertSame( [], $hidden_include_response->get_data() );
			self::assertSame( 0, $hidden_include_response->get_headers()['X-WP-Total'] );

			$page_search = new WP_REST_Request( 'HEAD', '/wp/v2/search' );
			$page_search->set_query_params(
				[
					'search'  => 'Restindexneedle',
					'subtype' => 'page',
				]
			);
			$page_response = $server->dispatch( $page_search );

			self::assertSame( 0, $page_response->get_headers()['X-WP-Total'] );

			$session_id                           = 'v1.' . str_repeat( 'a', 64 );
			$_COOKIE[ self::SESSION_COOKIE_NAME ] = $session_id;
			$_COOKIE[ self::COOKIE_NAME ]         = $this->create_clearance_cookie( str_repeat( 'b', 64 ), $session_id, $post_scope, time() + 300 );
			$cleared_response                     = $server->dispatch( $search );

			self::assertSame( 2, $cleared_response->get_headers()['X-WP-Total'] );
		} finally {
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restore the native REST registry.
			$GLOBALS['wp_rest_server'] = $previous_server;
		}
	}

	/**
	 * Broad REST searches inspect every batch without rejecting the request.
	 *
	 * @return void
	 */
	public function test_rest_search_filters_candidates_beyond_first_batch(): void {
		$protected_post = $this->factory()->post->create( [ 'post_status' => 'publish' ] );
		$public_post    = $this->factory()->post->create( [ 'post_status' => 'publish' ] );
		$this->configure_protected_urls( get_permalink( $protected_post ) );
		$_SERVER['REQUEST_URI'] = '/wp-json/wp/v2/search';
		$subject                = new ProtectContent();
		$subject->init();
		$request = new WP_REST_Request( 'GET', '/wp/v2/search' );
		$args    = [
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => 10,
		];
		$offsets = [];
		$pretend = static function ( $posts, WP_Query $query ) use ( &$offsets, $protected_post, $public_post ) {
			if ( 'ids' === $query->get( 'fields' ) ) {
				self::assertSame( 500, $query->get( 'posts_per_page' ) );
				$offset    = (int) $query->get( 'offset' );
				$offsets[] = $offset;

				return 1000 === $offset ? [ $protected_post ] : array_fill( 0, 500, $public_post );
			}

			return $posts;
		};

		add_filter( 'posts_pre_query', $pretend, 10, 2 );

		try {
			$result = $subject->protect_rest_query( $args, $request );

			self::assertSame( [ 0, 500, 1000 ], $offsets );
			self::assertSame( [ $protected_post ], $result['post__not_in'] );
			self::assertSame( 10, $result['posts_per_page'] );
			$response = new WP_REST_Response( [] );
			self::assertSame( $response, $subject->protect_rest_response( $response, [], $request ) );
		} finally {
			remove_filter( 'posts_pre_query', $pretend );
		}

		$included     = new WP_REST_Request( 'GET', '/wp/v2/search' );
		$included_ids = array_merge( array_fill( 0, 1000, $public_post ), [ $protected_post ] );
		$result       = $subject->protect_rest_query( [ 'post__in' => $included_ids ], $included );

		self::assertSame( array_fill( 0, 1000, $public_post ), array_values( $result['post__in'] ) );
	}

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

		// The final WordPress route can be protected even when the raw URI is not.
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

		self::assertSame( -PHP_INT_MAX, has_action( 'template_redirect', [ $subject, 'protect_content' ] ) );

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
	 * Configured expressions and literal URLs select the intended clearance scope.
	 *
	 * @dataProvider dp_test_init_matches_url_rules
	 *
	 * @param string $rules Rules separated by newlines.
	 * @param string $uri   Request URI.
	 * @param string $scope Expected matching rule.
	 */
	public function test_init_matches_url_rules( string $rules, string $uri, string $scope ): void {
		update_option(
			'hcaptcha_settings',
			[
				'protect_content' => [ 'on' ],
				'protected_urls'  => $rules,
			]
		);
		hcaptcha()->init_hooks();
		$_SERVER['REQUEST_URI'] = $uri;
		$subject                = new ProtectContent();
		$subject->init();

		self::assertSame( $scope, $this->get_protected_property( $subject, 'resource_scope' ) );
		self::assertSame( -PHP_INT_MAX, has_action( 'template_redirect', [ $subject, 'protect_content' ] ) );
	}

	/**
	 * URL rules retain literal compatibility and regular expression semantics.
	 *
	 * @return array
	 */
	public function dp_test_init_matches_url_rules(): array {
		$expression = '/private/(members|reports)/[0-9]+';
		$limited    = '(*NO_AUTO_POSSESS)(*LIMIT_MATCH=10)https?://.*/(a+)+$';
		$preflight  = '(*NO_START_OPT)(*LIMIT_MATCH=1)(?:/private/.*|(?:a?)*)';

		return [
			'wildcard'          => [ '/private/.*', '/private/welcome', '/private/.*' ],
			'alternation'       => [ $expression, '/private/reports/42', $expression ],
			'nonmatch'          => [ $expression, '/public/welcome', '' ],
			'case variation'    => [ '/private/.*', '/PRIVATE/welcome', '/private/.*' ],
			'encoded path'      => [ '/private/.*', '/%70rivate/welcome', '/private/.*' ],
			'encoded literal'   => [ '/private/%61ccount', '/private/%61ccount', '/private/%61ccount' ],
			'decoded literal'   => [ '/private/%61ccount', '/private/account', '/private/%61ccount' ],
			'literal query'     => [ '/private/file.php?download=1', '/private/file.php?download=1', '/private/file.php?download=1' ],
			'absolute URL'      => [ home_url( '/private/.*' ), '/private/welcome', home_url( '/private/.*' ) ],
			'delimiter'         => [ '/private/!.*', '/private/!welcome', '/private/!.*' ],
			'escaped delimiter' => [ '/private/\!.*', '/private/!welcome', '/private/\!.*' ],
			'delimiter choice'  => [ '/private/[~#!%@;]+', '/private/~', '/private/[~#!%@;]+' ],
			'invalid literal'   => [ '/private/[', '/private/[', '/private/[' ],
			'invalid nonmatch'  => [ '/private/[', '/public/welcome', '' ],
			'longest scope'     => [ "/private/.*\n/private/reports/[0-9]+", '/private/reports/42', '/private/reports/[0-9]+' ],
			'CRLF whitespace'   => [ "\r\n /private/.* \r\n", '/private/welcome', '/private/.*' ],
			'empty list'        => [ "\r\n \t", '/public/welcome', '/' ],
			'execution error'   => [ $limited, '/' . str_repeat( 'a', 200 ) . 'X', $limited ],
			'preflight error'   => [ $preflight, '/private/welcome', $preflight ],
		];
	}

	/**
	 * Named regex groups survive the actual settings sanitizer and textarea render.
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_named_group_survives_settings_round_trip(): void {
		$rule     = '/private/(?<id>[0-9]+)';
		$text     = $rule . "\n</textarea><script>alert(1)</script>";
		$settings = new Advanced();
		$value    = $settings->sanitize_option_callback(
			[
				'protect_content' => [ 'on' ],
				'protected_urls'  => $text,
			]
		);

		self::assertSame( $text, $value['protected_urls'] );
		update_option( 'hcaptcha_settings', $value );
		hcaptcha()->init_hooks();
		$settings = new Advanced();
		$method   = $this->set_method_accessibility( $settings, 'print_textarea_field' );

		ob_start();
		$method->invoke(
			$settings,
			[
				'field_id'      => 'protected_urls',
				'placeholder'   => '',
				'disabled'      => false,
				'preserve_text' => true,
			]
		);
		$html = ob_get_clean();
		$method->setAccessible( false );

		self::assertStringContainsString( esc_textarea( $text ), $html );
		self::assertStringNotContainsString( '<id>', $html );
		self::assertStringNotContainsString( '<script>', $html );
		self::assertSame( 1, substr_count( $html, '</textarea>' ) );
		$_SERVER['REQUEST_URI'] = '/private/42';
		$subject                = new ProtectContent();
		$subject->init();

		self::assertSame( $rule, $this->get_protected_property( $subject, 'resource_scope' ) );
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
			untrailingslashit( home_url() ) . '//attacker.example/anything?foo=bar',
			$method->invoke( $subject, '//attacker.example/anything?foo=bar' )
		);

		$method->setAccessible( false );
	}

	/**
	 * A leading double slash stays a path on the site, not another origin.
	 *
	 * @return void
	 */
	public function test_leading_double_slash_is_protected_on_site_origin(): void {
		$this->configure_protected_urls( '/private' );
		$_SERVER['REQUEST_URI'] = '//attacker.example/private';
		$subject                = new ProtectContent();
		$subject->init();

		self::assertSame( '/private', $this->get_protected_property( $subject, 'resource_scope' ) );
		self::assertSame(
			untrailingslashit( home_url() ) . '//attacker.example/private',
			$this->get_protected_property( $subject, 'request_uri' )
		);
		remove_action( 'template_redirect', [ $subject, 'protect_content' ], -PHP_INT_MAX );
	}

	/**
	 * A plain page selector is challenged when WordPress resolves a protected page.
	 *
	 * @return void
	 */
	public function test_resolved_protected_page_challenges_unprotected_raw_uri(): void {
		$this->with_queried_page(
			'private-page',
			function ( int $page_id ): void {
				$this->configure_protected_urls( '/private-page' );
				$_SERVER['REQUEST_URI']    = '/?page_id=' . $page_id;
				$_SERVER['REQUEST_METHOD'] = 'GET';

				$subject = Mockery::mock( ProtectContent::class )->makePartial();
				$subject->shouldAllowMockingProtectedMethods();
				$subject->shouldReceive( 'is_valid_cookie' )->once()->andReturnFalse();
				$subject->shouldReceive( 'ensure_session_cookie' )->once();
				$subject->shouldReceive( 'show_protection_page' )->once();
				$subject->shouldNotReceive( 'verify' );
				$subject->init();

				self::assertSame( '', $this->get_protected_property( $subject, 'resource_scope' ) );
				$subject->protect_content();
				self::assertSame( '/private-page', $this->get_protected_property( $subject, 'resource_scope' ) );
				self::assertSame( get_permalink( $page_id ), $this->get_protected_property( $subject, 'request_uri' ) );
				remove_action( 'template_redirect', [ $subject, 'protect_content' ], -PHP_INT_MAX );
			}
		);
	}

	/**
	 * The routed page restores a protected scope when the direct scope is empty.
	 */
	public function test_resolved_page_restores_empty_direct_scope(): void {
		$this->with_queried_page(
			'private-page',
			function (): void {
				$this->configure_protected_urls( '/private-page' );
				$_SERVER['REQUEST_URI'] = '/private-page/';
				$subject                = new ProtectContent();
				$subject->init();
				$this->set_protected_property( $subject, 'resource_scope', '' );
				$method = $this->set_method_accessibility( $subject, 'resolve_resource_scope' );
				$method->invoke( $subject );

				self::assertSame( '/private-page', $this->get_protected_property( $subject, 'resource_scope' ) );
			}
		);
	}

	/**
	 * Rules containing every available regex delimiter cannot become expressions.
	 */
	public function test_url_rule_with_no_available_delimiter(): void {
		$subject = new ProtectContent();
		$method  = $this->set_method_accessibility( $subject, 'matches_protected_url' );
		$rule    = '~#!%@;' . "\x01";

		self::assertFalse( $method->invoke( $subject, $rule, '/public-page' ) );
	}

	/**
	 * A clearance for one page does not unlock a POST to another protected page.
	 *
	 * @return void
	 */
	public function test_resolved_post_rejects_clearance_from_another_scope(): void {
		$this->with_queried_page(
			'private-page',
			function ( int $page_id ): void {
				$this->factory()->post->create(
					[
						'post_type'   => 'page',
						'post_status' => 'publish',
						'post_name'   => 'members-page',
					]
				);
				$this->configure_protected_urls( "/members-page\n/private-page" );

				$session_id                           = 'v1.' . str_repeat( 'a', 64 );
				$_COOKIE[ self::SESSION_COOKIE_NAME ] = $session_id;
				$_COOKIE[ self::COOKIE_NAME ]         = $this->create_clearance_cookie(
					str_repeat( 'b', 64 ),
					$session_id,
					'/members-page',
					time() + 300
				);
				$_SERVER['REQUEST_URI']               = '/?page_id=' . $page_id;
				$_SERVER['REQUEST_METHOD']            = 'POST';

				$subject = Mockery::mock( ProtectContent::class )->makePartial();
				$subject->shouldAllowMockingProtectedMethods();
				$subject->shouldReceive( 'ensure_session_cookie' )->once();
				$subject->shouldReceive( 'verify' )->once()->andReturn( 'Challenge required.' );
				$subject->shouldReceive( 'show_protection_page' )->once();
				$subject->init();

				$this->set_protected_property( $subject, 'resource_scope', '/members-page' );
				$subject->shouldAllowMockingProtectedMethods();
				self::assertTrue( $subject->is_valid_cookie() );
				$this->set_protected_property( $subject, 'resource_scope', '' );

				$subject->protect_content();
				self::assertSame( '/private-page', $this->get_protected_property( $subject, 'resource_scope' ) );
				$subject->shouldAllowMockingProtectedMethods();
				self::assertFalse( $subject->is_valid_cookie() );
				self::assertSame( 'Challenge required.', $this->get_protected_property( $subject, 'error_message' ) );
				self::assertSame( get_permalink( $page_id ), $this->get_protected_property( $subject, 'request_uri' ) );
				remove_action( 'template_redirect', [ $subject, 'protect_content' ], -PHP_INT_MAX );
			}
		);
	}

	/**
	 * A resolved public page without a matching rule is not challenged.
	 *
	 * @return void
	 */
	public function test_resolved_unprotected_page_is_not_challenged(): void {
		$this->with_queried_page(
			'public-page',
			function ( int $page_id ): void {
				$this->configure_protected_urls( '/private-page' );
				$_SERVER['REQUEST_URI'] = '/?page_id=' . $page_id;

				$subject = Mockery::mock( ProtectContent::class )->makePartial();
				$subject->shouldAllowMockingProtectedMethods();
				$subject->shouldNotReceive( 'is_valid_cookie' );
				$subject->shouldNotReceive( 'ensure_session_cookie' );
				$subject->shouldNotReceive( 'show_protection_page' );
				$subject->init();
				$subject->protect_content();

				self::assertSame( '', $this->get_protected_property( $subject, 'resource_scope' ) );
				remove_action( 'template_redirect', [ $subject, 'protect_content' ], -PHP_INT_MAX );
			}
		);
	}

	/**
	 * A query-specific direct rule remains distinct from the resolved permalink.
	 *
	 * @return void
	 */
	public function test_query_specific_direct_rule_survives_resolved_page(): void {
		$this->with_queried_page(
			'reports',
			function (): void {
				$this->configure_protected_urls( '/reports/?download=1' );
				$_SERVER['REQUEST_URI'] = '/reports/?download=1';

				$subject = Mockery::mock( ProtectContent::class )->makePartial();
				$subject->shouldAllowMockingProtectedMethods();
				$subject->shouldReceive( 'is_valid_cookie' )->once()->andReturnTrue();
				$subject->shouldNotReceive( 'show_protection_page' );
				$subject->init();
				$subject->protect_content();

				self::assertSame( '/reports/?download=1', $this->get_protected_property( $subject, 'resource_scope' ) );
				self::assertSame(
					home_url( '/reports/?download=1' ),
					$this->get_protected_property( $subject, 'request_uri' )
				);

				$_SERVER['REQUEST_URI'] = '/reports/';
				$plain_subject          = Mockery::mock( ProtectContent::class )->makePartial();
				$plain_subject->shouldAllowMockingProtectedMethods();
				$plain_subject->shouldNotReceive( 'is_valid_cookie' );
				$plain_subject->shouldNotReceive( 'show_protection_page' );
				$plain_subject->init();
				$plain_subject->protect_content();

				self::assertSame( '', $this->get_protected_property( $plain_subject, 'resource_scope' ) );
				remove_action( 'template_redirect', [ $subject, 'protect_content' ], -PHP_INT_MAX );
				remove_action( 'template_redirect', [ $plain_subject, 'protect_content' ], -PHP_INT_MAX );
			}
		);
	}

	/**
	 * A rule appearing only in a query value cannot reuse another page's clearance.
	 *
	 * @return void
	 */
	public function test_query_value_cannot_switch_resolved_page_clearance_scope(): void {
		$this->with_queried_page(
			'private-page',
			function (): void {
				$this->configure_protected_urls( "/private-page\n/members-page-long" );

				$session_id                           = 'v1.' . str_repeat( 'a', 64 );
				$_COOKIE[ self::SESSION_COOKIE_NAME ] = $session_id;
				$_COOKIE[ self::COOKIE_NAME ]         = $this->create_clearance_cookie(
					str_repeat( 'b', 64 ),
					$session_id,
					'/members-page-long',
					time() + 300
				);

				$_SERVER['REQUEST_URI']    = '/private-page/?next=/members-page-long';
				$_SERVER['REQUEST_METHOD'] = 'GET';

				$subject = Mockery::mock( ProtectContent::class )->makePartial();
				$subject->shouldAllowMockingProtectedMethods();
				$subject->shouldReceive( 'ensure_session_cookie' )->once();
				$subject->shouldReceive( 'show_protection_page' )->once();
				$subject->init();

				self::assertSame( '/members-page-long', $this->get_protected_property( $subject, 'resource_scope' ) );
				$subject->shouldAllowMockingProtectedMethods();
				self::assertTrue( $subject->is_valid_cookie() );
				$subject->protect_content();
				self::assertSame( "/private-page\n/members-page-long", $this->get_protected_property( $subject, 'resource_scope' ) );
				$subject->shouldAllowMockingProtectedMethods();
				self::assertFalse( $subject->is_valid_cookie() );
				remove_action( 'template_redirect', [ $subject, 'protect_content' ], -PHP_INT_MAX );
			}
		);
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
		$this->set_protected_property( $subject, 'resource_scope', '/protected-content' );
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
		$this->prepare_content_verification( $verified );

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
		$this->prepare_content_verification();

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

		$subject->shouldAllowMockingProtectedMethods();
		self::assertFalse( $subject->is_valid_cookie() );

		$time = time();
		FunctionMocker::replace( 'time', $time );

		$_COOKIE[ self::COOKIE_NAME ] = $time . '|' . wp_hash( $time );

		$subject->shouldAllowMockingProtectedMethods();
		self::assertFalse( $subject->is_valid_cookie() );

		$_COOKIE[ self::COOKIE_NAME ]         = [ 'v2.invalid' ];
		$_COOKIE[ self::SESSION_COOKIE_NAME ] = 'v1.' . str_repeat( 'a', 64 );

		$subject->shouldAllowMockingProtectedMethods();
		self::assertFalse( $subject->is_valid_cookie() );

		// A valid, unexpired token works in its bound session and scope.
		$token      = str_repeat( 'b', 64 );
		$session_id = 'v1.' . str_repeat( 'a', 64 );
		$cookie     = $this->create_clearance_cookie( $token, $session_id, '/members', $time + 300 );

		$_COOKIE[ self::COOKIE_NAME ]         = $cookie;
		$_COOKIE[ self::SESSION_COOKIE_NAME ] = $session_id;

		self::assertTrue( $subject->is_valid_cookie() );

		// A structurally invalid token is rejected before its signature is checked.
		$_COOKIE[ self::COOKIE_NAME ] = str_replace( $token, str_repeat( 'g', 64 ), $cookie );
		self::assertFalse( $subject->is_valid_cookie() );

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
	 * Failure to obtain entropy must not produce a clearance token.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 * @group process-isolation-php74
	 */
	public function test_generate_token_fails_closed_when_entropy_fails(): void {
		$subject = new ProtectContent();
		require_once dirname( __DIR__ ) . '/Stubs/ProtectContent/random_bytes.php';

		$method = $this->set_method_accessibility( $subject, 'generate_token' );
		self::assertSame( '', $method->invoke( $subject ) );
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
		$this->prepare_content_verification();

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

		$subject->shouldAllowMockingProtectedMethods();
		self::assertSame( '', $subject->verify() );
		self::assertSame( [ self::COOKIE_NAME => $cookie ], $response_cookies );

		$_COOKIE = array_merge( $_COOKIE, $response_cookies );

		$subject->shouldAllowMockingProtectedMethods();
		self::assertTrue( $subject->is_valid_cookie() );

		// Fresh client receives the complete successful clearance response.
		$_COOKIE = $response_cookies;

		$subject->shouldAllowMockingProtectedMethods();
		self::assertFalse( $subject->is_valid_cookie() );

		// Even an independently established session cannot use session A's token.
		$_COOKIE[ self::SESSION_COOKIE_NAME ] = $session_b;

		$subject->shouldAllowMockingProtectedMethods();
		self::assertFalse( $subject->is_valid_cookie() );

		$_COOKIE[ self::SESSION_COOKIE_NAME ] = $session_a;

		$subject->shouldAllowMockingProtectedMethods();
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

		$subject->shouldAllowMockingProtectedMethods();
		self::assertTrue( $subject->is_valid_cookie() );

		$subject = $this->init_subject_for_uri( '/members/account' );

		$subject->shouldAllowMockingProtectedMethods();
		self::assertTrue( $subject->is_valid_cookie() );

		$subject = $this->init_subject_for_uri( '/private/welcome' );

		$subject->shouldAllowMockingProtectedMethods();
		self::assertFalse( $subject->is_valid_cookie() );
	}

	/**
	 * Enable content protection for the given URL rules.
	 *
	 * @param string $rules URL rules.
	 *
	 * @return void
	 */
	private function configure_protected_urls( string $rules ): void {
		update_option(
			'hcaptcha_settings',
			[
				'protect_content' => [ 'on' ],
				'protected_urls'  => $rules,
			]
		);
		hcaptcha()->init_hooks();
	}

	/**
	 * Run assertions with a published page selected by the main WordPress query.
	 *
	 * @param string   $slug       Page slug.
	 * @param callable $assertions Assertions to run with the selected page ID.
	 *
	 * @return void
	 */
	private function with_queried_page( string $slug, callable $assertions ): void {
		$old_structure = (string) get_option( 'permalink_structure' );
		$old_query     = $GLOBALS['wp_query'] ?? null;

		try {
			$this->set_permalink_structure( '/%postname%/' );
			$page_id = $this->factory()->post->create(
				[
					'post_type'   => 'page',
					'post_status' => 'publish',
					'post_name'   => $slug,
				]
			);
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$GLOBALS['wp_query'] = new WP_Query( [ 'page_id' => $page_id ] );

			self::assertTrue( is_singular( 'page' ) );
			self::assertSame( $page_id, get_queried_object_id() );
			$assertions( $page_id );
		} finally {
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$GLOBALS['wp_query'] = $old_query;
			$this->set_permalink_structure( $old_structure );
		}
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
		$key     = wp_salt();
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
	 * @return ProtectContent|Mockery\MockInterface
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
	 * Prepare a submission from the content protection renderer.
	 *
	 * @param bool $verified Remote challenge result.
	 */
	private function prepare_content_verification( bool $verified = true ): void {
		$this->prepare_verify_post( 'hcaptcha_protect_content_nonce', 'hcaptcha_protect_content', $verified );

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ hcaptcha()->settings()->get_plugin_name() ],
				'form_id' => 'protect',
			]
		);
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
