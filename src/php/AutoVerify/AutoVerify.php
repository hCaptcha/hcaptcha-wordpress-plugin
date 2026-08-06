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
	 * Maximum serialized transient size in bytes.
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
		$id = (array) ( $args['id'] ?? [] );

		return [
			'source'  => empty( $id['source'] ) ? [ self::class ] : (array) $id['source'],
			'form_id' => empty( $id['form_id'] ) ? (int) get_the_ID() : $id['form_id'],
		];
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
				'successMsg' => __( 'The form was submitted successfully.', 'hcaptcha-for-forms-and-more' ),
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

		$path            = $this->get_path( $request_uri );
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
				'args'      => $args,
			];
		}

		$this->update_transient( $forms_data );
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

		return $this->get_path( $form_action );
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
	 * Update form data in transient.
	 *
	 * @param array $forms_data Forms data to update in transient.
	 */
	protected function update_transient( array $forms_data ): void {
		$transient        = get_transient( self::TRANSIENT );
		$registered_forms = $transient ?: [];

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
			$action       = $data['action'];

			unset( $data['action'] );

			$inputs    = (array) $data['inputs'];
			$widget_id = (string) ( $data['widget_id'] ?? '' );
			$args      = $data['args'];
			$auto      = $args['auto'];

			$key          = false;
			$action_forms = $registered_forms[ $action ] ?? [];

			foreach ( $action_forms as $index => $action_form ) {
				if ( $this->is_same_registered_form_identity( (array) $action_form, $inputs, $widget_id ) ) {
					$key = $index;
					break;
				}
			}

			$registered = false !== $key;

			if ( $auto ) {
				if ( $registered ) {
					$action_forms[ $key ] = $data;
				} else {
					$action_forms[] = $data;
				}

				// Move the action to the end of the array to mark it as recently used.
				unset( $registered_forms[ $action ] );

				$registered_forms[ $action ] = array_values( $action_forms );

				continue;
			}

			if ( $registered ) {
				$this->remove_registered_form( $registered_forms, $action, $action_forms, $key );
			}
		}

		$registered_forms = $this->limit_transient_size( $registered_forms );

		set_transient(
			self::TRANSIENT,
			$registered_forms,
			/** This filter is documented in wp-includes/pluggable.php. */
			apply_filters( 'nonce_life', constant( 'DAY_IN_SECONDS' ) ) // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		);
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
	 * Limit the serialized transient size by removing the least recently used actions.
	 *
	 * @param array $registered_forms Registered forms.
	 *
	 * @return array
	 */
	private function limit_transient_size( array $registered_forms ): array {
		$empty_array_size = $this->get_serialized_array_wrapper_size( 0 );

		/**
		 * Filters the maximum serialized size of the auto-verify transient.
		 *
		 * @param int $max_size Maximum size in bytes.
		 */
		$max_size = (int) apply_filters( 'hcap_auto_verify_transient_max_size', self::MAX_TRANSIENT_SIZE );
		$max_size = max( $empty_array_size, $max_size );

		$entry_sizes            = [];
		$payload_size           = 0;
		$registered_forms_count = count( $registered_forms );

		foreach ( $registered_forms as $action => $action_forms ) {
			$entry_size             = $this->get_serialized_array_entry_size( $action, $action_forms );
			$entry_sizes[ $action ] = $entry_size;
			$payload_size          += $entry_size;
		}

		while (
			$registered_forms &&
			$this->get_serialized_array_wrapper_size( $registered_forms_count ) + $payload_size > $max_size
		) {
			$action        = array_key_first( $registered_forms );
			$payload_size -= $entry_sizes[ $action ];

			unset( $registered_forms[ $action ], $entry_sizes[ $action ] );

			--$registered_forms_count;
		}

		return $registered_forms;
	}

	/**
	 * Get the serialized size of an array wrapper.
	 *
	 * @param int $count Number of array entries.
	 *
	 * @return int
	 */
	private function get_serialized_array_wrapper_size( int $count ): int {
		return strlen( 'a:' . $count . ':{' ) + 1;
	}

	/**
	 * Get the serialized size of an array entry.
	 *
	 * @param int|string $key   Array key.
	 * @param mixed      $value Array value.
	 *
	 * @return int
	 */
	private function get_serialized_array_entry_size( $key, $value ): int {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		$serialized_entry = serialize( [ $key => $value ] );

		return strlen( $serialized_entry ) - $this->get_serialized_array_wrapper_size( 1 );
	}

	/**
	 * Get registered form.
	 *
	 * @param string $path URL path.
	 *
	 * @return array|null
	 */
	protected function get_registered_form( string $path ): ?array {
		$registered_forms = get_transient( self::TRANSIENT );

		if ( empty( $registered_forms ) ) {
			return null;
		}

		$action_forms = (array) ( $registered_forms[ $path ] ?? [] );

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

		return [];
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
