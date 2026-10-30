<?php
/**
 * PasswordProtected class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\WP;

use HCaptcha\Helpers\API;
use HCaptcha\Helpers\HCaptcha;
use WP_Error;
use WP_Post;
use WP_REST_Comments_Controller;
use WP_REST_Posts_Controller;
use WP_REST_Request;

/**
 * Class PasswordProtected.
 */
class PasswordProtected {

	/**
	 * Nonce action.
	 */
	private const ACTION = 'hcaptcha_password_protected';

	/**
	 * Nonce name.
	 */
	private const NONCE = 'hcaptcha_password_protected_nonce';

	/**
	 * Whether this request passed the post password CAPTCHA verifier.
	 *
	 * @var bool
	 */
	private bool $post_pass_verified = false;

	/**
	 * PasswordProtected constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Add hooks.
	 *
	 * @return void
	 */
	public function init_hooks(): void {
		add_filter( 'the_password_form', [ $this, 'add_captcha' ], PHP_INT_MAX, 2 );
		add_action( 'login_form_postpass', [ $this, 'verify' ] );
		add_filter( 'post_password_expires', [ $this, 'set_proof_cookie' ], PHP_INT_MAX );
		add_filter( 'post_password_required', [ $this, 'require_captcha_proof' ], 1, 2 );
		add_filter( 'rest_request_before_callbacks', [ $this, 'reject_rest_password' ], 10, 3 );
		add_action( 'clear_auth_cookie', [ $this, 'clear_proof_cookie' ], PHP_INT_MAX );
	}

	/**
	 * Reject REST password attempts before core checks a candidate password.
	 *
	 * @param mixed           $response Existing REST response.
	 * @param array           $handler  Matched route handler.
	 * @param WP_REST_Request $request  REST request.
	 *
	 * @return mixed|WP_Error
	 */
	public function reject_rest_password( $response, array $handler, WP_REST_Request $request ) {
		if ( is_wp_error( $response ) || ! $request->has_param( 'password' ) ) {
			return $response;
		}

		$callback = $handler['callback'] ?? null;

		if ( ! is_array( $callback ) || count( $callback ) < 2 || ! is_object( $callback[0] ) ) {
			return $response;
		}

		[ $controller, $method ] = $callback;
		$is_read                 = $request->is_method( 'GET' ) || $request->is_method( 'HEAD' );

		if (
			! ( $is_read && $controller instanceof WP_REST_Posts_Controller && in_array( $method, [ 'get_item', 'get_items' ], true ) ) &&
			! ( $controller instanceof WP_REST_Comments_Controller && in_array( $method, [ 'get_item', 'get_items', 'create_item' ], true ) )
		) {
			return $response;
		}

		$id = $this->get_expected_id();

		/** This filter is documented in src/php/Helpers/HCaptcha.php. */
		if ( ! apply_filters( 'hcap_protect_form', true, $id['source'], $id['form_id'] ) ) {
			return $response;
		}

		return new WP_Error(
			'hcaptcha_rest_password_forbidden',
			__( 'REST password attempts require the protected password form.', 'hcaptcha-for-forms-and-more' ),
			[ 'status' => 403 ]
		);
	}

	/**
	 * Filters the template created by the Download Manager plugin and adds hcaptcha.
	 *
	 * @param string|mixed $output The password form's HTML output.
	 * @param WP_Post      $post   Post object.
	 *
	 * @return string
	 * @noinspection PhpUnusedParameterInspection
	 * @noinspection UnnecessaryCastingInspection
	 */
	public function add_captcha( $output, WP_Post $post ): string {
		$args = [
			'action' => self::ACTION,
			'name'   => self::NONCE,
			'id'     => $this->get_expected_id(),
		];

		$hcaptcha = HCaptcha::form( $args );

		return (string) preg_replace( '/(<\/form>)/', $hcaptcha . '$1', (string) $output );
	}

