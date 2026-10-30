<?php
/**
 * AutoVerify class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\AutoVerify;

use HCaptcha\Helpers\API;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Helpers\Request;
use WP_Query;
use WP_Widget_Block;

/**
 * Class AutoVerify
 */
class AutoVerify {

	/**
	 * Transient name where to store registered forms.
	 */
	public const TRANSIENT = 'hcaptcha_auto_verify';

	/**
	 * Prefix for persistent registrations, keyed by the form action path.
	 */
	private const OPTION_PREFIX = 'hcaptcha_auto_verify_form_';

	/**
	 * Public WordPress query variables selecting a single post.
	 */
	private const QUERY_SELECTORS = [ 'page_id', 'p', 'pagename', 'name', 'attachment_id', 'subpost_id', 'attachment', 'subpost' ];

	/**
	 * Legacy transient size limit, retained for compatibility.
	 *
	 * @deprecated Registrations are stored in persistent options.
	 * @noinspection PhpUnused
	 */
	public const MAX_TRANSIENT_SIZE = 512 * 1024;

	/**
	 * Script handle.
	 */
	public const HANDLE = 'hcaptcha-auto-verify';

	/**
	 * Script localization object.
	 */
	public const OBJECT = 'HCaptchaAutoVerifyObject';

	/**
	 * The hCaptcha forms' registry.
	 *
	 * @var array
	 */
	protected array $registry = [];

	/**
	 * Delete stored form registrations during plugin uninstallation.
	 *
	 * @return void
	 */
	public static function delete_all(): void {
		global $wpdb;

		$like = $wpdb->esc_like( self::OPTION_PREFIX ) . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$option_names = $wpdb->get_col(
			$wpdb->prepare( "SELECT option_name FROM $wpdb->options WHERE option_name LIKE %s", $like )
		);

		foreach ( $option_names as $option_name ) {
			delete_option( $option_name );
		}

		delete_transient( self::TRANSIENT );
	}

