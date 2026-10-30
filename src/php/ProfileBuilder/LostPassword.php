<?php
/**
 * LostPassword class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\ProfileBuilder;

use HCaptcha\Helpers\API;
use HCaptcha\Helpers\HCaptcha;
use WP_User;

/**
 * Class LostPassword.
 */
class LostPassword {

	/**
	 * Nonce action.
	 */
	private const ACTION = 'hcaptcha_profile_builder_lost_password';

	/**
	 * Nonce name.
	 */
	private const NONCE = 'hcaptcha_profile_builder_lost_password_nonce';

	/**
	 * $_POST key to check.
	 */
	private const POST_KEY = 'action';

	/**
	 * $_POST value to check.
	 */
	private const POST_VALUE = 'recover_password';

	/**
	 * Native reset-password submission marker.
	 */
	private const RESET_POST_VALUE = 'recover_password2';

	/**
	 * The hCaptcha validation error message.
	 *
	 * @var string|null
	 */
	private ?string $error_message = null;

	/**
	 * Username or email from $_POST.
	 *
	 * @var string
	 */
	private string $username_email = '';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	protected function init_hooks(): void {
		add_action( 'wppb_recover_password_before_content_output', [ $this, 'add_captcha' ] );
		add_filter( 'pre_do_shortcode_tag', [ $this, 'verify' ], 10, 4 );
		add_filter( 'wppb_recover_password_extra_validation', [ $this, 'verify_reset_password' ], 10, 2 );
		add_filter( 'wppb_recover_password_displayed_message1', [ $this, 'recover_password_displayed_message1' ] );
	}

	/**
	 * Add captcha.
	 *
	 * @param string|mixed $output Recover password form HTML.
	 *
	 * @return string|mixed
	 */
	public function add_captcha( $output ) {
		$args = [
			'action' => static::ACTION,
			'name'   => static::NONCE,
			'id'     => $this->get_expected_id(),
		];

		$search = '<p class="form-submit">';

		return str_replace( $search, HCaptcha::form( $args ) . $search, $output );
	}

	/**
	 * Verify a lost password form.
	 *
	 * @param false|mixed  $output Shortcode output.
	 * @param string       $tag    Shortcode name.
	 * @param array|string $attr   Shortcode attribute array or empty string.
	 * @param array        $m      Regular expression match array.
	 *
	 * @return string|mixed
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function verify( $output, string $tag, $attr, array $m ) {
		if ( 'wppb-recover-password' !== $tag ) {
			return $output;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$post_value = isset( $_POST[ static::POST_KEY ] ) ?
			sanitize_text_field( wp_unslash( $_POST[ static::POST_KEY ] ) ) :
			'';

		if (
			( ! isset( $_POST[ static::POST_KEY ] ) ) ||
			( self::POST_VALUE && self::POST_VALUE !== $post_value )
		) {
			// This class cannot handle a submitted lost password form.
			return $output;
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$this->error_message = API::verify_post( static::NONCE, static::ACTION, $this->get_expected_id() );

		if ( null === $this->error_message ) {
			return $output;
		}

		// Stop password recovery code in Profile Builder.
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$this->username_email = isset( $_POST['username_email'] ) ?
			sanitize_text_field( wp_unslash( $_POST['username_email'] ) ) :
			'';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$_POST['username_email'] = '';

		return $output;
	}

	/**
	 * Verify hCaptcha at Profile Builder's final password-reset boundary.
	 *
	 * @param string|mixed $message Native validation message.
	 * @param mixed        $user    User resolved from the reset key.
	 *
	 * @return string|mixed
	 */
	public function verify_reset_password( $message, $user ) {
		// Profile Builder applies this filter after its nonce, key, user, and password checks.
		// Match the native handler's raw, case-sensitive method and action marker.
		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if (
			! empty( $message ) ||
			! $user instanceof WP_User ||
			'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ||
			self::RESET_POST_VALUE !== ( $_POST['action2'] ?? null )
		) {
			return $message;
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		return API::verify_post( static::NONCE, static::ACTION, $this->get_expected_id() ) ?? $message;
	}

	/**
	 * Filter recover password displayed message 1.
	 *
	 * @param string|mixed $message Message.
	 *
	 * @return string|mixed
	 */
	public function recover_password_displayed_message1( $message ) {
		if ( ! $this->error_message ) {
			return $message;
		}

		$_POST['username_email'] = $this->username_email;

		return '<p class="wppb-warning">' . $this->error_message . '</p>';
	}

	/**
	 * Get the expected form identity.
	 *
	 * @return array
	 */
	private function get_expected_id(): array {
		return [
			'source'  => HCaptcha::get_class_source( __CLASS__ ),
			'form_id' => 'lost_password',
		];
	}
}