	/**
	 * Verify request.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 * @noinspection ForgottenDebugOutputInspection
	 */
	public function verify(): void {
		$this->post_pass_verified = false;

		/**
		 * Mimic the behavior of the WordPress login form.
		 * These lines are copied from the wp-login.php file.
		 * This is needed to process a direct `/wp-login.php?action=postpass` request with the same logic.
		 */
		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$redirect_to = $_POST['redirect_to'] ?? wp_get_referer();

		if ( ! isset( $_POST['post_password'] ) || ! is_string( $_POST['post_password'] ) ) {
			// @codeCoverageIgnoreStart
			wp_safe_redirect( $redirect_to );

			exit;
			// @codeCoverageIgnoreEnd
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash

		// Standard hCaptcha logic for verifying the post password request.
		$result = API::verify(
			[
				'nonce_name'   => self::NONCE,
				'nonce_action' => self::ACTION,
				'expected_id'  => $this->get_expected_id(),
			]
		);

		if ( null === $result ) {
			$this->post_pass_verified = true;

			return;
		}

		wp_die(
			esc_html( $result ),
			'hCaptcha',
			[
				'back_link' => true,
				'response'  => 303,
			]
		);
	}

	/**
	 * Issue proof with the native cookie's password scope and lifetime.
	 *
	 * @param int $expires Native post password cookie expiry; zero means session.
	 *
	 * @return int
	 */
	public function set_proof_cookie( int $expires ): int {
		// Only the verified native postpass request can issue proof.
		// Preserve the exact native password rather than sanitizing it.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$password = isset( $_POST['post_password'] ) ? wp_unslash( $_POST['post_password'] ) : null;

		if ( ! $this->post_pass_verified || ! is_string( $password ) ) {
			return $expires;
		}

		$value = $expires < 0 ? '' : $expires . ':' . $this->proof_signature( $password, $expires );

		setcookie( $this->proof_cookie_name(), $value, $this->cookie_options( $expires ) );

		return $expires;
	}

	/**
	 * Reject native cookie authorization without verified CAPTCHA provenance.
	 *
	 * @param bool|mixed   $required Native password check result.
	 * @param WP_Post|null $post     Post object.
	 *
	 * @return bool
	 */
	public function require_captcha_proof( $required, ?WP_Post $post ): bool {
		$required = (bool) $required;

		if ( $required || ! $post instanceof WP_Post || empty( $post->post_password ) ) {
			return $required;
		}

		$id = $this->get_expected_id();

		/** This filter is documented in src/php/Helpers/HCaptcha.php. */
		if ( ! apply_filters( 'hcap_protect_form', true, $id['source'], $id['form_id'] ) ) {
			return false;
		}

		// Enforce cookie provenance before core's explicit REST password/editor overrides.
		return ! $this->has_valid_proof( $post->post_password );
	}

	/**
	 * Wait for WordPress's final cookie-send decision before clearing proof.
	 *
	 * @return void
	 */
	public function clear_proof_cookie(): void {
		add_filter( 'send_auth_cookies', [ $this, 'clear_proof_if_allowed' ], PHP_INT_MAX, 6 );
	}

	/**
	 * Clear proof only when native password-cookie clearing is allowed.
	 *
	 * @param bool   $send       Whether WordPress may send cookies.
	 * @param int    $expire     Cookie expiry.
	 * @param int    $expiration Authentication expiry.
	 * @param int    $user_id    User ID.
	 * @param string $scheme     Authentication scheme.
	 * @param string $token      Session token.
	 *
	 * @return bool
	 */
	public function clear_proof_if_allowed( bool $send, int $expire = 0, int $expiration = 0, int $user_id = 0, string $scheme = '', string $token = '' ): bool {
		// WordPress versions before 6.2 pass only the cookie-send flag.
		if ( $expire || $expiration || $user_id || '' !== $scheme || '' !== $token ) {
			return $send;
		}

		remove_filter( 'send_auth_cookies', [ $this, 'clear_proof_if_allowed' ], PHP_INT_MAX );

		if ( $send ) {
			$options           = $this->cookie_options( time() - YEAR_IN_SECONDS );
			$options['secure'] = false;

			setcookie( $this->proof_cookie_name(), '', $options );
		}

		return $send;
	}

	/**
	 * Check the proof's signature, expiry and native password scope.
	 *
	 * @param string $password Native post password.
	 *
	 * @return bool
	 */
	private function has_valid_proof( string $password ): bool {
		// This signed cookie is verified below before use.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$proof = isset( $_COOKIE[ $this->proof_cookie_name() ] ) ? wp_unslash( $_COOKIE[ $this->proof_cookie_name() ] ) : null;

		if ( ! is_string( $proof ) || ! preg_match( '/^(0|[1-9][0-9]{0,18}):([a-f0-9]{64})$/D', $proof, $parts ) ) {
			return false;
		}

		$expires = (int) $parts[1];

		if ( (string) $expires !== $parts[1] || ( 0 !== $expires && $expires <= time() ) ) {
			return false;
		}

		return hash_equals( $this->proof_signature( $password, $expires ), $parts[2] );
	}

	/**
	 * Sign proof with a site secret without exposing the post password.
	 *
	 * @param string $password Native post password.
	 * @param int    $expires  Native cookie expiry.
	 *
	 * @return string
	 */
	private function proof_signature( string $password, int $expires ): string {
		return hash_hmac( 'sha256', 'hcaptcha-postpass|' . COOKIEHASH . '|' . $expires . '|' . $password, wp_salt() );
	}

	/**
	 * Get the proof cookie name for the native site's cookie scope.
	 *
	 * @return string
	 */
	private function proof_cookie_name(): string {
		return 'hcaptcha_postpass_' . COOKIEHASH;
	}

	/**
	 * Match native password-cookie scope and add browser protections.
	 *
	 * @param int $expires Cookie expiry.
	 *
	 * @return array
	 */
	private function cookie_options( int $expires ): array {
		// The native postpass handler uses the same redirect when choosing Secure.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$redirect = isset( $_POST['redirect_to'] ) ? wp_unslash( $_POST['redirect_to'] ) : wp_get_referer();
		$secure   = is_string( $redirect ) && 'https' === wp_parse_url( $redirect, PHP_URL_SCHEME );

		return [
			'expires'  => $expires,
			'path'     => COOKIEPATH,
			'domain'   => COOKIE_DOMAIN,
			'secure'   => $secure,
			'httponly' => true,
			'samesite' => 'Lax',
		];
	}

	/**
	 * Get expected hCaptcha widget id.
	 *
	 * @return array
	 */
	private function get_expected_id(): array {
		return [
			'source'  => HCaptcha::get_class_source( __CLASS__ ),
			'form_id' => 'password_protected',
		];
	}
}
