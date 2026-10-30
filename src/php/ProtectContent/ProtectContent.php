<?php
/**
 * ProtectContent class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\ProtectContent;

use Exception;
use HCaptcha\Helpers\API;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Helpers\Request;
use WP_Error;
use WP_Post;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Class ProtectContent
 */
class ProtectContent {

	/**
	 * Nonce action.
	 */
	protected const ACTION = 'hcaptcha_protect_content';

	/**
	 * Nonce name.
	 */
	protected const NONCE = 'hcaptcha_protect_content_nonce';

	/**
	 * Cookie name.
	 */
	private const COOKIE_NAME = 'hcaptcha_content_protection';

	/**
	 * Browser session cookie name.
	 */
	private const SESSION_COOKIE_NAME = 'hcaptcha_content_protection_session';

	/**
	 * Cookie expiration.
	 *
	 * 5 minutes in seconds.
	 */
	private const COOKIE_EXPIRATION = 5 * MINUTE_IN_SECONDS;

	/**
	 * Number of random bytes in a clearance or session token.
	 */
	private const TOKEN_BYTES = 32;

	/**
	 * Number of candidate posts inspected per REST query batch.
	 */
	private const REST_QUERY_BATCH_SIZE = 500;

	/**
	 * Error message.
	 *
	 * @var string
	 */
	protected string $error_message = '';

	/**
	 * Request URI.
	 *
	 * @var string
	 */
	protected string $request_uri = '';

	/**
	 * Matched protected URL rule.
	 *
	 * Each configured rule is an authorization scope. The longest matching rule
	 * allows refreshes and navigation within that set without unlocking another
	 * configured set.
	 *
	 * @var string
	 */
	protected string $resource_scope = '';

	/**
	 * Configured URL rules used before and after WordPress resolves the request.
	 *
	 * @var string[]
	 */
	private array $protected_urls = [];

	/**
	 * REST requests containing a protected post without clearance.
	 *
	 * @var array<int, bool>
	 */
	private array $denied_rest_requests = [];

