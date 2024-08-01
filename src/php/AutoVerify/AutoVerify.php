<?php
/**
 * AutoVerify class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\AutoVerify;

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
		add_action( 'init', [ $this, 'verify_form' ], - PHP_INT_MAX );
		add_filter( 'the_content', [ $this, 'content_filter' ], PHP_INT_MAX );
		add_filter( 'widget_block_content', [ $this, 'widget_block_content_filter' ], PHP_INT_MAX, 3 );
		add_action( 'hcap_auto_verify_register', [ $this, 'content_filter' ] );
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
	 * Verify a form automatically.
	 *
	 * @return void
	 * @noinspection ForgottenDebugOutputInspection
	 */
	public function verify_form(): void {
		if ( ! Request::is_post() || ! Request::is_frontend() ) {
			return;
		}

		$path = $this->get_path( $this->get_request_uri() );

		if ( ! $path ) {
			return;
		}

		if ( ! $this->is_form_registered( $path ) ) {
			return;
		}

		$result = hcaptcha_verify_post();

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
	 * Register forms.
	 *
	 * @param array $forms Forms found in the content.
	 */
	protected function register_forms( array $forms ): void {
		$forms_data = [];

		foreach ( $forms as $form ) {
			$action = $this->get_form_action( $form );

			if ( ! $action ) {
				// We cannot register form without action specified or determined from $_SERVER['REQUEST_URI'].
				continue;
			}

			$forms_data[] = [
				'action' => $action,
				'inputs' => $this->get_visible_input_names( $form ),
				'auto'   => $this->is_form_auto( $form ),
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

		if ( preg_match( '#<form [\S\s]*?action="(.*?)"[\S\s]*?>#', $form, $m ) ) {
			$form_action = $m[1];
		}

		$form_action = $form_action ?: $this->get_request_uri();

		return $this->get_path( $form_action );
	}

	/**
	 * Get REQUEST_URI without trailing slash.
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
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );

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
			if ( ! $this->is_input_visible( $input ) ) {
				continue;
			}

			$name = $this->get_input_name( $input );

			if ( $name ) {
				$names[] = $name;
			}
		}

		return $names;
	}

	/**
	 * Check if input is visible.
	 *
	 * @param string $input Input.
	 *
	 * @return bool
	 */
	private function is_input_visible( string $input ): bool {
		return ! preg_match( '#type\s*?=\s*?["\']hidden["\']#', $input );
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
	 * Get form auto.
	 *
	 * @param string $form Form.
	 *
	 * @return string|null
	 */
	private function get_form_auto( string $form ): ?string {
		if ( preg_match( '#class="h-captcha"[\S\s]+?data-auto="(.*)"[\S\s]*?>#', $form, $matches ) ) {
			return $matches[1];
		}

		return null;
	}

	/**
	 * Check if the form is auto.
	 *
	 * @param string $form Form.
	 *
	 * @return bool
	 */
	private function is_form_auto( string $form ): bool {
		return 'true' === $this->get_form_auto( $form );
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
			$action = $form_data['action'];
			$inputs = $form_data['inputs'];
			$auto   = $form_data['auto'];

			$key = isset( $registered_forms[ $action ] ) ?
				array_search( $inputs, $registered_forms[ $action ], true ) :
				false;

			$registered = false !== $key;

			if ( $auto && ! $registered ) {
				$registered_forms[ $action ][] = $inputs;
			}

			if ( ! $auto && $registered ) {
				unset( $registered_forms[ $action ][ $key ] );
			}
		}

		set_transient(
			self::TRANSIENT,
			$registered_forms,
			/** This filter is documented in wp-includes/pluggable.php. */
			apply_filters( 'nonce_life', constant( 'DAY_IN_SECONDS' ) )
		);
	}

	/**
	 * Is the form registered?
	 *
	 * @param string $path URL path.
	 *
	 * @return bool
	 */
	protected function is_form_registered( string $path ): bool {
		$registered_forms = get_transient( self::TRANSIENT );

		if ( empty( $registered_forms ) ) {
			return false;
		}

		if ( ! isset( $registered_forms[ $path ] ) ) {
			return false;
		}

		foreach ( $registered_forms[ $path ] as $registered_form ) {
			// Nonce is verified later, in hcaptcha_verify_post().
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( ! empty( array_intersect( array_keys( $_POST ), $registered_form ) ) ) {
				return true;
			}
		}

		return false;
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
				'#<form [\S\s]+?class="h-captcha"[\S\s]+?</form>#',
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
