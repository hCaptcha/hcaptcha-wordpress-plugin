<?php
/**
 * AutoVerify class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\AutoVerify;

use WP_Rewrite;

/**
 * Class AutoVerify
 */
class AutoVerify {

	/**
	 * Transient name where to store registered forms.
	 */
	const TRANSIENT = 'hcaptcha_auto_verify';

	/**
	 * Init class.
	 */
	public function init() {
		$this->init_hooks();
	}

	/**
	 * Init hooks.
	 */
	private function init_hooks() {
		add_action( 'init', [ $this, 'verify_form' ], - PHP_INT_MAX );
		add_filter( 'the_content', [ $this, 'content_filter' ], PHP_INT_MAX );
	}

	/**
	 * Filter page content and register the form for auto verification.
	 *
	 * @param string $content Content.
	 *
	 * @return string
	 */
	public function content_filter( $content ) {
		if ( ! $this->is_frontend() ) {
			return $content;
		}

		if (
			preg_match_all(
				'#<form [\S\s]+?class="h-captcha"[\S\s]+?</form>#',
				$content,
				$matches
			)
		) {
			$forms = array_map(
				static function ( $match ) {
					return $match[0];
				},
				$matches
			);
			$this->register_forms( $forms );
		}

		return $content;
	}

	/**
	 * Verify a form automatically.
	 *
	 * @noinspection ForgottenDebugOutputInspection
	 */
	public function verify_form() {
		if ( ! $this->is_frontend() ) {
			return;
		}

		$request_method = isset( $_SERVER['REQUEST_METHOD'] ) ?
			filter_var( wp_unslash( $_SERVER['REQUEST_METHOD'] ), FILTER_SANITIZE_FULL_SPECIAL_CHARS ) :
			'';

		if ( 'POST' !== $request_method ) {
			return;
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ?
			filter_var( wp_unslash( $_SERVER['REQUEST_URI'] ), FILTER_SANITIZE_FULL_SPECIAL_CHARS ) :
			'';

		$request_uri = wp_parse_url( $request_uri, PHP_URL_PATH );

		if ( ! $request_uri ) {
			return;
		}

		if ( ! $this->is_form_registered( $request_uri ) ) {
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
	 * Check if it is a frontend request.
	 *
	 * @return bool
	 */
	private function is_frontend() {
		return ! ( $this->is_cli() || is_admin() || wp_doing_ajax() || $this->is_rest() );
	}

	/**
	 * Checks if the current request is a WP REST API request.
	 *
	 * Case #1: After WP_REST_Request initialisation
	 * Case #2: Support "plain" permalink settings
	 * Case #3: It can happen that WP_Rewrite is not yet initialized,
	 *          so do this (wp-settings.php)
	 * Case #4: URL Path begins with wp-json/ (your REST prefix)
	 *          Also supports WP installations in sub folders
	 *
	 * @return bool
	 * @author matzeeable
	 */
	private function is_rest() {
		if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return false;
		}

		// Case #1.
		if ( defined( 'REST_REQUEST' ) && constant( 'REST_REQUEST' ) ) {
			return true;
		}

		// Case #2.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$rest_route = isset( $_GET['rest_route'] ) ?
			filter_input( INPUT_GET, 'rest_route', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) :
			'';

		if ( 0 === strpos( trim( $rest_route, '\\/' ), rest_get_url_prefix() ) ) {
			return true;
		}

		// Case #3.
		global $wp_rewrite;
		if ( null === $wp_rewrite ) {
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$wp_rewrite = new WP_Rewrite();
		}

		// Case #4.
		$current_url = wp_parse_url( add_query_arg( [] ), PHP_URL_PATH );
		$rest_url    = wp_parse_url( trailingslashit( rest_url() ), PHP_URL_PATH );

		return 0 === strpos( $current_url, $rest_url );
	}

	/**
	 * Check of it is a CLI request
	 *
	 * @return bool
	 */
	private function is_cli() {
		return defined( 'WP_CLI' ) && constant( 'WP_CLI' );
	}

	/**
	 * Register forms.
	 *
	 * @param array $forms Forms found in the content.
	 */
	private function register_forms( $forms ) {
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
	private function get_form_action( $form ) {
		$form_action = '';

		if ( preg_match( '#<form [\S\s]*?action="(.*?)"[\S\s]*?>#', $form, $m ) ) {
			$form_action = $m[1];
		}

		if ( ! $form_action ) {
			$form_action = isset( $_SERVER['REQUEST_URI'] ) ?
				filter_var( wp_unslash( $_SERVER['REQUEST_URI'] ), FILTER_SANITIZE_FULL_SPECIAL_CHARS ) :
				'';
		}

		$form_action = wp_parse_url( $form_action, PHP_URL_PATH );

		return untrailingslashit( $form_action );
	}

	/**
	 * Get names of visible inputs on the form.
	 *
	 * @param string $form Form.
	 *
	 * @return array
	 */
	private function get_visible_input_names( $form ) {
		$names = [];

		if ( ! preg_match_all( '#<input[\S\s]+?>#', $form, $matches ) ) {
			return $names;
		}

		$inputs = $matches[0];

		foreach ( $inputs as $input ) {
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
	private function is_input_visible( $input ) {
		return ! preg_match( '#type[\s]*?=[\s]*?["\']hidden["\']#', $input );
	}

	/**
	 * Get input name.
	 *
	 * @param string $input Input.
	 *
	 * @return string|null
	 */
	private function get_input_name( $input ) {
		if ( preg_match( '#name[\s]*?=[\s]*?["\'](.+?)["\']#', $input, $matches ) ) {
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
	private function get_form_auto( $form ) {
		if ( preg_match( '#class="h-captcha"[\S\s]+?data-auto="(.*)"[\S\s]*?>#', $form, $matches ) ) {
			return $matches[1];
		}

		return null;
	}

	/**
	 * Check if form is auto.
	 *
	 * @param string $form Form.
	 *
	 * @return bool
	 */
	private function is_form_auto( $form ) {
		return 'true' === $this->get_form_auto( $form );
	}

	/**
	 * Update form data in transient.
	 *
	 * @param array $forms_data Forms data to update in transient.
	 */
	private function update_transient( $forms_data ) {
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

			if ( $auto && $registered ) {
				$registered_forms[ $action ][ $key ] = $inputs;
			}

			if ( ! $auto && $registered ) {
				unset( $registered_forms[ $action ][ $key ] );
			}
		}

		set_transient(
			self::TRANSIENT,
			$registered_forms,
			/** This filter is documented in wp-includes/pluggable.php. */
			apply_filters( 'nonce_life', DAY_IN_SECONDS )
		);
	}

	/**
	 * Is form registered.
	 *
	 * @param string $request_uri Request uri.
	 *
	 * @return bool
	 */
	private function is_form_registered( $request_uri ) {
		$registered_forms = get_transient( self::TRANSIENT );

		if ( empty( $registered_forms ) ) {
			return false;
		}

		$request_uri = untrailingslashit( $request_uri );

		if ( ! isset( $registered_forms[ $request_uri ] ) ) {
			return false;
		}

		foreach ( $registered_forms[ $request_uri ] as $registered_form ) {
			// Nonce is verified later, in hcaptcha_verify_post().
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( ! empty( array_intersect( array_keys( $_POST ), $registered_form ) ) ) {
				return true;
			}
		}

		return false;
	}
}