	/**
	 * Init class.
	 *
	 * @return void
	 */
	public function init(): void {
		$this->init_hooks();
	}

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		add_action( 'init', [ $this, 'verify' ], - PHP_INT_MAX );
		add_filter( 'hcap_form_args', [ $this, 'add_default_id' ] );
		add_filter( 'the_content', [ $this, 'content_filter' ], PHP_INT_MAX );
		add_filter( 'widget_block_content', [ $this, 'widget_block_content_filter' ], PHP_INT_MAX, 3 );
		add_action( 'hcap_auto_verify_register', [ $this, 'content_filter' ] );
		add_action( 'hcap_register_form', [ $this, 'register_hcaptcha' ] );
		add_action( 'wp_print_footer_scripts', [ $this, 'enqueue_scripts' ], 9 );
	}

	/**
	 * Add default id to the auto-verified hCaptcha form.
	 *
	 * @param array|mixed $args hCaptcha form arguments.
	 *
	 * @return array
	 */
	public function add_default_id( $args ): array {
		$args = (array) $args;
		$auto = filter_var( $args['auto'] ?? false, FILTER_VALIDATE_BOOLEAN );

		if ( ! $auto ) {
			return $args;
		}

		$args['id'] = $this->normalize_id( $args );

		return $args;
	}

	/**
	 * Filter page content and register the form for auto verification.
	 *
	 * @param string|mixed $content Content.
	 *
	 * @return string
	 */
	public function content_filter( $content ): string {
		return $this->process_content( $content );
	}

	/**
	 * Filter block widget content and register the form for auto verification.
	 *
	 * @param string|mixed    $content  The widget content.
	 * @param array           $instance Array of settings for the current widget.
	 * @param WP_Widget_Block $widget   Current Block widget instance.
	 *
	 * @return string
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function widget_block_content_filter( $content, array $instance, WP_Widget_Block $widget ): string {
		return $this->process_content( $content );
	}

	/**
	 * Register hCaptcha form.
	 *
	 * @param array|mixed $args Arguments.
	 *
	 * @return void
	 */
	public function register_hcaptcha( $args ): void {
		if ( ! is_array( $args ) ) {
			return;
		}

		$args['id'] = $this->normalize_id( $args );
		$widget_id  = HCaptcha::widget_id_value( $args['id'] );

		$this->registry[ $widget_id ] = $args;
	}

	/**
	 * Normalize hCaptcha widget id.
	 *
	 * @param array $args Arguments.
	 *
	 * @return array
	 */
	private function normalize_id( array $args ): array {
		$id            = (array) ( $args['id'] ?? [] );
		$normalized_id = [
			'source'  => empty( $id['source'] ) ? [ self::class ] : (array) $id['source'],
			'form_id' => empty( $id['form_id'] ) ? (int) get_the_ID() : $id['form_id'],
		];
		$honeypot      = $id['honeypot'] ?? $args['honeypot'] ?? null;

		if ( null !== $honeypot ) {
			$normalized_id['honeypot'] = filter_var( $honeypot, FILTER_VALIDATE_BOOLEAN );
		}

		return $normalized_id;
	}

	/**
	 * Enqueue scripts.
	 *
	 * @return void
	 */
	public function enqueue_scripts(): void {
		if ( ! array_filter( array_column( $this->registry ?? [], 'ajax' ) ) ) {
			return;
		}

		$min = hcap_min_suffix();

		wp_enqueue_script(
			self::HANDLE,
			constant( 'HCAPTCHA_URL' ) . "/assets/js/hcaptcha-auto-verify$min.js",
			[ 'jquery' ],
			constant( 'HCAPTCHA_VERSION' ),
			true
		);

		wp_localize_script(
			self::HANDLE,
			self::OBJECT,
			[
				'successMsg'      => __( 'The form was submitted successfully.', 'hcaptcha-for-forms-and-more' ),
				'submittingMsg'   => __( 'Submitting the form...', 'hcaptcha-for-forms-and-more' ),
				'errorMsg'        => __( 'The form could not be submitted. Please try again.', 'hcaptcha-for-forms-and-more' ),
				'networkErrorMsg' => __( 'Could not confirm whether the form was submitted. Check before trying again.', 'hcaptcha-for-forms-and-more' ),
			]
		);

		wp_enqueue_script( 'hcaptcha' );
	}

	/**
	 * Verify a form automatically.
	 *
	 * @return void
	 * @noinspection ForgottenDebugOutputInspection
	 */
	public function verify(): void {
		// Do not let client-controlled REST request shape bypass verification.
		if ( ! Request::is_post() || ! Request::is_frontend( false ) ) {
			return;
		}

		$registered_form = $this->get_registered_form_for_request();

		if ( null === $registered_form ) {
			return;
		}

		$args   = $registered_form['args'] ?? [];
		$ajax   = $args['ajax'] ?? '';
		$result = $this->verify_submission( $registered_form );

		if ( $ajax ) {
			add_filter( 'wp_doing_ajax', '__return_true' );
		}

		if ( null !== $result ) {
			$_POST = [];

			wp_die(
				esc_html( $result ),
				'hCaptcha',
				[
					'back_link' => true,
					'response'  => 403,
				]
			);
		}
	}

	/**
	 * Get the registered form for the current request.
	 *
	 * @return array|null
	 */
	private function get_registered_form_for_request(): ?array {
		$request_uri = $this->get_request_uri();

		if ( ! $request_uri ) {
			return null;
		}

		$path       = $this->get_path( $request_uri );
		$target_key = $this->get_target_key( $request_uri );

		if ( $target_key !== $path ) {
			$registered_form = $this->get_registered_form( $target_key );

			if ( null !== $registered_form ) {
				return $registered_form;
			}
		}

		$registered_form = $path ? $this->get_registered_form( $path ) : null;

		if ( null !== $registered_form ) {
			return $registered_form;
		}

		foreach ( $this->get_canonical_request_keys() as $canonical_key ) {
			if ( ! $canonical_key || $canonical_key === $path || $canonical_key === $target_key ) {
				continue;
			}

			$registered_form = $this->get_registered_form( $canonical_key );

			if ( null !== $registered_form ) {
				return $registered_form;
			}
		}

		return null;
	}

	/**
	 * Verify a registered form submission.
	 *
	 * @param array $registered_form Registered form.
	 *
	 * @return string|null
	 */
	private function verify_submission( array $registered_form ): ?string {
		if ( ! $registered_form ) {
			return API::filtered_result( hcap_get_error_messages()['bad-signature'], [ 'bad-signature' ] );
		}

		$args   = $registered_form['args'] ?? [];
		$action = $args['action'] ?? '';
		$name   = $args['name'] ?? '';

		return API::verify(
			$this->get_entry( $name, $action, $this->get_expected_id( $args ) )
		);
	}

	/**
	 * Get entry.
	 *
	 * @param string $name        Nonce field name.
	 * @param string $action      Nonce action name.
	 * @param array  $expected_id Expected hCaptcha widget id.
	 *
	 * @return array
	 */
	private function get_entry( string $name, string $action, array $expected_id ): array {
		$entry = [
			'nonce_name'         => $name,
			'nonce_action'       => $action,
			'h-captcha-response' => Request::filter_input( INPUT_POST, 'h-captcha-response' ),
			'data'               => $this->get_data(),
			'expected_id'        => $expected_id,
		];

		/**
		 * Filter entry data for the server-registered automatic form.
		 *
		 * @param array $entry Verification entry with the registered form identity.
		 */
		return (array) apply_filters( 'hcap_auto_verify_entry', $entry );
	}

	/**
	 * Get expected hCaptcha widget id.
	 *
	 * @param array $args hCaptcha form arguments.
	 *
	 * @return array
	 */
	private function get_expected_id( array $args ): array {
		return (array) ( $args['id'] ?? [] );
	}

	/**
	 * Get form data for anti-spam checks.
	 *
	 * @return array
	 */
	private function get_data(): array {
		$data          = [];
		$excluded_keys = [
			'hcap_',
			'hcaptcha-',
			'hcaptcha_',
			'h-captcha-response',
			'_wp_http_referer',
		];

		// Nonce is verified later, in \HCaptcha\Helpers\API::verify().
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		foreach ( $_POST as $key => $value ) {
			$key_str = (string) $key;

			foreach ( $excluded_keys as $excluded_key ) {
				if ( 0 === strpos( $key_str, $excluded_key ) ) {
					continue 2;
				}
			}

			$data[ $key ] = Request::filter_input( INPUT_POST, $key );
		}

		return $data;
	}

	/**
	 * Register forms.
	 *
	 * @param array $forms Forms found in the content.
	 */
	protected function register_forms( array $forms ): void {
		$forms_data = [];

		foreach ( $forms as $form ) {
			$action = $this->get_form_action( $form );

			if ( ! $action ) {
				// We cannot register a form without action specified or determined from $_SERVER['REQUEST_URI'].
				continue;
			}

			$widget_id_value = $this->get_widget_id_value( $form );
			$args            = $this->registry[ $widget_id_value ] ?? [];

			$forms_data[] = [
				'action'    => $action,
				'inputs'    => $this->get_visible_input_names( $form ),
				'widget_id' => $widget_id_value,
				'source'    => $this->get_registration_source(),
				'args'      => $args,
			];
		}

		$this->update_form_registrations( $forms_data );
	}

	/**
	 * Get form action.
	 *
	 * @param string $form Form.
	 *
	 * @return string
	 */
	private function get_form_action( string $form ): string {
		$form_action = $this->get_form_action_attribute( $form );
		$form_action = trim( str_replace( [ "\t", "\r", "\n" ], '', $form_action ), "\x00..\x20" );

		if ( '' === $form_action ) {
			$form_action = $this->get_request_uri();
		} else {
			$form_action = $this->resolve_form_action( $form_action );
		}

		return $this->get_target_key( $form_action );
	}

	/**
	 * Read the first action attribute from the form's opening tag.
	 *
	 * @param string $form Form.
	 *
	 * @return string
	 */
	private function get_form_action_attribute( string $form ): string {

		// Read only the opening tag, consuming quoted values before inspecting the next attribute.
		if ( preg_match( '#<form(?=[\x09\x0a\x0c\x0d\x20/>])(?:[^>"\']|"[^"]*"|\'[^\']*\')*>#i', $form, $tag ) ) {
			preg_match_all(
				'#([^\x09\x0a\x0c\x0d\x20=/>]+)(?:[\x09\x0a\x0c\x0d\x20]*=[\x09\x0a\x0c\x0d\x20]*(?:"([^"]*)"|\'([^\']*)\'|([^\x09\x0a\x0c\x0d\x20>]*)))?#',
				substr( $tag[0], 5, -1 ),
				$attributes,
				PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL
			);

			foreach ( $attributes as $attribute ) {
				if ( 'action' !== strtolower( $attribute[1] ) ) {
					continue;
				}

				// Browsers use the first duplicate attribute and decode its entities once.
				return $this->decode_action_attribute( $attribute[2] ?? $attribute[3] ?? $attribute[4] ?? '' );
			}
		}

		return '';
	}

	/**
	 * Decode HTML attribute references once, including browser forms without semicolons.
	 *
	 * @param string $value Attribute value.
	 *
	 * @return string
	 */
	private function decode_action_attribute( string $value ): string {
		$legacy = [ 'AMP', 'COPY', 'GT', 'LT', 'QUOT', 'REG', 'amp', 'gt', 'lt', 'quot' ];

		foreach ( get_html_translation_table( HTML_ENTITIES, ENT_QUOTES | ENT_HTML401 ) as $character => $entity ) {
			if ( preg_match( '/^(?:\xc2[\xa0-\xbf]|\xc3[\x80-\xbf])$/', $character ) ) {
				$legacy[] = substr( $entity, 1, -1 );
			}
		}

		return preg_replace_callback(
			'/&#(?:[xX][0-9a-fA-F]+|[0-9]+);?|&([a-zA-Z][a-zA-Z0-9]*)(;|(?![a-zA-Z0-9=]))/',
			function ( array $matches ) use ( $legacy ): string {
				if ( 0 === strpos( $matches[0], '&#' ) ) {
					return $this->decode_numeric_reference( $matches[0] );
				}

				if ( ';' !== $matches[2] && ! in_array( $matches[1], $legacy, true ) ) {
					return $matches[0];
				}

				return html_entity_decode( '&' . $matches[1] . ';', ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			},
			$value
		);
	}

	/**
	 * Decode a numeric reference with HTML's replacement and legacy control mapping.
	 *
	 * @param string $reference Numeric character reference.
	 *
	 * @return string
	 */
	private function decode_numeric_reference( string $reference ): string {
		$number = substr( rtrim( $reference, ';' ), 2 );
		$code   = 'x' === strtolower( $number[0] ) ? hexdec( substr( $number, 1 ) ) : (float) $number;

		if ( 0 === (int) $code || $code > 0x10ffff || ( $code >= 0xd800 && $code <= 0xdfff ) ) {
			return "\xef\xbf\xbd";
		}

		$controls = [
			0x80 => 0x20ac,
			0x82 => 0x201a,
			0x83 => 0x0192,
			0x84 => 0x201e,
			0x85 => 0x2026,
			0x86 => 0x2020,
			0x87 => 0x2021,
			0x88 => 0x02c6,
			0x89 => 0x2030,
			0x8a => 0x0160,
			0x8b => 0x2039,
			0x8c => 0x0152,
			0x8e => 0x017d,
			0x91 => 0x2018,
			0x92 => 0x2019,
			0x93 => 0x201c,
			0x94 => 0x201d,
			0x95 => 0x2022,
			0x96 => 0x2013,
			0x97 => 0x2014,
			0x98 => 0x02dc,
			0x99 => 0x2122,
			0x9a => 0x0161,
			0x9b => 0x203a,
			0x9c => 0x0153,
			0x9e => 0x017e,
			0x9f => 0x0178,
		];
		$code     = $controls[ (int) $code ] ?? (int) $code;

		if ( $code < 0x80 ) {
			return chr( $code );
		}

		if ( $code < 0x800 ) {
			return chr( 0xc0 | ( $code >> 6 ) ) . chr( 0x80 | ( $code & 0x3f ) );
		}

		if ( $code < 0x10000 ) {
			return chr( 0xe0 | ( $code >> 12 ) ) . chr( 0x80 | ( ( $code >> 6 ) & 0x3f ) ) . chr( 0x80 | ( $code & 0x3f ) );
		}

		return chr( 0xf0 | ( $code >> 18 ) ) . chr( 0x80 | ( ( $code >> 12 ) & 0x3f ) ) . chr( 0x80 | ( ( $code >> 6 ) & 0x3f ) ) . chr( 0x80 | ( $code & 0x3f ) );
	}

	/**
	 * Resolve an action against the current document before decoding URL path octets.
	 *
	 * @param string $action Form action.
	 *
	 * @return string
	 */
	private function resolve_form_action( string $action ): string {
		// Backslashes are path separators in browser HTTP URLs, but not in their queries.
		$action = preg_replace_callback(
			'/^[^?#]*/',
			static function ( array $matches ): string {
				return str_replace( '\\', '/', $matches[0] );
			},
			$action
		);
		$parts  = (array) wp_parse_url( $action );
		$base   = (array) wp_parse_url( $this->get_request_uri() );
		$origin = isset( $parts['host'] ) || isset( $parts['scheme'] );
		$path   = $parts['path'] ?? '';

		if ( $origin ) {
			$path = '' === $path ? '/' : $path;
		} elseif ( '' === $path ) {
			$path = $base['path'] ?? '/';
		} elseif ( '/' !== $path[0] ) {
			$base_path = $base['path'] ?? '/';
			$path      = substr( $base_path, 0, (int) strrpos( $base_path, '/' ) + 1 ) . $path;
		}

		$query = $this->get_action_query( $parts, $base, $origin );

		return $this->normalize_action_path( $path ) . ( '' !== $query ? '?' . $query : '' );
	}

	/**
	 * A pathless relative action inherits the document query unless it supplies one.
	 *
	 * @param array $parts  Action URL parts.
	 * @param array $base   Document URL parts.
	 * @param bool  $origin Whether the action specifies an origin.
	 *
	 * @return string
	 */
	private function get_action_query( array $parts, array $base, bool $origin ): string {
		return $parts['query'] ?? ( ! $origin && ! isset( $parts['path'] ) ? ( $base['query'] ?? '' ) : '' );
	}

	/**
	 * Remove browser dot segments while retaining empty segments and encoded separators.
	 *
	 * @param string $path Action path.
	 *
	 * @return string
	 */
	private function normalize_action_path( string $path ): string {
		$segments = [];

		foreach ( explode( '/', ltrim( $path, '/' ) ) as $segment ) {
			$dot_segment = str_ireplace( '%2e', '.', $segment );

			if ( '..' === $dot_segment ) {
				array_pop( $segments );
			} elseif ( '.' !== $dot_segment ) {
				$segments[] = $segment;
			}
		}

		return '/' . implode( '/', $segments );
	}

	/**
	 * Distinguish WordPress query routes sharing the same URL path.
	 *
	 * @param string $url Form target or request URL.
	 *
	 * @return string
	 */
	private function get_target_key( string $url ): string {
		$path = $this->get_path( $url );

		if ( '' === $path && 0 === strpos( $url, '?' ) ) {
			$path = $this->get_path( $this->get_request_uri() );
		}

		$query = wp_parse_url( $url, PHP_URL_QUERY );

		if ( ! is_string( $query ) || '' === $query ) {
			return $path;
		}

		parse_str( $query, $query_vars );

		if ( ! array_filter( array_intersect_key( $query_vars, array_flip( self::QUERY_SELECTORS ) ) ) ) {
			return $path;
		}

		$home_path  = $this->get_path( home_url( '/' ) );
		$index_path = ( '/' === $home_path ? '' : $home_path ) . '/index.php';

		if ( $path !== $home_path && $path !== $index_path ) {
			return $path;
		}

		$post_id = $this->get_query_post_id( $query_vars );

		return $post_id ? 'post:' . $post_id . ':' . $path : $path;
	}

	/**
	 * Get the post selected by WordPress query variables.
	 *
	 * @param array $query_vars Parsed query variables.
	 *
	 * @return int
	 */
	private function get_query_post_id( array $query_vars ): int {
		$query_vars = array_intersect_key( $query_vars, array_flip( array_merge( self::QUERY_SELECTORS, [ 'post_type' ] ) ) );

		if ( ! array_filter( array_intersect_key( $query_vars, array_flip( self::QUERY_SELECTORS ) ) ) ) {
			return 0;
		}

		// Use the installed core's selector normalization, aliases and precedence.
		$query = new WP_Query(
			$query_vars + [
				'posts_per_page' => 1,
				'no_found_rows'  => true,
			]
		);

		// Retain page-qualified action keys even when a numeric target does not exist yet.
		return $query->get_queried_object_id() ?: (int) ( $query->query_vars['page_id'] ?: $query->query_vars['p'] );
	}

	/**
	 * Get widget id value.
	 *
	 * @param string $form Form.
	 *
	 * @return string
	 */
	private function get_widget_id_value( string $form ): string {
		$widget_id_value = '';

		if ( preg_match( '#<input\s[\S\s]*?name="hcaptcha-widget-id"\s[\S\s]*?value="(.*?)"[\S\s]*?>#', $form, $m ) ) {
			$widget_id_value = $m[1];
		}

		return $widget_id_value;
	}

	/**
	 * Get equivalent registration keys for the current WordPress page.
	 *
	 * @return array
	 */
	private function get_canonical_request_keys(): array {
		// The native login script does not route page selectors through WordPress's main query.
		if ( 'wp-login.php' === basename( Request::filter_input( INPUT_SERVER, 'SCRIPT_NAME' ) ) ) {
			return [];
		}

		$query_vars = [];

		foreach ( array_merge( self::QUERY_SELECTORS, [ 'post_type' ] ) as $key ) {
			// Preserve WordPress's input and POST-before-GET precedence. WP_Query validates these selectors.
			// Nonce is verified later, in API::verify().
			// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
			$query_vars[ $key ] = $_POST[ $key ] ?? $_GET[ $key ] ?? '';
		}

		$post_id = $this->get_query_post_id( $query_vars );
		$post_id = $post_id ?: url_to_postid( Request::current_url() );

		if ( ! $post_id ) {
			return [];
		}

		$permalink = get_permalink( $post_id );

		if ( ! is_string( $permalink ) ) {
			return [];
		}

		$home_path  = $this->get_path( home_url( '/' ) );
		$index_path = ( '/' === $home_path ? '' : $home_path ) . '/index.php';

		// Existing registrations can use either plain permalink entry point.
		return array_unique(
			[
				$this->get_target_key( $permalink ),
				'post:' . $post_id . ':' . $home_path,
				'post:' . $post_id . ':' . $index_path,
				$this->get_path( $permalink ),
			]
		);
	}

	/**
	 * Get REQUEST_URI without a trailing slash.
	 *
	 * @return string
	 */
	private function get_request_uri(): string {
		return isset( $_SERVER['REQUEST_URI'] ) ?
			(string) filter_var( wp_unslash( $_SERVER['REQUEST_URI'] ), FILTER_SANITIZE_URL ) :
			'';
	}

	/**
	 * Identify the page that rendered a registration, including plain permalink pages.
	 *
	 * @return string
	 */
	private function get_registration_source(): string {
		$post_id = get_queried_object_id();
		$post_id = $post_id ?: url_to_postid( Request::current_url() );
		$post_id = $post_id ?: (int) get_the_ID();

		return $post_id ? 'post:' . $post_id : $this->get_request_uri();
	}

	/**
	 * Get a path without a trailing slash.
	 * Return '/' for home page.
	 *
	 * @param string $url URL.
	 *
	 * @return string
	 */
	private function get_path( string $url ): string {
		if ( 0 === strpos( $url, '//' ) ) {
			$url = '/' . ltrim( $url, '/' );
		}

		$path = rawurldecode( (string) wp_parse_url( $url, PHP_URL_PATH ) );
		$path = preg_replace( '#/+#', '/', $path );

		return '/' === $path ? $path : untrailingslashit( $path );
	}

	/**
	 * Get names of visible inputs on the form.
	 *
	 * @param string $form Form.
	 *
	 * @return array
	 */
	private function get_visible_input_names( string $form ): array {
		$names = [];

		if ( ! preg_match_all( '#<input[\S\s]+?>#', $form, $matches ) ) {
			return $names;
		}

		foreach ( $matches[0] as $input ) {
			if ( ! $this->is_stable_input( $input ) ) {
				continue;
			}

			$name = $this->get_input_name( $input );

			if ( $name && 0 !== strpos( $name, 'hcap_' ) ) {
				$names[] = $name;
			}
		}

		return $names;
	}

	/**
	 * Check if an input is always included in a regular form submission.
	 *
	 * @param string $input Input.
	 *
	 * @return bool
	 */
	private function is_stable_input( string $input ): bool {
		if ( preg_match( '#\sdisabled(?:\s|=|/?>)#i', $input ) ) {
			return false;
		}

		if ( ! preg_match( '#type\s*?=\s*?["\'](.+?)["\']#i', $input, $matches ) ) {
			return true;
		}

		return ! in_array(
			strtolower( $matches[1] ),
			[ 'hidden', 'checkbox', 'radio', 'submit', 'button', 'reset', 'image', 'file' ],
			true
		);
	}

	/**
	 * Get input name.
	 *
	 * @param string $input Input.
	 *
	 * @return string|null
	 */
	private function get_input_name( string $input ): ?string {
		if ( preg_match( '#name\s*?=\s*?["\'](.+?)["\']#', $input, $matches ) ) {
			return $matches[1];
		}

		return null;
	}

	/**
	 * Persist form registrations and retire corresponding legacy transient entries.
	 *
	 * @param array $forms_data Forms data to register.
	 */
	protected function update_form_registrations( array $forms_data ): void {
		$transient        = get_transient( self::TRANSIENT );
		$legacy_forms     = is_array( $transient ) ? $transient : [];
		$registered_forms = $legacy_forms;

		foreach ( $forms_data as $form_data ) {
			$data         = wp_parse_args(
				$form_data,
				[
					'action' => '',
					'inputs' => [],
					'args'   => [],
				]
			);
			$data['args'] = wp_parse_args(
				$data['args'],
				[
					'auto' => false,
				]
			);
			if ( $this->update_form_registration( $registered_forms, $data ) ) {
				unset( $legacy_forms[ $data['action'] ] );
			}
		}

		if ( ! is_array( $transient ) || $legacy_forms === $transient ) {
			return;
		}

		if ( $legacy_forms ) {
			set_transient(
				self::TRANSIENT,
				$legacy_forms,
				/** This filter is documented in wp-includes/pluggable.php. */
				apply_filters( 'nonce_life', constant( 'DAY_IN_SECONDS' ) ) // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
			);
		} else {
			delete_transient( self::TRANSIENT );
		}
	}

	/**
	 * Update one action in its persistent registration.
	 *
	 * @param array $registered_forms Registered forms.
	 * @param array $data             Form data.
	 *
	 * @return bool Whether a registration was persisted or removed.
	 */
	private function update_form_registration( array &$registered_forms, array $data ): bool {
		$action = $data['action'];

		unset( $data['action'] );

		$inputs       = (array) $data['inputs'];
		$widget_id    = (string) ( $data['widget_id'] ?? '' );
		$option_name  = $this->get_option_name( $action );
		$saved_forms  = get_option( $option_name, false );
		$key          = false;
		$action_forms = is_array( $saved_forms ) ? $saved_forms : ( $registered_forms[ $action ] ?? [] );

		foreach ( $action_forms as $index => $action_form ) {
			if (
				( ! isset( $action_form['source'] ) || ( $data['source'] ?? null ) === $action_form['source'] ) &&
				$this->is_same_registered_form_identity( (array) $action_form, $inputs, $widget_id )
			) {
				$key = $index;
				break;
			}
		}

		$registered = false !== $key;

		if ( $data['args']['auto'] ) {
			if ( $registered ) {
				$action_forms[ $key ] = $data;
			} else {
				$action_forms[] = $data;
			}

			$registered_forms[ $action ] = array_values( $action_forms );
			update_option( $option_name, $registered_forms[ $action ], false );

			return true;
		}

		return $this->remove_form_registration( $registered_forms, $data, $action, $action_forms, $key );
	}

	/**
	 * Remove a registration only from the page that created it.
	 *
	 * @param array     $registered_forms Registered forms.
	 * @param array     $data             Rendered form data.
	 * @param string    $action           Form action.
	 * @param array     $action_forms     Forms registered for the action.
	 * @param int|false $key              Form key.
	 *
	 * @return bool Whether a registration was removed.
	 */
	private function remove_form_registration(
		array &$registered_forms,
		array $data,
		string $action,
		array $action_forms,
		$key
	): bool {
		if ( false === $key || ( $action_forms[ $key ]['source'] ?? null ) !== ( $data['source'] ?? null ) ) {
			return false;
		}

		$this->remove_registered_form( $registered_forms, $action, $action_forms, $key );

		$option_name = $this->get_option_name( $action );

		if ( $registered_forms[ $action ] ?? [] ) {
			update_option( $option_name, $registered_forms[ $action ], false );
		} else {
			delete_option( $option_name );
		}

		return true;
	}

	/**
	 * Whether form data has the same registration identity.
	 *
	 * @param array  $registered_form Registered form.
	 * @param array  $inputs Input names.
	 * @param string $widget_id Widget ID.
	 *
	 * @return bool
	 */
	private function is_same_registered_form_identity( array $registered_form, array $inputs, string $widget_id ): bool {
		if ( $widget_id ) {
			return (string) ( $registered_form['widget_id'] ?? '' ) === $widget_id;
		}

		return ( $registered_form['inputs'] ?? [] ) === $inputs;
	}

	/**
	 * Whether a request matches the registered form structure.
	 *
	 * @param array  $registered_form Registered form.
	 * @param array  $post Submitted form data.
	 * @param string $widget_id Submitted widget ID.
	 *
	 * @return bool
	 */
	private function is_same_registered_form( array $registered_form, array $post, string $widget_id ): bool {
		$inputs               = (array) ( $registered_form['inputs'] ?? [] );
		$registered_widget_id = (string) ( $registered_form['widget_id'] ?? '' );

		if ( ! $widget_id || $registered_widget_id !== $widget_id ) {
			return false;
		}

		foreach ( $inputs as $input ) {
			if ( ! $this->has_input( $input, $post ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Check a registered HTML input name against PHP's parsed submission.
	 *
	 * @param string $name Input name.
	 * @param array  $post Submitted form data.
	 *
	 * @return bool
	 */
	private function has_input( string $name, array $post ): bool {
		// Let PHP normalize the root key without interpreting name characters as query separators.
		parse_str( rawurlencode( $name ) . '=1', $parsed );

		if ( ! $parsed ) {
			return false;
		}

		preg_match_all( '/\[([^\]]*)\]/', $name, $matches );
		$keys = array_merge( [ array_key_first( $parsed ) ], $matches[1] );

		return $this->has_input_path( $post, $keys );
	}

	/**
	 * Resolve named array keys, treating empty brackets as any submitted array element.
	 *
	 * @param mixed $value Submitted value.
	 * @param array $keys  Remaining input name keys.
	 *
	 * @return bool
	 */
	private function has_input_path( $value, array $keys ): bool {
		if ( ! $keys ) {
			return true;
		}

		if ( ! is_array( $value ) ) {
			return false;
		}

		$key = array_shift( $keys );

		if ( '' !== $key ) {
			return array_key_exists( $key, $value ) && $this->has_input_path( $value[ $key ], $keys );
		}

		foreach ( $value as $item ) {
			if ( $this->has_input_path( $item, $keys ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Remove a form from a registered action.
	 *
	 * @param array  $registered_forms Registered forms.
	 * @param string $action           Form action.
	 * @param array  $action_forms     Forms registered for the action.
	 * @param int    $key              Form key.
	 *
	 * @return void
	 */
	private function remove_registered_form(
		array &$registered_forms,
		string $action,
		array $action_forms,
		int $key
	): void {
		unset( $action_forms[ $key ] );

		if ( $action_forms ) {
			$registered_forms[ $action ] = array_values( $action_forms );

			return;
		}

		unset( $registered_forms[ $action ] );
	}

	/**
	 * Get the non-autoloaded option name for a form action path.
	 *
	 * @param string $action Form action path.
	 *
	 * @return string
	 */
	private function get_option_name( string $action ): string {
		return self::OPTION_PREFIX . hash( 'sha256', $action );
	}

	/**
	 * Get registered form.
	 *
	 * @param string $path URL path.
	 *
	 * @return array|null
	 */
	protected function get_registered_form( string $path ): ?array {
		$saved_forms  = get_option( $this->get_option_name( $path ), false );
		$action_forms = is_array( $saved_forms ) ? $saved_forms : [];

		if ( ! $action_forms ) {
			$registered_forms = get_transient( self::TRANSIENT );
			$action_forms     = (array) ( $registered_forms[ $path ] ?? [] );
		}

		if ( ! $action_forms ) {
			return null;
		}

		$action_forms = $this->get_applicable_action_forms( $action_forms );

		if ( ! $action_forms ) {
			return null;
		}

		// Nonce is verified later, in \HCaptcha\Helpers\API::verify().
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$post      = $_POST;
		$widget_id = Request::filter_input( INPUT_POST, HCaptcha::HCAPTCHA_WIDGET_ID );
		$widget_id = is_string( $widget_id ) ? $widget_id : '';

		foreach ( $action_forms as $registered_form ) {
			if ( $this->is_same_registered_form( (array) $registered_form, $post, $widget_id ) ) {
				return $registered_form;
			}
		}

		/**
		 * Filters an unmatched request for an action that has auto-verified forms.
		 *
		 * A dedicated integration may return null to defer verification when it
		 * owns the submitted signed widget ID and verifies the request itself.
		 *
		 * @param array|null $registered_form Empty array by default.
		 * @param string     $path            Request path.
		 * @param string     $widget_id       Submitted widget ID.
		 */
		$registered_form = apply_filters( 'hcap_auto_verify_unmatched_form', [], $path, $widget_id );

		return is_array( $registered_form ) ? $registered_form : null;
	}

	/**
	 * Exclude Brevo forms when Brevo will not process the submission.
	 *
	 * @param array $action_forms Forms registered for the action.
	 *
	 * @return array
	 */
	private function get_applicable_action_forms( array $action_forms ): array {
		if ( 'subscribe_form_submit' === Request::filter_input( INPUT_POST, 'sib_form_action' ) ) {
			return $action_forms;
		}

		return array_values(
			array_filter(
				$action_forms,
				static function ( $form ): bool {
					$source = (array) ( $form['args']['id']['source'] ?? [] );

					return ! in_array( 'mailin/sendinblue.php', $source, true );
				}
			)
		);
	}

	/**
	 * Process content and register the form for auto verification.
	 *
	 * @param string|mixed $content Content.
	 *
	 * @return string
	 */
	private function process_content( $content ): string {
		$content = (string) $content;

		if ( ! Request::is_frontend() ) {
			return $content;
		}

		$forms = $this->get_content_forms( $content );

		if ( $forms ) {
			$this->register_forms( $forms );
		}

		return $content;
	}

	/**
	 * Find complete form elements without treating raw text or comments as markup.
	 *
	 * @param string $content Rendered content.
	 *
	 * @return array
	 */
	private function get_content_forms( string $content ): array {
		$tag = '(?:[^>"\']|"[^"]*"|\'[^\']*\')*>';
		preg_match_all(
			'#<!--[\S\s]*?-->|<(script|style|textarea|title)(?=[\x09\x0a\x0c\x0d\x20/>])' . $tag . '[\S\s]*?</\1[\x09\x0a\x0c\x0d\x20]*>|</?form(?=[\x09\x0a\x0c\x0d\x20/>])' . $tag . '#i',
			$content,
			$matches,
			PREG_OFFSET_CAPTURE
		);
		$forms = [];
		$start = null;

		foreach ( $matches[0] as [ $markup, $offset ] ) {
			if ( null === $start && 0 === stripos( $markup, '<form' ) ) {
				$start = $offset;
			} elseif ( null !== $start && 0 === stripos( $markup, '</form' ) ) {
				$form = substr( $content, $start, $offset + strlen( $markup ) - $start );

				if ( preg_match( '#class="[^"]*\bh-captcha\b[^"]*"#', $form ) ) {
					$forms[] = $form;
				}

				$start = null;
			}
		}

		return $forms;
	}
}