	/**
	 * Init class.
	 *
	 * @return void
	 */
	public function init(): void {
		$this->resource_scope       = '';
		$this->protected_urls       = [];
		$this->denied_rest_requests = [];

		$settings = hcaptcha()->settings();

		if ( ! $settings || ! $settings->is_on( 'protect_content' ) ) {
			return;
		}

		$protected_urls       = explode( "\n", $settings->get( 'protected_urls' ) );
		$protected_urls       = array_filter( array_map( 'trim', $protected_urls ) );
		$this->protected_urls = $protected_urls ?: [ '/' ]; // Protect all URLs by default.

		add_action( 'rest_api_init', [ $this, 'init_rest_hooks' ], PHP_INT_MAX );
		add_filter( 'rest_request_after_callbacks', [ $this, 'protect_rest_response' ], PHP_INT_MAX, 3 );

		if ( ! Request::is_frontend() ) {
			return;
		}

		// Do not use Request::filter_input() here: sanitize_text_field() strips
		// percent-encoded octets and can make routed URLs miss the protection list.
		$request_uri = '';

		if ( isset( $_SERVER['REQUEST_URI'] ) && is_string( $_SERVER['REQUEST_URI'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$request_uri = wp_unslash( $_SERVER['REQUEST_URI'] );
		}

		$this->request_uri    = $this->normalize_url( $request_uri );
		$request_uri          = $this->normalize_url( $request_uri, true );
		$this->resource_scope = $this->match_resource_scope( $request_uri );

		$this->init_hooks();
	}

	/**
	 * Register a response guard for every REST-enabled post type.
	 *
	 * @return void
	 */
	public function init_rest_hooks(): void {
		add_filter( 'rest_post_search_query', [ $this, 'protect_rest_query' ], PHP_INT_MAX, 2 );

		foreach ( get_post_types( [ 'show_in_rest' => true ] ) as $post_type ) {
			add_filter( 'rest_' . $post_type . '_query', [ $this, 'protect_rest_query' ], PHP_INT_MAX, 2 );
			add_filter( 'rest_prepare_' . $post_type, [ $this, 'protect_rest_post' ], PHP_INT_MAX, 3 );
		}
	}

	/**
	 * Suppress a protected resource before it enters a REST response.
	 *
	 * @param mixed           $response Prepared response.
	 * @param WP_Post         $post     Server-selected post.
	 * @param WP_REST_Request $request  REST request.
	 *
	 * @return mixed
	 */
	public function protect_rest_post( $response, WP_Post $post, WP_REST_Request $request ) {
		if ( ! $response instanceof WP_REST_Response || ( ! $request->is_method( 'GET' ) && ! $request->is_method( 'HEAD' ) ) ) {
			return $response;
		}

		if ( ! $this->is_rest_post_protected( $post, $request ) ) {
			return $response;
		}

		$this->denied_rest_requests[ spl_object_id( $request ) ] = true;

		return new WP_REST_Response( [], 403 );
	}

	/**
	 * Remove uncleared posts before REST queries calculate totals and pages.
	 *
	 * Search results do not pass through rest_prepare_post. Excluding IDs in
	 * native searches and collections also protects keyword counts.
	 *
	 * @param array           $query_args Native search query arguments.
	 * @param WP_REST_Request $request    REST request.
	 *
	 * @return array
	 */
	public function protect_rest_query( array $query_args, WP_REST_Request $request ): array {
		if ( ! $request->is_method( 'GET' ) && ! $request->is_method( 'HEAD' ) ) {
			return $query_args;
		}

		if ( ! empty( $query_args['post__in'] ) ) {
			$visible_ids = array_filter(
				$query_args['post__in'],
				function ( $post_id ) use ( $request ) {
					return ! $this->is_rest_post_protected( get_post( $post_id ), $request );
				}
			);

			// WP_Query ignores post__not_in whenever post__in is present.
			$query_args['post__in'] = $visible_ids ?: [ 0 ];

			return $query_args;
		}

		$candidate_args                   = $query_args;
		$candidate_args['fields']         = 'ids';
		$candidate_args['posts_per_page'] = self::REST_QUERY_BATCH_SIZE;
		$candidate_args['paged']          = 1;
		$candidate_args['nopaging']       = false;
		$candidate_args['no_found_rows']  = true;
		$protected_ids                    = [];

		for ( $offset = 0; ; $offset += self::REST_QUERY_BATCH_SIZE ) {
			$candidate_args['offset'] = $offset;
			$candidates               = new WP_Query( $candidate_args );

			foreach ( $candidates->posts as $post_id ) {
				if ( $this->is_rest_post_protected( get_post( $post_id ), $request ) ) {
					$protected_ids[] = $post_id;
				}
			}

			if ( count( $candidates->posts ) < self::REST_QUERY_BATCH_SIZE ) {
				break;
			}
		}

		if ( $protected_ids ) {
			$query_args['post__not_in'] = array_merge( $query_args['post__not_in'] ?? [], $protected_ids );
		}

		return $query_args;
	}

	/**
	 * Check the selected post against its own URL rule and clearance scope.
	 *
	 * @param WP_Post|null    $post    Selected post.
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return bool
	 */
	private function is_rest_post_protected( ?WP_Post $post, WP_REST_Request $request ): bool {
		if ( ! $post || ( 'edit' === $request['context'] && current_user_can( 'edit_post', $post->ID ) ) ) {
			return false;
		}

		$permalink = get_permalink( $post->ID );
		$scope     = $permalink ? $this->match_resource_scope( $this->normalize_url( $permalink, true ) ) : '';

		if ( '' === $scope ) {
			return false;
		}

		$previous_scope       = $this->resource_scope;
		$this->resource_scope = $scope;
		$cleared              = $this->is_valid_cookie();
		$this->resource_scope = $previous_scope;

		return ! $cleared;
	}

	/**
	 * Reject the entire response when it includes an uncleared resource.
	 *
	 * @param mixed           $response Existing REST response.
	 * @param array           $handler  Matched route handler.
	 * @param WP_REST_Request $request  REST request.
	 *
	 * @return mixed|WP_Error
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function protect_rest_response( $response, array $handler, WP_REST_Request $request ) {
		$request_id = spl_object_id( $request );

		if ( ! isset( $this->denied_rest_requests[ $request_id ] ) ) {
			return $response;
		}

		unset( $this->denied_rest_requests[ $request_id ] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return new WP_Error(
			'hcaptcha_content_protected',
			__( 'Complete the content protection challenge before accessing this resource.', 'hcaptcha-for-forms-and-more' ),
			[ 'status' => 403 ]
		);
	}

	/**
	 * Select the most specific configured rule matching a normalized URL.
	 *
	 * @param string $url Normalized URL.
	 *
	 * @return string
	 */
	private function match_resource_scope( string $url ): string {
		$scope = '';

		foreach ( $this->protected_urls as $rule ) {
			if ( $this->matches_protected_url( $rule, $url ) && strlen( $rule ) > strlen( $scope ) ) {
				$scope = $rule;
			}
		}

		return $scope;
	}

	/**
	 * Match literal URL rules and the regular expressions supported by settings.
	 *
	 * @param string $rule URL rule without regex delimiters.
	 * @param string $url  Normalized request URL.
	 *
	 * @return bool
	 */
	private function matches_protected_url( string $rule, string $url ): bool {
		// Literal paths use the same single decoding pass as the request path.
		$literal_parts = explode( '?', $rule, 2 );
		$literal_rule  = rawurldecode( $literal_parts[0] );
		$literal_rule .= isset( $literal_parts[1] ) ? '?' . $literal_parts[1] : '';

		if ( false !== stripos( $url, $rule ) || false !== stripos( $url, $literal_rule ) ) {
			return true;
		}

		// Choose a delimiter absent from the expression, preserving existing escapes.
		foreach ( [ '~', '#', '!', '%', '@', ';', "\x01" ] as $delimiter ) {
			if ( false !== strpos( $rule, $delimiter ) ) {
				continue;
			}

			$pattern = $delimiter . $rule . $delimiter . 'i';

			// Invalid expressions remain literal URL rules; do not emit compile warnings.
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( false === @preg_match( $pattern, '' ) ) {
				// Compilation errors are internal errors; execution limits fail closed.
				return PREG_INTERNAL_ERROR !== preg_last_error();
			}

			// A request-specific regex execution error must not disable protection.
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			$result = @preg_match( $pattern, $url );

			return false === $result || 1 === $result;
		}

		return false;
	}

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		add_action( 'template_redirect', [ $this, 'protect_content' ], -PHP_INT_MAX );
	}

	/**
	 * Protect site content.
	 *
	 * @return void
	 */
	public function protect_content(): void {
		$this->resolve_resource_scope();

		if ( '' === $this->resource_scope ) {
			return;
		}

		if ( $this->is_valid_cookie() ) {
			return;
		}

		$this->ensure_session_cookie();

		if ( 'post' === strtolower( Request::filter_input( INPUT_SERVER, 'REQUEST_METHOD' ) ) ) {
			$this->error_message = $this->verify();
		}

		$this->show_protection_page();
	}

	/**
	 * Bind the protection scope to the resource selected by WordPress.
	 *
	 * @return void
	 */
	private function resolve_resource_scope(): void {
		if ( is_singular() ) {
			$queried_id = get_queried_object_id();
			$permalink  = $queried_id ? get_permalink( $queried_id ) : false;

			if ( $permalink ) {
				$routed_url   = $this->normalize_url( $permalink, true );
				$routed_scope = $this->match_resource_scope( $routed_url );
				$request_path = untrailingslashit( rawurldecode( (string) wp_parse_url( $this->request_uri, PHP_URL_PATH ) ) );
				$routed_path  = untrailingslashit( (string) wp_parse_url( $routed_url, PHP_URL_PATH ) );

				if ( $request_path !== $routed_path ) {
					$this->resource_scope = $routed_scope;

					if ( '' !== $routed_scope ) {
						// A POST-selected page must remain the target after the challenge.
						$this->request_uri = $this->normalize_url( $permalink );
					}
				} elseif ( '' !== $routed_scope && '' !== $this->resource_scope && $routed_scope !== $this->resource_scope ) {
					// Both rules must be satisfied when a query matches another scope.
					$this->resource_scope = $routed_scope . "\n" . $this->resource_scope;
				} elseif ( '' !== $routed_scope ) {
					$this->resource_scope = $routed_scope;
				}
			}
		}
	}

	/**
	 * Verify hCaptcha.
	 *
	 * @return string
	 */
	protected function verify(): string {
		$settings = hcaptcha()->settings();

		// It is always too fast with Pro.
		$settings && $settings->set( 'set_min_submit_time', [ '' ] );

		$error_message = API::verify_post( self::NONCE, self::ACTION, $this->get_expected_id() );

		if ( null === $error_message ) {
			$session_id = $this->get_session_id();

			if ( '' === $session_id || '' === $this->resource_scope ) {
				return __( 'Your browser session could not be established. Please try again.', 'hcaptcha-for-forms-and-more' );
			}

			$time   = time();
			$cookie = $this->create_clearance( $session_id, $time );

			$this->setcookie(
				self::COOKIE_NAME,
				$cookie,
				[
					'expires'  => $time + self::COOKIE_EXPIRATION,
					'path'     => '/',
					'secure'   => is_ssl(),
					'httponly' => true,
					'samesite' => 'Lax',
				]
			);

			wp_safe_redirect( $this->request_uri );
		}

		return (string) $error_message;
	}

	/**
	 * Get the content protection form identity.
	 *
	 * @return array
	 */
	private function get_expected_id(): array {
		$settings = hcaptcha()->settings();

		return [
			'source'  => [ $settings ? $settings->get_plugin_name() : '' ],
			'form_id' => 'protect',
		];
	}

	/**
	 * Check whether the cookie is valid.
	 *
	 * @return bool
	 */
	protected function is_valid_cookie(): bool {
		$cookie     = $this->get_cookie( self::COOKIE_NAME );
		$session_id = $this->get_session_id();
		$parts      = (array) explode( '.', $cookie );

		if (
			6 !== count( $parts ) ||
			'v2' !== $parts[0] ||
			'' === $session_id ||
			'' === $this->resource_scope
		) {
			return false;
		}

		[ , $expires, $token, $session_binding, $scope_binding, $signature ] = $parts;

		if (
			! preg_match( '/^[0-9]{1,12}$/D', $expires ) ||
			! preg_match( '/^[a-f0-9]{64}$/D', $token ) ||
			! preg_match( '/^[a-f0-9]{64}$/D', $session_binding ) ||
			! preg_match( '/^[a-f0-9]{64}$/D', $scope_binding ) ||
			! preg_match( '/^[a-f0-9]{64}$/D', $signature )
		) {
			return false;
		}

		$payload = implode( '.', array_slice( $parts, 0, 5 ) );

		if ( ! hash_equals( $this->sign_clearance( $payload ), $signature ) ) {
			return false;
		}

		$expires = (int) $expires;
		$now     = time();

		if ( $expires <= $now || $expires > $now + self::COOKIE_EXPIRATION ) {
			return false;
		}

		return hash_equals( $this->hash_binding( 'session', $session_id ), $session_binding ) &&
			hash_equals( $this->hash_binding( 'scope', $this->resource_scope ), $scope_binding );
	}

	/**
	 * Establish the independent browser credential before a challenge succeeds.
	 *
	 * Copying a later clearance response does not copy this credential. Theft of
	 * both cookies is equivalent to theft of the browser session and is outside
	 * the clearance-only replay protection model.
	 *
	 * @return void
	 */
	protected function ensure_session_cookie(): void {
		if ( '' !== $this->get_session_id() ) {
			return;
		}

		$this->setcookie(
			self::SESSION_COOKIE_NAME,
			'v1.' . $this->generate_token(),
			[
				'expires'  => 0,
				'path'     => '/',
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			]
		);
	}

	/**
	 * Get the browser session ID from the request.
	 *
	 * @return string
	 */
	private function get_session_id(): string {
		$session_id = $this->get_cookie( self::SESSION_COOKIE_NAME );

		return preg_match( '/^v1\.[a-f0-9]{64}$/D', $session_id ) ? $session_id : '';
	}

	/**
	 * Get a scalar cookie value.
	 *
	 * @param string $name Cookie name.
	 *
	 * @return string
	 */
	private function get_cookie( string $name ): string {
		$value = Request::filter_input( INPUT_COOKIE, $name );

		return is_string( $value ) ? $value : '';
	}

	/**
	 * Generate an unpredictable token.
	 *
	 * @return string
	 */
	protected function generate_token(): string {
		try {
			return bin2hex( random_bytes( self::TOKEN_BYTES ) );
		} catch ( Exception $e ) {
			return '';
		}
	}

	/**
	 * Create a signed, session- and resource-bound clearance.
	 *
	 * The payload is stateless on the server and cannot outlive its embedded
	 * five-minute expiry, so successful challenges create no records to prune.
	 *
	 * @param string $session_id Browser session ID.
	 * @param int    $time       Issuance time.
	 *
	 * @return string
	 */
	private function create_clearance( string $session_id, int $time ): string {
		$payload = implode(
			'.',
			[
				'v2',
				(string) ( $time + self::COOKIE_EXPIRATION ),
				$this->generate_token(),
				$this->hash_binding( 'session', $session_id ),
				$this->hash_binding( 'scope', $this->resource_scope ),
			]
		);

		return $payload . '.' . $this->sign_clearance( $payload );
	}

	/**
	 * Hash a session or resource binding.
	 *
	 * @param string $type  Binding type.
	 * @param string $value Binding value.
	 *
	 * @return string
	 */
	private function hash_binding( string $type, string $value ): string {
		return hash_hmac( 'sha256', $type . '|' . $value, wp_salt() );
	}

	/**
	 * Sign a clearance payload.
	 *
	 * @param string $payload Clearance payload.
	 *
	 * @return string
	 */
	private function sign_clearance( string $payload ): string {
		return hash_hmac( 'sha256', 'clearance|' . $payload, wp_salt() );
	}

	/**
	 * Display the protection page.
	 *
	 * @return void
	 * @noinspection CssUnusedSymbol
	 */
	protected function show_protection_page(): void {
		$css = /* @lang CSS */ '
	* {
		box-sizing: border-box;
		margin: 0;
		padding: 0;
	}

	html {
		line-height: 1.15;
		-webkit-text-size-adjust: 100%;
		color: #5c6f8a;
		font-family: system-ui, -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Helvetica Neue, Arial, Noto Sans, sans-serif, Apple Color Emoji, Segoe UI Emoji, Segoe UI Symbol, Noto Color Emoji;
	}

	body {
		display: flex;
		flex-direction: column;
		height: 100vh;
		min-height: 100vh;
		margin-top: 0;
		margin-bottom: 0;
	}

	.main-content {
		margin: 8rem auto;
		max-width: 60rem;
		padding-left: 1.5rem;
	}

	@media (width <= 720px) {
		.main-content {
			margin-top: 4rem;
		}
	}

	.h2 {
		font-size: 1.5rem;
		font-weight: 500;
		line-height: 2.25rem;
	}

	@media (width <= 720px) {
		.h2 {
			font-size: 1.25rem;
			line-height: 1.5rem;
		}
	}

	body.theme-dark {
		background-color: #1b1b1d;
		color: #e3e3e3;
	}

	body.theme-dark a {
		color: #00bcb7;
	}

	body.theme-dark a:hover {
		color: #00bcb7;
		text-decoration: underline;
	}

	body.theme-dark .footer-inner {
		border-top: 1px solid #e3e3e3;
	}

	body.theme-light {
		background-color: #fff;
		color: #5c6f8a;
	}

	body.theme-light a {
		color: #0075ab;
	}

	body.theme-light a:hover {
		color: #0075ab;
		text-decoration: underline;
	}
	
	body.theme-light .footer-inner {
		border-top: 1px solid #5c6f8a;
	}

	a {
		background-color: transparent;
		color: #0075ab;
		text-decoration: none;
		transition: color .15s ease;
	}

	a:hover {
		color: #0075ab;
		text-decoration: underline;
	}

	.main-content {
		margin: 8rem auto;
		max-width: 60rem;
		padding-left: 1.5rem;
		padding-right: 1.5rem;
		width: 100%;
	}

	.spacer {
		margin: 2rem 0;
	}

	.spacer-top {
		margin-top: 2rem;
	}

	.spacer-bottom {
		margin-bottom: 2rem;
	}

	@media (width <= 720px) {
		.main-content {
			margin-top: 4rem;
		}
	}

	.main-wrapper {
		align-items: center;
		display: flex;
		flex: 1;
		flex-direction: column;
	}

	.h1 {
		font-size: 2.5rem;
		font-weight: 500;
		line-height: 3.75rem;
	}

	.h2 {
		font-weight: 500;
	}

	.core-msg, .h2 {
		font-size: 1.5rem;
		line-height: 2.25rem;
	}

	.core-msg {
		font-weight: 400;
	}

	@media (width <= 720px) {
		.h1 {
			font-size: 1.5rem;
			line-height: 1.75rem;
		}

		.h2 {
			font-size: 1.25rem;
		}

		.core-msg, .h2 {
			line-height: 1.5rem;
		}

		.core-msg {
			font-size: 1rem;
		}
	}

	.text-center {
		text-align: center;
	}

	.footer {
		font-size: .75rem;
		line-height: 1.125rem;
		margin: 0 auto;
		max-width: 60rem;
		padding-left: 1.5rem;
		padding-right: 1.5rem;
		width: 100%;
	}

	.footer-inner {
		border-top: 1px solid #5c6f8a;
		padding-bottom: 1rem;
		padding-top: 1rem;
	}

	.clearfix:after {
		clear: both;
		content: "";
		display: table;
	}

	.footer-text {
		margin-bottom: .5rem;
	}

	.core-msg, .zone-name-title {
		overflow-wrap: break-word;
	}

	@media (width <= 720px) {
		.zone-name-title {
			margin-bottom: 1rem;
		}
	}

	@media (prefers-color-scheme: dark) {
		body {
			background-color: #1b1b1d;
			color: #e3e3e3;
		}

		body a {
			color: #00bcb7;
		}

		body a:hover {
			color: #00bcb7;
			text-decoration: underline;
		}

		.footer-inner {
			border-top: 1px solid #e3e3e3;
		}
	}
	
	.main-content .h-captcha {
		margin-bottom: 0;
	}
	
	#hcaptcha-submit {
		display: none;
	}
';

		?>
		<html lang="en-US" dir="ltr">
		<head>
			<title>Content Protection</title>
			<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
			<meta http-equiv="X-UA-Compatible" content="IE=Edge">
			<meta name="robots" content="noindex,nofollow">
			<meta name="viewport" content="width=device-width,initial-scale=1">
			<meta http-equiv="refresh" content="<?php echo esc_attr( self::COOKIE_EXPIRATION ); ?>">
			<style>
				<?php

				HCaptcha::css_display( $css );
				hcaptcha()->print_inline_styles();

				?>
			</style>
			<?php wp_site_icon(); ?>
		</head>
		<body>
		<div class="main-wrapper" role="main">
			<div class="main-content">
				<h1 class="zone-name-title h1">
					<?php echo wp_kses_post( wp_parse_url( home_url(), PHP_URL_HOST ) ); ?>
				</h1>

				<p class="h2 spacer-bottom">
					<?php esc_html_e( 'Verifying you are human. This may take a few seconds.', 'hcaptcha-for-forms-and-more' ); ?>
				</p>

				<form method="post" action="<?php echo esc_url( $this->request_uri ); ?>">
				<?php

				$args = [
					'action' => static::ACTION,
					'name'   => static::NONCE,
					'force'  => true,
					'theme'  => 'auto',
					'size'   => 'normal',
					'id'     => $this->get_expected_id(),
				];

				HCaptcha::form_display( $args );

				?>
				<p id="hcaptcha-error"><?php echo esc_html( $this->error_message ); ?></p>
				<input type="submit" id="hcaptcha-submit" value="Submit">
				</form>

				<div class="core-msg spacer spacer-top">
					<?php

					echo wp_kses_post(
						sprintf(
						/* translators: 1: Site link. */
							__( '%1$s needs to review the security of your connection before proceeding.', 'hcaptcha-for-forms-and-more' ),
							wp_parse_url( home_url(), PHP_URL_HOST )
						)
					);

					?>
				</div>
			</div>
		</div>
		<div class="footer text-center" role="contentinfo">
			<div class="footer-inner">
				<div class="clearfix footer-text">
					<div>
						<?php esc_html_e( 'The hCaptcha plugin', 'hcaptcha-for-forms-and-more' ); ?>
					</div>
				</div>
				<div>
					<?php

					echo wp_kses_post(
						sprintf(
						/* translators: 1: hCaptcha link. */
							__( 'Privacy and security by %1$s', 'hcaptcha-for-forms-and-more' ),
							'<a href="https://www.hcaptcha.com/?r=wp&utm_source=wordpress&utm_medium=wpplugin&utm_campaign=sk" target="_blank" rel="noopener noreferrer">hCaptcha</a>'
						)
					);

					?>
				</div>
			</div>
		</div>
		<script>
			document.addEventListener( 'hCaptchaLoaded', function() {
				if ( document.getElementById( 'hcaptcha-error' ).innerText.length === 0 ) {
					document.getElementById( 'hcaptcha-submit' ).click();
				}
			} );
		</script>
		<?php

		hcaptcha()->print_footer_scripts();
		_wp_footer_scripts();

		?>
		</body>
		</html>
		<?php

		$this->exit();
	}

