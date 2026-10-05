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

		$canonical_path = $this->get_canonical_request_path();

		if ( ! $canonical_path || $canonical_path === $path ) {
			return null;
		}

		return $this->get_registered_form( $canonical_path );
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
		return [
			'nonce_name'         => $name,
			'nonce_action'       => $action,
			'h-captcha-response' => Request::filter_input( INPUT_POST, 'h-captcha-response' ),
			'data'               => $this->get_data(),
			'expected_id'        => $expected_id,
		];
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
		$form_action = '';

		if ( preg_match( '#<form\s[\S\s]*?action="(.*?)"[\S\s]*?>#', $form, $m ) ) {
			$form_action = $m[1];
		}

		$form_action = $form_action ?: $this->get_request_uri();

		return $this->get_target_key( $form_action );
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
		$post_id = $this->get_query_post_id( $query_vars );

		if ( ! $post_id ) {
			return $path;
		}

		$home_path  = $this->get_path( home_url( '/' ) );
		$index_path = ( '/' === $home_path ? '' : $home_path ) . '/index.php';

		return $path === $home_path || $path === $index_path ? 'post:' . $post_id . ':' . $path : $path;
	}

	/**
	 * Get the post selected by WordPress query variables.
	 *
	 * @param array $query_vars Parsed query variables.
	 *
	 * @return int
	 */
	private function get_query_post_id( array $query_vars ): int {
		foreach ( [ 'page_id', 'p' ] as $key ) {
			$value = $query_vars[ $key ] ?? null;

			if ( is_scalar( $value ) && ctype_digit( (string) $value ) ) {
				return (int) $value;
			}
		}

		if ( ! empty( $query_vars['pagename'] ) && is_string( $query_vars['pagename'] ) ) {
			$page = get_page_by_path( $query_vars['pagename'] );

			return $page->ID ?? 0;
		}

		return 0;
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
	 * Get the canonical path of the current WordPress request.
	 *
	 * @return string
	 */
	private function get_canonical_request_path(): string {
		$post_id = url_to_postid( Request::current_url() );

		if ( ! $post_id ) {
			$pagename = Request::filter_input( INPUT_GET, 'pagename' );

			if ( is_string( $pagename ) && '' !== $pagename ) {
				$page = get_page_by_path( $pagename );

				if ( $page ) {
					$post_id = $page->ID;
				}
			}
		}

		if ( ! $post_id ) {
			return '';
		}

		$permalink = get_permalink( $post_id );

		if ( ! is_string( $permalink ) ) {
			return '';
		}

		return $this->get_path( $permalink );
	}

	/**
	 * Get REQUEST_URI without a trailing slash.
	 *
	 * @return string
	 */
	private function get_request_uri(): string {
		return isset( $_SERVER['REQUEST_URI'] ) ?
			(string) filter_var( wp_unslash( $_SERVER['REQUEST_URI'] ), FILTER_SANITIZE_FULL_SPECIAL_CHARS ) :
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
	 * @param array  $post_keys Submitted input names.
	 * @param string $widget_id Submitted widget ID.
	 *
	 * @return bool
	 */
	private function is_same_registered_form( array $registered_form, array $post_keys, string $widget_id ): bool {
		$inputs               = (array) ( $registered_form['inputs'] ?? [] );
		$registered_widget_id = (string) ( $registered_form['widget_id'] ?? '' );

		return $widget_id && $registered_widget_id === $widget_id && ! array_diff( $inputs, $post_keys );
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
		$post_keys = array_keys( $_POST );
		$widget_id = Request::filter_input( INPUT_POST, HCaptcha::HCAPTCHA_WIDGET_ID );
		$widget_id = is_string( $widget_id ) ? $widget_id : '';

		foreach ( $action_forms as $registered_form ) {
			if ( $this->is_same_registered_form( (array) $registered_form, $post_keys, $widget_id ) ) {
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

		if (
			preg_match_all(
				'#<form [\S\s]+?class="[^"]*\bh-captcha\b[^"]*"[\S\s]+?</form>#',
				$content,
				$matches,
				PREG_PATTERN_ORDER
			)
		) {
			$forms = $matches[0];

			$this->register_forms( $forms );
		}

		return $content;
	}
}
