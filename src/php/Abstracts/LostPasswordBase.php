<?php
/**
 * LostPasswordBase class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Abstracts;

use HCaptcha\Helpers\API;
use HCaptcha\Helpers\EntryData;
use HCaptcha\Helpers\HCaptcha;
use WP_Error;
use WP_User;

/**
 * Class LostPasswordBase
 */
abstract class LostPasswordBase extends FormOwnerBase {

	/**
	 * Request owner filter.
	 */
	protected const OWNER_FILTER = 'hcap_lost_password_request_owner';

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
		$this->init_owner_hooks();
		$this->init_auto_verify_hooks();

		add_action( static::ADD_CAPTCHA_ACTION, [ $this, 'add_captcha' ] );
		add_action( 'lostpassword_post', [ $this, 'verify' ], 10, 2 );
	}

	/**
	 * Add captcha.
	 *
	 * @return void
	 */
	public function add_captcha(): void {
		$args = [
			'action' => static::ACTION,
			'name'   => static::NONCE,
			'id'     => $this->get_expected_id(),
		];

		HCaptcha::form_display( $args );
	}

	/**
	 * Verify a lost password form.
	 *
	 * @param WP_Error|mixed     $errors Error.
	 * @param WP_User|false|null $user   Native resolved recovery recipient.
	 *
	 * @return void
	 */
	public function verify( $errors, $user = null ): void {
		if ( ! $this->can_handle_request() ) {
			// This class cannot handle a submitted lost password form.
			return;
		}

		$ownership = $this->get_request_ownership();

		if ( false === $ownership ) {
			return;
		}

		if ( null === $ownership ) {
			HCaptcha::add_error_message( $errors, hcap_get_error_messages()['bad-signature'] );

			return;
		}

		$entry         = [
			'nonce_name'   => static::NONCE,
			'nonce_action' => static::ACTION,
			'data'         => EntryData::from_post(
				[
					'username' => [ 'user_login', 'user_email' ],
				]
			),
			'expected_id'  => $this->get_expected_id(),
		];
		$error_message = API::verify( EntryData::with_emails( $entry, $user instanceof WP_User ? [ $user->user_email ] : [] ) );

		HCaptcha::add_error_message( $errors, $error_message );
	}

	/**
	 * Get expected hCaptcha widget id.
	 *
	 * @return array
	 */
	protected function get_expected_id(): array {
		return [
			'source'  => HCaptcha::get_class_source( static::class ),
			'form_id' => 'lost_password',
		];
	}

	/**
	 * Whether the current request has the lost-password action.
	 *
	 * @return bool
	 */
	protected function is_owner_action(): bool {
		return $this->is_lost_password_action() && $this->can_handle_request();
	}

	/**
	 * Whether the current request uses a native WordPress recovery action.
	 *
	 * @return bool
	 */
	protected function is_lost_password_action(): bool {
		return $this->is_wp_login_action( 'lostpassword' ) || $this->is_wp_login_action( 'retrievepassword' );
	}

	/**
	 * Whether this verifier can handle the submitted lost-password form.
	 *
	 * @return bool
	 */
	protected function can_handle_request(): bool {
		// Nonce is verified later by the owning form integration.
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$post_value = isset( $_POST[ static::POST_KEY ] ) ?
			sanitize_text_field( wp_unslash( $_POST[ static::POST_KEY ] ) ) :
			'';

		return isset( $_POST[ static::POST_KEY ] ) &&
			( ! static::POST_VALUE || static::POST_VALUE === $post_value );
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}
}