	/**
	 * Setcookie wrapper for test purposes.
	 *
	 * @param string $name    The name of the cookie.
	 * @param string $value   The value of the cookie.
	 * @param array  $options The cookie options.
	 *
	 * @return bool
	 */
	protected function setcookie( string $name, string $value = '', array $options = [] ): bool {
		// @codeCoverageIgnoreStart
		$options = wp_parse_args(
			$options,
			[
				'expires'  => 0,
				'path'     => '',
				'domain'   => '',
				'secure'   => false,
				'httponly' => false,
				'samesite' => 'Lax',
			]
		);

		return setcookie( $name, $value, $options );
		// @codeCoverageIgnoreEnd
	}

	/**
	 * Exit wrapper for test purposes.
	 *
	 * @return void
	 */
	protected function exit(): void {
		// @codeCoverageIgnoreStart
		exit();
		// @codeCoverageIgnoreEnd
	}

	/**
	 * Normalize URL.
	 *
	 * @param string $url         URL.
	 * @param bool   $decode_path Whether to decode percent-encoded path octets.
	 *
	 * @return string
	 */
	private function normalize_url( string $url, bool $decode_path = false ): string {
		$scheme = is_ssl() ? 'https' : 'http';
		$host   = wp_parse_url( home_url(), PHP_URL_HOST );
		$url    = 0 === strpos( $url, '//' ) ? $scheme . '://' . $host . $url : $url;

		$parts = wp_parse_url( $url );
		$parts = wp_parse_args(
			$parts,
			[
				'scheme'   => $scheme,
				'host'     => $host,
				'path'     => '',
				'query'    => '',
				'fragment' => '',
			]
		);

		// Force the site origin so a supplied URL cannot move the challenge action.
		$parts = array_merge(
			$parts,
			[
				'scheme' => $scheme,
				'host'   => $host,
			]
		);

		if ( $decode_path ) {
			$parts['path'] = rawurldecode( $parts['path'] );
		}

		// Rebuild the URL.
		$url = $parts['scheme'] . '://';

		$url .= $parts['host'];
		$url .= $parts['path'] ?: '';
		$url .= $parts['query'] ? '?' . $parts['query'] : '';
		$url .= $parts['fragment'] ? '#' . $parts['fragment'] : '';

		return $url;
	}
}
