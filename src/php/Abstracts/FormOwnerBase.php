<?php
/**
 * FormOwnerBase class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Abstracts;

use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Helpers\Request;

/**
 * Class FormOwnerBase.
 */
abstract class FormOwnerBase {

	/**
	 * Request owner filter.
	 */
	protected const OWNER_FILTER = '';

	/**
	 * Init request owner hook.
	 *
	 * @return void
	 */
	protected function init_owner_hooks(): void {
		add_filter( static::OWNER_FILTER, [ $this, 'claim_request_owner' ], 10, 2 );
	}

	/**
	 * Init auto-verification handoff hook.
	 *
	 * @return void
	 */
	protected function init_auto_verify_hooks(): void {
		add_filter( 'hcap_auto_verify_unmatched_form', [ $this, 'defer_auto_verification' ], 10, 3 );
	}

	/**
	 * Claim a request matching this verifier's signed widget ID.
	 *
	 * @param string|mixed $owner Current request owner class.
	 * @param array        $id    Submitted widget ID data.
	 *
	 * @return string|mixed
	 */
	public function claim_request_owner( $owner, array $id ) {
		if ( $owner ) {
			return $owner;
		}

		return $this->get_expected_id() === $id ? static::class : $owner;
	}

	/**
	 * Defer an unmatched auto-verified request to its form verifier.
	 *
	 * @param array|null|mixed $registered_form Registered auto-verified form.
	 * @param string           $path            Request path.
	 * @param string           $widget_id       Submitted widget ID.
	 *
	 * @return array|null|mixed
	 */
	public function defer_auto_verification( $registered_form, string $path, string $widget_id ) {
		$login_path = untrailingslashit( (string) wp_parse_url( wp_login_url(), PHP_URL_PATH ) );
		$id_info    = HCaptcha::decode_id_info();

		if (
			$login_path === $path &&
			$this->is_owner_action() &&
			$widget_id &&
			$id_info['valid'] &&
			HCaptcha::widget_id_value( $id_info['id'] ) === $widget_id &&
			true === $this->get_request_ownership()
		) {
			return null;
		}

		return $registered_form;
	}

	/**
	 * Get this verifier's ownership status for the submitted widget ID.
	 *
	 * @return bool|null True when this verifier owns the request, false when another registered verifier owns it,
	 *                   or null for an invalid or unknown widget ID.
	 */
	protected function get_request_ownership(): ?bool {
		$id_info = HCaptcha::decode_id_info();

		if ( ! $id_info['valid'] ) {
			return null;
		}

		$owner = (string) apply_filters( static::OWNER_FILTER, '', $id_info['id'] );

		if ( ! $owner ) {
			return null;
		}

		return static::class === $owner;
	}

	/**
	 * Whether the current request has the expected WordPress login action.
	 *
	 * @param string $expected_action Expected action.
	 *
	 * @return bool
	 */
	protected function is_wp_login_action( string $expected_action ): bool {
		// Nonce is verified later by the owning form integration.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$input_type = isset( $_POST['action'] ) ? INPUT_POST : INPUT_GET;
		$action     = Request::filter_input( $input_type, 'action' );

		return $expected_action === $action;
	}

	/**
	 * Whether the current request has the action handled by this verifier.
	 *
	 * @return bool
	 */
	abstract protected function is_owner_action(): bool;

	/**
	 * Get expected hCaptcha widget ID.
	 *
	 * @return array
	 */
	abstract protected function get_expected_id(): array;
}
